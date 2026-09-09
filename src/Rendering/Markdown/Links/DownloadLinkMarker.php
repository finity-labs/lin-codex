<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Links;

use FinityLabs\LinCodex\Rendering\DownloadLink;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Stamps every link to a file (DownloadLink::fileName()) with a `download`
 * attribute, so "[Guide](/storage/codex/2026/09/guide.pdf)" saves the PDF
 * instead of navigating to it. Runs after ArticleLinkResolver: a resolved
 * article href has no file extension and is never touched. Image nodes
 * are not links and are never touched either.
 */
final class DownloadLinkMarker
{
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof Link) {
                continue;
            }

            $name = DownloadLink::fileName($node->getUrl());

            if ($name !== null) {
                $node->data->set('attributes/download', $name);
            }
        }
    }
}
