<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Livewire\HelpCenter;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;
use Livewire\Livewire;

/**
 * Switch to one of the three sources over the docs-visibility tree, seeding
 * the database twins when the source reads the database. A copy of the
 * VisibilityLeakTest helper: Pest test files share one global scope and
 * load order is not guaranteed, so cross-file helpers are never called.
 */
function linCodexHelpCenterLeakUseSource(string $source): void
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
 * Every tree node slug in the rendered help center, in document order
 * (depth first, as the partial recurses).
 *
 * @return list<string>
 */
function linCodexHelpCenterLeakTreeSlugs(string $html): array
{
    preg_match_all('/data-codex-tree-node="([^"]+)"/', $html, $matches);

    return $matches[1];
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
});

it('never leaks through the help center', function (string $source, string $viewer, string $slug, bool $guestSees, bool $userSees, string $query): void {
    linCodexHelpCenterLeakUseSource($source);

    if ($viewer === 'user') {
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    $expected = $viewer === 'user' ? $userSees : $guestSees;

    $page = $this->get('/help/'.$slug);
    $content = (string) $page->getContent();

    expect($page->getStatusCode())->toBe(200)->not->toBe(403)
        ->and(str_contains($content, 'data-codex-slug="'.$slug.'"'))->toBe($expected)
        ->and(str_contains($content, __('lin-codex::lin-codex.ui.not_found')))->toBe(! $expected);

    $component = Livewire::test(HelpCenter::class);

    expect(str_contains($component->html(), 'data-codex-tree-node="'.$slug.'"'))->toBe($expected);

    $component->set('query', $query);

    expect(str_contains($component->html(), 'data-codex-hit="'.$slug.'"'))->toBe($expected);
})->with('lin-codex sources', 'lin-codex viewers', 'lin-codex leak articles');

it('hides a section and its public child wholesale', function (string $source): void {
    linCodexHelpCenterLeakUseSource($source);

    expect(linCodexHelpCenterLeakTreeSlugs(Livewire::test(HelpCenter::class)->html()))
        ->toBe(['group', 'group/public-child', 'only-en', 'public-published', 'shared']);

    $this->actingAs(new GenericUser(['id' => 1]));

    expect(linCodexHelpCenterLeakTreeSlugs(Livewire::test(HelpCenter::class)->html()))
        ->toBe(['auth-published', 'group', 'group/public-child', 'internal', 'internal/public-child', 'only-en', 'public-published', 'shared']);
})->with('lin-codex sources');
