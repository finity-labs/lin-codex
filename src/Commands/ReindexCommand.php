<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Search\SearchReindexer;
use Illuminate\Console\Command;

/**
 * codex:reindex runs SearchReindexer::reindex() behind a progress bar and
 * reports the translation rows walked and what happened to the in-memory
 * index for the configured source mode (rebuilt with a document count, or
 * skipped in database mode where a search never reads it). codex:install
 * ends with this command; run it by hand after seeding rows with the
 * query builder or after a SearchText::VERSION bump.
 *
 * The bar has no known maximum (counting the rows first would cost a
 * query for no gain), so it advances as a spinner-style bar in a console
 * and stays silent under a non-interactive output.
 */
class ReindexCommand extends Command
{
    protected $signature = 'codex:reindex';

    protected $description = 'Rebuild search_text for every translation and the in-memory search index.';

    public function handle(SearchReindexer $reindexer): int
    {
        $this->comment('Re-indexing translations...');

        $bar = $this->output->createProgressBar();
        $bar->start();

        $report = $reindexer->reindex(function (int $done) use ($bar): void {
            $bar->setProgress($done);
        });

        $bar->finish();
        $this->newLine();

        $this->info(sprintf('  %d %s indexed', $report->translations, $report->translations === 1 ? 'translation' : 'translations'));

        if ($report->indexedDocuments === null) {
            $this->info(sprintf('  In-memory index skipped (%s source)', $report->mode));
        } else {
            $this->info(sprintf('  In-memory index rebuilt with %d documents', $report->indexedDocuments));
        }

        return self::SUCCESS;
    }
}
