<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use Illuminate\Console\Command;

/**
 * Remove the revisions of every article beyond the keep count, locale by
 * locale. Runs whatever the enabled switch says, because the operator
 * asked; --keep overrides CodexSettings::$revisions_keep for this run and
 * leaves the settings untouched.
 */
final class RevisionsPruneCommand extends Command
{
    protected $signature = 'codex:revisions:prune
        {--keep= : Keep this many revisions per article and language for this run instead of the settings value}';

    protected $description = 'Remove article revisions beyond the keep count.';

    public function handle(RevisionManager $revisions): int
    {
        $keep = $this->option('keep');

        if ($keep !== null && ! ctype_digit($keep)) {
            $this->error('--keep must be a whole number of zero or more.');

            return self::FAILURE;
        }

        $effective = $keep === null ? $revisions->keep() : (int) $keep;
        $removed = 0;
        $articles = 0;

        foreach (Article::query()->whereHas('revisions')->lazyById(100) as $article) {
            $removed += $revisions->prune($article, $effective);
            $articles++;
        }

        $this->info(sprintf('Removed %d revisions from %d articles (keeping %d per language).', $removed, $articles, $effective));

        return self::SUCCESS;
    }
}
