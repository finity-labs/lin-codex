<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\AiCallFailed;
use FinityLabs\LinCodex\Ai\LaravelAiClient;
use FinityLabs\LinCodex\Ai\StructuredRequest;
use FinityLabs\LinCodex\Ai\TranslationAgent;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

/** The three fields every translation call asks for. */
function linCodexSeamFields(): array
{
    return ['title' => 'The title', 'excerpt' => 'The excerpt', 'body' => 'The body'];
}

function linCodexSeamRequest(
    string $provider = 'anthropic',
    ?string $model = null,
    int $timeout = 120,
    ?string $apiKey = null,
): StructuredRequest {
    return new StructuredRequest(
        instructions: 'Be formal.',
        prompt: "Translate.\n<source_body>x</source_body>",
        fields: linCodexSeamFields(),
        provider: $provider,
        model: $model,
        timeout: $timeout,
        apiKey: $apiKey,
    );
}

/**
 * A text gateway that answers one truncated structured step.
 *
 * The SDK's own fake cannot express truncation: `FakeTextGateway` rebuilds
 * every faked answer into a step with `FinishReason::Stop`, and the response
 * object a fake closure returns is only read for its text, usage, meta and
 * structured payload, so assigning `$response->steps` on it has no effect.
 * Installing a gateway on the provider instead leaves the SDK's real
 * generation loop to build the response out of this step, which is exactly
 * the object the seam reads the flag from. Only usable while the agent is
 * NOT faked: a faked agent gets a clone of the provider carrying the fake
 * gateway.
 *
 * @param  array<string, string>  $fields
 */
function linCodexTruncatingGateway(array $fields): StepTextGateway
{
    return new class($fields) implements StepTextGateway
    {
        /** @param  array<string, string>  $fields */
        public function __construct(private array $fields) {}

        public function generateTextStep(
            TextProvider $provider,
            string $model,
            ?string $instructions,
            array $messages,
            array $tools,
            ?array $schema,
            ?TextGenerationOptions $options,
            ?int $timeout,
            StepContext $stepContext,
        ): StepResponse {
            return new StepResponse(
                (string) json_encode($this->fields),
                [],
                FinishReason::Length,
                new Usage(promptTokens: 10, completionTokens: 20),
                new Meta($provider->name(), $model),
                $this->fields,
            );
        }

        public function generateStreamStep(
            string $invocationId,
            TextProvider $provider,
            string $model,
            ?string $instructions,
            array $messages,
            array $tools,
            ?array $schema,
            ?TextGenerationOptions $options,
            ?int $timeout,
            StepContext $stepContext,
        ): Generator {
            throw new RuntimeException('The truncating gateway does not stream.');
            yield;
        }
    };
}

function linCodexSeamHttpException(int $status): RequestException
{
    return new RequestException(new Response(new PsrResponse($status)));
}

it('answers unavailable everywhere without the SDK', function (): void {
    $client = app(LaravelAiClient::class);

    expect($client->installed())->toBeFalse()
        ->and($client->providers())->toBe([])
        ->and($client->testConnection('anthropic', null, 'sk'))->toBe('unavailable');

    expect(fn () => $client->tierModels('anthropic'))
        ->toThrow(AiCallFailed::class, 'AI call failed: unavailable');

    expect(fn () => $client->structured(new StructuredRequest('i', 'p', ['title' => 'd'], 'anthropic', null, 30, null)))
        ->toThrow(AiCallFailed::class, 'AI call failed: unavailable');
})->skip(fn (): bool => class_exists('Laravel\\Ai\\AnonymousAgent'), 'laravel/ai is installed');

it('maps :dataset to a reason key', function (Closure $throwable, string $reason): void {
    expect(app(LaravelAiClient::class)->reason($throwable()))->toBe($reason);
})->with([
    'a bare connection exception' => [fn (): Throwable => new ConnectionException('cURL error 28: timed out'), 'timeout'],
    'HTTP 401' => [fn (): Throwable => linCodexSeamHttpException(401), 'authentication_failed'],
    'HTTP 403' => [fn (): Throwable => linCodexSeamHttpException(403), 'authentication_failed'],
    'HTTP 429' => [fn (): Throwable => linCodexSeamHttpException(429), 'rate_limited'],
    'HTTP 402' => [fn (): Throwable => linCodexSeamHttpException(402), 'quota_exceeded'],
    'HTTP 503' => [fn (): Throwable => linCodexSeamHttpException(503), 'unavailable'],
    'HTTP 400' => [fn (): Throwable => linCodexSeamHttpException(400), 'unknown'],
    'no provider configured' => [fn (): Throwable => new RuntimeException('No AI providers were configured.'), 'unavailable'],
    'an invalid argument' => [fn (): Throwable => new InvalidArgumentException('x'), 'unavailable'],
    'a logic error' => [fn (): Throwable => new LogicException('x'), 'unavailable'],
    'an unrelated runtime error' => [fn (): Throwable => new RuntimeException('boom'), 'unknown'],
    'a failure that already carries a reason' => [fn (): Throwable => new AiCallFailed('rate_limited'), 'rate_limited'],
]);

