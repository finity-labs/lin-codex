<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Figure;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * The stock image renderer plus loading="lazy", decoding="async" and the
 * data-codex-lightbox hook on every <img>. Inside a figure the title is
 * left off the img because the figcaption carries it.
 */
final class CodexImageRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    private ConfigurationInterface $config;

    /**
     * @param  Image  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Image::assertInstanceOf($node);

        $attributes = $node->data->get('attributes');

        $attributes['src'] = ! $this->config->get('allow_unsafe_links') && RegexHelper::isLinkPotentiallyUnsafe($node->getUrl())
            ? ''
            : $node->getUrl();
        $attributes['alt'] = $this->altText($node);

        $title = $node->getTitle();

        if ($title !== null && $title !== '' && ! $node->parent() instanceof Figure) {
            $attributes['title'] = $title;
        }

        $attributes['loading'] = 'lazy';
        $attributes['decoding'] = 'async';
        $attributes['data-codex-lightbox'] = true;

        return new HtmlElement('img', $attributes, '', true);
    }

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    private function altText(Image $node): string
    {
        $text = '';

        foreach (new NodeIterator($node) as $child) {
            if ($child instanceof StringContainerInterface) {
                $text .= $child->getLiteral();
            } elseif ($child instanceof Newline) {
                $text .= "\n";
            }
        }

        return $text;
    }
}
