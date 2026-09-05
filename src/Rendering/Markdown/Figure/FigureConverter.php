<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Figure;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\NodeIterator;

/**
 * Replaces paragraphs whose only child is an image with a Figure block, so
 * the <figure> never ends up inside a <p>. Inline and linked images are
 * left where they are.
 */
final class FigureConverter
{
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $paragraphs = [];

        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if ($node instanceof Paragraph && $node->firstChild() instanceof Image && $node->firstChild() === $node->lastChild()) {
                $paragraphs[] = $node;
            }
        }

        foreach ($paragraphs as $paragraph) {
            $image = $paragraph->firstChild();

            if ($image === null) {
                continue;
            }

            $figure = new Figure;
            $figure->appendChild($image);
            $paragraph->replaceWith($figure);
        }
    }
}
