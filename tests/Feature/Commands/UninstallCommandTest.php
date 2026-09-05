<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

const LIN_CODEX_UNINSTALL_TABLE_KEYS = ['articles', 'article_translations', 'article_contexts', 'article_revisions', 'media'];

/**
 * @return list<string> the five configured table names
 */
function linCodexUninstallTables(): array
{
    return array_map(static fn (string $key): string => (string) config('lin-codex.table_names.'.$key), LIN_CODEX_UNINSTALL_TABLE_KEYS);
}

/**
 * A migrations table with one package record and one host record, the
 * way a host looks after codex:install and its own migrate.
 */
function linCodexUninstallMigrationsTable(): void
{
    Schema::create('migrations', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });

    DB::table('migrations')->insert([
        ['migration' => '2024_01_01_000000_create_codex_articles_table', 'batch' => 1],
        ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
    ]);
}

function linCodexUninstallSettingsRows(): int
{
    return DB::table('settings')->where('group', 'lin-codex')->count();
}

it('is discovered under its name', function (): void {
    expect(Artisan::all())->toHaveKey('codex:uninstall');
});

it('lists what will go and removes nothing when declined', function (): void {
    try {
        $this->artisan('codex:uninstall')
            ->expectsOutputToContain((string) config('lin-codex.table_names.articles'))
            ->expectsOutputToContain('settings rows')
            ->expectsOutputToContain('Docs folders are never touched')
            ->expectsConfirmation('Remove everything listed?', 'no')
            ->expectsOutputToContain('Nothing removed')
            ->assertExitCode(1);

        foreach (linCodexUninstallTables() as $table) {
            expect(Schema::hasTable($table))->toBeTrue($table.' was dropped');
        }

        expect(linCodexUninstallSettingsRows())->toBe(count(CodexSettings::defaults()))
            ->and(app(CodexSettings::class)->refresh()->revisions_keep)->toBe(10);
    } finally {
        $this->markPackageSchemaDirty();
    }
});

it('drops the tables, the settings rows and the package migration records with --force', function (): void {
    linCodexUninstallMigrationsTable();

    try {
        $command = $this->artisan('codex:uninstall', ['--force' => true]);

        foreach (linCodexUninstallTables() as $table) {
            $command->expectsOutputToContain('Dropped '.$table);
        }

        $command
            ->expectsOutputToContain('Settings rows deleted')
            ->expectsOutputToContain('1 migration record deleted')
            ->expectsOutputToContain('lin-codex uninstalled')
            ->assertExitCode(0)
            ->run();

        foreach (linCodexUninstallTables() as $table) {
            expect(Schema::hasTable($table))->toBeFalse($table.' still exists');
        }

        expect(linCodexUninstallSettingsRows())->toBe(0)
            ->and(DB::table('migrations')->pluck('migration')->all())->toBe(['2024_01_01_000001_create_users_table']);
    } finally {
        Schema::dropIfExists('migrations');
        $this->markPackageSchemaDirty();
    }
});

it('clears the package caches and the settings cache', function (): void {
    Cache::forever(InMemoryIndex::CACHE_KEY, ['hash' => 'x', 'documents' => []]);

    try {
        $this->artisan('codex:uninstall', ['--force' => true])
            ->expectsOutputToContain('Caches cleared')
            ->assertExitCode(0);

        expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse()
            ->and(app(ArticleRenderer::class)->generation())->toBe(2);
    } finally {
        $this->markPackageSchemaDirty();
    }
});

it('--files removes the published files and never touches the docs folder', function (): void {
    $hadVendor = File::isDirectory(public_path('vendor'));
    $hadJs = File::isDirectory(resource_path('js'));
    $hadSettings = File::isDirectory(database_path('settings'));
    $hadDocs = File::isDirectory(resource_path('codex'));

    $config = config_path('lin-codex.php');
    $stylesheet = public_path('vendor/lin-codex/codex.css');
    $stub = resource_path('js/codex/codex.ts');
    $migration = database_path('migrations/2024_01_01_000000_create_codex_articles_table.php');
    $settingsMigration = database_path('settings/2024_01_01_000000_create_codex_settings.php');
    $docs = resource_path('codex/en/intro.md');

    foreach ([$config, $stylesheet, $stub, $migration, $settingsMigration, $docs] as $file) {
        File::ensureDirectoryExists(dirname($file));
        File::put($file, '// lin-codex uninstall test');
    }

    try {
        $this->artisan('codex:uninstall', ['--force' => true, '--files' => true])
            ->expectsOutputToContain('Deleted '.$config)
            ->expectsOutputToContain('Deleted '.public_path('vendor/lin-codex'))
            ->expectsOutputToContain('Deleted '.resource_path('js/codex'))
            ->expectsOutputToContain('Deleted '.$migration)
            ->expectsOutputToContain('Deleted '.$settingsMigration)
            ->assertExitCode(0);

        expect(File::exists($config))->toBeFalse()
            ->and(File::exists($stylesheet))->toBeFalse()
            ->and(File::isDirectory(public_path('vendor/lin-codex')))->toBeFalse()
            ->and(File::exists($stub))->toBeFalse()
            ->and(File::isDirectory(resource_path('js/codex')))->toBeFalse()
            ->and(File::exists($migration))->toBeFalse()
            ->and(File::exists($settingsMigration))->toBeFalse()
            ->and(File::exists($docs))->toBeTrue()
            ->and(File::get($docs))->toBe('// lin-codex uninstall test');
    } finally {
        File::delete([$config, $stylesheet, $stub, $migration, $settingsMigration, $docs]);
        File::deleteDirectory(public_path('vendor/lin-codex'));
        File::deleteDirectory(resource_path('js/codex'));

        if (! $hadVendor) {
            File::deleteDirectory(public_path('vendor'));
        }

        if (! $hadJs) {
            File::deleteDirectory(resource_path('js'));
        }

        if (! $hadSettings) {
            File::deleteDirectory(database_path('settings'));
        }

        if ($hadDocs) {
            File::deleteDirectory(resource_path('codex/en'));
        } else {
            File::deleteDirectory(resource_path('codex'));
        }

        $this->markPackageSchemaDirty();
    }
});

it('works when the migrations table does not exist', function (): void {
    expect(Schema::hasTable('migrations'))->toBeFalse();

    try {
        $this->artisan('codex:uninstall', ['--force' => true])
            ->doesntExpectOutputToContain('migration record')
            ->expectsOutputToContain('lin-codex uninstalled')
            ->assertExitCode(0);

        foreach (linCodexUninstallTables() as $table) {
            expect(Schema::hasTable($table))->toBeFalse($table.' still exists');
        }
    } finally {
        $this->markPackageSchemaDirty();
    }
});
