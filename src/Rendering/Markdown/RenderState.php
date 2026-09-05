<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown;

/**
 * Per-render input the memoized Environment cannot receive through its
 * constructor: MarkdownPipeline::render() sets these before convert() and
 * the renderers, normalizer, and listeners read them during the run.
 */
final class RenderState
{
    public string $locale = 'en';

    public string $slug = '';
}
