<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Links;

use FinityLabs\LinCodex\Rendering\ArticlePath;
use FinityLabs\LinCodex\Rendering\Markdown\RenderState;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Rewrites article-to-article links written as relative Markdown file paths
 * ("roles.md", "../billing/invoices.md#totals") into help center hrefs and
 * stamps them with data-codex-article so the drawer can open them in place.
 * The current slug is read from RenderState on every run, so one memoized
 * Environment serves every article. Links ArticlePath cannot resolve stay
 * exactly as written; Image nodes are never touched.
 */
final class ArticleLinkResolver
{
    public function __construct(private readonly RenderState $state) {}

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $links = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Link) {
                $links[] = $node;
            }
        }

        foreach ($links as $link) {
            $resolved = ArticlePath::resolve($this->state->slug, $link->getUrl());

            if ($resolved === null) {
                continue;
            }

            $link->setUrl(ArticlePath::href($resolved['slug'], $resolved['fragment']));
            $link->data->set('attributes/data-codex-article', $resolved['slug']);
        }
    }
}
