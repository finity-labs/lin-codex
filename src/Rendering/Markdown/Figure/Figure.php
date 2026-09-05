<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Figure;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A paragraph that held nothing but one image. Its single child is the
 * Image inline; the image title becomes the figcaption.
 */
final class Figure extends AbstractBlock {}
