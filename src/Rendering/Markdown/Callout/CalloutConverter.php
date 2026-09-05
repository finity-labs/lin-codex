<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Callout;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\NodeIterator;

/**
 * Turns `> [!WARNING] Optional title` blockquotes into Callout nodes after
 * parsing. The marker must be the first Text literal of the first paragraph;
 * anything else in that literal becomes the plain-text title, and inline
 * markup after the marker stays in the body (documented v1 limitation).
 * Blockquotes without a known marker are left alone.
 */
final class CalloutConverter
{
    private const MARKER = '/^\[!(note|tip|important|warning|caution)\](?:[ \t]+(.*))?$/i';

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $quotes = [];

        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if ($node instanceof BlockQuote) {
                $quotes[] = $node;
            }
        }

        foreach ($quotes as $quote) {
            $this->convert($quote);
        }
    }

    private function convert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return;
        }

        $text = $paragraph->firstChild();

        if (! $text instanceof Text || preg_match(self::MARKER, $text->getLiteral(), $match) !== 1) {
            return;
        }

        $type = CalloutType::fromKey(strtolower($match[1]));
        $title = isset($match[2]) && trim($match[2]) !== '' ? trim($match[2]) : null;

        $next = $text->next();
        $text->detach();

        if ($next instanceof Newline) {
            $next->detach();
        }

        if (! $paragraph->hasChildren()) {
            $paragraph->detach();
        }

        $callout = new Callout($type, $title);

        foreach ($quote->children() as $child) {
            $callout->appendChild($child);
        }

        $quote->replaceWith($callout);
    }
}
