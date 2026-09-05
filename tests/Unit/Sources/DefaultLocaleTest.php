<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\DefaultLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('reads the default locale from the seeded settings', function (): void {
    expect((new DefaultLocale)->get())->toBe('en');
});

it('follows a saved settings change', function (): void {
    $settings = app(CodexSettings::class);
    $settings->default_locale = 'de';
    $settings->save();

    expect((new DefaultLocale)->get())->toBe('de');
});

it('falls back to app.locale when the settings group is unseeded', function (): void {
    DB::table('settings')->where('group', 'lin-codex')->delete();
    app()->forgetInstance(CodexSettings::class);

    expect((new DefaultLocale)->get())->toBe(config('app.locale'))->toBe('en');
});

it('falls back to app.locale when the settings table is missing', function (): void {
    Schema::drop('settings');
    app()->forgetInstance(CodexSettings::class);

    expect((new DefaultLocale)->get())->toBe('en');
});

it('uses the configured app.locale as the fallback value', function (): void {
    config()->set('app.locale', 'hu');
    DB::table('settings')->where('group', 'lin-codex')->delete();
    app()->forgetInstance(CodexSettings::class);

    expect((new DefaultLocale)->get())->toBe('hu');
});
