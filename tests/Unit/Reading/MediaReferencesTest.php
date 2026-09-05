<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Reading\MediaReferences;
use FinityLabs\LinCodex\Sources\SlugPath;

/**
 * @param  array<string, string>  $bodiesByLocale  locale => body
 */
function linCodexRefsArticle(string $slug, array $bodiesByLocale, ArticleFormat $format = ArticleFormat::Markdown): ArticleData
{
    $translations = [];

    foreach ($bodiesByLocale as $locale => $body) {
        $translations[$locale] = new TranslationData($locale, ucfirst($slug), null, $body, null);
    }

    return new ArticleData(
        slug: $slug,
        parentSlug: SlugPath::parentOf($slug),
        order: 0,
        icon: null,
        format: $format,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: [],
        translations: $translations,
    );
}

/**
 * @param  list<ArticleData>  $articles  in the order the all() map would list them
 */
function linCodexRefs(array $articles, string $prefix = '/codex/media'): MediaReferences
{
    $map = [];

    foreach ($articles as $article) {
        $map[$article->slug] = $article;
    }

    return MediaReferences::fromArticles($map, $prefix);
}

it('finds markdown image references with and without a title', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => "![A](/codex/media/en/images/a.png)\n\n![B](/codex/media/en/images/b.png \"title\")"]),
    ]);

    expect($refs->owners('en/images/a.png'))->toBe(['x'])
        ->and($refs->owners('en/images/b.png'))->toBe(['x']);
});

it('finds html image references in double and single quotes', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('y', ['en' => '<p><img src="/codex/media/en/images/c.png" alt="C"><img src=\'/codex/media/en/d.png\'></p>'], ArticleFormat::Html),
    ]);

    expect($refs->owners('en/images/c.png'))->toBe(['y'])
        ->and($refs->owners('en/d.png'))->toBe(['y']);
});

it('strips a query string and a fragment from the reference', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => '![V](/codex/media/en/images/a.png?v=2) ![F](/codex/media/en/images/f.png#frag)']),
    ]);

    expect($refs->owners('en/images/a.png'))->toBe(['x'])
        ->and($refs->owners('en/images/f.png'))->toBe(['x'])
        ->and($refs->isReferenced('en/images/a.png?v=2'))->toBeFalse();
});

it('lists every owner in map order and each owner once', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('second', ['en' => '![S](/codex/media/en/images/shared.png)']),
        linCodexRefsArticle('first', [
            'en' => '![S](/codex/media/en/images/shared.png)',
            'de' => '![S](/codex/media/en/images/shared.png)',
        ]),
    ]);

    expect($refs->owners('en/images/shared.png'))->toBe(['second', 'first']);
});

it('reports an unreferenced path as owned by nobody', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => '![A](/codex/media/en/images/a.png)']),
    ]);

    expect($refs->owners('en/images/nope.png'))->toBe([])
        ->and($refs->isReferenced('en/images/nope.png'))->toBeFalse()
        ->and($refs->isReferenced('en/images/a.png'))->toBeTrue();
});

it('ignores paths outside the media prefix', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => '![U](/storage/codex/a.png) ![R](https://example.com/other/a.png)']),
    ]);

    expect($refs->owners('codex/a.png'))->toBe([])
        ->and($refs->owners('other/a.png'))->toBe([])
        ->and($refs->owners('en/a.png'))->toBe([]);
});

// Any occurrence of the prefix followed by a path counts, so a full URL that
// contains the prefix is indexed too: it points at the same file.
it('indexes a scheme-qualified url that contains the prefix', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => '![R](https://example.com/codex/media/en/x.png)']),
    ]);

    expect($refs->owners('en/x.png'))->toBe(['x']);
});

it('accepts a custom prefix with a trailing slash', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => '![A](/help-media/en/a.png) ![B](/codex/media/en/b.png)']),
    ], '/help-media/');

    expect($refs->owners('en/a.png'))->toBe(['x'])
        ->and($refs->owners('en/b.png'))->toBe([]);
});

it('survives serialize() and yields no models', function (): void {
    $refs = linCodexRefs([
        linCodexRefsArticle('x', ['en' => '![A](/codex/media/en/images/a.png)']),
    ]);

    expect(unserialize(serialize($refs)))->toEqual($refs);

    linCodexAssertNoModels($refs);
});
