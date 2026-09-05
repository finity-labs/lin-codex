<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * The shared visibility matrix over the drawer's four read paths: the page
 * articles captured at mount, open(slug), the tree view and search. Every
 * rule comes from the Phase 4 and 5 read services; the drawer adds none,
 * so these rows must agree with VisibilityLeakTest and ApiLeakTest.
 */

/**
 * Switch to one of the three sources over the docs-visibility tree, seeding
 * the database twins when the source reads the database. A copy of the
 * ApiLeakTest helper under this file's prefix: Pest test files share one
 * global scope and load order is not guaranteed, so cross-file helpers are
 * never called.
 */
function linCodexDrawerLeakUseSource(string $source): void
{
    config()->set('lin-codex.source', $source);

    if ($source !== 'filesystem') {
        linCodexLeakSeedDatabase();
    }

    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);
}

/**
 * The page count the drawer captures on a real GET to /leak/{slug}, the
 * url: context every docs-visibility article declares. Registers the
 * catch-all route (once per test: every dataset row is a fresh application).
 */
function linCodexDrawerLeakPageCount(string $slug): int
{
    Route::get('/leak/{any}', fn () => Blade::render('<x-lin-codex::help-drawer />'))->where('any', '.+')->middleware('web');

    $content = (string) test()->get('/leak/'.$slug)->assertOk()->getContent();

    expect($content)->toContain('data-codex-drawer');

    preg_match('/data-codex-page-count="(\d+)"/', $content, $matches);

    return (int) ($matches[1] ?? -1);
}

/**
 * Every data-codex-tree-node slug in a render, sorted.
 *
 * @return list<string>
 */
function linCodexDrawerLeakTreeSlugs(string $html): array
{
    preg_match_all('/data-codex-tree-node="([^"]+)"/', $html, $matches);

    $slugs = $matches[1];
    sort($slugs);

    return array_values($slugs);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
});

it('never leaks through the drawer', function (string $source, string $viewer, string $slug, bool $guestSees, bool $userSees, string $query): void {
    linCodexDrawerLeakUseSource($source);

    if ($viewer === 'user') {
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    $expected = $viewer === 'user' ? $userSees : $guestSees;

    expect(linCodexDrawerLeakPageCount($slug))->toBe($expected ? 1 : 0);

    $component = Livewire::test(HelpDrawer::class)->call('open', $slug)->assertSet('view', 'article');

    if ($expected) {
        $component->assertSeeHtml('data-codex-slug="'.$slug.'"');
    } else {
        $component->assertSee(__('lin-codex::lin-codex.ui.not_found'))->assertDontSeeHtml('data-codex-slug="'.$slug.'"');
    }

    $component->call('goTo', 'tree')->assertSet('view', 'tree');

    expect(in_array($slug, linCodexDrawerLeakTreeSlugs($component->html()), true))->toBe($expected);

    $component->set('query', $query)->assertSet('view', 'search');

    if ($expected) {
        $component->assertSeeHtml('data-codex-hit="'.$slug.'"');
    } else {
        $component->assertDontSeeHtml('data-codex-hit="'.$slug.'"');
    }
})->with('lin-codex sources', 'lin-codex viewers', 'lin-codex leak articles');

it('hides a section and its public child from the guest tree wholesale', function (string $source): void {
    linCodexDrawerLeakUseSource($source);

    $guest = Livewire::test(HelpDrawer::class)->call('goTo', 'tree')->html();

    expect(linCodexDrawerLeakTreeSlugs($guest))
        ->toBe(['group', 'group/public-child', 'only-en', 'public-published', 'shared']);

    $this->actingAs(new GenericUser(['id' => 1]));

    $user = Livewire::test(HelpDrawer::class)->call('goTo', 'tree')->html();

    expect(linCodexDrawerLeakTreeSlugs($user))
        ->toBe(['auth-published', 'group', 'group/public-child', 'internal', 'internal/public-child', 'only-en', 'public-published', 'shared']);
})->with('lin-codex sources');

it('answers a hidden, a restricted and a missing slug identically', function (string $source): void {
    linCodexDrawerLeakUseSource($source);

    foreach (['auth-published', 'internal/public-child', 'does-not-exist'] as $slug) {
        Livewire::test(HelpDrawer::class)
            ->call('open', $slug)
            ->assertSet('isOpen', true)
            ->assertSet('view', 'article')
            ->assertSee(__('lin-codex::lin-codex.ui.not_found'))
            ->assertDontSeeHtml('data-codex-slug=');
    }
})->with('lin-codex sources');
