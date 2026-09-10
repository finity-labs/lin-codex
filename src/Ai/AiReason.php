<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

/**
 * Why an AI call did not produce a usable translation.
 *
 * Plain string constants rather than an enum: no column stores a reason, the
 * keys travel through a job result and a notification, and the label pattern
 * the package uses everywhere else is a lang file keyed by the string.
 *
 * `output_rejected` and `invalid_output` are the translator's own verdicts on
 * an answer that arrived intact - a canary in the text, or a payload that was
 * empty, truncated or structurally broken. The rest are mapped from what the
 * SDK threw. fin-codex renders `label()`.
 */
final class AiReason
{
    public const TIMEOUT = 'timeout';

    public const AUTHENTICATION_FAILED = 'authentication_failed';

    public const RATE_LIMITED = 'rate_limited';

    public const QUOTA_EXCEEDED = 'quota_exceeded';

    /** A canary in the answer (translator verdict). */
    public const OUTPUT_REJECTED = 'output_rejected';

    /** Empty, truncated, malformed or structure-breaking answer (translator verdict). */
    public const INVALID_OUTPUT = 'invalid_output';

    public const UNAVAILABLE = 'unavailable';

    public const UNKNOWN = 'unknown';

    /** @var list<string> */
    public const ALL = [
        self::TIMEOUT,
        self::AUTHENTICATION_FAILED,
        self::RATE_LIMITED,
        self::QUOTA_EXCEEDED,
        self::OUTPUT_REJECTED,
        self::INVALID_OUTPUT,
        self::UNAVAILABLE,
        self::UNKNOWN,
    ];

    public static function label(string $reason): string
    {
        return (string) __('lin-codex::lin-codex.ai.reasons.'.$reason);
    }
}
