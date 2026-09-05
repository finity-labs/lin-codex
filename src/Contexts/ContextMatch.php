<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contexts;

use FinityLabs\LinCodex\Data\ContextData;

/**
 * One article matched by one of its contexts, as the context index reports
 * it: the slug, the (normalised) context that matched, and whether that
 * context is an exact key rather than a wildcard.
 */
final readonly class ContextMatch
{
    public function __construct(
        public string $slug,
        public ContextData $context,
        public bool $exact,
    ) {}
}
