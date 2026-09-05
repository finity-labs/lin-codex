<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\RevisionReason;
use Illuminate\Support\Arr;

/** @return array<string, mixed> The raw lang array of one locale. */
function linCodexLang(string $locale): array
{
    return require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/lin-codex.php';
}

/** @return list<string> Sorted dotted key paths of one locale's lang file. */
function linCodexLangKeys(string $locale): array
{
    $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys = is_array($value) ? [...$keys, ...$flatten($value, $path)] : [...$keys, $path];
        }

        sort($keys);

        return $keys;
    };

    return $flatten(linCodexLang($locale));
}

it('ships the same lang keys in en, de and hu', function (string $locale): void {
    expect(array_keys(linCodexLang($locale)))->toBe(array_keys(linCodexLang('en')))
        ->and(linCodexLangKeys($locale))->toBe(linCodexLangKeys('en'))
        ->and(linCodexLangKeys('en'))->not->toBeEmpty();
})->with(['de', 'hu']);

it('loads the package translations in every locale', function (): void {
    expect(__('lin-codex::lin-codex.ui.help'))->toBe('Help')
        ->and(RevisionReason::Restore->label())->toBe('Restore');

    app()->setLocale('de');

    expect(__('lin-codex::lin-codex.ui.help'))->toBe('Hilfe')
        ->and(RevisionReason::Restore->label())->toBe('Wiederherstellung');

    app()->setLocale('hu');

    expect(__('lin-codex::lin-codex.ui.help'))->toBe('Súgó')
        ->and(RevisionReason::Restore->label())->toBe('Visszaállítás');
});

it('keeps every placeholder in every locale', function (string $key, array $placeholders): void {
    $english = Arr::get(linCodexLang('en'), $key);

    expect($english)->toBeString();

    foreach ($placeholders as $placeholder) {
        expect(str_contains($english, $placeholder))->toBeTrue("en {$key} lacks {$placeholder}");
    }

    foreach (['de', 'hu'] as $locale) {
        $value = Arr::get(linCodexLang($locale), $key);

        expect($value)->toBeString();

        foreach ($placeholders as $placeholder) {
            expect(str_contains($value, $placeholder))->toBeTrue("{$locale} {$key} lacks {$placeholder}");
        }
    }
})->with([
    'ui.rate_limited' => ['ui.rate_limited', [':seconds']],
    'ui.back_to_app' => ['ui.back_to_app', [':app']],
    'ui.shortcut_hint' => ['ui.shortcut_hint', [':shortcut']],
    'fallback_notice' => ['fallback_notice', [':language']],
    'anchor_label' => ['anchor_label', [':heading']],
    'source_warnings.invalid_context' => ['source_warnings.invalid_context', [':path', ':detail']],
]);
