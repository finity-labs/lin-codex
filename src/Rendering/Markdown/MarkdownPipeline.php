<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown;

use FinityLabs\LinCodex\Rendering\Markdown\Attributes\CodexClassFilter;
use FinityLabs\LinCodex\Rendering\Markdown\Callout\CalloutExtension;
use FinityLabs\LinCodex\Rendering\Markdown\Container\FencedContainerExtension;
use FinityLabs\LinCodex\Rendering\Markdown\Figure\FigureExtension;
use FinityLabs\LinCodex\Rendering\Markdown\Links\ArticleLinkExtension;
use FinityLabs\LinCodex\Rendering\PlainTextExtractor;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterProviderInterface;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The single configured league/commonmark Environment for article bodies:
 * raw HTML stripped, unsafe links dropped, nesting and delimiter limits on,
 * stable heading ids with permalinks, GFM tables and task lists, safe
 * external links, and front matter kept out of the output.
 *
 * Front matter found while rendering is exposed in the metadata as a
 * convenience only; the file source stays the source of truth for article
 * metadata.
 */
final class MarkdownPipeline
{
    private RenderState $state;

    private ?Environment $environment = null;

    private ?MarkdownConverter $converter = null;

    public function __construct(private readonly PlainTextExtractor $plainText = new PlainTextExtractor)
    {
        $this->state = new RenderState;
    }

    public function render(string $body, string $locale, string $slug): RenderedArticle
    {
        $this->state->locale = $locale;
        $this->state->slug = $slug;

        $converter = $this->converter();
        $warnings = [];

        try {
            $result = $converter->convert($body);
        } catch (InvalidFrontMatterException) {
            $body = preg_replace('/\A---\R.*?\R---\R/s', '', $body, 1) ?? $body;
            $warnings[] = 'invalid_front_matter';
            $result = $converter->convert($body);
        }

        $html = $result->getContent();
        $frontMatter = $result instanceof FrontMatterProviderInterface ? $result->getFrontMatter() : null;

        return new RenderedArticle(
            html: $html,
            toc: TableOfContentsExtractor::extract($result->getDocument()),
            plainText: $this->plainText->fromHtml($html),
            metadata: [
                'front_matter' => is_array($frontMatter) ? $frontMatter : null,
                'warnings' => $warnings,
            ],
        );
    }

    /**
     * Built once per instance; config is read at that moment, so tests that
     * change app.url or the limits afterwards need a fresh pipeline.
     */
    public function environment(): Environment
    {
        if ($this->environment !== null) {
            return $this->environment;
        }

        $config = $this->environmentConfig();
        $config['slug_normalizer']['instance'] = new CodexSlugNormalizer($this->state);

        $environment = new Environment($config);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TaskListExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addExtension(new FrontMatterExtension);
        $environment->addExtension(new AttributesExtension);
        $environment->addExtension(new DefaultAttributesExtension);
        $environment->addExtension(new ExternalLinkExtension);

        $environment->addExtension(new CalloutExtension($this->state));
        $environment->addExtension(new FencedContainerExtension($this->state));
        $environment->addExtension(new FigureExtension);
        $environment->addExtension(new ArticleLinkExtension($this->state));

        $environment->addEventListener(DocumentParsedEvent::class, [new CodexClassFilter, 'onDocumentParsed'], -10);
        $environment->addRenderer(HeadingPermalink::class, new HeadingAnchorRenderer($this->state), 10);

        return $this->environment = $environment;
    }

    /**
     * The scalar-only description of the Environment (no objects, no
     * closures) so the render cache can hash it as a fingerprint.
     *
     * @return array<string, mixed>
     */
    public function environmentConfig(): array
    {
        return [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => (int) config('lin-codex.render.limits.max_nesting_level', 50),
            'max_delimiters_per_line' => (int) config('lin-codex.render.limits.max_delimiters_per_line', 500),
            'slug_normalizer' => [
                'unique' => 'document',
                'max_length' => 255,
            ],
            'heading_permalink' => [
                'min_heading_level' => 2,
                'max_heading_level' => 6,
                'insert' => 'after',
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'html_class' => 'codex-anchor',
                'symbol' => '#',
                'title' => '',
                'aria_hidden' => false,
            ],
            'external_link' => [
                'internal_hosts' => (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
                'open_in_new_window' => true,
                'html_class' => 'codex-external',
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
            'attributes' => [
                'allow' => ['id', 'class'],
            ],
            'default_attributes' => [
                'attributes' => [
                    Table::class => ['class' => 'codex-table'],
                ],
            ],
            'table' => [
                'max_autocompleted_cells' => (int) config('lin-codex.render.limits.max_autocompleted_cells', 10000),
            ],
        ];
    }

    /**
     * @return list<class-string>
     */
    public function extensionClasses(): array
    {
        return array_map(get_class(...), iterator_to_array($this->environment()->getExtensions(), false));
    }

    public function state(): RenderState
    {
        return $this->state;
    }

    private function converter(): MarkdownConverter
    {
        return $this->converter ??= new MarkdownConverter($this->environment());
    }
}
