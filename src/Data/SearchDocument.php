<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use FinityLabs\LinCodex\Enums\Visibility;

/**
 * One searchable unit: an article in one locale.
 *
 * The text keeps the Phase 3 shape (search text plus keywords) for consumers
 * of ArticleSet::allForSearch(). The keywords, body and article id let the
 * search index fold each field on its own and tell database rows from file
 * articles: the body is TranslationData::searchText as-is (raw plain text
 * for files, the folded blob for database rows) without the keywords
 * appended, and the article id is null for file articles.
 */
final readonly class SearchDocument
{
    /**
     * @param  list<string>  $keywords  the article's keywords, unfolded
     * @param  string|null  $body  TranslationData::searchText as-is, without the keywords
     * @param  int|null  $articleId  ArticleData::id; null for file articles
     */
    public function __construct(
        public string $slug,
        public string $locale,
        public string $title,
        public ?string $excerpt,
        public string $text,
        public Visibility $visibility,
        public bool $published,
        public array $keywords = [],
        public ?string $body = null,
        public ?int $articleId = null,
    ) {}

    public static function fromTranslation(ArticleData $article, TranslationData $translation): self
    {
        return new self(
            $article->slug,
            $translation->locale,
            $translation->title,
            $translation->excerpt,
            trim(($translation->searchText ?? '').' '.implode(' ', $article->keywords)),
            $article->visibility,
            $article->published,
            $article->keywords,
            $translation->searchText,
            $article->id,
        );
    }
}
