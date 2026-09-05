<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Locale\TranslationChoice;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $codes
 */
function linCodexLocaleUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault, string $default = 'en'): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = $default;
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * @param  list<string>  $locales
 */
function linCodexLocaleArticle(array $locales): ArticleData
{
    $translations = [];

    foreach ($locales as $code) {
        $translations[$code] = new TranslationData($code, 'Title '.$code, null, 'Body '.$code, null);
    }

    return new ArticleData(
        slug: 'sample',
        parentSlug: null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: [],
        translations: $translations,
    );
}

it('reads the seeded defaults', function (): void {
    $locales = app(LocaleResolver::class);

    expect($locales->languages())->toBe(['en'])
        ->and($locales->defaultLocale())->toBe('en')
        ->and($locales->fallback())->toBe(FallbackBehaviour::ShowDefault);
});

it('sees a saved settings change', function (): void {
    linCodexLocaleUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

    $locales = app(LocaleResolver::class);

    expect($locales->languages())->toBe(['en', 'de'])
        ->and($locales->fallback())->toBe(FallbackBehaviour::Hide);
});

it('falls back to the app locale when the settings group is unseeded', function (): void {
    DB::table('settings')->where('group', 'lin-codex')->delete();

    $locales = app(LocaleResolver::class);

    expect($locales->languages())->toBe([config('app.locale')])
        ->and($locales->defaultLocale())->toBe(config('app.locale'))
        ->and($locales->fallback())->toBe(FallbackBehaviour::ShowDefault)
        ->and($locales->displayName('en'))->toBe('en');
});

it('resolves null to the current app locale', function (): void {
    $locales = app(LocaleResolver::class);

    expect($locales->resolve(null))->toBe(app()->getLocale());

    app()->setLocale('de');

    expect($locales->resolve(null))->toBe('de')
        ->and($locales->resolve('hu'))->toBe('hu');
});

it('gives the display name from the settings entry or the code itself', function (): void {
    $locales = app(LocaleResolver::class);

    expect($locales->displayName('en'))->toBe(app(CodexSettings::class)->languages[0]['display'])
        ->and($locales->displayName('xx'))->toBe('xx');
});

it('builds the fallback notice from the lang key', function (): void {
    $locales = app(LocaleResolver::class);
    $notice = $locales->fallbackNotice('en');

    expect($notice)->not->toBe('')
        ->and($notice)->toContain($locales->displayName('en'))
        ->and($notice)->not->toStartWith('lin-codex::')
        ->and(__('lin-codex::lin-codex.fallback_notice', ['language' => 'German']))->toContain('German');
});

it('picks the translation by the exact locale with the settings-driven fallback', function (array $languages, string $requested, array $available, FallbackBehaviour $fallback, ?string $expectedLocale, bool $expectedIsFallback): void {
    linCodexLocaleUseLanguages($languages, $fallback);

    $choice = app(LocaleResolver::class)->pick(linCodexLocaleArticle($available), $requested);

    if ($expectedLocale === null) {
        expect($choice)->toBeNull();

        return;
    }

    expect($choice)->toBeInstanceOf(TranslationChoice::class)
        ->and($choice->translation->locale)->toBe($expectedLocale)
        ->and($choice->translation->title)->toBe('Title '.$expectedLocale)
        ->and($choice->isFallback)->toBe($expectedIsFallback);
})->with([
    'exact match' => [['en', 'de'], 'de', ['en', 'de'], FallbackBehaviour::ShowDefault, 'de', false],
    'missing translation, show default' => [['en', 'de'], 'de', ['en'], FallbackBehaviour::ShowDefault, 'en', true],
    'missing translation, hide' => [['en', 'de'], 'de', ['en'], FallbackBehaviour::Hide, null, false],
    'locale outside the list, show default' => [['en'], 'de', ['en', 'de'], FallbackBehaviour::ShowDefault, 'en', true],
    'locale outside the list, hide' => [['en'], 'de', ['en', 'de'], FallbackBehaviour::Hide, null, false],
    'no default translation' => [['en', 'de'], 'en', ['de'], FallbackBehaviour::ShowDefault, null, false],
    'no language-part fallback, show default' => [['en', 'de'], 'de_DE', ['en', 'de'], FallbackBehaviour::ShowDefault, 'en', true],
    'no language-part fallback, hide' => [['en', 'de'], 'de_DE', ['de'], FallbackBehaviour::Hide, null, false],
    'default locale under hide' => [['en', 'de'], 'en', ['en'], FallbackBehaviour::Hide, 'en', false],
]);

it('returns a readonly choice that survives serialization', function (): void {
    $choice = app(LocaleResolver::class)->pick(linCodexLocaleArticle(['en']), 'en');

    expect($choice)->toBeInstanceOf(TranslationChoice::class)
        ->and(unserialize(serialize($choice)))->toEqual($choice);

    linCodexAssertNoModels($choice);
});
