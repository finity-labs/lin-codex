<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use FinityLabs\LinCodex\Rendering\Markdown\RenderState;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * <details class="codex-details"><summary>Title</summary>...</details>; the
 * summary is the fence argument or the translated default in the render
 * locale.
 */
final class DetailsRenderer
{
    public function __construct(private readonly RenderState $state) {}

    public function render(FencedContainer $node, ChildNodeRendererInterface $childRenderer): string
    {
        $separator = $childRenderer->getInnerSeparator();
        $title = $node->argument !== ''
            ? $node->argument
            : (string) __('lin-codex::lin-codex.details_default', [], $this->state->locale);
        $body = $childRenderer->renderNodes($node->children());

        $summary = new HtmlElement('summary', [], Xml::escape($title));

        return (string) new HtmlElement('details', ['class' => 'codex-details'], $summary.($body === '' ? $separator : $separator.$body.$separator));
    }
}