it('reports the throwable behind an unknown reason and nothing else', function (): void {
    Exceptions::fake();

    $client = app(LaravelAiClient::class);

    expect($client->reason(new RuntimeException('boom')))->toBe('unknown');
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'boom');

    expect($client->reason(linCodexSeamHttpException(400)))->toBe('unknown');
    Exceptions::assertReported(RequestException::class);

    Exceptions::fake();

    expect($client->reason(new AiCallFailed('rate_limited')))->toBe('rate_limited')
        ->and($client->reason(linCodexSeamHttpException(401)))->toBe('authentication_failed')
        ->and($client->reason(linCodexSeamHttpException(503)))->toBe('unavailable')
        ->and($client->reason(new ConnectionException('timed out')))->toBe('timeout')
        ->and($client->reason(new LogicException('x')))->toBe('unavailable');

    Exceptions::assertNothingReported();
});

describe('with the SDK', function (): void {
    beforeEach(function (): void {
        if (! class_exists('Laravel\\Ai\\AnonymousAgent')) {
            $this->markTestSkipped('laravel/ai is not installed');
        }

        config(['ai.providers.anthropic.key' => null]);
    });

    it('lists the offered providers with the Lab case names', function (): void {
        expect(app(LaravelAiClient::class)->providers())->toBe([
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
    });

    it('reads the tier models from the provider', function (): void {
        $provider = Ai::textProvider('openai');

        expect(app(LaravelAiClient::class)->tierModels('openai'))->toBe([
            'default' => $provider->defaultTextModel(),
            'cheapest' => $provider->cheapestTextModel(),
            'smartest' => $provider->smartestTextModel(),
        ]);

        expect(fn () => app(LaravelAiClient::class)->tierModels('azure'))
            ->toThrow(AiCallFailed::class, 'AI call failed: unavailable');
    });

    it('injects the stored key for one call and restores the config', function (): void {
        $seen = [];

        TranslationAgent::fake(function (string $prompt, $attachments, $provider, string $model) use (&$seen): array {
            $seen = [
                'key' => $provider->providerCredentials()['key'],
                'provider' => $provider->name(),
                'model' => $model,
            ];

            return ['title' => 'Titel', 'excerpt' => '', 'body' => 'Text'];
        })->preventStrayPrompts();

        $completion = app(LaravelAiClient::class)->structured(
            linCodexSeamRequest(model: 'claude-haiku-4-5-20251001', apiKey: 'sk-stored'),
        );

        expect($seen)->toBe([
            'key' => 'sk-stored',
            'provider' => 'anthropic',
            'model' => 'claude-haiku-4-5-20251001',
        ])
            ->and(config('ai.providers.anthropic.key'))->toBeNull()
            ->and($completion->fields)->toBe(['title' => 'Titel', 'excerpt' => '', 'body' => 'Text'])
            ->and($completion->truncated)->toBeFalse();

        TranslationAgent::assertPrompted(fn (AgentPrompt $p): bool => $p->timeout === 120
            && str_contains($p->prompt, '<source_body>')
            && $p->agent->instructions() === 'Be formal.'
            && $p->agent->maxTokens() === 16000);
        TranslationAgent::assertPromptedTimes(1);
    });

    it('uses the SDK env key when no key is stored', function (): void {
        config(['ai.providers.anthropic.key' => 'sk-env']);

        $seen = null;

        TranslationAgent::fake(function (string $prompt, $attachments, $provider, string $model) use (&$seen): array {
            $seen = $provider->providerCredentials()['key'];

            return ['title' => 'Titel', 'excerpt' => '', 'body' => 'Text'];
        })->preventStrayPrompts();

        app(LaravelAiClient::class)->structured(linCodexSeamRequest(model: 'claude-haiku-4-5-20251001'));

        expect($seen)->toBe('sk-env')
            ->and(config('ai.providers.anthropic.key'))->toBe('sk-env');
    });

    it('reads the usage of an answer that finished', function (): void {
        TranslationAgent::fake(function (string $prompt, $attachments, $provider, string $model): StructuredTextResponse {
            $fields = ['title' => 'Titel', 'excerpt' => '', 'body' => 'Text'];

            return new StructuredTextResponse(
                $fields,
                (string) json_encode($fields),
                new Usage(promptTokens: 120, completionTokens: 80),
                new Meta($provider->name(), $model),
            );
        })->preventStrayPrompts();

        $completion = app(LaravelAiClient::class)->structured(linCodexSeamRequest(model: 'claude-haiku-4-5-20251001'));

        expect($completion->promptTokens)->toBe(120)
            ->and($completion->completionTokens)->toBe(80)
            ->and($completion->truncated)->toBeFalse();
    });

    it('flags an answer that stopped at the output ceiling', function (): void {
        $fields = ['title' => 'Titel', 'excerpt' => '', 'body' => 'Halb'];

        /*
         * No TranslationAgent::fake() here on purpose: the gateway goes on the
         * provider itself (see linCodexTruncatingGateway()), so the SDK's own
         * generation loop builds the response and the seam reads the finish
         * reason the loop recorded, not one this test wrote onto a response.
         */
        Ai::textProvider('anthropic')->useTextGateway(linCodexTruncatingGateway($fields));

        $completion = app(LaravelAiClient::class)->structured(linCodexSeamRequest(model: 'claude-haiku-4-5-20251001'));

        expect($completion->truncated)->toBeTrue()
            ->and($completion->fields)->toBe($fields)
            ->and($completion->promptTokens)->toBe(10)
            ->and($completion->completionTokens)->toBe(20);
    });

    it('honours lin-codex.ai.max_tokens', function (): void {
        config(['lin-codex.ai.max_tokens' => 4000]);

        TranslationAgent::fake(fn (): array => ['title' => 'Titel', 'excerpt' => '', 'body' => 'Text'])
            ->preventStrayPrompts();

        app(LaravelAiClient::class)->structured(linCodexSeamRequest(model: 'claude-haiku-4-5-20251001'));

        TranslationAgent::assertPrompted(fn (AgentPrompt $p): bool => $p->agent->maxTokens() === 4000);
    });

    it('maps :dataset thrown by the SDK to a reason key', function (Closure $throw, string $reason): void {
        config(['ai.providers.anthropic.key' => null]);

        TranslationAgent::fake($throw)->preventStrayPrompts();

        try {
            app(LaravelAiClient::class)->structured(
                linCodexSeamRequest(model: 'claude-haiku-4-5-20251001', apiKey: 'sk-stored'),
            );
            $this->fail('the SDK exception did not surface');
        } catch (AiCallFailed $e) {
            expect($e->reason)->toBe($reason)
                ->and($e->getPrevious())->not->toBeNull()
                ->and(config('ai.providers.anthropic.key'))->toBeNull();
        }
    })->with([
        'a rate limit' => [fn (): Closure => fn () => throw RateLimitedException::forProvider('anthropic'), 'rate_limited'],
        'an empty credit balance' => [fn (): Closure => fn () => throw InsufficientCreditsException::forProvider('anthropic'), 'quota_exceeded'],
        'an overloaded provider' => [fn (): Closure => fn () => throw ProviderOverloadedException::forProvider('anthropic'), 'unavailable'],
        'a connection that timed out' => [fn (): Closure => fn () => throw ProviderConnectionException::forProvider('anthropic', 0, new ConnectionException('cURL error 28: Operation timed out')), 'timeout'],
        'a refused connection' => [fn (): Closure => fn () => throw ProviderConnectionException::forProvider('anthropic', 0, new ConnectionException('Connection refused')), 'unavailable'],
    ]);

    it('refuses a provider that is not offered before prompting', function (): void {
        TranslationAgent::fake(fn (): array => ['title' => 'Titel'])->preventStrayPrompts();

        expect(fn () => app(LaravelAiClient::class)->structured(linCodexSeamRequest(provider: 'azure')))
            ->toThrow(AiCallFailed::class, 'AI call failed: unavailable');

        TranslationAgent::assertPromptedTimes(0);
    });

    it('tests the connection through the anonymous agent', function (): void {
        AnonymousAgent::fake(['OK']);

        expect(app(LaravelAiClient::class)->testConnection('anthropic', null, 'sk-stored'))->toBeNull();

        AnonymousAgent::assertPrompted(fn (AgentPrompt $p): bool => $p->prompt === 'Reply with the single word OK.'
            && $p->timeout === 10);

        AnonymousAgent::fake(fn () => throw RateLimitedException::forProvider('anthropic'));

        expect(app(LaravelAiClient::class)->testConnection('anthropic', null, 'sk-stored'))->toBe('rate_limited');
    });
});
