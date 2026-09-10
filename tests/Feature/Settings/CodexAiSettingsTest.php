<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexAiSettings;
use FinityLabs\LinCodex\Translation\DefaultInstructions;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

function linCodexAiSettingsMigration(): SettingsMigration
{
    return include dirname(__DIR__, 3).'/database/settings/create_codex_ai_settings.php';
}

it('resolves the seeded AI defaults immediately after migrating', function (): void {
    $settings = app(CodexAiSettings::class);

    expect($settings->enabled)->toBeFalse()
        ->and($settings->provider)->toBeNull()
        ->and($settings->model)->toBeNull()
        ->and($settings->api_key)->toBeNull()
        ->and($settings->timeout)->toBe(120)
        ->and($settings->translation_instructions)->toBe(DefaultInstructions::TEXT);
});

it('seeds exactly six rows in the lin-codex-ai group and leaves lin-codex at five', function (): void {
    expect(DB::table('settings')->where('group', 'lin-codex-ai')->count())->toBe(6)
        ->and(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(5);
});

it('runs the AI settings migration idempotently', function (): void {
    $settings = app(CodexAiSettings::class);
    $settings->timeout = 30;
    $settings->save();

    linCodexAiSettingsMigration()->up();

    expect(DB::table('settings')->where('group', 'lin-codex-ai')->count())->toBe(6)
        ->and(app(CodexAiSettings::class)->timeout)->toBe(30);
});

it('removes and restores the AI rows on down and up', function (): void {
    $migration = linCodexAiSettingsMigration();

    $migration->down();

    expect(DB::table('settings')->where('group', 'lin-codex-ai')->count())->toBe(0)
        ->and(DB::table('settings')->where('group', 'lin-codex')->count())->toBe(5);

    $migration->up();

    expect(DB::table('settings')->where('group', 'lin-codex-ai')->count())->toBe(6)
        ->and(app(CodexAiSettings::class)->timeout)->toBe(120);
});

it('encrypts the API key at rest and decrypts it on read', function (): void {
    $settings = app(CodexAiSettings::class);
    $settings->api_key = 'sk-test-123';
    $settings->save();

    $payload = DB::table('settings')->where('group', 'lin-codex-ai')->where('name', 'api_key')->value('payload');

    expect($payload)->toBeString()
        ->and($payload)->not->toBeEmpty()
        ->and($payload)->not->toContain('sk-test-123')
        ->and(app(CodexAiSettings::class)->api_key)->toBe('sk-test-123');

    $cleared = app(CodexAiSettings::class);
    $cleared->api_key = null;
    $cleared->save();

    expect(app(CodexAiSettings::class)->api_key)->toBeNull();
});

it('seeds the package default instructions naming the formal register', function (): void {
    expect(DefaultInstructions::TEXT)->toContain('Sie')
        ->and(DefaultInstructions::TEXT)->toContain('Ön')
        ->and(DefaultInstructions::TEXT)->toContain('formal')
        ->and(DefaultInstructions::TEXT)->toContain('terminology')
        ->and(DefaultInstructions::TEXT)->toContain('Do not add');
});

it('persists writes through the settings object', function (): void {
    $settings = app(CodexAiSettings::class);
    $settings->enabled = true;
    $settings->provider = 'anthropic';
    $settings->model = 'claude-haiku-4-5-20251001';
    $settings->save();

    $fresh = app(CodexAiSettings::class);

    expect($fresh->enabled)->toBeTrue()
        ->and($fresh->provider)->toBe('anthropic')
        ->and($fresh->model)->toBe('claude-haiku-4-5-20251001');
});
