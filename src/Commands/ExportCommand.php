<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Commands\Concerns\PrintsSyncSummary;
use FinityLabs\LinCodex\Sync\ArticleExporter;
use FinityLabs\LinCodex\Sync\ExportOptions;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Write every database article back to files, to its recorded source path
 * or to {locale}/{slug}.md under the first configured docs path, or under
 * --path. The work is ArticleExporter's; this command parses the options
 * and prints the summary.
 */
final class ExportCommand extends Command
{
    use PrintsSyncSummary;

    protected $signature = 'codex:export
        {--only=* : Export only these slugs}
        {--locale= : Export one language only}
        {--path= : Write under this folder instead of the configured docs path}
        {--dry-run : Report what would happen without writing}';

    protected $description = 'Write database articles back to files.';

    public function handle(ArticleExporter $exporter): int
    {
        $locale = $this->option('locale');
        $path = $this->option('path');
        $dryRun = (bool) $this->option('dry-run');

        /** @var list<string> $only */
        $only = array_values(array_filter((array) $this->option('only'), 'is_string'));

        $options = new ExportOptions(
            only: $only,
            locale: is_string($locale) && $locale !== '' ? $locale : null,
            path: is_string($path) && $path !== '' ? $path : null,
            dryRun: $dryRun,
        );

        try {
            $report = $exporter->export($options);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printSyncSummary($report, $dryRun);

        return $this->syncExitCode($report);
    }
}
