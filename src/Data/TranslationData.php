<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

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
}
