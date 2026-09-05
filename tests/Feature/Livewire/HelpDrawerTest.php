<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * The drawer's state machine and markup, driven through Livewire::test().
 *
 * Livewire::test() mounts through a real GET to
 * "/livewire-unit-test-endpoint/{random}" on Livewire 3 and 4 alike, which
 * matches no context, so a test that needs the docs intro page passes the
 * Dashboard page class (intro declares class:App\Filament\Pages\Dashboard)
 * and a test that needs a url: match registers a route and renders the tag
 * through $this->get().
 */
const LIN_CODEX_DRAWER_DASHBOARD = 'App\Filament\Pages\Dashboard';

/**
 * Mount parameters that give the drawer the docs intro page.
 *
 * @return array{pageClass: string}
 */
function linCodexDrawerOnDashboard(): array
{
    return ['pageClass' => LIN_CODEX_DRAWER_DASHBOARD];
}

/**
 * @param  list<string>  $codes
 */
function linCodexDrawerUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * Point the filesystem source at another fixture tree ('docs-ui', ...).
 */
function linCodexDrawerUseDocs(string $tree): void
{
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath($tree)]);

    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('starts closed on the page view with the mount-time articles', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->assertSet('isOpen', false)
        ->assertSet('view', 'page')
        ->assertSet('slug', null)
        ->assertSet('history', [])
        ->assertSet('pageArticles.0.slug', 'intro')
        ->assertSeeHtml('data-codex-page-count="1"')
        ->assertSeeHtml('data-codex-view="page"');
});

it('opens on the first page article with no slug', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->assertSet('isOpen', true)
        ->assertSet('view', 'article')
        ->assertSet('slug', 'intro')
        ->assertSeeHtml('data-codex-slug="intro"')
        ->assertSee('Introduction')
        ->assertDontSeeHtml('codex-also');
});

it('lists the other page articles above the article', function (): void {
    linCodexDrawerUseDocs('docs-ui');

    Route::get('/ui-page', fn () => Blade::render('<livewire:lin-codex.help-drawer slug="guide" />'))->middleware('web');

    $response = $this->get('/ui-page');

    expect($response->getStatusCode())->toBe(200);

    $content = (string) $response->getContent();

    expect($content)->toContain('data-codex-page-count="2"', 'data-codex-slug="guide"', 'codex-also', 'data-codex-page-article="tips"')
        ->not->toContain('data-codex-page-article="guide"')
        ->not->toContain('members-only');
});

it('shows the no-help line, the search box and the tree when the page has no articles', function (): void {
    linCodexDrawerUseDocs('docs-ui');

    Livewire::test(HelpDrawer::class)
        ->assertSet('pageArticles', [])
        ->call('open')
        ->assertSet('isOpen', true)
        ->assertSet('view', 'page')
        ->assertSet('slug', null)
        ->assertSee(__('lin-codex::lin-codex.ui.no_help_for_page'))
        ->assertSeeHtml('data-codex-tree-node="guide"')
        ->assertSeeHtml('data-codex-tree-node="tips"')
        ->assertDontSeeHtml('data-codex-tree-node="members-only"')
        ->assertSeeHtml('wire:model.live.debounce.300ms="query"');
});

it('opens a given slug from the open action and the mount parameter', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open', 'users')
        ->assertSet('isOpen', true)
        ->assertSet('slug', 'users')
        ->assertSet('view', 'article')
        ->assertSeeHtml('data-codex-slug="users"')
        ->assertSeeHtml('data-codex-lightbox')
        ->assertSeeHtml('data-codex-article="users/roles"');

    Livewire::test(HelpDrawer::class, ['slug' => 'users'])
        ->assertSet('isOpen', true)
        ->assertSet('view', 'article')
        ->assertSet('slug', 'users')
        ->assertSeeHtml('data-codex-slug="users"');
});

it('shows the not-found line for a hidden or missing slug, never an error', function (string $slug): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open', $slug)
        ->assertSet('isOpen', true)
        ->assertSet('view', 'article')
        ->assertSet('slug', $slug)
        ->assertSee(__('lin-codex::lin-codex.ui.not_found'))
        ->assertDontSeeHtml('data-codex-slug="'.$slug.'"');
})->with([
    'unpublished' => 'users/roles',
    'missing' => 'does-not-exist',
    'authenticated-only for a guest' => 'users/permissions',
]);

it('keeps the mount-time page articles through search and tree navigation', function (): void {
    $component = Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->set('query', 'roles')
        ->assertSet('view', 'search')
        ->assertSeeHtml('data-codex-hit="users"')
        ->call('goTo', 'tree')
        ->assertSet('view', 'tree')
        ->assertSeeHtml('data-codex-tree-node="users"')
        ->assertSeeHtml('data-codex-tree-node="intro"')
        ->assertDontSeeHtml('data-codex-tree-node="users/permissions"')
        ->call('goTo', 'page')
        ->assertSet('slug', 'intro')
        ->assertSet('view', 'article')
        ->assertSet('pageArticles.0.slug', 'intro')
        ->assertSeeHtml('data-codex-slug="intro"');

    expect(fn () => $component->set('page', ['path' => '/other']))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('walks back through the history one view at a time and keeps the state on close', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->assertSet('history', [['view' => 'page', 'slug' => null]])
        ->call('goTo', 'tree')
        ->assertSet('view', 'tree')
        ->call('show', 'users')
        ->assertSet('view', 'article')
        ->assertSet('slug', 'users')
        ->call('close')
        ->assertSet('isOpen', false)
        ->assertSet('view', 'article')
        ->assertSet('slug', 'users')
        ->call('open')
        ->assertSet('isOpen', true)
        ->assertSet('view', 'article')
        ->assertSet('slug', 'users')
        ->call('back')
        ->assertSet('view', 'tree')
        ->assertSet('slug', 'intro')
        ->call('back')
        ->assertSet('view', 'article')
        ->assertSet('slug', 'intro')
        ->call('back')
        ->assertSet('view', 'page')
        ->assertSet('slug', null)
        ->assertSet('history', [])
        ->call('back')
        ->assertSet('view', 'page')
        ->assertSet('isOpen', true);
});

it('ignores an unknown view name', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->call('goTo', 'article')
        ->assertSet('view', 'article')
        ->assertSet('history', [['view' => 'page', 'slug' => null]])
        ->call('goTo', 'nope')
        ->assertSet('view', 'article')
        ->assertSet('history', [['view' => 'page', 'slug' => null]]);
});

