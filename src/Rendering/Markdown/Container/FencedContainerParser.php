<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Keeps a container open until a closing fence at least as long as the
 * opening one, or the end of the document. The container adds no
 * indentation, so every other line passes through untouched.
 *
 * MarkdownParser asks the outermost open block first and closes everything
 * inside it on the first finished(), so an outer `:::steps` would swallow
 * the `:::` meant for an inner `:::details`. The guard walks from the
 * active block up to this one and yields whenever another container is
 * still open in between.
 */
final class FencedContainerParser extends AbstractBlockContinueParser
{
    private FencedContainer $block;

    public function __construct(int $fenceLength, string $name, string $argument)
    {
        $this->block = new FencedContainer($name, $argument, $fenceLength);
    }

    public function getBlock(): FencedContainer
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
    {
        if ($this->innerContainerIsOpen($activeBlockParser->getBlock())) {
            return BlockContinue::at($cursor);
        }

        if (! $cursor->isIndented() && preg_match('/^[ \t]{0,3}:{'.$this->block->fenceLength.',}[ \t]*$/', $cursor->getRemainder()) === 1) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    private function innerContainerIsOpen(AbstractBlock $activeBlock): bool
    {
        for ($node = $activeBlock; $node !== null && $node !== $this->block; $node = $node->parent()) {
            if ($node instanceof FencedContainer) {
                return true;
            }
        }

        return false;
    }
}
