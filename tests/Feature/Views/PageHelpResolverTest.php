<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\View\PageHelp;
use FinityLabs\LinCodex\View\PageHelpResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;

/**
 * @param  list<string>  $codes
 */
function linCodexPageHelpUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * Point the filesystem source at one fixture tree and drop the resolved
 * source singletons so the next read sees it.
 */
function linCodexPageHelpUseDocs(string $name): void
{
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath($name)]);
    test()->forgetSources();
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('resolves the current request\'s page once and memoises per key', function (): void {
    $resolver = app(PageHelpResolver::class);

    $help = $resolver->for();

    expect($help)->toBeInstanceOf(PageHelp::class)
        ->and($help->context->path)->toBe('/')
        ->and($help->locale)->toBe('en')
        ->and($help->articles)->toBe([
            ['slug' => 'intro', 'title' => 'Introduction', 'excerpt' => 'What Codex does and where to start.', 'isFallback' => false],
        ])
        ->and($help->count())->toBe(1)
        ->and($help->firstSlug())->toBe('intro')
        ->and($resolver->for())->toBe($help);

    $other = $resolver->for('App\Filament\Pages\Dashboard', 'admin');

    expect($other)->not->toBe($help)
        ->and($other->context->pageClass)->toBe('App\Filament\Pages\Dashboard')
        ->and($other->context->panelId)->toBe('admin');
});

it('captures the route name and path of a real request', function (): void {
    Route::get('/dashboard', fn () => app(PageHelpResolver::class)->for()->context->toArray())->name('dashboard')->middleware('web');

    expect($this->get('/dashboard')->assertOk()->json())->toBe([
        'route' => 'dashboard',
        'path' => '/dashboard',
        'class' => null,
        'panel' => null,
    ]);
});

it('forgets the memo when the request changes', function (): void {
    Route::get('/ui-page', fn () => (string) app(PageHelpResolver::class)->for()->count())->middleware('web');
    Route::get('/nothing-here', fn () => (string) app(PageHelpResolver::class)->for()->count())->middleware('web');
    linCodexPageHelpUseDocs('docs-ui');

    expect($this->get('/ui-page')->assertOk()->getContent())->toBe('2')
        ->and($this->get('/nothing-here')->assertOk()->getContent())->toBe('0');
});

it('scopes the page articles to a guest like ContextResolver', function (): void {
    Route::get('/ui-page', fn () => array_column(app(PageHelpResolver::class)->for()->articles, 'slug'))->middleware('web');
    linCodexPageHelpUseDocs('docs-ui');

    expect($this->get('/ui-page')->assertOk()->json())->toBe(['guide', 'tips']);
});

// Every docs-ui context is an exact url match in position 0 of its own
// list, so ContextIndex falls through to the slug for the order; the
// article's "order" key plays no part in context matching.
it('orders and scopes the page articles like ContextResolver', function (): void {
    Route::get('/ui-page', fn () => array_column(app(PageHelpResolver::class)->for()->articles, 'slug'))->middleware('web');
    linCodexPageHelpUseDocs('docs-ui');

    $this->actingAs(new GenericUser(['id' => 1]));

    expect($this->get('/ui-page')->assertOk()->json())->toBe(['guide', 'members-only', 'tips']);
});

it('flags fallback entries', function (): void {
    linCodexPageHelpUseLanguages(['en', 'de']);
    Route::get('/ui-page', fn () => app(PageHelpResolver::class)->for(null, null, 'de')->articles)->middleware('web');
    linCodexPageHelpUseDocs('docs-ui');

    $articles = $this->get('/ui-page')->assertOk()->json();

    // The excerpt is a translation key and de/guide.md declares none.
    expect($articles)->toBe([
        ['slug' => 'guide', 'title' => 'Anleitung', 'excerpt' => null, 'isFallback' => false],
        ['slug' => 'tips', 'title' => 'Tips', 'excerpt' => null, 'isFallback' => true],
    ]);
});

it('is a readonly value with no models', function (): void {
    linCodexAssertNoModels(app(PageHelpResolver::class)->for());
});
