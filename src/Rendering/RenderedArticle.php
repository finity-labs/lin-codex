<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

use FinityLabs\LinCodex\Data\Shape;
use InvalidArgumentException;

/**
 * The result of rendering one article body: safe HTML, table-of-contents
 * data, search text, and whatever the renderer learned on the way. Readers,
 * the drawer, and the JSON API consume this shape; nothing is injected into
 * the body on their behalf.
 *
 * The render cache stores toArray(), never the object: a cache store on
 * Laravel 13's defaults refuses to hand PHP objects back. fromArray() is
 * the way back and tryFromArray() answers a raw cache read.
 */
final readonly class RenderedArticle
{
    /**
     * @param  list<array{level: int, text: string, id: string}>  $toc  h2 and h3 only, in document order
     * @param  array<string, mixed>  $metadata  keys: front_matter (array<string, mixed>|null), warnings (list<string>)
     */
    public function __construct(
        public string $html,
        public array $toc,
        public string $plainText,
        public array $metadata = [],
    ) {}

    /**
     * The cacheable form: arrays and scalars only.
     *
     * @return array{html: string, toc: list<array{level: int, text: string, id: string}>, plain_text: string, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'html' => $this->html,
            'toc' => $this->toc,
            'plain_text' => $this->plainText,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidArgumentException when $data is not the shape toArray() writes
     */
    public static function fromArray(array $data): self
    {
        $toc = [];

        foreach (Shape::arrayList($data, 'toc') as $entry) {
            $toc[] = [
                'level' => Shape::int($entry, 'level'),
                'text' => Shape::string($entry, 'text'),
                'id' => Shape::string($entry, 'id'),
            ];
        }

        return new self(
            Shape::string($data, 'html'),
            $toc,
            Shape::string($data, 'plain_text'),
            Shape::map($data, 'metadata'),
        );
    }

    /**
     * The article behind a raw cache read, or null when the value is
     * anything else: a miss, an object entry written by an earlier release,
     * a poisoned entry. The caller renders again and overwrites.
     */
    public static function tryFromArray(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }

        try {
            return self::fromArray($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
