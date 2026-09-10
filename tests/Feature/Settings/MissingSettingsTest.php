<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexAiSettings;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

it('throws MissingSettings when the group has not been seeded', function () {
    DB::table('settings')->where('group', 'lin-codex')->delete();

    expect(fn () => app(CodexSettings::class)->languages)->toThrow(MissingSettings::class);
});

it('throws MissingSettings for the AI group when it has not been seeded and leaves the lin-codex group readable', function () {
    DB::table('settings')->where('group', 'lin-codex-ai')->delete();

    expect(fn () => app(CodexAiSettings::class)->enabled)->toThrow(MissingSettings::class)
        ->and(app(CodexSettings::class)->default_locale)->toBe('en');
});
