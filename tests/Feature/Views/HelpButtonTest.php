<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use FinityLabs\LinCodex\View\PageHelpResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * <x-lin-codex::help-button> and <x-lin-codex::help-drawer> on any layout.
 *
 * $this->blade() renders against the Testbench base request, whose path is
 * "/", so the docs fixture's intro (url:/) is the one page article there.
 * A url: match on another path goes through a registered route and
 * $this->get(). Livewire's injected asset tags are never asserted on.
 */
const LIN_CODEX_HELP_BUTTON_DASHBOARD = 'App\Filament\Pages\Dashboard';

/**
 * Point the filesystem source at another fixture tree and drop the
 * request-scoped resolver memo so the next render resolves again.
 */
function linCodexHelpButtonUseDocs(string $tree): void
{
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath($tree)]);

    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);
    app()->forgetInstance(PageHelpResolver::class);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('renders an icon button linking to the help center that dispatches the open event', function (): void {
    $this->blade('<x-lin-codex::help-button />')
        ->assertSeeHtml('class="codex-help-button"')
        ->assertSeeHtml('href="'.route('lin-codex.help-center').'"')
        ->assertSeeHtml('data-codex-help-button')
        ->assertSeeHtml("CustomEvent('codex:open')")
        ->assertSeeHtml('x-on:click.prevent')
        ->assertSeeHtml('<svg')
        ->assertSeeHtml('codex-help-button__icon')
        ->assertSeeHtml('aria-label="'.__('lin-codex::lin-codex.ui.help').'"')
        ->assertDontSeeHtml('codex-help-button__label')
        ->assertDontSeeHtml('codex-help-button--labelled')
        ->assertDontSeeHtml('codex-help-button--floating');
});

it('adds a label and the labelled modifier', function (): void {
    $this->blade('<x-lin-codex::help-button label="Need help?" />')
        ->assertSeeHtml('codex-help-button--labelled')
        ->assertSeeHtml('codex-help-button__label')
        ->assertSee('Need help?')
        ->assertSeeHtml('aria-label="Need help?"');
});

it('renders the floating pill', function (): void {
    $this->blade('<x-lin-codex::help-button floating />')
        ->assertSeeHtml('codex-help-button--floating');
});

it('merges host classes and attributes', function (): void {
    $this->blade('<x-lin-codex::help-button class="ml-2" id="help" />')
        ->assertSeeHtml('class="codex-help-button ml-2"')
        ->assertSeeHtml('id="help"');
});

it('shows the badge with the mount-time page count', function (): void {
    $this->blade('<x-lin-codex::help-button />')
        ->assertSeeHtml('codex-help-button__badge')
        ->assertSeeHtml('>1<');

    $this->blade('<x-lin-codex::help-button :badge="false" />')
        ->assertDontSeeHtml('codex-help-button__badge');

    $this->blade('<x-lin-codex::help-button :count="7" />')
        ->assertSeeHtml('codex-help-button__badge')
        ->assertSeeHtml('>7<');
});

it('hides the badge when the page has no articles', function (): void {
    linCodexHelpButtonUseDocs('docs-ui');

    $this->blade('<x-lin-codex::help-button />')
        ->assertSeeHtml('data-codex-help-button')
        ->assertDontSeeHtml('codex-help-button__badge');
});

it('shares one resolution with the drawer on a guest page', function (): void {
    linCodexHelpButtonUseDocs('docs-ui');

    Route::get('/ui-page', fn () => Blade::render('<x-lin-codex::help-button /><x-lin-codex::help-drawer />'))->middleware('web');

    $content = (string) $this->get('/ui-page')->assertOk()->getContent();

    expect($content)->toContain('codex-help-button__badge', '>2<', 'data-codex-page-count="2"', 'data-codex-drawer')
        ->not->toContain('members-only');
});

it('shares one resolution with the drawer for a signed-in user', function (): void {
    linCodexHelpButtonUseDocs('docs-ui');

    Route::get('/ui-page', fn () => Blade::render('<x-lin-codex::help-button /><x-lin-codex::help-drawer />'))->middleware('web');

    $this->actingAs(new GenericUser(['id' => 1]));

    $content = (string) $this->get('/ui-page')->assertOk()->getContent();

    expect($content)->toContain('codex-help-button__badge', '>3<', 'data-codex-page-count="3"', 'members-only');
});

/**
 * A second guard with a user while the default web guard stays a guest,
 * set on the guard directly: actingAs() would call shouldUse() and move the
 * default guard, so the "omitted guard" render would pass for the wrong
 * reason.
 */
function linCodexHelpButtonSignInOnStaff(): void
{
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    app(AuthFactory::class)->guard('staff')->setUser(new GenericUser(['id' => 2]));
}

