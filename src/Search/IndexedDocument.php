<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Data\Shape;
use InvalidArgumentException;

/**
 * One translation of one article with its four fields already folded by
 * SearchText::fold(), exactly the shape the matcher reads. The in-memory
 * index caches toArray(), never the object, so a cache store that refuses
 * to hand PHP objects back (Laravel 13's default) still serves the index.
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

    /**
     * @return array{slug: string, locale: string, article_id: ?int, title: string, keywords: string, excerpt: string, body: string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'locale' => $this->locale,
            'article_id' => $this->articleId,
            'title' => $this->title,
            'keywords' => $this->keywords,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidArgumentException when $data is not the shape toArray() writes
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Shape::string($data, 'slug'),
            Shape::string($data, 'locale'),
            Shape::nullableInt($data, 'article_id'),
            Shape::string($data, 'title'),
            Shape::string($data, 'keywords'),
            Shape::string($data, 'excerpt'),
            Shape::string($data, 'body'),
        );
    }
}
