<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands\Concerns;

use FinityLabs\LinCodex\Sync\SyncReport;
use Illuminate\Console\Command;

/**
 * The summary codex:import and codex:export share: a dry-run notice, one
 * table row per locale, the skipped slugs with an optional hint, every
 * failure with its reason, then the source warnings. The exit code is 1
 * only when an article failed; skipped articles and warnings are normal.
 *
 * @mixin Command
 */
trait PrintsSyncSummary
{
    protected function printSyncSummary(SyncReport $report, bool $dryRun, string $skippedHint = ''): void
    {
        if ($dryRun) {
            $this->comment('Dry run: nothing was written.');
        }

        $rows = [];

        foreach ($report->locales() as $locale) {
            $rows[] = [
                $locale,
                (string) $report->count($locale, 'created'),
                (string) $report->count($locale, 'updated'),
                (string) $report->count($locale, 'skipped'),
                (string) $report->count($locale, 'failed'),
            ];
        }

        $this->table(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], $rows);

        $skipped = $report->skippedSlugs();

        if ($skipped !== []) {
            $this->line('Skipped: '.implode(', ', $skipped));

            if ($skippedHint !== '') {
                $this->comment('  '.$skippedHint);
            }
        }

        $failures = $report->failures();

        if ($failures !== []) {
            $this->line('Failed:');

            foreach ($failures as $key => $reason) {
                $this->error('  '.$key.': '.$reason);
            }
        }

        $warnings = $report->warnings();

        if ($warnings !== []) {
            $this->line('Warnings:');

            foreach ($warnings as $warning) {
                $this->warn('  '.$warning);
            }
        }
    }

    protected function syncExitCode(SyncReport $report): int
    {
        return $report->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }
}
