<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\AiAvailabilityCheck;
use FinityLabs\LinCodex\Ai\Contracts\AiClient;
use FinityLabs\LinCodex\Ai\LaravelAiClient;
use FinityLabs\LinCodex\Tests\Fixtures\FakeAiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bind a fake seam and resolve the check through it. The check is a plain
 * autowired class, so it picks up whatever instance is bound at this moment.
 */
function linCodexAiCheck(bool $installed = true): AiAvailabilityCheck
{
    app()->instance(AiClient::class, new FakeAiClient(installed: $installed));

    return app(AiAvailabilityCheck::class);
}

it('reports the SDK missing before anything else', function (): void {
    $this->enableAi();

    $availability = linCodexAiCheck(installed: false)->check();

    expect($availability->available)->toBeFalse()
        ->and($availability->reason)->toBe('sdk_missing')
        ->and($availability->label())->not->toBe('')
        ->and($availability->label())->not->toBe('sdk_missing')
        ->and($availability->label())->not->toContain('lin-codex::');
});

it('reports the AI settings as not migrated when the group is unseeded', function (): void {
    DB::table('settings')->where('group', 'lin-codex-ai')->delete();

    expect(linCodexAiCheck()->check()->reason)->toBe('not_migrated');
});

it('reports not migrated when the settings table is missing', function (): void {
    Schema::drop('settings');

    expect(linCodexAiCheck()->check()->reason)->toBe('not_migrated');
});

it('reports disabled on a fresh seed', function (): void {
    expect(linCodexAiCheck()->check()->reason)->toBe('disabled');
});

it('reports no key when no provider is chosen', function (): void {
    $this->enableAi(['provider' => null]);

    expect(linCodexAiCheck()->check()->reason)->toBe('no_key');

    $this->enableAi(['provider' => 'azure']);

    expect(linCodexAiCheck()->check()->reason)->toBe('no_key');
});

it('reports no key when neither a stored key nor the env key exists', function (): void {
    $this->enableAi(['api_key' => null]);
    config(['ai.providers.anthropic.key' => null]);

    expect(linCodexAiCheck()->check()->reason)->toBe('no_key');
});

it('is available with a stored key', function (): void {
    $this->enableAi();

    $availability = linCodexAiCheck()->check();

    expect($availability->available)->toBeTrue()
        ->and($availability->reason)->toBeNull()
        ->and($availability->label())->toBe('');
});

it('is available with the SDK env key alone', function (): void {
    $this->enableAi(['api_key' => null]);
    config(['ai.providers.anthropic.key' => 'sk-env']);

    expect(linCodexAiCheck()->check()->available)->toBeTrue();
});

it('treats Ollama as keyless and needs its URL', function (): void {
    $this->enableAi(['provider' => 'ollama', 'api_key' => null]);
    config(['ai.providers.ollama.url' => null, 'ai.providers.ollama.key' => '']);

    expect(linCodexAiCheck()->check()->reason)->toBe('no_key');

    config(['ai.providers.ollama.url' => 'http://localhost:11434']);

    expect(linCodexAiCheck()->check()->available)->toBeTrue();
});

it('binds the real seam as a singleton', function (): void {
    expect(app(AiClient::class))->toBeInstanceOf(LaravelAiClient::class)
        ->and(app(AiClient::class))->toBe(app(AiClient::class));
});

it('reports the SDK missing through the real seam', function (): void {
    $this->enableAi();

    expect(app(AiAvailabilityCheck::class)->check()->reason)->toBe('sdk_missing');
})->skip(fn (): bool => class_exists('Laravel\\Ai\\AnonymousAgent'), 'laravel/ai is installed');

it('mirrors check() in available()', function (): void {
    expect(linCodexAiCheck()->available())->toBeFalse();

    $this->enableAi();

    expect(linCodexAiCheck()->available())->toBeTrue();
});
