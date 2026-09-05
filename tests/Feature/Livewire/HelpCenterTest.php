<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Livewire\HelpCenter;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Point the filesystem source at one fixture tree and drop the resolved
 * source singletons so the next read sees it. A copy of the helper other
 * files carry under their own prefix: Pest test files share one global
 * scope and load order is not guaranteed.
 */
function linCodexHelpCenterUseDocs(string $name = 'docs'): void
{
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath($name)]);
    config()->set('lin-codex.source', 'filesystem');

    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);
}

/**
 * @param  list<string>  $codes
 */
function linCodexHelpCenterUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * The markup of one search hit: from its data-codex-hit attribute to the
 * closing anchor tag.
 */
function linCodexHelpCenterHitMarkup(string $html, string $slug): string
{
    $start = strpos($html, 'data-codex-hit="'.$slug.'"');

    expect($start)->not->toBeFalse('no hit for '.$slug);

    $end = strpos($html, '</a>', (int) $start);

    return substr($html, (int) $start, (int) $end - (int) $start);
}

beforeEach(function (): void {
    linCodexHelpCenterUseDocs();
});

it('starts on the topic list with the visible tree', function (): void {
    Livewire::test(HelpCenter::class)
        ->assertSet('slug', null)
        ->assertSet('locale', 'en')
        ->assertSeeHtml('data-codex-help-center')
        ->assertSeeHtml('codex-help-center__layout')
        ->assertSeeHtml('data-codex-tree-node="intro"')
        ->assertSeeHtml('data-codex-tree-node="users"')
        ->assertDontSeeHtml('data-codex-tree-node="users/permissions"')
        ->assertSee(__('lin-codex::lin-codex.ui.pick_a_topic'))
        ->assertSeeHtml('wire:model.live.debounce.300ms="query"')
        ->assertSeeHtml('codex-help-center__toggle');
});

it('renders an article with breadcrumbs, body and the on-this-page column', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(HelpCenter::class, ['slug' => 'users/permissions'])
        ->assertSeeHtml('data-codex-slug="users/permissions"')
        ->assertSeeHtml('codex-breadcrumb')
        ->assertSee('Users')
        ->assertSeeHtml('<code>users.delete</code>')
        ->assertSeeHtml('codex-tree__article--active')
        ->assertSeeHtml('aria-current="page"');

    linCodexHelpCenterUseDocs('docs-ui');

    $html = Livewire::test(HelpCenter::class, ['slug' => 'guide'])
        ->assertSeeHtml('codex-help-center__toc')
        ->assertDontSeeHtml('<details class="codex-toc"')
        ->html();

    $toc = substr($html, (int) strpos($html, 'codex-help-center__toc'));

    expect(substr_count($toc, 'codex-toc__item'))->toBe(4)
        ->and($toc)->toContain('href="#first-step"', 'href="#second-step"', 'href="#third-step"', 'href="#fourth-step"');

    linCodexHelpCenterUseDocs();

    Livewire::test(HelpCenter::class, ['slug' => 'intro'])
        ->assertSeeHtml('data-codex-slug="intro"')
        ->assertDontSeeHtml('codex-help-center__toc');
});

it('shows the not-found line for a hidden or missing slug and keeps the tree', function (string $slug): void {
    Livewire::test(HelpCenter::class, ['slug' => $slug])
        ->assertSee(__('lin-codex::lin-codex.ui.not_found'))
        ->assertDontSeeHtml('data-codex-slug=')
        ->assertSeeHtml('data-codex-tree-node="intro"');
})->with(['users/permissions', 'does-not-exist', 'users/roles']);

it('loads an article in place from show', function (): void {
    Livewire::test(HelpCenter::class)
        ->call('show', 'users')
        ->assertSet('slug', 'users')
        ->assertSeeHtml('data-codex-slug="users"')
        ->assertSeeHtml('data-codex-lightbox');
});

it('replaces the article column with results while a query is typed', function (): void {
    linCodexHelpCenterUseDocs('docs-search');

    $component = Livewire::test(HelpCenter::class)
        ->call('show', 'billing')
        ->assertSeeHtml('data-codex-slug="billing"')
        ->set('query', 'reset')
        ->assertSeeHtml('data-codex-hit="password-reset"')
        ->assertSeeHtml('data-codex-hit="phrase-a"')
        ->assertSeeHtml('codex-search-hit__snippet')
        ->assertDontSeeHtml('data-codex-slug="billing"');

    expect(linCodexHelpCenterHitMarkup($component->html(), 'password-reset'))->not->toContain('codex-search-hit__path');

    $component
        ->set('query', '')
        ->assertSeeHtml('data-codex-slug="billing"')
        ->assertDontSeeHtml('data-codex-hit=');

    Livewire::test(HelpCenter::class)
        ->set('query', 'reset')
        ->assertSeeHtml('data-codex-hit="password-reset"')
        ->call('show', 'password-reset')
        ->assertSet('query', '')
        ->assertSet('slug', 'password-reset')
        ->assertSeeHtml('data-codex-slug="password-reset"')
        ->assertDontSeeHtml('data-codex-hit=');
});

it('asks the searcher for 50 hits and respects the clamp', function (): void {
    linCodexHelpCenterUseDocs('docs-search');

    config()->set('lin-codex.search.limit', 1);

    $html = Livewire::test(HelpCenter::class)->set('query', 'reset')->html();

    expect(substr_count($html, 'data-codex-hit='))->toBe(3)
        ->and($html)->toContain('data-codex-hit="password-reset"', 'data-codex-hit="phrase-a"', 'data-codex-hit="phrase-b"');

    config()->set('lin-codex.search.max_limit', 1);

    expect(substr_count(Livewire::test(HelpCenter::class)->set('query', 'reset')->html(), 'data-codex-hit='))->toBe(1);
});

it('shows the rate-limit message', function (): void {
    linCodexHelpCenterUseDocs('docs-search');

    config()->set('lin-codex.search.rate_limit.guest', 0);

    Livewire::test(HelpCenter::class)
        ->set('query', 'reset')
        ->assertSee(__('lin-codex::lin-codex.ui.rate_limited', ['seconds' => 60]))
        ->assertDontSeeHtml('data-codex-hit=');
});

it('shows the fallback notice under a missing translation', function (): void {
    linCodexHelpCenterUseLanguages(['en', 'de']);
    linCodexHelpCenterUseDocs('docs-ui');

    Livewire::test(HelpCenter::class, ['slug' => 'tips', 'locale' => 'de'])
        ->assertSet('locale', 'de')
        ->assertSeeHtml('data-codex-slug="tips"')
        ->assertSeeHtml('codex-fallback-notice');

    Livewire::test(HelpCenter::class, ['slug' => 'guide', 'locale' => 'de'])
        ->assertSee('Anleitung')
        ->assertDontSeeHtml('codex-fallback-notice');
});

it('locks locale and page state', function (): void {
    expect(fn () => Livewire::test(HelpCenter::class)->set('locale', 'de'))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => Livewire::test(HelpCenter::class)->set('page.path', '/other'))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => Livewire::test(HelpCenter::class)->set('pageArticles', []))->toThrow(CannotUpdateLockedPropertyException::class);
});
