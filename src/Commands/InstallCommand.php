<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\LinCodexServiceProvider;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

/**
 * codex:install brings a host app from "composer require" to a working
 * package in one run, in this order: publish the config (kept when it
 * exists, --force overwrites), create spatie's settings table when the
 * host has none, publish the package migrations (the five tables plus
 * the settings seed under database/settings), run exactly those files,
 * seed the settings group if the seed did not run, publish the
 * stylesheet with --assets, then codex:reindex. It ends with a summary
 * and the next steps.
 *
 * The migrations are run with --path (the published app paths from
 * pathsToPublish), --realpath and --force. --path with --realpath keeps
 * the host's own pending migrations out of an install that only asked
 * for the package's, and is the only way the settings file under
 * database/settings runs on a host that never registered that
 * directory; --force answers the production confirmation that a
 * non-interactive migrate would otherwise decline. The migrator records
 * each file, so the host's next plain migrate is a no-op for them.
 *
 * Every step is safe to repeat: package-tools reuses the timestamped
 * names of files already published, vendor:publish skips an existing
 * file without --force, migrate skips recorded files, the settings seed
 * only adds missing keys, and reindex recomputes what is there.
 */
class InstallCommand extends Command
{
    protected $signature = 'codex:install
        {--force : Overwrite an existing config file}
        {--assets : Publish the stylesheet to public/vendor/lin-codex}';

    protected $description = 'Install lin-codex: config, migrations, settings and the search index.';

    public function handle(): int
    {
        $this->info('Installing lin-codex...');
        $this->newLine();

        $this->publishConfig();
        $this->ensureSettingsTableExists();
        $this->publishAndRunMigrations();
        $this->ensureSettingsSeeded();

        if ($this->option('assets')) {
            $this->comment('Publishing the stylesheet...');
            $this->callSilently('vendor:publish', ['--tag' => 'lin-codex-assets', '--force' => true]);
            $this->info('  Stylesheet published to public/vendor/lin-codex/codex.css');
        }

        $this->call('codex:reindex');

        $this->newLine();
        $this->info('lin-codex installed.');
        $this->newLine();

        $locale = (string) config('app.locale', 'en');

        $this->table(['Next steps', 'Details'], [
            ['Add the styles', '<x-lin-codex::styles /> in <head>'],
            ['Add the button and the drawer', '<x-lin-codex::help-button /> anywhere, <x-lin-codex::help-drawer /> once before </body>'],
            ['Write the first article', 'php artisan codex:make intro --title="Introduction" (resources/codex/'.$locale.'/ is created for you)'],
            ['Find pages without help', 'php artisan codex:coverage'],
        ]);

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $this->comment('Publishing the config...');

        if (File::exists(config_path('lin-codex.php')) && ! $this->option('force')) {
            $this->info('  Config already published (pass --force to overwrite)');

            return;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'lin-codex-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('  Config published to config/lin-codex.php');
    }

    /**
     * A host without spatie/laravel-settings' table gets it here, from
     * the package's own publish of spatie's migration, run by path so no
     * host migration comes along.
     */
    private function ensureSettingsTableExists(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        $this->comment('Creating the settings table...');

        $this->callSilently('vendor:publish', [
            '--provider' => LaravelSettingsServiceProvider::class,
            '--tag' => 'migrations',
        ]);

        $this->call('migrate', [
            '--path' => array_values(ServiceProvider::pathsToPublish(LaravelSettingsServiceProvider::class, 'migrations')),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->info('  Settings table created');
    }

    private function publishAndRunMigrations(): void
    {
        $this->comment('Publishing the migrations...');
        $this->callSilently('vendor:publish', ['--tag' => 'lin-codex-migrations']);
        $this->info('  Migrations published');

        $this->comment('Running the package migrations...');

        $this->call('migrate', [
            '--path' => array_values(ServiceProvider::pathsToPublish(LinCodexServiceProvider::class, 'lin-codex-migrations')),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->info('  Migrations complete');
    }

    /**
     * The settings migration just ran seeds the group; this is the
     * fallback for a host whose settings migration path differs and
     * whose earlier run left the group unseeded.
     */
    private function ensureSettingsSeeded(): void
    {
        try {
            $settings = app(CodexSettings::class);
            $settings->refresh();
            $keep = $settings->revisions_keep;
        } catch (MissingSettings) {
            (include dirname(__DIR__, 2).'/database/settings/create_codex_settings.php')->up();
            $keep = (int) CodexSettings::defaults()['revisions_keep'];
        }

        $this->info(sprintf('  Settings seeded (lin-codex group, %d revisions kept per language)', $keep));
    }
}
