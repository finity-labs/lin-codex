<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use FinityLabs\LinCodex\Enums\SourceWarningKind;
use InvalidArgumentException;

/**
 * Something a source noticed and worked around while reading its content:
 * a file it skipped, a value it replaced with the default, a context it
 * dropped. Warnings never stop a read; they are reported so an author can
 * fix the input. The file source caches toArray(), never the object.
 */
final readonly class SourceWarning
{
    public function __construct(
        public SourceWarningKind $kind,
        public ?string $path,
        public ?string $slug,
        public ?string $locale,
        public string $detail,
    ) {}

    public function message(): string
    {
        return (string) __('lin-codex::lin-codex.source_warnings.'.$this->kind->key(), [
            'detail' => $this->detail,
            'path' => (string) $this->path,
        ]);
    }

    /**
     * @return array{kind: int, path: ?string, slug: ?string, locale: ?string, detail: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'path' => $this->path,
            'slug' => $this->slug,
            'locale' => $this->locale,
            'detail' => $this->detail,
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
            Shape::enum($data, 'kind', SourceWarningKind::class),
            Shape::nullableString($data, 'path'),
            Shape::nullableString($data, 'slug'),
            Shape::nullableString($data, 'locale'),
            Shape::string($data, 'detail'),
        );
    }
}
