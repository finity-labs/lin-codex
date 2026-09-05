<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A `:::name argument` block closed by a fence of at least the same length.
 * Known names (steps, details) get their own markup; unknown names render
 * their children without a wrapper so nothing is lost.
 */
final class FencedContainer extends AbstractBlock
{
    public function __construct(
        public readonly string $name,
        public readonly string $argument,
        public readonly int $fenceLength,
    ) {
        parent::__construct();
    }
}
