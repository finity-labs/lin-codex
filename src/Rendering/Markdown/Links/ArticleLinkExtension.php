<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Links;

use FinityLabs\LinCodex\Rendering\Markdown\RenderState;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Registers the article link resolver at DocumentParsedEvent priority 0,
 * ahead of the ExternalLinkProcessor at -50: a rewritten root-relative href
 * has no host and is skipped by the external link pass, and an absolute
 * help center URL on the app host is classed internal. Resolved article
 * links therefore never receive target, rel or codex-external.
 */
final class ArticleLinkExtension implements ExtensionInterface
{
    public function __construct(private readonly RenderState $state) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, [new ArticleLinkResolver($this->state), 'onDocumentParsed'], 0);
    }
}
