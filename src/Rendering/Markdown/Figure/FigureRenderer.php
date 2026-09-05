<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Figure;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * <figure class="codex-figure"> around the image, with a <figcaption> only
 * when the image has a title. No separators: the content is inline.
 */
final class FigureRenderer implements NodeRendererInterface
{
    /**
     * @param  Figure  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Figure::assertInstanceOf($node);

        $image = $node->firstChild();
        $inner = $childRenderer->renderNodes($node->children());
        $caption = '';

        if ($image instanceof Image && $image->getTitle() !== null && $image->getTitle() !== '') {
            $caption = new HtmlElement('figcaption', [], Xml::escape($image->getTitle()));
        }

        return new HtmlElement('figure', ['class' => 'codex-figure'], $inner.$caption);
    }
}
