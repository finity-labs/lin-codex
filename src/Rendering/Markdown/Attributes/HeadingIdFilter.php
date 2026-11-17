<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Attributes;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\NodeIterator;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * Drops an author-supplied id ("## Heading {#custom}") from every heading
 * the permalink processor is about to give one, so a heading id is always
 * the slug of its text: the same text yields the same id, and duplicates
 * get the slugger's -2, -3 suffixes.
 *
 * The processor used to overwrite such an id itself. Since league/commonmark
 * 2.10.4 it keeps the id it finds and points the permalink at it, which
 * skips CodexSlugNormalizer, so the attribute has to be gone before the
 * processor runs. Registered at DocumentParsedEvent priority -10 like
 * CodexClassFilter: after the AttributesListener (0) wrote the id into node
 * data, before the HeadingPermalinkProcessor (-100) reads it.
 *
 * A heading outside the permalink levels keeps its id, as it always did:
 * nothing generates one for it.
 */
final class HeadingIdFilter implements ConfigurationAwareInterface
{
    private ConfigurationInterface $config;

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $min = (int) $this->config->get('heading_permalink/min_heading_level');
        $max = (int) $this->config->get('heading_permalink/max_heading_level');

        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if ($node instanceof Heading && $node->getLevel() >= $min && $node->getLevel() <= $max) {
                $node->data->remove('attributes/id');
            }
        }
    }
}
