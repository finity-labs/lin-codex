<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;

/**
 * Switch to one of the three sources over the docs-visibility tree, seeding
 * the database twins when the source reads the database. A copy of the
 * VisibilityLeakTest helper: Pest test files share one global scope and
 * load order is not guaranteed, so cross-file helpers are never called.
 */
function linCodexApiLeakUseSource(string $source): void
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
 * Every slug of the JSON tree, depth first through the children key.
 *
 * @param  list<array<string, mixed>>  $nodes
 *
 * @return list<string>
 */
function linCodexApiLeakTreeSlugs(array $nodes): array
{
    $slugs = [];

    foreach ($nodes as $node) {
        $slugs[] = (string) $node['slug'];

        /** @var list<array<string, mixed>> $children */
        $children = $node['children'];

        foreach (linCodexApiLeakTreeSlugs($children) as $slug) {
            $slugs[] = $slug;
        }
    }

    return $slugs;
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
});

it('never leaks through the json api', function (string $source, string $viewer, string $slug, bool $guestSees, bool $userSees, string $query): void {
    linCodexApiLeakUseSource($source);

    if ($viewer === 'user') {
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    $expected = $viewer === 'user' ? $userSees : $guestSees;

    $article = $this->getJson('/codex/api/articles/'.$slug);
    $article->assertStatus($expected ? 200 : 404);

    expect($article->getStatusCode())->not->toBe(403);

    if ($expected) {
        expect($article->json('data.slug'))->toBe($slug);
    } else {
        $article->assertExactJson(['message' => 'Article not found.']);
    }

    /** @var list<array<string, mixed>> $tree */
    $tree = $this->getJson('/codex/api/tree')->assertOk()->json('data');

    expect(in_array($slug, linCodexApiLeakTreeSlugs($tree), true))->toBe($expected)
        ->and($this->getJson('/codex/api/context?path=/leak/'.$slug)->assertOk()->json('data.*.slug'))->toBe($expected ? [$slug] : []);

    /** @var list<string> $hits */
    $hits = $this->getJson('/codex/api/search?'.http_build_query(['q' => $query]))->assertOk()->json('data.*.slug');

    expect(in_array($slug, $hits, true))->toBe($expected);
})->with('lin-codex sources', 'lin-codex viewers', 'lin-codex leak articles');

it('hides a section and its public child from the guest tree wholesale over http', function (string $source): void {
    linCodexApiLeakUseSource($source);

    /** @var list<array<string, mixed>> $guestTree */
    $guestTree = $this->getJson('/codex/api/tree')->assertOk()->json('data');

    expect(linCodexApiLeakTreeSlugs($guestTree))
        ->toBe(['group', 'group/public-child', 'only-en', 'public-published', 'shared']);

    $this->actingAs(new GenericUser(['id' => 1]));

    /** @var list<array<string, mixed>> $userTree */
    $userTree = $this->getJson('/codex/api/tree')->assertOk()->json('data');

    expect(linCodexApiLeakTreeSlugs($userTree))
        ->toBe(['auth-published', 'group', 'group/public-child', 'internal', 'internal/public-child', 'only-en', 'public-published', 'shared']);
})->with('lin-codex sources');

it('answers a hidden, a restricted and a missing slug identically over http', function (string $source): void {
    linCodexApiLeakUseSource($source);

    $hidden = $this->getJson('/codex/api/articles/auth-published');
    $missing = $this->getJson('/codex/api/articles/does-not-exist');
    $restricted = $this->getJson('/codex/api/articles/internal/public-child');

    foreach ([$hidden, $missing, $restricted] as $response) {
        expect($response->getStatusCode())->toBe(404)->not->toBe(403);

        $response->assertExactJson(['message' => 'Article not found.']);
    }

    expect($hidden->getContent())->toBe($missing->getContent())
        ->and($restricted->getContent())->toBe($missing->getContent())
        ->and($hidden->headers->get('Content-Type'))->toBe($missing->headers->get('Content-Type'))
        ->and($restricted->headers->get('Content-Type'))->toBe($missing->headers->get('Content-Type'))
        ->and($this->getJson('/codex/api/context?path=/leak/auth-published')->assertOk()->json('data'))->toBe([])
        ->and($this->getJson('/codex/api/context?path=/leak/does-not-exist')->assertOk()->json('data'))->toBe([])
        ->and($this->getJson('/codex/api/search?'.http_build_query(['q' => 'Authenticated published']))->assertOk()->json('data'))->toBe([])
        ->and($this->getJson('/codex/api/search?'.http_build_query(['q' => 'does not exist']))->assertOk()->json('data'))->toBe([]);
})->with('lin-codex sources');

it('keeps guest search results inside the guest tree on every source', function (string $source): void {
    linCodexApiLeakUseSource($source);

    /** @var list<array<string, mixed>> $tree */
    $tree = $this->getJson('/codex/api/tree')->assertOk()->json('data');
    $treeSlugs = linCodexApiLeakTreeSlugs($tree);

    /** @var list<string> $hits */
    $hits = $this->getJson('/codex/api/search?q=Public')->assertOk()->json('data.*.slug');

    // shared.md's body says "A public article", so the file source adds it;
    // every hit must still be one the guest tree shows.
    expect(array_values(array_diff($hits, $treeSlugs)))->toBe([])
        ->and($hits)->toContain('public-published')
        ->and($hits)->toContain('group/public-child')
        ->and($hits)->not->toContain('public-unpublished')
        ->and($hits)->not->toContain('internal/public-child');
})->with('lin-codex sources');
