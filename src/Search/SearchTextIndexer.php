<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;

/**
 * Fills a translation's search_text column with the four folded segments
 * SearchText::compose() writes, in this order: the title, the article's
 * keywords, the excerpt and the plain text of the rendered body.
 *
 * The plain text comes from ArticleRenderer::plainText() under the same
 * render slug the reader uses (ArticlePath::renderSlug(), so a section
 * renders as "{slug}/index"), which means the render cache entry this
 * warms is the one the reader reads later; indexing never duplicates a
 * render.
 *
 * This class only computes; ArticleTranslation's saving hook decides when
 * to call it (a null search_text, or a changed title, excerpt or body
 * without an explicit search_text) and Article::reindexTranslations()
 * calls it after the keywords or the format changed. An explicitly
 * assigned search_text therefore always wins. Rows written with the query
 * builder fire no model events and stay unindexed; a reindex command is a
 * Phase 8 todo.
 */
final class SearchTextIndexer
{
    public function __construct(private readonly ArticleRenderer $renderer) {}

    /**
     * Set $translation->search_text from its title, excerpt and body plus the
     * article's keywords and format. Does not save. A translation without an
     * article (unsaved parent) indexes as Markdown with no keywords.
     */
    public function index(ArticleTranslation $translation): void
    {
        $article = $this->articleOf($translation);
        $keywords = is_array($article?->keywords) ? array_values(array_filter($article->keywords, 'is_string')) : [];
        $format = $article !== null ? $article->format : ArticleFormat::Markdown;
        $slug = $article !== null ? $article->slug : '';
        $isSection = $article !== null && Article::query()->where('slug', 'like', $article->slug.'/%')->exists();

        $body = $this->renderer->plainText(
            $translation->body,
            $format,
            $translation->locale,
            ArticlePath::renderSlug($slug, $isSection),
        );

        $translation->search_text = SearchText::compose((string) $translation->title, $keywords, $translation->excerpt, $body);
    }

    /**
     * The parent article: the preset relation when a caller loaded it
     * (Article::reindexTranslations() does), null for a translation that has
     * no article yet, else one query.
     */
    private function articleOf(ArticleTranslation $translation): ?Article
    {
        if ($translation->relationLoaded('article')) {
            $loaded = $translation->getRelation('article');

            return $loaded instanceof Article ? $loaded : null;
        }

        if ($translation->getAttribute('article_id') === null) {
            return null;
        }

        return $translation->article()->first();
    }
}
