<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Callout;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

/**
 * The five GitHub alert types. key() is both the lang key
 * (lin-codex::lin-codex.callouts.{key}) and the class suffix
 * (codex-callout--{key}); the int value never reaches the output.
 */
enum CalloutType: int
{
    use HasKey;

    case Note = 1;
    case Tip = 2;
    case Important = 3;
    case Warning = 4;
    case Caution = 5;
}
