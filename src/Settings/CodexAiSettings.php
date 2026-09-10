<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Settings;

use FinityLabs\LinCodex\Translation\DefaultInstructions;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

/**
 * AI translation configuration, in its own settings group so a host that
 * upgrades lin-codex without wanting AI changes nothing: the group is
 * seeded by its own migration under database/settings, and a host that
 * never publishes and runs it simply has no rows.
 *
 * That unseeded state is the normal "AI is off" state, never an error.
 * The first property read of an unseeded group throws spatie's
 * MissingSettings, and a host with no settings table at all throws a
 * QueryException; every reader catches both and reports AI as
 * unavailable, the way Sources\DefaultLocale falls back to app.locale.
 *
 * $timeout is seconds, and one setting covers both paths a translation
 * takes: the synchronous action in the panel and the queued job. $provider
 * is a laravel/ai lab name such as "anthropic", and a null $model means
 * the provider's own default text model. $api_key is stored encrypted
 * through spatie; a null key means the SDK's env key for that provider is
 * used untouched.
 *
 * Not final, mirroring CodexSettings: a host may extend it.
 */
class CodexAiSettings extends Settings
{
    /**
     * The keys of defaults() whose rows are seeded encrypted. The settings
     * migration reads this instead of naming api_key twice.
     *
     * @var list<string>
     */
    public const ENCRYPTED = ['api_key'];

    public bool $enabled;

    public ?string $provider;

    public ?string $model;

    #[ShouldBeEncrypted]
    public ?string $api_key;

    public int $timeout;

    public string $translation_instructions;

    public static function group(): string
    {
        return 'lin-codex-ai';
    }

    /**
     * Seed values for a fresh install: AI off, no provider preselected,
     * the package's default translation instructions.
     *
     * Scalars only, so the settings migration can persist the array as-is.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'provider' => null,
            'model' => null,
            'api_key' => null,
            'timeout' => 120,
            'translation_instructions' => DefaultInstructions::TEXT,
        ];
    }
}
