<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Figure;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;

final class FigureExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, [new FigureConverter, 'onDocumentParsed'], 0);
        $environment->addRenderer(Figure::class, new FigureRenderer);
        $environment->addRenderer(Image::class, new CodexImageRenderer, 10);
    }
}
