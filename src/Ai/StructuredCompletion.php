<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

/**
 * What came back from one structured call.
 *
 * A provider that could not produce valid JSON still answers, with `fields`
 * empty; the caller decides whether that is `invalid_output`. `truncated`
 * says the last step stopped at the output-token ceiling, which is the usual
 * cause of a half-written body.
 */
final readonly class StructuredCompletion
{
    /**
     * @param  array<string, mixed>  $fields  the decoded structured answer, [] when the SDK could not decode it
     * @param  bool  $truncated  the last step finished because of the output ceiling
     */
    public function __construct(
        public array $fields,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public bool $truncated = false,
    ) {}

    /** The field as a string, null when absent or not a string. */
    public function string(string $field): ?string
    {
        $value = $this->fields[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
