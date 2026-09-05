<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;

/**
 * A hash of everything the rendered output depends on besides the article
 * body, locale and slug: the markup version, the Markdown environment config
 * and extension list, the HTML sanitizer inputs, and the render and routes
 * config. It is part of every render cache key, so a config or extension
 * change invalidates cached articles without a manual cache clear.
 *
 * Only scalars go into the hash. json_encode throws if an object or closure
 * sneaks into one of the inputs, which surfaces in tests rather than as
 * silently changing keys.
 */
final class RendererFingerprint
{
    /**
     * Bump when renderer output changes without a config or extension
     * change: a renderer, a DocumentParsedEvent listener such as
     * CodexClassFilter (which is not an extension and so does not appear in
     * extensionClasses()), the heading pass, or the plain-text extractor.
     */
    public const MARKUP_VERSION = 1;

    public function __construct(
        private readonly MarkdownPipeline $markdown,
        private readonly HtmlPipeline $html,
    ) {}

    /**
     * @return string a 64-character hex sha256
     */
    public function hash(): string
    {
        return hash('sha256', json_encode($this->input(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function input(): array
    {
        return [
            'markup_version' => self::MARKUP_VERSION,
            'markdown' => [
                'environment' => $this->markdown->environmentConfig(),
                'extensions' => $this->markdown->extensionClasses(),
            ],
            'html' => $this->html->fingerprintInput(),
            'render' => config('lin-codex.render'),
            'routes' => config('lin-codex.routes'),
        ];
    }
}
