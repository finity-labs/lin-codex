<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

function codexSettingsMigration(): SettingsMigration
{
    return include dirname(__DIR__, 3).'/database/settings/create_codex_settings.php';
}

it('resolves the seeded defaults immediately after migrating', function () {
    $settings = app(CodexSettings::class);

    expect($settings->default_locale)->toBe('en')
        ->and($settings->fallback)->toBe(FallbackBehaviour::ShowDefault)
        ->and($settings->revisions_enabled)->toBeFalse()
        ->and($settings->revisions_keep)->toBe(10)
        ->and($settings->languages)->toHaveCount(1)
        ->and($settings->languages[0]['code'])->toBe('en')
        ->and($settings->languages[0]['flag-icon'])->toBe('gb');

    if (extension_loaded('intl')) {
        expect($settings->languages)->toBe([['code' => 'en', 'display' => 'English', 'flag-icon' => 'gb']]);
    } else {
        expect($settings->languages[0]['display'])->not->toBeEmpty();
    }
});

it('seeds exactly five rows in the lin-codex group', function () {
    expect(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(5);
});

it('runs the settings migration idempotently', function () {
    codexSettingsMigration()->up();

    expect(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(5)
        ->and(app(CodexSettings::class)->revisions_keep)->toBe(10);
});

it('removes and restores the rows on down and up', function () {
    $migration = codexSettingsMigration();

    $migration->down();

    expect(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(0);

    $migration->up();

    expect(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(5)
        ->and(app(CodexSettings::class)->fallback)->toBe(FallbackBehaviour::ShowDefault);
});

it('persists writes through the settings object', function () {
    $settings = app(CodexSettings::class);
    $settings->revisions_keep = 25;
    $settings->save();

    expect(app(CodexSettings::class)->revisions_keep)->toBe(25);
});
