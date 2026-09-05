<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Html;

use FinityLabs\LinCodex\Rendering\PlainTextExtractor;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Renders HTML-format article bodies: the sanitizer runs first as the single
 * security boundary, then HeadingPass adds ids, permalinks, TOC data and
 * link parity so the result is interchangeable with the Markdown pipeline's.
 * Never on save, always on render.
 */
final class HtmlPipeline
{
    public function __construct(
        private readonly HtmlSanitizerInterface $sanitizer,
        private readonly HeadingPass $headingPass = new HeadingPass,
        private readonly PlainTextExtractor $plainText = new PlainTextExtractor,
    ) {}

    public function render(string $body, string $locale, string $slug): RenderedArticle
    {
        $clean = $this->sanitizer->sanitize($body);
        $passed = $this->headingPass->apply($clean, $locale, $slug);

        return new RenderedArticle(
            html: $passed['html'],
            toc: $passed['toc'],
            plainText: $this->plainText->fromHtml($passed['html']),
            metadata: [
                'front_matter' => null,
                'warnings' => [],
            ],
        );
    }

    /**
     * Everything the output depends on besides the body, locale and slug, as
     * scalars so the render cache can hash it as a fingerprint.
     *
     * @return array<string, mixed>
     */
    public function fingerprintInput(): array
    {
        return [
            'sanitizer' => config('lin-codex.render.sanitizer'),
            'help_center' => config('lin-codex.routes.help_center'),
            'internal_host' => (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
        ];
    }
}
