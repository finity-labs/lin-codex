<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Callout;

use FinityLabs\LinCodex\Rendering\Markdown\RenderState;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * <aside class="codex-callout codex-callout--{type}" role="note"> with a
 * title paragraph (empty icon span as the CSS hook, never inline SVG) and a
 * body div. The default title is translated in the render locale.
 */
final class CalloutRenderer implements NodeRendererInterface
{
    public function __construct(private readonly RenderState $state) {}

    /**
     * @param  Callout  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Callout::assertInstanceOf($node);

        $title = $node->title ?? (string) __('lin-codex::lin-codex.callouts.'.$node->type->key(), [], $this->state->locale);
        $body = $childRenderer->renderNodes($node->children());
        $separator = $childRenderer->getInnerSeparator();

        $icon = new HtmlElement('span', ['class' => 'codex-callout__icon', 'aria-hidden' => 'true'], '');
        $heading = new HtmlElement('p', ['class' => 'codex-callout__title'], $icon.Xml::escape($title));
        $content = new HtmlElement('div', ['class' => 'codex-callout__body'], $body === '' ? '' : $separator.$body.$separator);

        return new HtmlElement(
            'aside',
            ['class' => 'codex-callout codex-callout--'.$node->type->key(), 'role' => 'note'],
            $separator.$heading.$separator.$content.$separator,
        );
    }
}
