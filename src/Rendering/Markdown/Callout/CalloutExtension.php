<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Callout;

use FinityLabs\LinCodex\Rendering\Markdown\RenderState;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

final class CalloutExtension implements ExtensionInterface
{
    public function __construct(private readonly RenderState $state) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, [new CalloutConverter, 'onDocumentParsed'], 0);
        $environment->addRenderer(Callout::class, new CalloutRenderer($this->state));
    }
}
