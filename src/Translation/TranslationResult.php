<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

use FinityLabs\LinCodex\Ai\AiReason;

/**
 * What one translation call produced: the three fields, or a reason why not.
 *
 * A failure is a value here, never an exception, because both callers work
 * per locale and have to carry on: the queued job records the reason against
 * that locale and translates the next one, and the tab action shows the
 * label next to the field it could not fill.
 *
 * $excerpt is null when the source excerpt was blank - the package's own
 * never-invent rule, applied in PHP rather than trusted to the model - and a
 * string, possibly empty, when there was something to translate. $title and
 * $body are null only on a failure; on success they are strings, empty only
 * when the source field was.
 *
 * $reason is an Ai\AiReason key, the same vocabulary the seam and the report
 * speak, so fin-codex renders it with AiReason::label() in the admin's own
 * language.
 */
final readonly class TranslationResult
{
    private function __construct(
        public bool $ok,
        public ?string $title,
        public ?string $excerpt,
        public ?string $body,
        public int $promptTokens,
        public int $completionTokens,
        public ?string $reason,
    ) {}

    public static function success(string $title, ?string $excerpt, string $body, int $promptTokens = 0, int $completionTokens = 0): self
    {
        return new self(true, $title, $excerpt, $body, $promptTokens, $completionTokens, null);
    }

    /**
     * @param  string  $reason  an Ai\AiReason key
     */
    public static function failed(string $reason): self
    {
        return new self(false, null, null, null, 0, 0, $reason);
    }

    /** The reason's label in the current locale; '' when the call succeeded. */
    public function reasonLabel(): string
    {
        return $this->reason === null ? '' : AiReason::label($this->reason);
    }
}
