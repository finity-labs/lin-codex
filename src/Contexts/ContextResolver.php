<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contexts;

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Locale\LocaleResolver;

/**
 * The articles for a page, for one viewer, in one locale, in the order the
 * drawer should offer them (it opens the first).
 *
 * Two passes over a ContextIndex built from one ContentSource::all() call:
 * pass 1 keeps the contexts scoped to the page's panel, pass 2 the
 * panel-less contexts, and pass 2 runs only when pass 1 has no VISIBLE
 * article. The gate and the locale pick are applied before the emptiness
 * check, so a guest on a panel page whose only panel article is sign-in-only
 * still reaches the public panel-less one; that is the interpretation of
 * "if pass 1 yields any article" and ContextResolverTest pins it. A page
 * without a panel runs the panel-less pass only.
 *
 * Ordering within a pass is the index's: exact before wildcard, class,
 * route, url, sortOrder, slug, one entry per article.
 * ContentSource::findByContext() is not used because wildcard keys cannot
 * go through an exact match.
 */
final class ContextResolver
{
    public function __construct(
        private readonly ContentSource $source,
        private readonly ArticleGate $gate,
        private readonly LocaleResolver $locales,
    ) {}

    /**
     * @return list<ArticleData>
     */
    public function resolve(PageContext $page, Viewer $viewer, ?string $locale = null): array
    {
        $all = $this->source->all();
        $index = ContextIndex::fromArticles($all);
        $locale = $this->locales->resolve($locale);
        $passes = $page->panelId !== null ? [$page->panelId, null] : [null];

        foreach ($passes as $panelId) {
            $result = [];

            foreach ($index->candidates($page, $panelId) as $match) {
                $article = $all[$match->slug];

                if ($this->gate->allows($article, $viewer, $all) && $this->locales->pick($article, $locale) !== null) {
                    $result[] = $article;
                }
            }

            if ($result !== []) {
                return $result;
            }
        }

        return [];
    }
}
