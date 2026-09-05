<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Cache\CacheClearer;
use Illuminate\Console\Command;

/**
 * codex:cache-clear runs CacheClearer::clear() and reports one line per
 * cache the package keeps. Rendered HTML is orphaned by the generation
 * bump rather than counted (the render store cannot be enumerated), so
 * the line names the new generation; the file-source entries and the
 * in-memory search index are forgotten and counted. The context index
 * and the stylesheet hash are listed so the reader knows they hold
 * nothing to clear: one is rebuilt per request, the other lives in
 * memory only.
 */
class CacheClearCommand extends Command
{
    protected $signature = 'codex:cache-clear';

    protected $description = 'Drop rendered HTML, the file source caches and the in-memory search index.';

    public function handle(CacheClearer $clearer): int
    {
        $report = $clearer->clear();
        $entries = $report->fileEntries;

        $this->components->twoColumnDetail('Rendered HTML', sprintf('generation %d (old entries expire with their ttl)', $report->generation));
        $this->components->twoColumnDetail('File sources', $entries === 0 ? 'nothing cached' : sprintf('%d %s forgotten', $entries, $entries === 1 ? 'entry' : 'entries'));
        $this->components->twoColumnDetail('Search index', $report->searchIndexWasCached ? 'forgotten' : 'nothing cached');
        $this->components->twoColumnDetail('Context index', 'not cached (rebuilt per request)');
        $this->components->twoColumnDetail('Stylesheet hash', 'not cached (in memory only)');

        return self::SUCCESS;
    }
}
