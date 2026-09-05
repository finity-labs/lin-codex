<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Json;

use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Locale\LocaleResolver;

/**
 * The JSON shape of the articles for a page: slug, picked title and excerpt
 * and the fallback flag per entry, in the order the drawer should offer
 * them. The meta block echoes the canonical page context (route, path,
 * class, panel) next to the locales so a client can see what was matched.
 */
final readonly class ContextPayload
{
    /**
     * @param  list<ArticleData>  $articles  every entry visible and pick() non-null, as ContextResolver guarantees
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function make(array $articles, PageContext $page, string $locale, string $defaultLocale, LocaleResolver $locales): array
    {
        $data = [];

        foreach ($articles as $article) {
            $choice = $locales->pick($article, $locale);

            if ($choice === null) {
                continue;
            }

            $data[] = [
                'slug' => $article->slug,
                'title' => $choice->translation->title,
                'excerpt' => $choice->translation->excerpt,
                'isFallback' => $choice->isFallback,
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'locale' => $locale,
                'defaultLocale' => $defaultLocale,
            ] + $page->toArray(),
        ];
    }
}
