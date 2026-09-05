<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Revisions;

use Closure;
use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Database\QueryException;
use LogicException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Revision history for database articles.
 *
 * A revision holds one locale's previous title and body plus the article
 * format they were written in; the excerpt is not revisioned. Two model
 * hooks feed this class: ArticleTranslation's saving hook calls
 * recordTranslationChange() when an existing translation's title or body is
 * dirty, and Article's updating hook calls recordFormatChange() when the
 * format is dirty, which stores one revision per translation. Both hooks do
 * nothing while CodexSettings::$revisions_enabled is false; an unseeded
 * settings group or a missing settings table counts as disabled, so a save
 * never fails on the switch. After storing, the locale is pruned to
 * CodexSettings::$revisions_keep in the same save.
 *
 * The reason and the author come from a scope: attributing() records what
 * the caller says (the importer passes Import and an optional user id, as
 * given), withoutRevisions() suppresses the hooks entirely. Scopes nest and
 * the innermost wins, so attributing() inside withoutRevisions() records.
 * Outside any scope a revision is Manual, authored by the authenticated
 * viewer when there is one and by nobody in console.
 *
 * snapshot() stores on request whatever the switch says and restore()
 * uses it: the current content is snapshotted first, then title, body and
 * format are swapped in under withoutRevisions(). The keep count is per
 * article and locale, so ten English edits never evict a German revision.
 */
final class RevisionManager
{
    /** @var list<array{reason: RevisionReason, userId: ?int, suppressed: bool}> */
    private array $stack = [];

    public function __construct(private readonly ViewerResolver $viewers) {}

    /**
     * CodexSettings::$revisions_enabled, false when the group is unseeded
     * or the settings table is missing.
     */
    public function enabled(): bool
    {
        try {
            return app(CodexSettings::class)->revisions_enabled;
        } catch (MissingSettings|QueryException) {
            return false;
        }
    }

    /**
     * CodexSettings::$revisions_keep, never below zero; 10 when the group
     * is unseeded or the settings table is missing.
     */
    public function keep(): int
    {
        try {
            return max(0, app(CodexSettings::class)->revisions_keep);
        } catch (MissingSettings|QueryException) {
            return 10;
        }
    }

