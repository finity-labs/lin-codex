<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use FinityLabs\LinCodex\Enums\ContextType;
use InvalidArgumentException;

/**
 * Binds an article to a page class, route name or URL pattern, optionally
 * scoped to one panel. The string form is `[panel:]class|route|url:key`,
 * the shape used in front matter and exports.
 */
final readonly class ContextData
{
    public function __construct(
        public ContextType $type,
        public string $key,
        public ?string $panelId = null,
        public int $sortOrder = 0,
    ) {}

    /**
     * Only the first two colons split; the key keeps any further colons, so
     * `route:a:b` has the key `a:b` and a class key keeps its backslashes.
     *
     * @throws InvalidArgumentException when the string is not [panel:]class|route|url:key
     */
    public static function fromString(string $context, int $sortOrder = 0): self
    {
        $parts = explode(':', $context, 2);
        $head = $parts[0];
        $rest = $parts[1] ?? null;
        $panelId = null;

        if ($rest !== null && ContextType::tryFromKey($head) === null) {
            $panelId = $head;
            $parts = explode(':', $rest, 2);
            $head = $parts[0];
            $rest = $parts[1] ?? null;
        }

        $type = ContextType::tryFromKey($head);

        if ($rest === null || $rest === '' || $type === null || $panelId === '') {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid context; expected [panel:]class|route|url:key.',
                $context,
            ));
        }

        return new self($type, $rest, $panelId, $sortOrder);
    }

    public function toString(): string
    {
        return ($this->panelId !== null ? $this->panelId.':' : '').$this->type->key().':'.$this->key;
    }
}
