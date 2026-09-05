<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Replaces the stock permalink renderer, which has no aria-label option.
 * Emits <a class="codex-anchor" href="#slug" aria-label="...">#</a>; the
 * attribute order class, href, aria-label is part of the markup contract
 * (PlainTextExtractor matches it, Phase 7 styles it).
 */
final class HeadingAnchorRenderer implements NodeRendererInterface
{
    public function __construct(private readonly RenderState $state) {}

    /**
     * @param  HeadingPermalink  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        HeadingPermalink::assertInstanceOf($node);

        $slug = $node->getSlug();
        $heading = $node->parent();
        $text = $heading instanceof Heading
            ? StringContainerHelper::getChildText($heading, [RawMarkupContainerInterface::class])
            : '';

        return new HtmlElement('a', [
            'class' => 'codex-anchor',
            'href' => '#'.$slug,
            'aria-label' => (string) __('lin-codex::lin-codex.anchor_label', ['heading' => $text], $this->state->locale),
        ], '#');
    }
}