    /**
     * Run $work with every revision it records carrying $reason and $userId
     * (null included: the scope is taken as given, not filled from the
     * viewer). Returns whatever $work returns; the scope is popped even
     * when $work throws.
     */
    public function attributing(RevisionReason $reason, ?int $userId, Closure $work): mixed
    {
        $this->stack[] = ['reason' => $reason, 'userId' => $userId, 'suppressed' => false];

        try {
            return $work();
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * Run $work with the model hooks recording nothing. An attributing()
     * scope opened inside re-enables recording for its own extent.
     */
    public function withoutRevisions(Closure $work): mixed
    {
        $this->stack[] = ['reason' => RevisionReason::Manual, 'userId' => null, 'suppressed' => true];

        try {
            return $work();
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * Store the persisted title and body of an existing translation with the
     * article's current format, then prune that locale. Always stores,
     * whatever the enabled switch says: the caller asked for it.
     *
     * @throws LogicException for a translation that has not been saved yet
     */
    public function snapshot(ArticleTranslation $translation, RevisionReason $reason, ?int $userId): ArticleRevision
    {
        if (! $translation->exists) {
            throw new LogicException('Only a saved translation can be snapshotted.');
        }

        $article = $translation->article;

        $revision = $this->store(
            $article,
            $translation->locale,
            (string) $translation->getOriginal('title'),
            (string) $translation->getOriginal('body'),
            $article->format,
            $reason,
            $userId,
        );

        $this->pruneLocale($article, $translation->locale, $this->keep());

        return $revision;
    }

    /**
     * Put a revision's title, body and format back on its article.
     *
     * The current content is snapshotted first with reason Manual and the
     * given author, so the restore itself can be undone; then the swap runs
     * under withoutRevisions(), because the translation save and the format
     * save would each record the content just snapshotted. A translation
     * that was deleted since the revision was taken is recreated from it,
     * with nothing to snapshot. The translation save re-indexes search_text
     * through the model's own hook.
     */
    public function restore(ArticleRevision $revision, ?int $userId): ArticleTranslation
    {
        $article = $revision->article;
        $translation = ArticleTranslation::query()->firstOrNew(['article_id' => $revision->article_id, 'locale' => $revision->locale]);

        if ($translation->exists) {
            $this->snapshot($translation, RevisionReason::Manual, $userId);
        }

        return $this->withoutRevisions(function () use ($translation, $revision, $article): ArticleTranslation {
            $translation->fill(['title' => $revision->title, 'body' => $revision->body])->save();

            if ($article->format !== $revision->format) {
                $article->format = $revision->format;
                $article->save();
            }

            return $translation;
        });
    }

    /**
     * Delete every revision of the article beyond the keep count, locale by
     * locale, newest kept. $keep overrides the settings value for this call.
     * Returns the number of rows deleted.
     */
    public function prune(Article $article, ?int $keep = null): int
    {
        $keep ??= $this->keep();
        $deleted = 0;

        $locales = ArticleRevision::query()
            ->where('article_id', $article->id)
            ->distinct()
            ->orderBy('locale')
            ->pluck('locale');

        foreach ($locales as $locale) {
            $deleted += $this->pruneLocale($article, (string) $locale, $keep);
        }

        return $deleted;
    }

    /**
     * Hook entry for ArticleTranslation::saving. Stores the persisted title
     * and body with the article's current format, which is the format the
     * old content was written in when only the translation changes, then
     * prunes the locale. Nothing happens while suppressed or disabled.
     */
    public function recordTranslationChange(ArticleTranslation $translation): void
    {
        if ($this->suppressed() || ! $this->enabled()) {
            return;
        }

        $scope = $this->current();
        $article = $translation->article;

        $this->store(
            $article,
            $translation->locale,
            (string) $translation->getOriginal('title'),
            (string) $translation->getOriginal('body'),
            $article->format,
            $scope['reason'],
            $scope['userId'],
        );

        $this->pruneLocale($article, $translation->locale, $this->keep());
    }

    /**
     * Hook entry for Article::updating when the format is dirty. Every
     * translation's current title and body is stored with the article's
     * original format, the one that content was written in, then each
     * locale is pruned. Nothing happens while suppressed or disabled.
     */
    public function recordFormatChange(Article $article): void
    {
        if ($this->suppressed() || ! $this->enabled()) {
            return;
        }

        $scope = $this->current();
        $format = $article->getOriginal('format');
        $format = $format instanceof ArticleFormat ? $format : $article->format;
        $keep = $this->keep();

        foreach ($article->translations()->get() as $translation) {
            $this->store($article, $translation->locale, $translation->title, $translation->body, $format, $scope['reason'], $scope['userId']);
            $this->pruneLocale($article, $translation->locale, $keep);
        }
    }

    /**
     * The innermost scope, or the default outside any scope: Manual,
     * authored by the authenticated viewer when there is one.
     *
     * @return array{reason: RevisionReason, userId: ?int, suppressed: bool}
     */
    private function current(): array
    {
        $top = end($this->stack);

        if ($top !== false) {
            return $top;
        }

        return ['reason' => RevisionReason::Manual, 'userId' => $this->defaultUserId(), 'suppressed' => false];
    }

    private function suppressed(): bool
    {
        $top = end($this->stack);

        return $top !== false && $top['suppressed'];
    }

    /**
     * The authenticated viewer's id, null for a guest and in console, where
     * no guard has a user.
     */
    private function defaultUserId(): ?int
    {
        $id = $this->viewers->resolve()->user?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    private function store(
        Article $article,
        string $locale,
        string $title,
        string $body,
        ArticleFormat $format,
        RevisionReason $reason,
        ?int $userId,
    ): ArticleRevision {
        return ArticleRevision::query()->create([
            'article_id' => $article->id,
            'locale' => $locale,
            'title' => $title,
            'body' => $body,
            'format' => $format,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    /**
     * Delete the revisions of one locale beyond $keep, newest first by
     * created_at then id (the timestamp has second granularity, so the id
     * breaks ties). Ids are collected and deleted with whereIn because an
     * offset without a limit is not portable across drivers.
     */
    private function pruneLocale(Article $article, string $locale, int $keep): int
    {
        $ids = ArticleRevision::query()
            ->where('article_id', $article->id)
            ->where('locale', $locale)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $stale = array_slice($ids, $keep);

        if ($stale === []) {
            return 0;
        }

        return ArticleRevision::query()->whereIn('id', $stale)->delete();
    }
}
