<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums\Concerns;

use Illuminate\Support\Str;
use ValueError;

/**
 * Int-backed enums keep the database small; files, exports, and the JSON API
 * speak in words. The key is the snake_case form of the case name and is the
 * value that appears in front matter (`visibility: public`) and lang files.
 *
 * @mixin \BackedEnum
 */
trait HasKey
{
    public function key(): string
    {
        return Str::snake($this->name);
    }

    public static function fromKey(string $key): static
    {
        return static::tryFromKey($key)
            ?? throw new ValueError(sprintf('"%s" is not a valid key for enum %s', $key, static::class));
    }

    public static function tryFromKey(string $key): ?static
    {
        foreach (static::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_map(static fn (self $case): string => $case->key(), static::cases());
    }

    public function label(): string
    {
        return (string) __('lin-codex::lin-codex.enums.'.static::translationGroup().'.'.$this->key());
    }

    private static function translationGroup(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
