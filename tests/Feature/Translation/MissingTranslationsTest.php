<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Translation\MissingTranslations;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $codes
 */
function linCodexMissingUseLanguages(array $codes, string $default = 'en'): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = $default;
    $settings->save();
}

/**
 * @param  array<string, array<string, mixed>>  $translations  locale => attributes
 */
function linCodexMissingArticle(array $translations): Article
{
    $factory = Article::factory();

    foreach ($translations as $locale => $attributes) {
        $factory = $factory->withTranslation($locale, $attributes);
    }

    return $factory->create();
}

beforeEach(function (): void {
    linCodexMissingUseLanguages(['en', 'de', 'hu']);
});

it('lists the configured non-default locales an article has no row for', function (): void {
    $article = linCodexMissingArticle(['en' => ['title' => 'T', 'body' => 'B']]);

    $missing = app(MissingTranslations::class);

    expect($missing->candidates())->toBe(['de', 'hu'])
        ->and($missing->for($article))->toBe(['de', 'hu']);
});

it('counts a row with an empty or blank title or body as missing', function (): void {
    $missing = app(MissingTranslations::class);

    $emptyTitle = linCodexMissingArticle([
        'en' => ['title' => 'T', 'body' => 'B'],
        'de' => ['title' => '', 'body' => 'x'],
    ]);

    $blankBody = linCodexMissingArticle([
        'en' => ['title' => 'T', 'body' => 'B'],
        'de' => ['title' => 'x', 'body' => "  \n"],
    ]);

    $filled = linCodexMissingArticle([
        'en' => ['title' => 'T', 'body' => 'B'],
        'de' => ['title' => 'x', 'body' => 'y'],
    ]);

    expect($missing->isMissing($emptyTitle, 'de'))->toBeTrue()
        ->and($missing->isMissing($blankBody, 'de'))->toBeTrue()
        ->and($missing->isMissing($filled, 'de'))->toBeFalse()
        ->and($missing->for($filled))->toBe(['hu']);
});

it('never lists the default locale even when its row is empty', function (): void {
    $article = linCodexMissingArticle(['en' => ['title' => 'T', 'body' => '']]);

    $missing = app(MissingTranslations::class);

    expect($missing->for($article))->toBe(['de', 'hu'])
        ->and($missing->isMissing($article, 'en'))->toBeFalse();
});

it('answers isMissing per locale and false for an unconfigured one', function (): void {
    $article = linCodexMissingArticle(['en' => ['title' => 'T', 'body' => 'B']]);

    $missing = app(MissingTranslations::class);

    expect($missing->isMissing($article, 'de'))->toBeTrue()
        ->and($missing->isMissing($article, 'hu'))->toBeTrue()
        ->and($missing->isMissing($article, 'fr'))->toBeFalse();
});

it('reads the loaded relation without a query', function (): void {
    $article = linCodexMissingArticle([
        'en' => ['title' => 'T', 'body' => 'B'],
        'de' => ['title' => 'D', 'body' => 'B'],
    ]);

    $missing = app(MissingTranslations::class);
    $missing->candidates();

    $article->load('translations');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $result = $missing->for($article);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $table = $article->translations()->getModel()->getTable();

    // LocaleResolver reads the settings group on every call by design, so the
    // settings table is queried here; what a loaded relation must prevent is
    // a query against the translations table, once per article in a bulk run.
    $translationQueries = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains((string) $query['query'], $table),
    ));

    expect($result)->toBe(['hu'])
        ->and($translationQueries)->toBe([]);
});

it('follows the settings order', function (): void {
    linCodexMissingUseLanguages(['en', 'hu', 'de']);

    $article = linCodexMissingArticle(['en' => ['title' => 'T', 'body' => 'B']]);

    expect(app(MissingTranslations::class)->for($article))->toBe(['hu', 'de']);
});
