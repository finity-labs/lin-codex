<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

use RuntimeException;
use Throwable;

/**
 * A call that did not produce a usable answer, carrying an `AiReason` key.
 *
 * The key is what callers branch on and what the UI renders through
 * `AiReason::label()`; the message exists for logs and stack traces.
 */
final class AiCallFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('AI call failed: '.$reason, 0, $previous);
    }
}
