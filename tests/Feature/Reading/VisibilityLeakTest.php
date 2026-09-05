<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contexts\ContextResolver;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Reading\ArticleReader;
use FinityLabs\LinCodex\Reading\TreeBuilder;
use FinityLabs\LinCodex\Search\Searcher;
use FinityLabs\LinCodex\Search\SearchHit;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;

/**
 * Switch to one of the three sources over the docs-visibility tree, seeding
 * the database twins when the source reads the database.
 */
function linCodexLeakUseSource(string $source): void
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
 * @param  list<SearchHit>  $hits
 *
 * @return list<string>
 */
function linCodexLeakHitSlugs(array $hits): array
{
    return array_map(static fn (SearchHit $hit): string => $hit->slug, $hits);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
});

it('never leaks through the reader, the tree, the context resolver, the media route or search', function (string $source, string $viewer, string $slug, bool $guestSees, bool $userSees, string $query): void {
    linCodexLeakUseSource($source);

    if ($viewer === 'user') {
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    $expected = $viewer === 'user' ? $userSees : $guestSees;
    $current = app(ViewerResolver::class)->resolve();

    expect(app(ArticleReader::class)->read($slug, $current) !== null)->toBe($expected)
        ->and(in_array($slug, linCodexLeakTreeSlugs(app(TreeBuilder::class)->build($current)), true))->toBe($expected)
        ->and(linCodexLeakSlugs(app(ContextResolver::class)->resolve(new PageContext(null, '/leak/'.$slug), $current)))->toBe($expected ? [$slug] : [])
        ->and(in_array($slug, linCodexLeakHitSlugs(app(Searcher::class)->search($query, $current)->hits), true))->toBe($expected);

    $response = $this->get('/codex/media/en/images/'.str_replace('/', '-', $slug).'.png');
    $response->assertStatus($expected ? 200 : 404);
    expect($response->getStatusCode())->not->toBe(403);
})->with('lin-codex sources', 'lin-codex viewers', 'lin-codex leak articles');

it('hides a section and its public child from the guest tree wholesale', function (string $source): void {
    linCodexLeakUseSource($source);

    $guest = app(ViewerResolver::class)->resolve();

    expect(linCodexLeakTreeSlugs(app(TreeBuilder::class)->build($guest)))
        ->toBe(['group', 'group/public-child', 'only-en', 'public-published', 'shared']);

    $this->actingAs(new GenericUser(['id' => 1]));
    $user = app(ViewerResolver::class)->resolve();

    expect(linCodexLeakTreeSlugs(app(TreeBuilder::class)->build($user)))
        ->toBe(['auth-published', 'group', 'group/public-child', 'internal', 'internal/public-child', 'only-en', 'public-published', 'shared']);
})->with('lin-codex sources');

it('answers a hidden and a missing slug identically', function (string $source): void {
    linCodexLeakUseSource($source);

    $guest = app(ViewerResolver::class)->resolve();
    $reader = app(ArticleReader::class);
    $resolver = app(ContextResolver::class);

    expect($reader->read('auth-published', $guest))->toBeNull()
        ->and($reader->read('does-not-exist', $guest))->toBeNull()
        ->and($reader->read('internal/public-child', $guest))->toBeNull()
        ->and($resolver->resolve(new PageContext(null, '/leak/auth-published'), $guest))->toBe([])
        ->and($resolver->resolve(new PageContext(null, '/leak/does-not-exist'), $guest))->toBe([])
        ->and(app(Searcher::class)->search('Authenticated published', $guest)->hits)->toBe([])
        ->and(app(Searcher::class)->search('does not exist', $guest)->hits)->toBe([]);
})->with('lin-codex sources');

it('keeps the reader, tree, resolver and search output free of models', function (): void {
    linCodexLeakUseSource('filesystem');

    $this->actingAs(new GenericUser(['id' => 1]));
    $user = app(ViewerResolver::class)->resolve();

    linCodexAssertNoModels(app(ArticleReader::class)->read('public-published', $user));
    linCodexAssertNoModels(app(TreeBuilder::class)->build($user));
    linCodexAssertNoModels(app(ContextResolver::class)->resolve(new PageContext(null, '/leak/public-published'), $user));
    linCodexAssertNoModels(app(Searcher::class)->search('Public published', $user));
});

it('scopes search before matching on every source', function (string $source): void {
    linCodexLeakUseSource($source);

    $guest = app(ViewerResolver::class)->resolve();
    $guestSlugs = linCodexLeakHitSlugs(app(Searcher::class)->search('Public', $guest)->hits);

    // shared.md's body says "A public article", so the file source adds it;
    // every hit must still be one the guest tree shows.
    expect($guestSlugs)->toContain('public-published')
        ->and($guestSlugs)->toContain('group/public-child')
        ->and($guestSlugs)->not->toContain('public-unpublished')
        ->and($guestSlugs)->not->toContain('internal/public-child')
        ->and(array_values(array_diff($guestSlugs, ['group/public-child', 'public-published', 'shared'])))->toBe([]);

    $this->actingAs(new GenericUser(['id' => 1]));
    $user = app(ViewerResolver::class)->resolve();
    $userSlugs = linCodexLeakHitSlugs(app(Searcher::class)->search('Public', $user)->hits);

    expect($userSlugs)->toContain('internal/public-child')
        ->and($userSlugs)->not->toContain('public-unpublished')
        ->and($userSlugs)->toContain('public-published')
        ->and($userSlugs)->toContain('group/public-child');
})->with('lin-codex sources');
