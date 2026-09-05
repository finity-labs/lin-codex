<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

const LIN_CODEX_INSTALL_TABLE_KEYS = ['articles', 'article_translations', 'article_contexts', 'article_revisions', 'media'];

/**
 * Make the Testbench app look like a host that never ran the package
 * migrations: drop the five package tables (foreign key checks off),
 * delete the lin-codex settings rows and drop the migrations table so
 * the migrator has no record of anything. The settings table itself
 * stays, as it does on a host that already uses spatie/laravel-settings.
 */
function linCodexInstallFresh(TestCase $test): void
{
    Schema::disableForeignKeyConstraints();

    foreach (array_reverse(TestCase::PACKAGE_MIGRATIONS) as $file) {
        $test->migration($file)->down();
    }

    Schema::enableForeignKeyConstraints();

    if (Schema::hasTable('settings')) {
        DB::table('settings')->where('group', 'lin-codex')->delete();
    }

    Schema::dropIfExists('migrations');
}

/**
 * What the skeleton looked like before the test, so cleanup removes only
 * the directories the install created.
 *
 * @return array{settings: bool, vendor: bool}
 */
function linCodexInstallSnapshot(): array
{
    return [
        'settings' => File::isDirectory(database_path('settings')),
        'vendor' => File::isDirectory(public_path('vendor')),
    ];
}

/**
 * Remove everything codex:install publishes into the Testbench skeleton
 * and the migrations table it creates.
 *
 * @param  array{settings: bool, vendor: bool}  $before
 */
function linCodexInstallCleanup(array $before): void
{
    File::delete(config_path('lin-codex.php'));
    File::delete(File::glob(database_path('migrations/*_create_codex_*_table.php')));
    File::delete(File::glob(database_path('migrations/*_create_settings_table.php')));

    if ($before['settings']) {
        File::delete(File::glob(database_path('settings/*_create_codex_settings.php')));
    } else {
        File::deleteDirectory(database_path('settings'));
    }

    File::deleteDirectory(public_path('vendor/lin-codex'));

    if (! $before['vendor']) {
        File::deleteDirectory(public_path('vendor'));
    }

    Schema::dropIfExists('migrations');
}

/**
 * @return list<string> the published files matching one migration suffix
 */
function linCodexInstallPublished(string $pattern): array
{
    return File::glob(database_path($pattern));
}

function linCodexInstallCodexMigrationRows(): int
{
    return DB::table('migrations')->where('migration', 'like', '%create_codex_%')->count();
}

/**
 * @return list<string> the five configured table names
 */
function linCodexInstallTables(): array
{
    return array_map(static fn (string $key): string => (string) config('lin-codex.table_names.'.$key), LIN_CODEX_INSTALL_TABLE_KEYS);
}

it('is discovered under its name', function (): void {
    expect(Artisan::all())->toHaveKey('codex:install');
});

it('installs on a fresh app: config, migrations, settings, reindex, summary', function (): void {
    $before = linCodexInstallSnapshot();
    linCodexInstallFresh($this);

    try {
        $this->artisan('codex:install')
            ->expectsOutputToContain('Config published')
            ->expectsOutputToContain('Migrations published')
            ->expectsOutputToContain('Settings seeded')
            ->expectsOutputToContain('translations indexed')
            ->expectsOutputToContain('lin-codex installed')
            ->assertSuccessful();

        expect(File::exists(config_path('lin-codex.php')))->toBeTrue();

        foreach (LIN_CODEX_INSTALL_TABLE_KEYS as $key) {
            expect(linCodexInstallPublished('migrations/*_create_codex_'.$key.'_table.php'))->toHaveCount(1, $key);
        }

        expect(linCodexInstallPublished('settings/*_create_codex_settings.php'))->toHaveCount(1);

        foreach (linCodexInstallTables() as $table) {
            expect(Schema::hasTable($table))->toBeTrue($table.' was not created');
        }

        $settings = app(CodexSettings::class)->refresh();

        expect(linCodexInstallCodexMigrationRows())->toBe(6)
            ->and($settings->revisions_keep)->toBe(10)
            ->and($settings->revisions_enabled)->toBeFalse()
            ->and(File::isDirectory(public_path('vendor/lin-codex')))->toBeFalse();
    } finally {
        linCodexInstallCleanup($before);
        $this->markPackageSchemaDirty();
    }
});

