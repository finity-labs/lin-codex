<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

/**
 * Keys are the prefixes used in front matter and context strings:
 * `class:App\Filament\Resources\UserResource`, `route:users.index`, `url:/users/*`.
 */
enum ContextType: int
{
    use HasKey;

    case PageClass = 1;
    case Route = 2;
    case Url = 3;

    public function key(): string
    {
        return match ($this) {
            self::PageClass => 'class',
            self::Route => 'route',
            self::Url => 'url',
        };
    }
}