it('switches to the results view as the query changes and back when it is cleared', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->set('query', 'ro')
        ->assertSet('view', 'search')
        ->assertSeeHtml('codex-search-results')
        ->set('query', 'roles')
        ->assertSet('view', 'search')
        ->assertSeeHtml('data-codex-hit="users"')
        ->set('query', '')
        ->assertSet('view', 'article')
        ->assertSet('slug', 'intro')
        ->assertDontSeeHtml('data-codex-hit=');
});

it('shows the empty line for a query without hits', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->set('query', 'zzzz')
        ->assertSet('view', 'search')
        ->assertSee(__('lin-codex::lin-codex.ui.no_results'))
        ->assertDontSeeHtml('data-codex-hit=');
});

it('shows the rate-limit message when the searcher refuses', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 0);

    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open')
        ->set('query', 'roles')
        ->assertSet('view', 'search')
        ->assertSee(__('lin-codex::lin-codex.ui.rate_limited', ['seconds' => 60]))
        ->assertDontSeeHtml('data-codex-hit=');
});

it('opens the table of contents only from three headings', function (): void {
    linCodexDrawerUseDocs('docs-ui');

    $guide = Livewire::test(HelpDrawer::class)->call('open', 'guide')->html();

    expect($guide)->toMatch('/<details class="codex-toc"\s+open\s*>/')
        ->and(substr_count($guide, 'codex-toc__item'))->toBe(4);

    $tips = Livewire::test(HelpDrawer::class)->call('open', 'tips')->html();

    expect($tips)->toMatch('/<details class="codex-toc"\s*>/')
        ->and(substr_count($tips, 'codex-toc__item'))->toBe(1);
});

it('shows the fallback notice above the body', function (): void {
    linCodexDrawerUseLanguages(['en', 'de']);
    linCodexDrawerUseDocs('docs-ui');

    Livewire::test(HelpDrawer::class, ['locale' => 'de'])
        ->call('open', 'tips')
        ->assertSet('locale', 'de')
        ->assertSeeHtml('codex-fallback-notice')
        ->assertSee(app(LocaleResolver::class)->fallbackNotice('en'))
        ->call('open', 'guide')
        ->assertSee('Anleitung')
        ->assertDontSeeHtml('codex-fallback-notice');
});

it('renders breadcrumbs and related links', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->call('open', 'users/permissions')
        ->assertSeeHtml('codex-breadcrumb')
        ->assertSee('Users')
        ->assertSeeHtml("show('users')");

    linCodexDrawerUseDocs('docs-ui');

    Livewire::test(HelpDrawer::class)
        ->call('open', 'guide')
        ->assertSeeHtml('codex-related__link')
        ->assertSeeHtml("show('tips')");
});

it('wires every trigger in the markup', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->assertSeeHtml('x-data="codexDrawer(')
        ->assertSeeHtml('ctrl+\\\\\/')
        ->assertSeeHtml('x-on:codex:open.window=')
        ->assertSeeHtml('x-on:keydown.window=')
        ->assertSeeHtml('x-bind:data-open=')
        ->assertSeeHtml('data-codex-focus')
        ->assertSeeHtml('wire:ignore')
        ->assertSeeHtml('codex-lightbox')
        ->assertSeeHtml('x-cloak')
        ->assertSeeHtml('role="dialog"')
        ->assertSeeHtml('aria-modal="true"')
        ->assertSeeHtml('codex-drawer__overlay')
        ->assertSeeHtml('wire:click="close"')
        ->assertSeeHtml("wire:click.prevent=\"goTo('page')\"")
        ->assertSeeHtml("wire:click.prevent=\"goTo('tree')\"")
        ->assertSeeHtml('href="'.route('lin-codex.help-center').'"')
        ->assertSee(__('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => 'ctrl+/']));

    config()->set('lin-codex.ui.shortcut', null);

    Livewire::test(HelpDrawer::class)
        ->assertSeeHtml('shortcut\u0022:null')
        ->assertDontSee(__('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => 'ctrl+/']));

    config()->set('lin-codex.ui.shortcut', 'ctrl+shift+h');

    Livewire::test(HelpDrawer::class)
        ->assertSeeHtml('ctrl+shift+h')
        ->assertSee(__('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => 'ctrl+shift+h']));
});

it('shows the back button only with history', function (): void {
    Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())
        ->assertDontSeeHtml('wire:click="back"')
        ->call('open')
        ->assertSeeHtml('wire:click="back"');
});

it('locks the page articles against the client', function (): void {
    expect(fn () => Livewire::test(HelpDrawer::class, linCodexDrawerOnDashboard())->set('pageArticles', []))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
