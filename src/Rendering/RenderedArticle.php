<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

/**
 * The result of rendering one article body: safe HTML, table-of-contents
 * data, search text, and whatever the renderer learned on the way. Readers,
 * the drawer, and the JSON API consume this shape; nothing is injected into
 * the body on their behalf.
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
}
