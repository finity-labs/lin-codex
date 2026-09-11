<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Jobs;

use FinityLabs\LinCodex\Ai\AiAvailabilityCheck;
use FinityLabs\LinCodex\Ai\AiReason;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Events\ArticleTranslated;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Settings\CodexAiSettings;
use FinityLabs\LinCodex\Translation\ArticleTranslator;
use FinityLabs\LinCodex\Translation\MissingTranslations;
use FinityLabs\LinCodex\Translation\TranslationReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Throwable;

/**
 * Translate one article into the locales an admin asked for.
 *
 * One job per article, carrying locale codes and the id of the admin who
 * queued it - never a model. An article deleted between dispatch and work
 * then becomes a reported outcome rather than an exception at unserialize,
 * which matters because a bulk action may queue a hundred of these and the
 * admin wants a report, not a failed-jobs table.
 *
 * Queue: the app's default connection and queue, unless lin-codex.ai.queue
 * names another. A host with no worker runs it inline on the sync driver and
 * sees the rows appear during the request.
 *
 * Timeout: locales x the settings timeout + 30 seconds, set in the
 * constructor and public, so it travels with the serialized job. Both halves
 * matter. queue:work kills a job after 60 seconds by default, which a single
 * 120-second translation already exceeds; and the database and redis drivers
 * re-deliver a job still running after retry_after (90 seconds by default),
 * so a host that queues several locales must raise retry_after on the
 * connection above this timeout or the same article is translated twice at
 * once. The README says so. $tries is 1: a kill is a cost, not something to
 * repeat.
 *
 * At run time the availability rule is checked again - a key can be removed
 * or the toggle switched off between dispatch and work - and every locale is
 * re-checked for content, so a locale somebody filled in the meantime is
 * skipped rather than overwritten. The check queries per locale on purpose:
 * a locale filled while an earlier locale was being translated is still seen.
 *
 * The write is ArticleImporter::write()'s shape - firstOrNew, fill, save -
 * inside RevisionManager::attributing(Manual, the dispatching admin). Manual
 * and not an AI reason by decision: the AI only made the translation easier
 * to produce, the admin is still its author. The model's saving hook does
 * the rest, recording the previous content as a revision (existing row,
 * dirty title or body, revisions enabled) and indexing the search text, so
 * this job neither snapshots a revision of its own nor calls the search-text
 * indexer; either would record the same revision twice.
 *
 * A locale that fails is left missing with its reason and the next one is
 * tried. Whatever happened, the run ends with an ArticleTranslated event
 * carrying the per-locale report: fin-codex renders it as a notification.
 */
final class TranslateArticle implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** locales x the settings timeout + 30 seconds. */
    public int $timeout;

    /**
     * @param  list<string>  $locales  target locale codes, in the order they are worked through
     * @param  int|string|null  $userId  the admin who queued the run; the author of every revision it records
     */
    public function __construct(
        public readonly int $articleId,
        public readonly array $locales,
        public readonly int|string|null $userId = null,
    ) {
        $queue = config('lin-codex.ai.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }

        $this->timeout = count($this->locales) * $this->settingsTimeout() + 30;
    }

    public function handle(
        ArticleTranslator $translator,
        MissingTranslations $missing,
        AiAvailabilityCheck $availability,
        RevisionManager $revisions,
    ): void {
        $report = new TranslationReport;
        $article = Article::query()->find($this->articleId);
        $available = $availability->check()->available;

        foreach ($this->locales as $locale) {
            if ($article === null) {
                $report->failed($locale, AiReason::UNKNOWN);

                continue;
            }

            if (! $available) {
                $report->failed($locale, AiReason::UNAVAILABLE);

                continue;
            }

            if (! $missing->isMissing($article, $locale)) {
                $report->skipped($locale);

                continue;
            }

            try {
                $result = $translator->translate($article, $locale);
            } catch (Throwable $e) {
                /*
                 * The translator's own caller guards land here - an article
                 * with nothing to translate, a locale asked for its own
                 * source - and so does anything the source-row read raises.
                 * A queued job has no caller to show a message to, so the
                 * throwable goes to the host's error tooling before the
                 * locale is written off as unknown. An AI failure never
                 * reaches this catch: the translator reports the unknown
                 * ones itself and hands back a failed result, so nothing is
                 * reported twice.
                 */
                report($e);

                $report->failed($locale, AiReason::UNKNOWN);

                continue;
            }

            if (! $result->ok) {
                $report->failed($locale, (string) $result->reason);

                continue;
            }

            $revisions->attributing(RevisionReason::Manual, $this->userId, function () use ($article, $locale, $result): void {
                ArticleTranslation::query()
                    ->firstOrNew(['article_id' => $article->id, 'locale' => $locale])
                    ->fill([
                        'title' => (string) $result->title,
                        'excerpt' => $result->excerpt,
                        'body' => (string) $result->body,
                    ])
                    ->save();
            });

            $report->translated($locale, $result->promptTokens, $result->completionTokens);
        }

        event(new ArticleTranslated($this->articleId, $this->userId, $report));
    }

    /**
     * CodexAiSettings::$timeout, 120 when the group is unseeded or the host
     * has no settings table - the guard every settings reader here uses.
     */
    private function settingsTimeout(): int
    {
        try {
            return app(CodexAiSettings::class)->timeout;
        } catch (MissingSettings|QueryException) {
            return 120;
        }
    }
}
