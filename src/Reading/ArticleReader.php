<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Reading;

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;

/**
 * Reads one article for one viewer in one locale. A missing slug, a hidden
 * or unpublished article, a gate veto and an untranslated article under
 * Hide all return null, so a caller cannot tell them apart and the JSON API
 * turns every one of them into a not-found answer rather than a permission
 * error that would confirm the article exists.
 *
 * Built from a single ContentSource::all() call; the gate and the locale
 * rule are applied to the article, to its related slugs and to its ancestor
 * chain from that same map, and the related entries carry the title the
 * locale rule picked. The body is rendered under the picked
 * translation's own locale and under ArticlePath::renderSlug(), so the cache
 * entry warmed when the files were scanned is the one served.
 */
final class ArticleReader
{
    public function __construct(
        private readonly ContentSource $source,
        private readonly ArticleGate $gate,
        private readonly LocaleResolver $locales,
        private readonly ArticleRenderer $renderer,
    ) {}

    public function read(string $slug, Viewer $viewer, ?string $locale = null): ?ReadArticle
    {
        $all = $this->source->all();
        $article = $all[$slug] ?? null;

        if ($article === null || ! $this->gate->allows($article, $viewer, $all)) {
            return null;
        }

        $locale = $this->locales->resolve($locale);
        $choice = $this->locales->pick($article, $locale);

        if ($choice === null) {
            return null;
        }

        $translation = $choice->translation;
        $rendered = $this->renderer->render(
            $translation->body,
            $article->format,
            $translation->locale,
            ArticlePath::renderSlug($article->slug, $article->isSection),
        );

        $related = [];

        foreach ($article->related as $other) {
            if (! isset($all[$other]) || ! $this->gate->allows($all[$other], $viewer, $all)) {
                continue;
            }

            $otherChoice = $this->locales->pick($all[$other], $locale);

            if ($otherChoice === null) {
                continue;
            }

            $related[] = ['slug' => $other, 'title' => $otherChoice->translation->title];
        }

        $breadcrumbs = AncestorTitles::for($slug, $all, $locale, $this->locales);

        return new ReadArticle(
            $article,
            $translation,
            $translation->locale,
            $choice->isFallback,
            $rendered,
            $related,
            $breadcrumbs,
        );
    }
}
