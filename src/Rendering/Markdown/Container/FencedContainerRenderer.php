<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Dispatches on the container name; unknown names render their children
 * with no wrapper and no class.
 */
final class FencedContainerRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly StepsRenderer $steps,
        private readonly DetailsRenderer $details,
    ) {}

    /**
     * @param  FencedContainer  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        FencedContainer::assertInstanceOf($node);

        return match ($node->name) {
            'steps' => $this->steps->render($node, $childRenderer),
            'details' => $this->details->render($node, $childRenderer),
            default => $childRenderer->renderNodes($node->children()),
        };
    }
}
