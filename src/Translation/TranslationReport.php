<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

/**
 * What a translation run did, locale by locale.
 *
 * A collector in SyncReport's voice, not a value object: the job appends to
 * it while it works and the ArticleTranslated event carries it once. Every
 * requested locale lands in exactly one of three statuses - translated (with
 * the tokens the call cost), skipped (it gained a title and a body between
 * dispatch and run) or failed with a reason.
 *
 * The reason is an Ai\AiReason key, never a sentence: fin-codex renders it
 * with AiReason::label() in the admin's language, so the same report reads
 * correctly for two admins on two locales.
 *
 * toArray() is the notification payload shape. It keeps the recording order,
 * so a notification lists locales in the order the job worked through them,
 * and fromArray() rebuilds a report from a stored payload, tolerating the
 * keys an older payload may lack.
 */
final class TranslationReport
{
    public const TRANSLATED = 'translated';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    /**
     * @var array<string, array{status: string, reason: string|null, prompt_tokens: int, completion_tokens: int}>
     */
    private array $locales = [];

    public function translated(string $locale, int $promptTokens = 0, int $completionTokens = 0): void
    {
        $this->locales[$locale] = [
            'status' => self::TRANSLATED,
            'reason' => null,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ];
    }

    public function skipped(string $locale): void
    {
        $this->locales[$locale] = [
            'status' => self::SKIPPED,
            'reason' => null,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
        ];
    }

    public function failed(string $locale, string $reason): void
    {
        $this->locales[$locale] = [
            'status' => self::FAILED,
            'reason' => $reason,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        return $this->withStatus(self::TRANSLATED);
    }

    /**
     * @return list<string>
     */
    public function skippedLocales(): array
    {
        return $this->withStatus(self::SKIPPED);
    }

    /**
     * @return array<string, string> locale => reason key
     */
    public function failedLocales(): array
    {
        $failed = [];

        foreach ($this->locales as $locale => $entry) {
            if ($entry['status'] === self::FAILED) {
                $failed[$locale] = (string) $entry['reason'];
            }
        }

        return $failed;
    }

    public function hasFailures(): bool
    {
        return $this->failedLocales() !== [];
    }

    public function isEmpty(): bool
    {
        return $this->locales === [];
    }

    /**
     * The prompt tokens the translated locales cost together.
     */
    public function promptTokens(): int
    {
        return $this->sum('prompt_tokens');
    }

    /**
     * The completion tokens the translated locales cost together.
     */
    public function completionTokens(): int
    {
        return $this->sum('completion_tokens');
    }

    /**
     * @return array<string, array{status: string, reason: string|null, prompt_tokens: int, completion_tokens: int}> keyed by locale, in recording order
     */
    public function toArray(): array
    {
        return $this->locales;
    }

    /**
     * @param  array<string, array{status: string, reason?: string|null, prompt_tokens?: int, completion_tokens?: int}>  $data
     */
    public static function fromArray(array $data): self
    {
        $report = new self;

        foreach ($data as $locale => $entry) {
            $reason = $entry['reason'] ?? null;

            $report->locales[$locale] = [
                'status' => $entry['status'],
                'reason' => $reason === null ? null : (string) $reason,
                'prompt_tokens' => (int) ($entry['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($entry['completion_tokens'] ?? 0),
            ];
        }

        return $report;
    }

    /**
     * @return list<string>
     */
    private function withStatus(string $status): array
    {
        $locales = [];

        foreach ($this->locales as $locale => $entry) {
            if ($entry['status'] === $status) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * @param  'prompt_tokens'|'completion_tokens'  $key
     */
    private function sum(string $key): int
    {
        $total = 0;

        foreach ($this->locales as $entry) {
            if ($entry['status'] === self::TRANSLATED) {
                $total += $entry[$key];
            }
        }

        return $total;
    }
}