it('is idempotent', function (): void {
    $before = linCodexInstallSnapshot();
    linCodexInstallFresh($this);

    try {
        $this->artisan('codex:install')->assertSuccessful();

        $this->artisan('codex:install')
            ->expectsOutputToContain('Config already published')
            ->expectsOutputToContain('Nothing to migrate')
            ->expectsOutputToContain('lin-codex installed')
            ->assertSuccessful();

        foreach (LIN_CODEX_INSTALL_TABLE_KEYS as $key) {
            expect(linCodexInstallPublished('migrations/*_create_codex_'.$key.'_table.php'))->toHaveCount(1, $key);
        }

        expect(linCodexInstallPublished('settings/*_create_codex_settings.php'))->toHaveCount(1)
            ->and(linCodexInstallCodexMigrationRows())->toBe(6)
            ->and(app(CodexSettings::class)->refresh()->revisions_keep)->toBe(10);
    } finally {
        linCodexInstallCleanup($before);
        $this->markPackageSchemaDirty();
    }
});

it('--force re-publishes the config', function (): void {
    $before = linCodexInstallSnapshot();
    linCodexInstallFresh($this);

    try {
        $this->artisan('codex:install')->assertSuccessful();

        File::append(config_path('lin-codex.php'), "\n// lin-codex-install-marker\n");

        expect(File::get(config_path('lin-codex.php')))->toContain('lin-codex-install-marker');

        $this->artisan('codex:install', ['--force' => true])
            ->expectsOutputToContain('Config published')
            ->assertSuccessful();

        expect(File::get(config_path('lin-codex.php')))->not->toContain('lin-codex-install-marker');
    } finally {
        linCodexInstallCleanup($before);
        $this->markPackageSchemaDirty();
    }
});

it('--assets publishes the stylesheet', function (): void {
    $before = linCodexInstallSnapshot();
    linCodexInstallFresh($this);

    try {
        $this->artisan('codex:install', ['--assets' => true])
            ->expectsOutputToContain('Stylesheet published')
            ->assertSuccessful();

        expect(File::exists(public_path('vendor/lin-codex/codex.css')))->toBeTrue();
    } finally {
        linCodexInstallCleanup($before);
        $this->markPackageSchemaDirty();
    }
});

it('creates the settings table when it is missing', function (): void {
    $before = linCodexInstallSnapshot();
    linCodexInstallFresh($this);
    Schema::dropIfExists('settings');

    try {
        $this->artisan('codex:install')
            ->expectsOutputToContain('Settings table created')
            ->expectsOutputToContain('Settings seeded')
            ->assertSuccessful();

        expect(Schema::hasTable('settings'))->toBeTrue()
            ->and(linCodexInstallPublished('migrations/*_create_settings_table.php'))->toHaveCount(1)
            ->and(app(CodexSettings::class)->refresh()->revisions_keep)->toBe(10)
            ->and(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(count(CodexSettings::defaults()));
    } finally {
        linCodexInstallCleanup($before);
        $this->markPackageSchemaDirty();
    }
});

it('runs only the package migrations', function (): void {
    $before = linCodexInstallSnapshot();
    linCodexInstallFresh($this);

    $probe = database_path('migrations/2020_01_01_000000_create_lin_codex_probe_table.php');

    File::put($probe, <<<'PROBE'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lin_codex_probe', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lin_codex_probe');
    }
};
PROBE);

    try {
        $this->artisan('codex:install')->assertSuccessful();

        expect(Schema::hasTable('lin_codex_probe'))->toBeFalse()
            ->and(DB::table('migrations')->where('migration', 'like', '%lin_codex_probe%')->count())->toBe(0)
            ->and(linCodexInstallCodexMigrationRows())->toBe(6);
    } finally {
        File::delete($probe);
        Schema::dropIfExists('lin_codex_probe');
        linCodexInstallCleanup($before);
        $this->markPackageSchemaDirty();
    }
});
