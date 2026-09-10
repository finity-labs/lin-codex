<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

/**
 * One structured call, described without a single SDK type.
 *
 * The caller owns the wording: `instructions` is the agent's system text and
 * `prompt` the user turn. `fields` becomes the output schema, so its keys are
 * the keys the answer comes back under.
 */
final readonly class StructuredRequest
{
    /**
     * @param  array<string, string>  $fields  field name => description, every field a required string
     * @param  string|null  $model  null: the provider's default text model
     * @param  int  $timeout  seconds
     * @param  string|null  $apiKey  null or '': use the SDK's own env key
     */
    public function __construct(
        public string $instructions,
        public string $prompt,
        public array $fields,
        public string $provider,
        public ?string $model,
        public int $timeout,
        public ?string $apiKey,
    ) {}
}
