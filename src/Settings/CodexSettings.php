<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Settings;

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use Illuminate\Support\Str;
use Spatie\LaravelSettings\Settings;

class CodexSettings extends Settings
{
    /**
     * Languages articles may be translated into, each built by languageEntry().
     *
     * Typed with the phpstan-only tag on purpose: spatie/laravel-settings resolves
     * the standard var tag through phpdocumentor/type-resolver, which cannot parse
     * array shapes or nested generics and would fail to build a cast for this
     * property. The native array type is all the settings store needs.
     *
     * @phpstan-var array<int, array{code: string, display: string, 'flag-icon': string}>
     */
    public array $languages;

    public string $default_locale;

    public FallbackBehaviour $fallback;

    public bool $revisions_enabled;

    public int $revisions_keep;

    public static function group(): string
    {
        return 'lin-codex';
    }

    /**
     * Seed values for a fresh install: one language derived from the app
     * locale, fallback to the default language, revisions off.
     *
     * Scalars only (the enum is stored as its backing value) so the settings
     * migration can persist the array as-is.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $code = (string) config('app.locale', 'en');

        return [
            'languages' => [
                static::languageEntry($code),
            ],
            'default_locale' => $code,
            'fallback' => FallbackBehaviour::ShowDefault->value,
            'revisions_enabled' => false,
            'revisions_keep' => 10,
        ];
    }

    /**
     * Build a language entry for the given locale code, using ext-intl for
     * the native display name when it is available.
     *
     * @return array{code: string, display: string, 'flag-icon': string}
     */
    public static function languageEntry(string $code): array
    {
        $display = extension_loaded('intl') ? (string) \Locale::getDisplayLanguage($code, $code) : $code;

        return [
            'code' => $code,
            'display' => Str::ucfirst($display),
            'flag-icon' => ['en' => 'gb'][$code] ?? substr($code, 0, 2),
        ];
    }
}
