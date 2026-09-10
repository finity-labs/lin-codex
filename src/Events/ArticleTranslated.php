<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Events;

use FinityLabs\LinCodex\Translation\TranslationReport;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A translation run finished, whatever it achieved.
 *
 * Jobs\TranslateArticle dispatches this once per run, after every requested
 * locale has been translated, skipped or failed; a run in which nothing
 * succeeded still fires, because "all three languages failed, rate limited"
 * is the message the admin needs most. fin-codex Phase 11 listens and renders
 * the report as a database notification.
 *
 * Ids and a plain report, no SerializesModels: the event survives a deleted
 * article and a deleted user, so a queued listener reports the outcome
 * instead of blowing up on unserialize.
 */
final class ArticleTranslated
{
    use Dispatchable;

    public function __construct(
        public readonly int $articleId,
        public readonly ?int $userId,
        public readonly TranslationReport $report,
    ) {}
}