it('resolves the badge through the guard prop and shares nothing between two guards', function (): void {
    linCodexHelpButtonUseDocs('docs-ui');
    linCodexHelpButtonSignInOnStaff();

    // Every docs-ui context is a url:/ui-page match, so the three renders
    // share the path and pick their markup from the query string.
    Route::get('/ui-page', fn () => Blade::render(match (request()->query('render')) {
        'staff' => '<x-lin-codex::help-button guard="staff" />',
        'both' => '<x-lin-codex::help-button guard="staff" /><x-lin-codex::help-button />',
        default => '<x-lin-codex::help-button />',
    }))->middleware('web');

    expect((string) $this->get('/ui-page?render=staff')->assertOk()->getContent())->toContain('>3<')
        ->and((string) $this->get('/ui-page')->assertOk()->getContent())->toContain('>2<')
        ->and((string) $this->get('/ui-page?render=both')->assertOk()->getContent())->toContain('>3<', '>2<');
});

it('shares one resolution with the drawer on a guard', function (): void {
    linCodexHelpButtonUseDocs('docs-ui');
    linCodexHelpButtonSignInOnStaff();

    Route::get('/ui-page', fn () => Blade::render('<x-lin-codex::help-button guard="staff" /><x-lin-codex::help-drawer guard="staff" />'))->middleware('web');

    $content = (string) $this->get('/ui-page')->assertOk()->getContent();

    expect($content)->toContain('codex-help-button__badge', '>3<', 'data-codex-page-count="3"', '&quot;guard&quot;:&quot;staff&quot;', 'members-only');
});

it('forwards guard, shortcut and width through the wrapper and lets two drawers differ', function (): void {
    linCodexHelpButtonSignInOnStaff();

    $this->blade('<x-lin-codex::help-drawer :width="360" shortcut="ctrl+." guard="staff" /><x-lin-codex::help-drawer :width="640" shortcut="" />')
        ->assertSeeHtml('--codex-drawer-width: 360px')
        ->assertSeeHtml('--codex-drawer-width: 640px')
        ->assertSee(__('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => 'ctrl+.']))
        ->assertSeeHtml('shortcut\u0022:null')
        ->assertSeeHtml('&quot;guard&quot;:&quot;staff&quot;');

    // An omitted shortcut never reaches the Livewire tag, so the configured one survives.
    $this->blade('<x-lin-codex::help-drawer />')
        ->assertSeeHtml('--codex-drawer-width: 480px')
        ->assertSeeHtml('ctrl+\\\\\/')
        ->assertDontSeeHtml('shortcut\u0022:null');

    $this->blade('<x-lin-codex::help-drawer locale="de" shortcut="" />')
        ->assertSeeHtml('&quot;locale&quot;:&quot;de&quot;')
        ->assertSeeHtml('shortcut\u0022:null');

    $this->blade('<x-lin-codex::help-drawer locale="de" />')
        ->assertSeeHtml('&quot;locale&quot;:&quot;de&quot;')
        ->assertSeeHtml('ctrl+\\\\\/');
});

it('passes page class and panel id through both components', function (): void {
    linCodexHelpButtonUseDocs('docs-ui');

    $this->blade('<x-lin-codex::help-button />')
        ->assertDontSeeHtml('codex-help-button__badge');

    linCodexHelpButtonUseDocs('docs');

    $this->blade('<x-lin-codex::help-button page-class="'.LIN_CODEX_HELP_BUTTON_DASHBOARD.'" panel-id="admin" />')
        ->assertSeeHtml('codex-help-button__badge')
        ->assertSeeHtml('>1<');

    $this->blade('<x-lin-codex::help-drawer page-class="'.LIN_CODEX_HELP_BUTTON_DASHBOARD.'" panel-id="admin" />')
        ->assertSeeHtml('data-codex-page-count="1"')
        ->assertSeeHtml('&quot;panel&quot;:&quot;admin&quot;');

    $this->blade('<x-lin-codex::help-drawer slug="users" />')
        ->assertSeeHtml('data-codex-slug="users"');

    $this->blade('<x-lin-codex::help-drawer locale="de" />')
        ->assertSeeHtml('&quot;locale&quot;:&quot;de&quot;');
});

it('works on a guest page that only Livewire renders', function (): void {
    Route::get('/login-stand-in', fn () => '<!doctype html><html><head></head><body>'.Blade::render('<x-lin-codex::help-button /><x-lin-codex::help-drawer />').'</body></html>')->middleware('web');

    $content = (string) $this->get('/login-stand-in')->assertOk()->getContent();

    expect($content)->toContain('data-codex-help-button', 'data-codex-drawer', 'data-codex-page-count="0"');

    Livewire::test(HelpDrawer::class, ['pageClass' => LIN_CODEX_HELP_BUTTON_DASHBOARD])
        ->call('open')
        ->assertSet('isOpen', true)
        ->assertSee('Introduction');
});
