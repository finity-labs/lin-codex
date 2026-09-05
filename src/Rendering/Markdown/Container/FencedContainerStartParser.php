<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Opens a container on `:::name` (three or more colons, up to three spaces
 * of indentation, optional argument). A bare `:::` never opens anything: it
 * is the closing fence when a container is open and plain text otherwise,
 * which is what keeps the files readable on GitHub.
 */
final class FencedContainerStartParser implements BlockStartParserInterface
{
    private const FENCE = '/^[ \t]{0,3}(:{3,})[ \t]*([a-z][a-z0-9_-]*)[ \t]*(.*?)[ \t]*$/i';

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== ':') {
            return BlockStart::none();
        }

        $line = $cursor->match(self::FENCE);

        if ($line === null || preg_match(self::FENCE, $line, $match) !== 1) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new FencedContainerParser(strlen($match[1]), strtolower($match[2]), $match[3]))->at($cursor);
    }
}
