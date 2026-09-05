<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

/**
 * Which part of the search text a hit came from. The cases are declared in
 * ranking order: a title hit outranks a keyword hit, which outranks an
 * excerpt hit, which outranks a body hit.
 */
enum SearchField: int
{
    use HasKey;

    case Title = 1;
    case Keywords = 2;
    case Excerpt = 3;
    case Body = 4;
}
