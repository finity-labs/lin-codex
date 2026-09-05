<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * One translation of one article with its four fields already folded by
 * SearchText::fold(), exactly the shape the matcher reads. This is what the
 * in-memory index caches, so it must stay a plain value: no models, no
 * closures, nothing the cache store cannot serialize and hand back.
 */
final readonly class IndexedDocument
{
    /**
     * @param  int|null  $articleId  ArticleData::id; null for file articles
     */
    public function __construct(
        public string $slug,
        public string $locale,
        public ?int $articleId,
        public string $title,
        public string $keywords,
        public string $excerpt,
        public string $body,
    ) {}
}
