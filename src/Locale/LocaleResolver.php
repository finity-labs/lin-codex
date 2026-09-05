<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Locale;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\DefaultLocale;
use Illuminate\Database\QueryException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * The one locale rule. pick() matches the requested locale exactly against
 * the codes in CodexSettings::$languages; there is no language-part fallback
 * (de_DE never becomes de) and a locale outside the list is treated exactly
 * like a missing translation. When the exact translation is missing,
 * FallbackBehaviour::ShowDefault returns the default-language translation
 * flagged as a fallback and FallbackBehaviour::Hide returns null.
 *
 * The same pick() serves the reader, the tree builder and the context
 * resolver. Settings are read at call time, inside the guard DefaultLocale
 * uses, so an unseeded group or a missing settings table never throws on a
 * read path and a test's save() or CodexSettings::fake() is seen at once.
 */
final class LocaleResolver
{
    public function __construct(private readonly DefaultLocale $defaultLocale) {}

    /**
     * The supported locale codes; the app locale alone when unseeded.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        try {
            $entries = app(CodexSettings::class)->languages;
        } catch (MissingSettings|QueryException) {
            return [(string) config('app.locale', 'en')];
        }

        $codes = [];

        foreach ($entries as $entry) {
            $codes[] = $entry['code'];
        }

        return $codes;
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale->get();
    }

    public function fallback(): FallbackBehaviour
    {
        try {
            return app(CodexSettings::class)->fallback;
        } catch (MissingSettings|QueryException) {
            return FallbackBehaviour::ShowDefault;
        }
    }

    /**
     * The display name of a locale from its settings entry, else the code.
     */
    public function displayName(string $code): string
    {
        try {
            $entries = app(CodexSettings::class)->languages;
        } catch (MissingSettings|QueryException) {
            return $code;
        }

        foreach ($entries as $entry) {
            if ($entry['code'] === $code) {
                return $entry['display'] !== '' ? $entry['display'] : $code;
            }
        }

        return $code;
    }

    /**
     * The locale a read call runs under: the argument, else the app locale.
     */
    public function resolve(?string $locale): string
    {
        return $locale ?? app()->getLocale();
    }

    /**
     * The "not yet available in your language" notice for a fallback,
     * naming the language that is shown instead.
     */
    public function fallbackNotice(string $shownLocale): string
    {
        return __('lin-codex::lin-codex.fallback_notice', ['language' => $this->displayName($shownLocale)]);
    }

    public function pick(ArticleData $article, string $locale): ?TranslationChoice
    {
        if (in_array($locale, $this->languages(), true) && ($exact = $article->translation($locale)) !== null) {
            return new TranslationChoice($exact, false);
        }

        if ($this->fallback() === FallbackBehaviour::ShowDefault && ($default = $article->translation($this->defaultLocale())) !== null) {
            return new TranslationChoice($default, true);
        }

        return null;
    }
}
