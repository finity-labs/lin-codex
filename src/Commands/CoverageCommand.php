<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Coverage\RouteCoverage;
use FinityLabs\LinCodex\Coverage\RouteCoverageRow;
use FinityLabs\LinCodex\Data\SourceWarning;
use Illuminate\Console\Command;

/**
 * Print RouteCoverage::report() as a table (or JSON with --json), then the
 * source warnings, then one summary line. The exit code is 1 when any
 * route lacks an article so the command can gate a deploy; --no-fail
 * keeps the output and exits 0.
 */
final class CoverageCommand extends Command
{
    protected $signature = 'codex:coverage
        {--json : Print JSON instead of a table}
        {--no-fail : Exit 0 even when routes lack coverage}';

    protected $description = 'List named routes that have no help article.';

    public function handle(RouteCoverage $coverage, ContentSource $source): int
    {
        $rows = $coverage->report();
        $uncovered = count(array_filter($rows, static fn (RouteCoverageRow $row): bool => ! $row->covered()));
        $warnings = array_map(static fn (SourceWarning $warning): string => $warning->message(), $source->warnings());

        if ($this->option('json')) {
            $this->line(json_encode([
                'routes' => array_map(static fn (RouteCoverageRow $row): array => $row->toArray(), $rows),
                'uncovered' => $uncovered,
                'warnings' => $warnings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Route', 'URI', 'Matched by', 'Article'],
                array_map(static fn (RouteCoverageRow $row): array => [
                    $row->name,
                    $row->uri,
                    $row->matchedBy ?? 'none',
                    $row->slug ?? '',
                ], $rows),
            );

            if ($warnings !== []) {
                $this->line('Warnings:');

                foreach ($warnings as $message) {
                    $this->warn('  '.$message);
                }
            }

            if ($uncovered === 0) {
                $this->info('Every route has a help article.');
            } else {
                $this->comment(sprintf('%d of %d routes have no help article.', $uncovered, count($rows)));
            }
        }

        return $uncovered > 0 && ! $this->option('no-fail') ? self::FAILURE : self::SUCCESS;
    }
}
