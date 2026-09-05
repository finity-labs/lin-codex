<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\View;

use FinityLabs\LinCodex\Contexts\PageContext;

/**
 * The help for one page: the canonical context that was matched, the
 * locale the titles were picked in, and the articles in the order the
 * drawer offers them (it opens the first). Each entry has the same shape
 * as a ContextPayload data row, so the drawer's locked state and the JSON
 * API describe a page identically.
 */
final readonly class PageHelp
{
    /**
     * @param  list<array{slug: string, title: string, excerpt: ?string, isFallback: bool}>  $articles
     */
    public function __construct(
        public PageContext $context,
        public string $locale,
        public array $articles,
    ) {}

    public function count(): int
    {
        return count($this->articles);
    }

    public function firstSlug(): ?string
    {
        return $this->articles[0]['slug'] ?? null;
    }
}
