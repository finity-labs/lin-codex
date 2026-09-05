<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

enum ArticleFormat: int
{
    use HasKey;

    case Markdown = 1;
    case Html = 2;
}
