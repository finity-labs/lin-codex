<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

/**
 * Markers that mean an answer carried something other than a translation.
 *
 * The built-in list is fin-sentinel's, verbatim: chat-template delimiters and
 * the usual prompt-injection phrases. `lin-codex.ai.output_canaries` extends
 * it, so a host can add a marker of its own without a release.
 *
 * A hit is a verdict about the answer, not about the provider, which is why
 * the translator reports it as `AiReason::OUTPUT_REJECTED`.
 */
final class OutputCanaries
{
    /**
     * fin-sentinel's list, verbatim.
     *
     * @var list<string>
     */
    public const CANARIES = [
        'PWNED',
        'ignore previous instructions',
        'ignore prior instructions',
        'disregard previous instructions',
        'disregard prior instructions',
        '<|im_start|>',
        '<|im_end|>',
        '<|system|>',
        '<|user|>',
        '<|assistant|>',
        '[INST]',
        '[/INST]',
    ];

    /**
     * The markers found in the text, case-insensitive, each one listed once.
     *
     * @return list<string>
     */
    public function hits(string $text): array
    {
        /** @var array<int, mixed> $extra */
        $extra = (array) config('lin-codex.ai.output_canaries', []);

        $found = [];

        foreach (array_merge(self::CANARIES, array_values($extra)) as $marker) {
            $marker = (string) $marker;

            if ($marker === '') {
                continue;
            }

            if (stripos($text, $marker) !== false) {
                $found[] = $marker;
            }
        }

        return $found;
    }

    public function hit(string $text): bool
    {
        return $this->hits($text) !== [];
    }
}
