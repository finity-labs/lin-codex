<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown;

use FinityLabs\LinCodex\Rendering\HeadingSlugger;
use League\CommonMark\Normalizer\UniqueSlugNormalizerInterface;

/**
 * Heading id normalizer for the commonmark Environment. Implementing the
 * unique interface keeps the Environment from wrapping it in the built-in
 * UniqueSlugNormalizer, whose suffixes start at -1; ours start at -2.
 * clearHistory() runs after every document, so the next render starts
 * with a fresh slugger for the then-current locale.
 */
final class CodexSlugNormalizer implements UniqueSlugNormalizerInterface
{
    private ?HeadingSlugger $slugger = null;

    public function __construct(private readonly RenderState $state) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function normalize(string $text, array $context = []): string
    {
        $this->slugger ??= new HeadingSlugger($this->state->locale);

        return $this->slugger->unique($text, (int) ($context['length'] ?? 255));
    }

    public function clearHistory(): void
    {
        $this->slugger = null;
    }
}
