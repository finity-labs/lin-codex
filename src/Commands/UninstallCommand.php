<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Cache\CacheClearer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * codex:uninstall reverses codex:install before composer remove. It
 * prints what will go, asks once (--force skips the question), then in
 * this order: drops the five tables in foreign-key order (media,
 * revisions, contexts, translations, articles) under the names the host
 * configured, deletes the lin-codex settings rows, deletes the
 * create_codex_* records from the migrations table so a reinstall
 * migrates again, removes the published files when --files is given,
 * and clears the settings cache and the package caches through
 * CacheClearer.
 *
 * The settings rows go through the package settings migration's own
 * down(), which uses the migrator's deleteIfExists() and so works with
 * whatever settings repository the host configured, not only the
 * database one. --files covers config/lin-codex.php,
 * public/vendor/lin-codex, resources/js/codex (the React and Vue stubs)
 * and the published create_codex_* migration and settings files. Docs
 * folders are content the host wrote and are never touched.
 */
class UninstallCommand extends Command
{
    protected $signature = 'codex:uninstall
        {--force : Skip the confirmation}
        {--files : Also delete the published config, stylesheet, React and Vue stubs and migration files}';

    protected $description = 'Remove the lin-codex tables, settings and caches (run before composer remove).';

    public function handle(CacheClearer $clearer): int
    {
        $tables = $this->tables();
        $files = $this->option('files') ? $this->publishedFiles() : [];

        $this->info('This will remove:');

        foreach ($tables as $table) {
            $this->line('  - table '.$table);
        }

        $this->line('  - the lin-codex settings rows');
        $this->line('  - the create_codex_* rows in the migrations table');
        $this->line('  - the package caches');

        foreach ($files as $file) {
            $this->line('  - '.$file);
        }

        $this->comment('Docs folders are never touched.');

        if (! $this->option('force') && ! $this->confirm('Remove everything listed?', false)) {
            $this->info('Nothing removed.');

            return self::FAILURE;
        }

        $this->dropTables($tables);
        $this->deleteSettingsRows();
        $this->deleteMigrationRecords();
        $this->deleteFiles($files);
        $this->clearCaches($clearer);

        $this->newLine();
        $this->info('lin-codex uninstalled. Remove the package with: composer remove finity-labs/lin-codex');

        return self::SUCCESS;
    }

    /**
     * The configured table names in drop order, dependents first. Read
     * here rather than in a constant so a cached config and a custom
     * prefix are both honoured.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $names = (array) config('lin-codex.table_names', []);

        return array_map(
            static fn (string $key): string => (string) ($names[$key] ?? 'codex_'.$key),
            ['media', 'article_revisions', 'article_contexts', 'article_translations', 'articles'],
        );
    }

    /**
     * The published paths that exist right now: files and the two
     * directories vendor:publish creates.
     *
     * @return list<string>
     */
    private function publishedFiles(): array
    {
        $candidates = [
            config_path('lin-codex.php'),
            public_path('vendor/lin-codex'),
            resource_path('js/codex'),
            ...File::glob(database_path('migrations/*_create_codex_*_table.php')),
            ...File::glob(database_path('settings/*_create_codex_settings.php')),
        ];

        return array_values(array_filter($candidates, static fn (string $path): bool => File::exists($path)));
    }

    /**
     * @param  list<string>  $tables
     */
    private function dropTables(array $tables): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
                $this->info('  Dropped '.$table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function deleteSettingsRows(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        try {
            (include dirname(__DIR__, 2).'/database/settings/create_codex_settings.php')->down();
            $this->info('  Settings rows deleted');
        } catch (Throwable $e) {
            $this->components->warn('Could not delete the settings rows: '.$e->getMessage());
        }
    }

    private function deleteMigrationRecords(): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        $deleted = DB::table('migrations')->where('migration', 'like', '%create_codex_%')->delete();

        $this->info(sprintf('  %d migration %s deleted', $deleted, $deleted === 1 ? 'record' : 'records'));
    }

    /**
     * @param  list<string>  $files
     */
    private function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            if (File::isDirectory($file)) {
                File::deleteDirectory($file);
            } else {
                File::delete($file);
            }

            $this->info('  Deleted '.$file);
        }
    }

    private function clearCaches(CacheClearer $clearer): void
    {
        try {
            $this->callSilently('settings:clear-cache');
        } catch (Throwable) {
            // The settings cache is optional; a host without it has nothing to clear.
        }

        $clearer->clear();
        $this->info('  Caches cleared');
    }
}
