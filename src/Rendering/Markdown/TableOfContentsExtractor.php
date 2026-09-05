<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Reads the table of contents off the parsed Document after the heading
 * permalink processor assigned ids. This stands in for commonmark's
 * TableOfContentsExtension: that extension can only inject a list node into
 * the body, and the contract wants data the readers place themselves, so it
 * is not registered.
 */
final class TableOfContentsExtractor
{
    /**
     * @return list<array{level: int, text: string, id: string}>
     */
    public static function extract(Document $document): array
    {
        $entries = [];

        foreach ($document->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (! $node instanceof Heading || $node->getLevel() < 2 || $node->getLevel() > 3) {
                continue;
            }

            $entries[] = [
                'level' => $node->getLevel(),
                'text' => StringContainerHelper::getChildText($node, [RawMarkupContainerInterface::class]),
                'id' => (string) $node->data->get('attributes/id', ''),
            ];
        }

        return $entries;
    }
}
