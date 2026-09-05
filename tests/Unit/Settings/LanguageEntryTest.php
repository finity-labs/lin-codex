<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

it('belongs to the lin-codex settings group', function () {
    expect(CodexSettings::group())->toBe('lin-codex');
});

it('builds the English language entry with the gb flag', function () {
    $entry = CodexSettings::languageEntry('en');

    expect($entry)->toHaveKeys(['code', 'display', 'flag-icon'])
        ->and($entry['code'])->toBe('en')
        ->and($entry['flag-icon'])->toBe('gb')
        ->and($entry['display'])->toBeString()->not->toBeEmpty();

    if (extension_loaded('intl')) {
        expect($entry['display'])->toBe('English');
    }
});

it('builds a German language entry keyed by its own code', function () {
    $entry = CodexSettings::languageEntry('de');

    expect($entry['code'])->toBe('de')
        ->and($entry['flag-icon'])->toBe('de')
        ->and($entry['display'])->toBeString()->not->toBeEmpty();
});

it('capitalises display names that intl returns in lowercase', function () {
    $display = CodexSettings::languageEntry('hu')['display'];

    expect($display)->not->toBeEmpty()
        ->and(ctype_upper(substr($display, 0, 1)))->toBeTrue();
});

it('seeds one language and the default locale from app.locale', function () {
    config()->set('app.locale', 'en');

    expect(CodexSettings::defaults())->toBe([
        'languages' => [
            ['code' => 'en', 'display' => 'English', 'flag-icon' => 'gb'],
        ],
        'default_locale' => 'en',
        'fallback' => FallbackBehaviour::ShowDefault->value,
        'revisions_enabled' => false,
        'revisions_keep' => 10,
    ]);
})->skip(! extension_loaded('intl'), 'ext-intl is required for the exact English display name');

it('follows app.locale when it is not English', function () {
    config()->set('app.locale', 'de');

    $defaults = CodexSettings::defaults();

    expect($defaults['languages'])->toHaveCount(1)
        ->and($defaults['languages'][0]['code'])->toBe('de')
        ->and($defaults['languages'][0]['flag-icon'])->toBe('de')
        ->and($defaults['default_locale'])->toBe('de');
});

it('ships a settings migration seeding the defaults', function () {
    $migration = include __DIR__.'/../../../database/settings/create_codex_settings.php';

    expect($migration)->toBeInstanceOf(SettingsMigration::class);
});
