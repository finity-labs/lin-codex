<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

/**
 * Whether AI translation can run at all, and if not, what is missing.
 *
 * Plan 09-03's `AiAvailabilityCheck` produces this; the tab, row and bulk
 * actions in fin-codex hide or disable themselves on it and show `label()`
 * as the reason. A provider that is not set, or one the package does not
 * offer, reports `no_key`: without a provider there is no key to look for,
 * and the label already asks the admin to pick one.
 */
final readonly class AiAvailability
{
    public const SDK_MISSING = 'sdk_missing';

    public const NOT_MIGRATED = 'not_migrated';

    public const DISABLED = 'disabled';

    public const NO_KEY = 'no_key';

    /** @var list<string> */
    public const REASONS = [self::SDK_MISSING, self::NOT_MIGRATED, self::DISABLED, self::NO_KEY];

    private function __construct(public bool $available, public ?string $reason) {}

    public static function available(): self
    {
        return new self(true, null);
    }

    public static function unavailable(string $reason): self
    {
        return new self(false, $reason);
    }

    /** The why-not label, '' when available. */
    public function label(): string
    {
        if ($this->reason === null) {
            return '';
        }

        return (string) __('lin-codex::lin-codex.ai.unavailable.'.$this->reason);
    }
}
