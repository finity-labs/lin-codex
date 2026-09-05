<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Coverage\RouteCoverage;
use FinityLabs\LinCodex\Coverage\RouteCoverageRow;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/**
 * A route action under a namespace the coverage report is told to skip.
 */
final class LinCodexCoverageVendorController
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * Stands in for a Livewire page component named in the route action.
 */
final class LinCodexCoverageFakePage {}

/**
 * @param  list<RouteCoverageRow>  $rows
 *
 * @return list<string>
 */
function linCodexCoverageNames(array $rows): array
{
    return array_map(static fn (RouteCoverageRow $row): string => $row->name, $rows);
}

/**
 * @param  list<RouteCoverageRow>  $rows
 */
function linCodexCoverageRow(array $rows, string $name): RouteCoverageRow
{
    foreach ($rows as $row) {
        if ($row->name === $name) {
            return $row;
        }
    }

    throw new RuntimeException('No coverage row for route '.$name);
}

/**
 * Register a named GET route on the web group.
 */
function linCodexCoverageWebRoute(string $uri, string $name): void
{
    Route::get($uri, static fn (): string => '')->name($name)->middleware('web');
}

beforeEach(function (): void {
    config()->set('lin-codex.source', 'filesystem');
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    $this->forgetSources();
});

/**
 * The report for the current config and routes; the source singletons are
 * dropped first so a database or config switch in the test is seen.
 *
 * @return list<RouteCoverageRow>
 */
function linCodexCoverageReport(): array
{
    app()->forgetInstance(RouteCoverage::class);

    return app(RouteCoverage::class)->report();
}

it('lists named GET routes on the web group and marks the uncovered ones', function (): void {
    linCodexCoverageWebRoute('/', 'dashboard');
    linCodexCoverageWebRoute('/orphan', 'orphan');
    Route::get('/nameless', static fn (): string => '')->middleware('web');
    Route::post('/submit', static fn (): string => '')->name('submit')->middleware('web');
    Route::get('/api-thing', static fn (): string => '')->name('api.thing')->middleware('api');

    $rows = linCodexCoverageReport();

    expect(linCodexCoverageNames($rows))->toBe(['dashboard', 'orphan']);

    $dashboard = linCodexCoverageRow($rows, 'dashboard');
    $orphan = linCodexCoverageRow($rows, 'orphan');

    expect($dashboard->uri)->toBe('/')
        ->and($dashboard->matchedBy)->toBe('route:dashboard')
        ->and($dashboard->slug)->toBe('intro')
        ->and($dashboard->covered())->toBeTrue()
        ->and($orphan->uri)->toBe('/orphan')
        ->and($orphan->matchedBy)->toBeNull()
        ->and($orphan->slug)->toBeNull()
        ->and($orphan->covered())->toBeFalse();
});

it('matches url patterns against the uri template with a placeholder as one segment', function (): void {
    linCodexCoverageWebRoute('/welcome/{page}', 'welcome');
    linCodexCoverageWebRoute('/welcome/{a}/{b}', 'welcome.deep');
    linCodexCoverageWebRoute('/welcome/{page?}', 'welcome.optional');

    $rows = linCodexCoverageReport();
    $welcome = linCodexCoverageRow($rows, 'welcome');
    $optional = linCodexCoverageRow($rows, 'welcome.optional');

    expect($welcome->uri)->toBe('/welcome/{page}')
        ->and($welcome->matchedBy)->toBe('url:/welcome/*')
        ->and($welcome->slug)->toBe('intro')
        ->and($optional->uri)->toBe('/welcome/{page}')
        ->and($optional->slug)->toBe('intro')
        ->and(linCodexCoverageRow($rows, 'welcome.deep')->covered())->toBeFalse();
});

it('prefers an exact route match over a wildcard and reports the context as written', function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();

    Article::factory()->withTranslation('en')->withContext(ContextType::Route, 'users.*')->create(['slug' => 'users']);
    Article::factory()->withTranslation('en')->withContext(ContextType::Route, 'users.index')->create(['slug' => 'users-list']);
    linCodexCoverageWebRoute('/users', 'users.index');
    linCodexCoverageWebRoute('/users/{user}', 'users.show');

    $rows = linCodexCoverageReport();

    expect(linCodexCoverageRow($rows, 'users.index')->matchedBy)->toBe('route:users.index')
        ->and(linCodexCoverageRow($rows, 'users.index')->slug)->toBe('users-list')
        ->and(linCodexCoverageRow($rows, 'users.show')->matchedBy)->toBe('route:users.*')
        ->and(linCodexCoverageRow($rows, 'users.show')->slug)->toBe('users');
});

