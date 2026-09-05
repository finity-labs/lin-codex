<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

enum FallbackBehaviour: int
{
    use HasKey;

    case ShowDefault = 1;
    case Hide = 2;
}
