<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Reading\AncestorTitles;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\SlugPath;

/**
 * @param  list<string>  $codes
 */
function linCodexAncestorUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * A public published article with one translation per locale.
 *
 * @param  array<string, string>  $titlesByLocale
 */
function linCodexAncestorArticle(string $slug, array $titlesByLocale): ArticleData
{
    $translations = [];

    foreach ($titlesByLocale as $locale => $title) {
        $translations[$locale] = new TranslationData($locale, $title, null, $title.' body', $title.' body');
    }

    return new ArticleData(
        slug: $slug,
        parentSlug: SlugPath::parentOf($slug),
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: [],
        translations: $translations,
    );
}

/**
 * @return array<string, ArticleData> keyed by slug
 */
function linCodexAncestorMap(ArticleData ...$articles): array
{
    $map = [];

    foreach ($articles as $article) {
        $map[$article->slug] = $article;
    }

    return $map;
}

beforeEach(function (): void {
    $this->locales = app(LocaleResolver::class);
});

it('lists the parent article with its title', function (): void {
    $all = linCodexAncestorMap(
        linCodexAncestorArticle('users', ['en' => 'Users']),
        linCodexAncestorArticle('users/roles', ['en' => 'Roles']),
    );

    expect(AncestorTitles::for('users/roles', $all, 'en', $this->locales))
        ->toBe([['slug' => 'users', 'title' => 'Users']]);
});

it('walks three levels root-first', function (): void {
    $all = linCodexAncestorMap(
        linCodexAncestorArticle('a', ['en' => 'A']),
        linCodexAncestorArticle('a/b', ['en' => 'B']),
        linCodexAncestorArticle('a/b/c', ['en' => 'C']),
    );

    expect(AncestorTitles::for('a/b/c', $all, 'en', $this->locales))
        ->toBe([['slug' => 'a', 'title' => 'A'], ['slug' => 'a/b', 'title' => 'B']]);
});

it('skips folder groups that are not articles', function (): void {
    $onlyChild = linCodexAncestorMap(linCodexAncestorArticle('group/child', ['en' => 'Child']));
    $gap = linCodexAncestorMap(
        linCodexAncestorArticle('x', ['en' => 'X']),
        linCodexAncestorArticle('x/y/z', ['en' => 'Z']),
    );

    expect(AncestorTitles::for('group/child', $onlyChild, 'en', $this->locales))->toBe([])
        ->and(AncestorTitles::for('x/y/z', $gap, 'en', $this->locales))->toBe([['slug' => 'x', 'title' => 'X']]);
});

it('gives nothing for a root slug', function (): void {
    $all = linCodexAncestorMap(linCodexAncestorArticle('root', ['en' => 'Root']));

    expect(AncestorTitles::for('root', $all, 'en', $this->locales))->toBe([]);
});

it('takes the title from the translation the locale rule picks', function (): void {
    linCodexAncestorUseLanguages(['en', 'de']);

    $all = linCodexAncestorMap(
        linCodexAncestorArticle('users', ['en' => 'Users', 'de' => 'Benutzer']),
        linCodexAncestorArticle('users/roles', ['en' => 'Roles', 'de' => 'Rollen']),
    );

    expect(AncestorTitles::for('users/roles', $all, 'de', $this->locales))
        ->toBe([['slug' => 'users', 'title' => 'Benutzer']]);
});

it('falls back to the default title under show-default and skips the ancestor under hide', function (): void {
    linCodexAncestorUseLanguages(['en', 'de']);

    $all = linCodexAncestorMap(
        linCodexAncestorArticle('users', ['en' => 'Users']),
        linCodexAncestorArticle('users/roles', ['en' => 'Roles', 'de' => 'Rollen']),
    );

    expect(AncestorTitles::for('users/roles', $all, 'de', $this->locales))
        ->toBe([['slug' => 'users', 'title' => 'Users']]);

    linCodexAncestorUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

    expect(AncestorTitles::for('users/roles', $all, 'de', $this->locales))->toBe([]);
});
