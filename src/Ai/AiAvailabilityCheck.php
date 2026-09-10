<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

use FinityLabs\LinCodex\Ai\Contracts\AiClient;
use FinityLabs\LinCodex\Settings\CodexAiSettings;
use Illuminate\Database\QueryException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * The one rule that answers "can this installation translate with AI?".
 *
 * Four things have to be true, and they are checked in this order so the
 * first thing an admin has to fix is the one reported:
 *
 * 1. `sdk_missing`   — the optional laravel/ai SDK is not installed. Nothing
 *                      below it can be true, so it comes first.
 * 2. `not_migrated`  — the AI settings group has never been seeded (spatie's
 *                      MissingSettings on the first property read), or the
 *                      host has no settings table at all (QueryException).
 *                      A host that upgraded lin-codex without publishing and
 *                      running the new settings migration lands here; it is
 *                      the normal "AI is off" state, never an error.
 * 3. `disabled`      — the settings exist and the toggle is off.
 * 4. `no_key`        — no provider is chosen, the chosen provider is not one
 *                      lin-codex offers, or there is no credential for it:
 *                      neither a key stored in the settings nor the SDK's own
 *                      env key (for Ollama, which is keyless, a URL counts).
 *
 * A missing or unoffered provider reports `no_key` on purpose rather than a
 * fifth key: without a provider there is no key to look for, and the label
 * already tells the admin to pick a provider and enter a key.
 *
 * fin-codex hides or disables its translate actions on this rule and shows
 * `label()` as the reason; the queued job re-runs it at run time, because a
 * key can be removed or the toggle switched off between dispatch and work.
 */
final class AiAvailabilityCheck
{
    public function __construct(private readonly AiClient $client) {}

    /**
     * The four ordered rules; `AiAvailability::available()` when all hold.
     */
    public function check(): AiAvailability
    {
        if (! $this->client->installed()) {
            return AiAvailability::unavailable(AiAvailability::SDK_MISSING);
        }

        try {
            /*
             * The first property read is what loads the group, so the read
             * has to happen inside the guard, the way Sources\DefaultLocale
             * reads the default locale.
             */
            $settings = app(CodexAiSettings::class);
            $enabled = $settings->enabled;
        } catch (MissingSettings|QueryException) {
            return AiAvailability::unavailable(AiAvailability::NOT_MIGRATED);
        }

        if (! $enabled) {
            return AiAvailability::unavailable(AiAvailability::DISABLED);
        }

        $provider = $settings->provider;

        if ($provider === null || $provider === '' || ! ProviderCatalog::isOffered($provider)) {
            return AiAvailability::unavailable(AiAvailability::NO_KEY);
        }

        $stored = $settings->api_key;

        if (is_string($stored) && $stored !== '') {
            return AiAvailability::available();
        }

        return ProviderCatalog::envConfigured($provider)
            ? AiAvailability::available()
            : AiAvailability::unavailable(AiAvailability::NO_KEY);
    }

    /** Shorthand for callers that do not need the reason. */
    public function available(): bool
    {
        return $this->check()->available;
    }
}
