<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Livewire\HelpCenter;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Livewire::test() mounts the component through a real GET to
 * "/livewire-unit-test-endpoint/{random}" on Livewire 3 and 4 alike, so
 * that is the path the detector captures there. A component test that
 * needs a page with articles passes pageClass (the docs fixture's intro
 * matches class:App\Filament\Pages\Dashboard); a test that needs a route
 * name or path registers a route and renders the tag through $this->get().
 */
const LIN_CODEX_REGISTRATION_TEST_PATH = '/livewire-unit-test-endpoint/';

const LIN_CODEX_REGISTRATION_DASHBOARD = 'App\Filament\Pages\Dashboard';

/**
 * The registered name, the class and the root marker of each component.
 *
 * @return array<string, array{0: string, 1: class-string, 2: string}>
 */
function linCodexRegistrationComponents(): array
{
    return [
        'drawer' => ['lin-codex.help-drawer', HelpDrawer::class, 'data-codex-drawer'],
        'help center' => ['lin-codex.help-center', HelpCenter::class, 'data-codex-help-center'],
    ];
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('resolves by name, by class and by tag on the installed Livewire', function (string $name, string $class, string $marker): void {
    Livewire::test($name)->assertSeeHtml($marker);
    Livewire::test($class)->assertSeeHtml($marker);

    expect(Blade::render('<livewire:'.$name.' />'))->toContain($marker);
})->with(linCodexRegistrationComponents());

it('captures the page context and locale at mount and locks them', function (string $name, string $class): void {
    $component = Livewire::test($class)->assertSet('locale', 'en')->assertSet('pageArticles', []);

    expect($component->get('page.path'))->toStartWith(LIN_CODEX_REGISTRATION_TEST_PATH)
        ->and(fn () => $component->set('page', ['path' => '/other']))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $component->set('page.path', '/other'))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $component->set('locale', 'de'))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $component->set('pageArticles', []))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(linCodexRegistrationComponents());

it('captures the page articles at mount from the page class', function (): void {
    Livewire::test(HelpDrawer::class, ['pageClass' => LIN_CODEX_REGISTRATION_DASHBOARD])
        ->assertSet('page.class', LIN_CODEX_REGISTRATION_DASHBOARD)
        ->assertSet('pageArticles.0.slug', 'intro')
        ->assertSet('pageArticles.0.title', 'Introduction')
        ->assertSet('pageArticles.0.isFallback', false)
        ->assertSeeHtml('data-codex-page-count="1"');
});

it('captures the route name and path of a real page request', function (): void {
    Route::get('/dashboard', fn () => Blade::render('<livewire:lin-codex.help-drawer />'))->name('dashboard')->middleware('web');

    $response = $this->get('/dashboard');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain('data-codex-drawer', 'data-codex-page-count="1"', '&quot;route&quot;:&quot;dashboard&quot;', '&quot;path&quot;:&quot;\/dashboard&quot;');
});

it('passes page class, panel id and locale through mount', function (): void {
    Livewire::test(HelpDrawer::class, ['pageClass' => '\\'.LIN_CODEX_REGISTRATION_DASHBOARD, 'panelId' => 'admin', 'locale' => 'de'])
        ->assertSet('page.class', LIN_CODEX_REGISTRATION_DASHBOARD)
        ->assertSet('page.panel', 'admin')
        ->assertSet('locale', 'de');
});

it('renders the help drawer root with the configured width and the page count', function (): void {
    Livewire::test(HelpDrawer::class)
        ->assertSeeHtml('data-codex-page-count="0"')
        ->assertSeeHtml('--codex-drawer-width: 480px');

    config()->set('lin-codex.ui.drawer_width', 360);

    Livewire::test(HelpDrawer::class, ['pageClass' => LIN_CODEX_REGISTRATION_DASHBOARD])
        ->assertSeeHtml('data-codex-page-count="1"')
        ->assertSeeHtml('--codex-drawer-width: 360px');
});
