<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * The two sync commands over the docs-roundtrip fixture with the composite
 * source bound, the way a host app runs them. The commands are only
 * option parsing and printing around ArticleImporter and ArticleExporter,
 * so the cases pin the output and the exit codes, not the services.
 */
function linCodexSyncCommandsUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Sam',
        'email' => 'sam-'.uniqid().'@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/lin-codex-sync-'.uniqid();
    File::ensureDirectoryExists($this->tmp);

    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-roundtrip')]);
    config()->set('lin-codex.source', 'composite');
    $this->forgetSources();
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

it('registers codex:import and codex:export', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('codex:import')
        ->and($commands)->toHaveKey('codex:export');
});

it('prints the per-locale table after an import', function (): void {
    $this->artisan('codex:import')
        ->expectsOutputToContain('Importing articles from '.$this->fixtureDocsPath('docs-roundtrip'))
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '3', '0', '0', '0'],
            ['en', '6', '0', '0', '0'],
        ])
        ->doesntExpectOutputToContain('Skipped:')
        ->doesntExpectOutputToContain('Failed:')
        ->doesntExpectOutputToContain('Warnings:')
        ->assertExitCode(0);

    expect(Article::query()->count())->toBe(6);
});

it('lists skipped slugs and hints at --force', function (): void {
    Article::factory()->withTranslation('en', ['title' => 'Kept'])->create(['slug' => 'intro']);

    $this->artisan('codex:import')
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '2', '0', '1', '0'],
            ['en', '5', '0', '1', '0'],
        ])
        ->expectsOutputToContain('Skipped: intro')
        ->expectsOutputToContain('--force')
        ->assertExitCode(0);

    expect(Article::query()->where('slug', 'intro')->firstOrFail()->translations()->where('locale', 'en')->value('title'))->toBe('Kept');
});

it('prints the dry-run line and writes nothing', function (): void {
    $this->artisan('codex:import', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '3', '0', '0', '0'],
            ['en', '6', '0', '0', '0'],
        ])
        ->assertExitCode(0);

    expect(Article::query()->count())->toBe(0);
});

it('passes --user, --only, --locale and --force through', function (): void {
    $userId = linCodexSyncCommandsUser();

    $this->artisan('codex:import', ['--only' => ['users/roles'], '--locale' => 'de', '--user' => (string) $userId])
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '1', '0', '0', '0'],
        ])
        ->assertExitCode(0);

    $roles = Article::query()->where('slug', 'users/roles')->firstOrFail();

    expect(Article::query()->count())->toBe(1)
        ->and($roles->translations()->pluck('locale')->all())->toBe(['de'])
        ->and($roles->created_by)->toBe($userId);

    $this->artisan('codex:import', ['--only' => ['users/roles'], '--locale' => 'de', '--force' => true])
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '0', '1', '0', '0'],
        ])
        ->assertExitCode(0);

    expect(ArticleTranslation::query()->count())->toBe(1);
});

it('exits 1 when an article failed and prints the reason', function (): void {
    $this->artisan('codex:import', ['--user' => '999999'])
        ->expectsOutputToContain('Failed:')
        ->expectsOutputToContain('en:intro:')
        ->assertExitCode(1);

    expect(Article::query()->count())->toBe(0);
});

it('prints the source warnings', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs')]);
    $this->forgetSources();

    $this->artisan('codex:import')
        ->expectsOutputToContain('Warnings:')
        ->expectsOutputToContain('slug "duplicate" is already taken by an earlier file')
        ->assertExitCode(0);
});

it('exports with --path and prints the table', function (): void {
    $this->artisan('codex:import')->assertExitCode(0);

    $this->artisan('codex:export', ['--path' => $this->tmp])
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '3', '0', '0', '0'],
            ['en', '6', '0', '0', '0'],
        ])
        ->assertExitCode(0);

    expect(is_file($this->tmp.'/en/01-intro.md'))->toBeTrue()
        ->and(is_file($this->tmp.'/de/02-users/index.md'))->toBeTrue();

    $this->artisan('codex:export', ['--path' => $this->tmp])
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '0', '3', '0', '0'],
            ['en', '0', '6', '0', '0'],
        ])
        ->assertExitCode(0);

    $this->artisan('codex:export', ['--path' => $this->tmp, '--only' => ['intro'], '--locale' => 'de', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '0', '1', '0', '0'],
        ])
        ->assertExitCode(0);
});

it('export fails up front without a docs path', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', []);
    $this->forgetSources();

    Article::factory()->withTranslation('en', ['title' => 'A'])->create(['slug' => 'a']);

    $this->artisan('codex:export')
        ->expectsOutputToContain('lin-codex.sources.filesystem.paths')
        ->assertExitCode(1);
});