it('matches the controller class in any panel', function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();

    Article::factory()->withTranslation('en')->withContext(ContextType::PageClass, 'App\Filament\Pages\Dashboard', 'admin')->create(['slug' => 'admin-help']);
    Route::get('/admin', 'App\Filament\Pages\Dashboard@__invoke')->name('filament.admin.pages.dashboard')->middleware([StartSession::class]);

    $row = linCodexCoverageRow(linCodexCoverageReport(), 'filament.admin.pages.dashboard');

    expect($row->pageClass)->toBe('App\Filament\Pages\Dashboard')
        ->and($row->matchedBy)->toBe('admin:class:App\Filament\Pages\Dashboard')
        ->and($row->slug)->toBe('admin-help');
});

it('admits routes whose expanded stack starts a session even without the web group', function (): void {
    Route::get('/panel', static fn (): string => '')->name('panel.page')->middleware([StartSession::class]);
    Route::get('/bare', static fn (): string => '')->name('bare.page');

    $names = linCodexCoverageNames(linCodexCoverageReport());

    expect($names)->toContain('panel.page')
        ->and($names)->not->toContain('bare.page');
});

it('drops ignored names and vendor namespaces', function (): void {
    config()->set('lin-codex.coverage.ignore', ['hidden.*']);
    config()->set('lin-codex.coverage.vendor_namespaces', ['LinCodexCoverageVendor']);
    linCodexCoverageWebRoute('/hidden', 'hidden.page');
    linCodexCoverageWebRoute('/shown', 'shown.page');
    Route::get('/vendor', LinCodexCoverageVendorController::class)->name('vendor.page')->middleware('web');

    $names = linCodexCoverageNames(linCodexCoverageReport());

    expect($names)->toContain('shown.page')
        ->and($names)->not->toContain('hidden.page')
        ->and($names)->not->toContain('vendor.page');

    config()->set('lin-codex.coverage.ignore', ['livewire.*', 'lin-codex.*']);

    $names = linCodexCoverageNames(linCodexCoverageReport());

    expect($names)->toContain('hidden.page');

    foreach ($names as $name) {
        expect($name)->not->toStartWith('livewire.')->not->toStartWith('lin-codex.');
    }
});

it('uses the default ignore list and vendor namespaces when nothing is configured', function (): void {
    linCodexCoverageWebRoute('/orphan', 'orphan');

    $names = linCodexCoverageNames(linCodexCoverageReport());

    expect($names)->toContain('orphan');

    foreach ($names as $name) {
        expect($name)->not->toStartWith('livewire.')->not->toStartWith('lin-codex.');
    }
});

it('reads the page component from the Livewire route action', function (): void {
    Route::get('/lw', ['uses' => static fn (): string => '', 'livewire_component' => LinCodexCoverageFakePage::class])->name('lw.page')->middleware('web');

    $row = linCodexCoverageRow(linCodexCoverageReport(), 'lw.page');

    expect($row->pageClass)->toBe(LinCodexCoverageFakePage::class)
        ->and($row->covered())->toBeFalse();

    config()->set('lin-codex.source', 'database');
    $this->forgetSources();
    Article::factory()->withTranslation('en')->withContext(ContextType::PageClass, LinCodexCoverageFakePage::class)->create(['slug' => 'lw-help']);

    $row = linCodexCoverageRow(linCodexCoverageReport(), 'lw.page');

    expect($row->matchedBy)->toBe('class:LinCodexCoverageFakePage')
        ->and($row->slug)->toBe('lw-help');
});

it('falls back to no page class when the Livewire component name cannot be resolved', function (): void {
    Route::get('/lw-named', ['uses' => static fn (): string => '', 'livewire_component' => 'pages::does-not-exist'])->name('lw.named')->middleware('web');

    $row = linCodexCoverageRow(linCodexCoverageReport(), 'lw.named');

    expect($row->pageClass)->toBeNull()
        ->and($row->covered())->toBeFalse();
});

it('treats an unpublished or authenticated-only article as a mapping', function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();

    Article::factory()->unpublished()->authenticated()->withTranslation('en')->withContext(ContextType::Route, 'secret.page')->create(['slug' => 'secret']);
    linCodexCoverageWebRoute('/secret', 'secret.page');

    $row = linCodexCoverageRow(linCodexCoverageReport(), 'secret.page');

    expect($row->matchedBy)->toBe('route:secret.page')
        ->and($row->slug)->toBe('secret');
});

it('sorts rows by route name and returns readonly rows', function (): void {
    linCodexCoverageWebRoute('/z', 'zeta');
    linCodexCoverageWebRoute('/a', 'alpha');
    linCodexCoverageWebRoute('/m', 'mid');

    $rows = linCodexCoverageReport();

    expect(linCodexCoverageNames($rows))->toBe(['alpha', 'mid', 'zeta'])
        ->and($rows[0]->toArray())->toBe([
            'name' => 'alpha',
            'uri' => '/a',
            'pageClass' => null,
            'matchedBy' => null,
            'slug' => null,
            'covered' => false,
        ]);

    linCodexAssertNoModels($rows);
});
