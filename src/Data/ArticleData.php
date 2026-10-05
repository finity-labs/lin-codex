<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use InvalidArgumentException;

/**
 * One article as every content source reports it: metadata, contexts and a
 * translation per locale. The slug is the identity; the parent is derived
 * from it, never stored separately by the source.
 *
 * The file source caches toArray(), never the object: enums travel as
 * their backing values and the contexts and translations as their own
 * arrays, so the stored bytes carry no class names.
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

    /**
     * @return array{slug: string, parent_slug: ?string, order: int, icon: ?string, format: int, visibility: int, published: bool, contexts: list<array<string, mixed>>, related: list<string>, keywords: list<string>, translations: array<string, array<string, mixed>>, meta: array<string, mixed>, is_section: bool, source_path: ?string, id: ?int}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'parent_slug' => $this->parentSlug,
            'order' => $this->order,
            'icon' => $this->icon,
            'format' => $this->format->value,
            'visibility' => $this->visibility->value,
            'published' => $this->published,
            'contexts' => array_map(static fn (ContextData $context): array => $context->toArray(), $this->contexts),
            'related' => $this->related,
            'keywords' => $this->keywords,
            'translations' => array_map(static fn (TranslationData $translation): array => $translation->toArray(), $this->translations),
            'meta' => $this->meta,
            'is_section' => $this->isSection,
            'source_path' => $this->sourcePath,
            'id' => $this->id,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidArgumentException when $data is not the shape toArray() writes
     */
    public static function fromArray(array $data): self
    {
        $translations = [];

        foreach (Shape::arrayMap($data, 'translations') as $locale => $translation) {
            $translations[$locale] = TranslationData::fromArray($translation);
        }

        return new self(
            slug: Shape::string($data, 'slug'),
            parentSlug: Shape::nullableString($data, 'parent_slug'),
            order: Shape::int($data, 'order'),
            icon: Shape::nullableString($data, 'icon'),
            format: Shape::enum($data, 'format', ArticleFormat::class),
            visibility: Shape::enum($data, 'visibility', Visibility::class),
            published: Shape::bool($data, 'published'),
            contexts: array_map(static fn (array $context): ContextData => ContextData::fromArray($context), Shape::arrayList($data, 'contexts')),
            related: Shape::stringList($data, 'related'),
            keywords: Shape::stringList($data, 'keywords'),
            translations: $translations,
            meta: Shape::map($data, 'meta'),
            isSection: Shape::bool($data, 'is_section'),
            sourcePath: Shape::nullableString($data, 'source_path'),
            id: Shape::nullableInt($data, 'id'),
        );
    }
}
