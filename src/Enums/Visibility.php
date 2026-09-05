<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

enum Visibility: int
{
    use HasKey;

    case Public = 1;
    case Authenticated = 2;
}
