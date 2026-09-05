<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;

/**
 * One article as every content source reports it: metadata, contexts and a
 * translation per locale. The slug is the identity; the parent is derived
 * from it, never stored separately by the source.
 */
final readonly class ArticleData
{
    /**
     * @param  list<ContextData>  $contexts
     * @param  list<string>  $related  slugs
     * @param  list<string>  $keywords
     * @param  array<string, TranslationData>  $translations  keyed by locale
     * @param  array<string, mixed>  $meta  unknown front matter keys, round-trip untouched
     * @param  bool  $isSection  index.md / index.html files, or a database article that has children
     * @param  string|null  $sourcePath  default-locale file path, or codex_articles.source_path
     * @param  int|null  $id  database id; null for files
     */
    public function __construct(
        public string $slug,
        public ?string $parentSlug,
        public int $order,
        public ?string $icon,
        public ArticleFormat $format,
        public Visibility $visibility,
        public bool $published,
        public array $contexts,
        public array $related,
        public array $keywords,
        public array $translations,
        public array $meta = [],
        public bool $isSection = false,
        public ?string $sourcePath = null,
        public ?int $id = null,
    ) {}

    public function translation(string $locale): ?TranslationData
    {
        return $this->translations[$locale] ?? null;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys($this->translations);
    }
}
