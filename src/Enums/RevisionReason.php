<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

enum RevisionReason: int
{
    use HasKey;

    case Manual = 1;
    case Import = 2;
    case AiRewrite = 3;

    /** The snapshot restore() takes before swapping a revision back in. */
    case Restore = 4;
}
