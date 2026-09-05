<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

/**
 * Which database branch answered a search: the engine's full-text index or
 * the portable LIKE pre-filter. Exposed so a test can prove the full-text
 * index was used; the hit set is the same either way because the PHP
 * matcher decides.
 */
enum SearchStrategy: int
{
    use HasKey;

    case FullText = 1;
    case Like = 2;
}
