<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use FinityLabs\LinCodex\Rendering\Markdown\RenderState;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

final class FencedContainerExtension implements ExtensionInterface
{
    public function __construct(private readonly RenderState $state) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new FencedContainerStartParser, 100);
        $environment->addRenderer(FencedContainer::class, new FencedContainerRenderer(new StepsRenderer, new DetailsRenderer($this->state)));
    }
}
