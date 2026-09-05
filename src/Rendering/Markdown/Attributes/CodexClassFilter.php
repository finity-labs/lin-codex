<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Attributes;

use League\CommonMark\Event\DocumentParsedEvent;

/**
 * Keeps author-supplied class attributes ("{.codex-lead .fi-btn}") down to
 * the tokens the renderer owns, so Markdown cannot borrow host CSS. The same
 * rule the HTML sanitizer's CodexClassSanitizer applies, so both formats
 * behave alike. An attribute with nothing left is removed rather than
 * emitted empty.
 *
 * Registered at DocumentParsedEvent priority -10: after the AttributesListener
 * and the default attributes processor (0) have written author and default
 * classes into node data, after the Codex converters (0) have built their
 * nodes, but before the ExternalLinkProcessor (-50) appends codex-external
 * and before the HeadingPermalinkProcessor (-100) adds ids. Renderer-level
 * classes such as language-* on fenced code never pass through node data,
 * so they are unaffected.
 */
final class CodexClassFilter
{
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            $class = $node->data->get('attributes/class', null);

            if (! is_string($class) || trim($class) === '') {
                continue;
            }

            $kept = [];

            foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
                if ($token !== '' && str_starts_with($token, 'codex-')) {
                    $kept[] = $token;
                }
            }

            if ($kept === []) {
                $node->data->remove('attributes/class');

                continue;
            }

            $node->data->set('attributes/class', implode(' ', $kept));
        }
    }
}
