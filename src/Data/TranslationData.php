<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use InvalidArgumentException;

/**
 * One locale's title, excerpt and body for an article.
 *
 * For file sources the body has the front matter and the consumed H1 removed
 * and image paths rewritten; for the database it is the raw column. The
 * search text is the plain text extracted at scan time for files and the
 * search_text column for the database (null until Phase 5 fills it).
 *
 * `updatedAt` is the ISO 8601 (DATE_ATOM, e.g. `2026-09-04T12:34:56+00:00`)
 * time of the last change when the source knows it, which today is the
 * database row's `updated_at`; null for file articles and whenever unknown.
 * It is a string, never a DateTime, so the object stays a plain readonly
 * value that `serialize()` and the no-model walk accept.
 *
 * The file source caches toArray(), never the object.
 */
final readonly class TranslationData
{
    public function __construct(
        public string $locale,
        public string $title,
        public ?string $excerpt,
        public string $body,
        public ?string $searchText,
        public ?string $sourcePath = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * @return array{locale: string, title: string, excerpt: ?string, body: string, search_text: ?string, source_path: ?string, updated_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'search_text' => $this->searchText,
            'source_path' => $this->sourcePath,
            'updated_at' => $this->updatedAt,
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
            Shape::string($data, 'locale'),
            Shape::string($data, 'title'),
            Shape::nullableString($data, 'excerpt'),
            Shape::string($data, 'body'),
            Shape::nullableString($data, 'search_text'),
            Shape::nullableString($data, 'source_path'),
            Shape::nullableString($data, 'updated_at'),
        );
    }
}
