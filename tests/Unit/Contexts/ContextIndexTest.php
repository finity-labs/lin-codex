<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contexts\ContextIndex;
use FinityLabs\LinCodex\Contexts\ContextMatch;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\SlugPath;

/**
 * @param  list<string|array{0: string, 1: int}>  $contexts  context strings, or [string, sortOrder] pairs
 */
function linCodexIndexArticle(string $slug, array $contexts): ArticleData
{
    $parsed = array_map(
        static fn (string|array $context): ContextData => is_array($context)
            ? ContextData::fromString($context[0], $context[1])
            : ContextData::fromString($context),
        $contexts,
    );

    return new ArticleData(
        slug: $slug,
        parentSlug: SlugPath::parentOf($slug),
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: $parsed,
        related: [],
        keywords: [],
        translations: ['en' => new TranslationData('en', ucfirst($slug), null, 'Body', 'body')],
    );
}

/**
 * @param  array<string, list<string|array{0: string, 1: int}>>  $articles  slug => contexts
 */
function linCodexIndex(array $articles): ContextIndex
{
    $map = [];

    foreach ($articles as $slug => $contexts) {
        $map[$slug] = linCodexIndexArticle($slug, $contexts);
    }

    return ContextIndex::fromArticles($map);
}

/**
 * @param  list<ContextMatch>  $matches
 *
 * @return list<string>
 */
function linCodexIndexSlugs(array $matches): array
{
    return array_map(static fn (ContextMatch $match): string => $match->slug, $matches);
}

function linCodexIndexPage(): PageContext
{
    return new PageContext('users.index', '/users', 'App\Pages\Users');
}

it('filters by the exact panel id, with null meaning panel-less only', function (): void {
    $index = linCodexIndex([
        'a' => ['admin:route:users.index'],
        'b' => ['route:users.index'],
    ]);

    expect(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), 'admin')))->toBe(['a'])
        ->and(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), null)))->toBe(['b'])
        ->and(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), 'other')))->toBe([]);
});

it('orders exact matches before wildcard matches regardless of sortOrder', function (): void {
    $index = linCodexIndex([
        'w' => ['route:users.*'],
        'e' => [['route:users.index', 5]],
    ]);

    $matches = $index->candidates(linCodexIndexPage(), null);

    expect(linCodexIndexSlugs($matches))->toBe(['e', 'w'])
        ->and($matches[0]->exact)->toBeTrue()
        ->and($matches[1]->exact)->toBeFalse();
});

it('orders by type: class, then route, then url', function (): void {
    $index = linCodexIndex([
        'u' => ['url:/users'],
        'r' => ['route:users.index'],
        'c' => ['class:App\Pages\Users'],
    ]);

    expect(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), null)))->toBe(['c', 'r', 'u']);
});

it('orders by sortOrder then slug within a type', function (): void {
    $index = linCodexIndex([
        'b' => [['route:users.index', 1]],
        'a' => [['route:users.index', 2]],
        'c' => [['route:users.index', 1]],
    ]);

    expect(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), null)))->toBe(['b', 'c', 'a']);
});

it('lists many articles for one key in their sortOrder', function (): void {
    $index = linCodexIndex([
        'x' => [['url:/users', 3]],
        'y' => [['url:/users', 1]],
        'z' => [['url:/users', 2]],
    ]);

    expect(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), null)))->toBe(['y', 'z', 'x']);
});

it('returns an article once even when several of its contexts match', function (): void {
    $index = linCodexIndex([
        'a' => ['route:users.index', 'url:/users', 'class:App\Pages\Users'],
    ]);

    $matches = $index->candidates(linCodexIndexPage(), null);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->slug)->toBe('a')
        ->and($matches[0]->context->type)->toBe(ContextType::PageClass)
        ->and($index->count())->toBe(3);
});

it('distinguishes * from ** by depth', function (): void {
    $index = linCodexIndex([
        'one' => ['url:/users/*'],
        'deep' => ['url:/users/**'],
    ]);

    expect(linCodexIndexSlugs($index->candidates(new PageContext(null, '/users/1'), null)))->toBe(['deep', 'one'])
        ->and(linCodexIndexSlugs($index->candidates(new PageContext(null, '/users/1/edit'), null)))->toBe(['deep'])
        ->and(linCodexIndexSlugs($index->candidates(new PageContext(null, '/users'), null)))->toBe(['deep']);
});

it('sorts catch-alls last', function (): void {
    $index = linCodexIndex([
        'all' => ['url:/**'],
        'any' => ['route:*'],
        'exact' => ['route:home'],
    ]);

    expect(linCodexIndexSlugs($index->candidates(new PageContext('home', '/home'), null)))->toBe(['exact', 'any', 'all']);
});

it('matches nothing against null page fields', function (): void {
    $index = linCodexIndex([
        'any' => ['route:*'],
        'c' => ['class:App\X'],
        'root' => ['url:/'],
    ]);

    expect(linCodexIndexSlugs($index->candidates(new PageContext(null, '/'), null)))->toBe(['root']);
});

it('matches a class key exactly, ignoring the leading backslash and never inheriting', function (): void {
    $index = linCodexIndex([
        'c' => ['class:\App\Pages\Users'],
    ]);

    expect(linCodexIndexSlugs($index->candidates(new PageContext(null, '/', 'App\Pages\Users'), null)))->toBe(['c'])
        ->and(linCodexIndexSlugs($index->candidates(new PageContext(null, '/', 'App\Pages\Base'), null)))->toBe([]);
});

it('normalises url keys at index time', function (): void {
    $index = linCodexIndex([
        't' => ['url:users/1/'],
    ]);

    $matches = $index->candidates(new PageContext(null, '/users/1'), null);

    expect(linCodexIndexSlugs($matches))->toBe(['t'])
        ->and($matches[0]->exact)->toBeTrue()
        ->and($matches[0]->context->key)->toBe('/users/1');
});

it('ignores articles without contexts and handles an empty map', function (): void {
    $index = linCodexIndex([
        'none' => [],
        'r' => ['route:users.index'],
    ]);

    expect($index->count())->toBe(1)
        ->and(linCodexIndexSlugs($index->candidates(linCodexIndexPage(), null)))->toBe(['r']);

    $empty = ContextIndex::fromArticles([]);

    expect($empty->count())->toBe(0)
        ->and($empty->candidates(linCodexIndexPage(), null))->toBe([]);
});

it('survives serialize() and yields no models', function (): void {
    $index = linCodexIndex([
        'a' => ['route:users.index', 'admin:url:/users'],
    ]);

    expect(unserialize(serialize($index)))->toEqual($index);

    linCodexAssertNoModels($index->candidates(linCodexIndexPage(), null));
});

it('lists the distinct panel ids in first-seen order', function (): void {
    $index = linCodexIndex([
        'a' => ['route:home', 'admin:route:home'],
        'b' => ['billing:url:/billing', 'admin:url:/admin'],
    ]);

    expect($index->panelIds())->toBe(['admin', 'billing'])
        ->and(linCodexIndex(['a' => ['route:home']])->panelIds())->toBe([])
        ->and(ContextIndex::fromArticles([])->panelIds())->toBe([]);
});
