<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Callout;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A blockquote that opened with a GitHub alert marker. The marker line is
 * gone by the time this node exists; the children are the callout body.
 */
final class Callout extends AbstractBlock
{
    public function __construct(public readonly CalloutType $type, public readonly ?string $title = null)
    {
        parent::__construct();
    }
}
