<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

use BackedEnum;

/**
 * The providers lin-codex offers for translation, and what the SDK config
 * says about them.
 *
 * Only providers an API key alone can reach are listed. Azure needs a
 * resource URL and a deployment name, Bedrock needs AWS credentials and a
 * region, and OpenAI-compatible needs a base URL, none of which a single key
 * field can carry; a host that runs one of those configures it in its own
 * `config/ai.php` and lin-codex stays out of the way. Ollama is offered but
 * keyless: it is reached over a URL and its key defaults to an empty string.
 *
 * The labels come from the SDK's `Lab` enum case names, filtered to what the
 * installed SDK actually has, so a host on an older or newer SDK never sees
 * a provider it cannot use.
 */
final class ProviderCatalog
{
    /**
     * Lab values, in display order.
     *
     * @var list<string>
     */
    public const OFFERED = [
        'anthropic',
        'openai',
        'gemini',
        'mistral',
        'groq',
        'deepseek',
        'xai',
        'openrouter',
        'ollama',
    ];

    /**
     * Providers that need a URL rather than a key.
     *
     * @var list<string>
     */
    public const KEYLESS = ['ollama'];

    /**
     * @return array<string, string> OFFERED entries the installed Lab enum has, value => case name; [] without the SDK
     */
    public static function labels(): array
    {
        $lab = self::labClass();

        if (! enum_exists($lab)) {
            return [];
        }

        $names = [];

        foreach ((array) $lab::cases() as $case) {
            if ($case instanceof BackedEnum) {
                $names[(string) $case->value] = $case->name;
            }
        }

        $labels = [];

        foreach (self::OFFERED as $provider) {
            if (isset($names[$provider])) {
                $labels[$provider] = $names[$provider];
            }
        }

        return $labels;
    }

    public static function isOffered(string $provider): bool
    {
        return in_array($provider, self::OFFERED, true);
    }

    public static function isKeyless(string $provider): bool
    {
        return in_array($provider, self::KEYLESS, true);
    }

    /**
     * The SDK's own credential for the provider is present: a non-empty
     * `ai.providers.{p}.key`, or a non-empty `ai.providers.{p}.url` for a
     * keyless provider.
     */
    public static function envConfigured(string $provider): bool
    {
        $leaf = self::isKeyless($provider) ? 'url' : 'key';

        $value = config("ai.providers.{$provider}.{$leaf}");

        return is_string($value) && $value !== '';
    }

    /** Declared `string` so PHPStan keeps the SDK enum out of the analysis. */
    private static function labClass(): string
    {
        return 'Laravel\\Ai\\Enums\\Lab';
    }
}
