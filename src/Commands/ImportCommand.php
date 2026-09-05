<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Commands\Concerns\PrintsSyncSummary;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use FinityLabs\LinCodex\Sync\ArticleImporter;
use FinityLabs\LinCodex\Sync\ImportOptions;
use Illuminate\Console\Command;

/**
 * Copy the articles under every configured docs path into the database.
 * An article whose slug is already in the database is skipped and listed
 * unless --force overwrites it, which records a revision with reason
 * import for each translation that changed when revisions are on. The
 * work is ArticleImporter's; this command parses the options and prints
 * the summary.
 */
final class ImportCommand extends Command
{
    use PrintsSyncSummary;

    protected $signature = 'codex:import
        {--only=* : Import only these slugs}
        {--locale= : Import one language only}
        {--force : Overwrite articles that already exist in the database (records an import revision when revisions are on)}
        {--dry-run : Report what would happen without writing}
        {--user= : Record this user id as the author}';

    protected $description = 'Copy file articles into the database.';

    public function handle(ArticleImporter $importer, FilesystemSource $files): int
    {
        $user = $this->option('user');

        if ($user !== null && ! ctype_digit($user)) {
            $this->error('--user must be a whole number.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $dryRun = (bool) $this->option('dry-run');

        /** @var list<string> $only */
        $only = array_values(array_filter((array) $this->option('only'), 'is_string'));

        $options = new ImportOptions(
            only: $only,
            locale: is_string($locale) && $locale !== '' ? $locale : null,
            force: (bool) $this->option('force'),
            dryRun: $dryRun,
            userId: $user === null ? null : (int) $user,
        );

        $this->info('Importing articles from '.implode(', ', $files->paths()).'...');

        $report = $importer->import($options);

        $this->printSyncSummary($report, $dryRun, 'Pass --force to overwrite articles that already exist in the database.');

        return $this->syncExitCode($report);
    }
}
