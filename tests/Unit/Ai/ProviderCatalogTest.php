<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\ProviderCatalog;

it('offers the nine key-only providers in display order', function (): void {
    expect(ProviderCatalog::OFFERED)->toBe([
        'anthropic',
        'openai',
        'gemini',
        'mistral',
        'groq',
        'deepseek',
        'xai',
        'openrouter',
        'ollama',
    ])->and(ProviderCatalog::KEYLESS)->toBe(['ollama']);
});

it('knows which providers are offered and which need no key', function (): void {
    expect(ProviderCatalog::isOffered('azure'))->toBeFalse()
        ->and(ProviderCatalog::isOffered('bedrock'))->toBeFalse()
        ->and(ProviderCatalog::isOffered('xai'))->toBeTrue()
        ->and(ProviderCatalog::isKeyless('ollama'))->toBeTrue()
        ->and(ProviderCatalog::isKeyless('anthropic'))->toBeFalse();
});

it('reads the SDK env key of a keyed provider', function (): void {
    config(['ai.providers.anthropic.key' => null]);

    expect(ProviderCatalog::envConfigured('anthropic'))->toBeFalse();

    config(['ai.providers.anthropic.key' => '']);

    expect(ProviderCatalog::envConfigured('anthropic'))->toBeFalse();

    config(['ai.providers.anthropic.key' => 'sk-env']);

    expect(ProviderCatalog::envConfigured('anthropic'))->toBeTrue();
});

it('reads the SDK url of a keyless provider', function (): void {
    config(['ai.providers.ollama.url' => '', 'ai.providers.ollama.key' => 'sk-env']);

    expect(ProviderCatalog::envConfigured('ollama'))->toBeFalse();

    config(['ai.providers.ollama.url' => 'http://localhost:11434']);

    expect(ProviderCatalog::envConfigured('ollama'))->toBeTrue();
});

it('has no labels without the SDK', function (): void {
    expect(ProviderCatalog::labels())->toBe([]);
})->skip(fn (): bool => class_exists('Laravel\\Ai\\AnonymousAgent'), 'laravel/ai is installed');

it('labels the offered providers with the Lab case names', function (): void {
    expect(ProviderCatalog::labels())->toBe([
        'anthropic' => 'Anthropic',
        'openai' => 'OpenAI',
        'gemini' => 'Gemini',
        'mistral' => 'Mistral',
        'groq' => 'Groq',
        'deepseek' => 'DeepSeek',
        'xai' => 'xAI',
        'openrouter' => 'OpenRouter',
        'ollama' => 'Ollama',
    ]);
})->skip(fn (): bool => ! class_exists('Laravel\\Ai\\AnonymousAgent'), 'laravel/ai is not installed');
