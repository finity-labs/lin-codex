<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

it('throws MissingSettings when the group has not been seeded', function () {
    DB::table('settings')->where('group', 'lin-codex')->delete();

    expect(fn () => app(CodexSettings::class)->languages)->toThrow(MissingSettings::class);
});
