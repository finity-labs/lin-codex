<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * The table header codex:coverage prints.
 *
 * @return list<string>
 */
function linCodexCoverageCommandHeaders(): array
{
    return ['Route', 'URI', 'Matched by', 'Article'];
}

/**
 * Register a named GET route on the web group.
 */
function linCodexCoverageCommandRoute(string $uri, string $name): void
{
    Route::get($uri, static fn (): string => '')->name($name)->middleware('web');
}

beforeEach(function (): void {
    config()->set('lin-codex.source', 'filesystem');
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    $this->forgetSources();

    linCodexCoverageCommandRoute('/', 'dashboard');
});

it('is discovered under src/Commands', function (): void {
    expect(Artisan::all())->toHaveKey('codex:coverage');
});

it('prints the table and exits 1 when a route lacks coverage', function (): void {
    linCodexCoverageCommandRoute('/orphan', 'orphan');

    $this->artisan('codex:coverage')
        ->expectsTable(linCodexCoverageCommandHeaders(), [
            ['dashboard', '/', 'route:dashboard', 'intro'],
            ['orphan', '/orphan', 'none', ''],
        ])
        ->expectsOutputToContain('1 of 2 routes have no help article')
        ->assertExitCode(1);
});

it('exits 0 when every route is covered', function (): void {
    $this->artisan('codex:coverage')
        ->expectsTable(linCodexCoverageCommandHeaders(), [
            ['dashboard', '/', 'route:dashboard', 'intro'],
        ])
        ->expectsOutputToContain('Every route has a help article')
        ->assertExitCode(0);
});

it('--no-fail forces exit 0 with the table intact', function (): void {
    linCodexCoverageCommandRoute('/orphan', 'orphan');

    $this->artisan('codex:coverage', ['--no-fail' => true])
        ->expectsTable(linCodexCoverageCommandHeaders(), [
            ['dashboard', '/', 'route:dashboard', 'intro'],
            ['orphan', '/orphan', 'none', ''],
        ])
        ->expectsOutputToContain('1 of 2 routes have no help article')
        ->assertExitCode(0);
});

it('--json prints machine-readable output', function (): void {
    linCodexCoverageCommandRoute('/orphan', 'orphan');

    $exit = Artisan::call('codex:coverage', ['--json' => true, '--no-fail' => true]);
    $output = Artisan::output();
    $decoded = json_decode($output, true);

    expect($exit)->toBe(0)
        ->and($output)->toContain('"uri": "/orphan"')
        ->and($decoded)->toBeArray()
        ->and(array_keys($decoded))->toBe(['routes', 'uncovered', 'warnings'])
        ->and($decoded['routes'])->toHaveCount(2)
        ->and(array_keys($decoded['routes'][0]))->toBe(['name', 'uri', 'pageClass', 'matchedBy', 'slug', 'covered'])
        ->and($decoded['routes'][0])->toBe([
            'name' => 'dashboard',
            'uri' => '/',
            'pageClass' => null,
            'matchedBy' => 'route:dashboard',
            'slug' => 'intro',
            'covered' => true,
        ])
        ->and($decoded['routes'][1]['name'])->toBe('orphan')
        ->and($decoded['routes'][1]['covered'])->toBeFalse()
        ->and($decoded['uncovered'])->toBe(1)
        ->and($decoded['warnings'])->toBeArray()
        ->and(array_is_list($decoded['warnings']))->toBeTrue()
        ->and($decoded['warnings'])->not->toBe([]);

    expect(Artisan::call('codex:coverage', ['--json' => true]))->toBe(1);
});

it('prints the source warnings after the table', function (): void {
    $this->artisan('codex:coverage')
        ->expectsOutputToContain('Warnings:')
        ->expectsOutputToContain('slug "duplicate" is already taken by an earlier file; this file was skipped.')
        ->assertExitCode(0);
});

it('prints no warnings block for a clean source', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-roundtrip')]);
    $this->forgetSources();

    $this->artisan('codex:coverage', ['--no-fail' => true])
        ->doesntExpectOutputToContain('Warnings:')
        ->assertExitCode(0);
});
