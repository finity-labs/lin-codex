<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

/**
 * The agent every translation call runs on, and the only file under src/
 * allowed to name a class from the optional laravel/ai SDK.
 *
 * It exists for one reason: the SDK resolves the output-token ceiling from a
 * `maxTokens()` method or a `#[MaxTokens]` attribute on the agent class, and
 * the anonymous agent has neither. Without a ceiling Anthropic is asked for
 * 64000 tokens and the other providers for no limit at all, so one chatty
 * answer can run to the provider's maximum on every locale.
 *
 * PHPStan scans this file for symbols but does not analyse it
 * (`phpstan.neon`, `excludePaths.analyse`): it cannot check a class whose
 * parent is absent, and it refuses to ignore that error anywhere else. The
 * class is autoloaded only when `LaravelAiClient` builds an agent, which
 * already requires the SDK.
 */
final class TranslationAgent extends \Laravel\Ai\StructuredAnonymousAgent
{
    public function maxTokens(): int
    {
        return max(1, (int) config('lin-codex.ai.max_tokens', 16000));
    }
}
