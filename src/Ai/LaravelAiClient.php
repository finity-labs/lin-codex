<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai;

use BackedEnum;
use Closure;
use Composer\InstalledVersions;
use FinityLabs\LinCodex\Ai\Contracts\AiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * The single place lin-codex talks to the laravel/ai SDK.
 *
 * Every SDK symbol is a string returned by a method declared `: string`, so
 * PHPStan types the results `mixed` and analyses this file cleanly whether
 * the SDK is installed or not, and a host without it never autoloads an SDK
 * class. `TranslationAgent` is the one exception, and it says why in its own
 * docblock.
 *
 * Key precedence: a key stored in the settings is injected into the SDK
 * config for exactly one call and restored in a `finally`; with no stored key
 * the SDK's own env key is used untouched.
 *
 * `structured()` throws and `testConnection()` returns, on purpose. The
 * translator wants an exception it can map to a per-locale reason; the
 * settings page wants a reason key it can render next to the key field.
 */
final class LaravelAiClient implements AiClient
{
    public function installed(): bool
    {
        return InstalledVersions::isInstalled('laravel/ai') && function_exists($this->agentFunction());
    }

    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        return $this->installed() ? ProviderCatalog::labels() : [];
    }

    /**
     * @return array{default: string, cheapest: string, smartest: string}
     */
    public function tierModels(string $provider): array
    {
        $this->ensureProvider($provider);

        $facade = $this->aiFacade();

        try {
            $instance = $facade::textProvider($provider);

            return [
                'default' => (string) $instance->defaultTextModel(),
                'cheapest' => (string) $instance->cheapestTextModel(),
                'smartest' => (string) $instance->smartestTextModel(),
            ];
        } catch (Throwable $e) {
            throw new AiCallFailed($this->reason($e), $e);
        }
    }

    public function structured(StructuredRequest $request): StructuredCompletion
    {
        $this->ensureProvider($request->provider);

        $fields = $request->fields;

        $agent = app()->make($this->agentClass(), [
            'instructions' => $request->instructions,
            'messages' => [],
            'tools' => [],
            'schema' => static fn ($schema): array => array_map(
                static fn (string $description) => $schema->string()->required()->description($description),
                $fields,
            ),
        ]);

        try {
            $response = $this->withProviderKey(
                $request->provider,
                $request->apiKey,
                fn () => $agent->prompt(
                    $request->prompt,
                    provider: $request->provider,
                    model: $request->model,
                    timeout: $request->timeout,
                ),
            );
        } catch (Throwable $e) {
            throw new AiCallFailed($this->reason($e), $e);
        }

        return $this->completion($response);
    }

    public function testConnection(string $provider, ?string $model, ?string $apiKey): ?string
    {
        try {
            $this->ensureProvider($provider);

            $agentFunction = $this->agentFunction();

            $this->withProviderKey($provider, $apiKey, static fn () => $agentFunction('', [], [])->prompt(
                'Reply with the single word OK.',
                provider: $provider,
                model: $model,
                timeout: 10,
            ));

            return null;
        } catch (Throwable $e) {
            return $this->reason($e);
        }
    }

    /**
     * The reason key for a throwable, whether it came from the SDK, from the
     * HTTP client underneath it or from this class. An unknown verdict is
     * reported through report() before it is returned, so Test connection,
     * the tier lookup and a translation all surface the same original
     * exception once.
     */
    public function reason(Throwable $e): string
    {
        if ($e instanceof AiCallFailed) {
            return $e->reason;
        }

        if ($e instanceof ConnectionException) {
            return AiReason::TIMEOUT;
        }

        $providerConnection = $this->providerConnectionExceptionClass();

        if ($e instanceof $providerConnection) {
            $previous = $e->getPrevious();

            return $previous instanceof ConnectionException && preg_match('/timed out|timeout|cURL error 28/i', $previous->getMessage()) === 1
                ? AiReason::TIMEOUT
                : AiReason::UNAVAILABLE;
        }

        $overloaded = $this->providerOverloadedExceptionClass();

        if ($e instanceof $overloaded) {
            return AiReason::UNAVAILABLE;
        }

        $rateLimited = $this->rateLimitedExceptionClass();

        if ($e instanceof $rateLimited) {
            return AiReason::RATE_LIMITED;
        }

        $insufficientCredits = $this->insufficientCreditsExceptionClass();

        if ($e instanceof $insufficientCredits) {
            return AiReason::QUOTA_EXCEEDED;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return match (true) {
                $status === 401, $status === 403 => AiReason::AUTHENTICATION_FAILED,
                $status === 429 => AiReason::RATE_LIMITED,
                $status === 402 => AiReason::QUOTA_EXCEEDED,
                $status >= 500 => AiReason::UNAVAILABLE,
                default => $this->unknown($e),
            };
        }

        if ($e instanceof RuntimeException && str_contains($e->getMessage(), 'No AI providers')) {
            return AiReason::UNAVAILABLE;
        }

        /* InvalidArgumentException extends LogicException; both mean the SDK could not resolve a provider. */
        if ($e instanceof LogicException) {
            return AiReason::UNAVAILABLE;
        }

        return $this->unknown($e);
    }

    /**
     * The one reason with no name: hand the throwable to the host's error
     * tooling on the way out, so the stack is seen exactly once - here, and
     * never again by a caller that only sees the reason key.
     */
    private function unknown(Throwable $e): string
    {
        report($e);

        return AiReason::UNKNOWN;
    }

    /**
     * The SDK is there, the provider is one lin-codex offers, and the
     * installed Lab enum knows it. `tryFrom`, never `from`: a stored value
     * that a later SDK dropped must read as unavailable, not raise a
     * ValueError.
     */
    private function ensureProvider(string $provider): void
    {
        if (! $this->installed() || ! ProviderCatalog::isOffered($provider)) {
            throw new AiCallFailed(AiReason::UNAVAILABLE);
        }

        $lab = $this->labClass();

        if (! is_subclass_of($lab, BackedEnum::class) || $lab::tryFrom($provider) === null) {
            throw new AiCallFailed(AiReason::UNAVAILABLE);
        }
    }

    /**
     * Inject the stored key for one call, then restore the config either way.
     * `forgetInstance()` drops the cached provider so the next call re-reads
     * the key.
     */
    private function withProviderKey(string $provider, ?string $key, Closure $call): mixed
    {
        if ($key === null || $key === '') {
            return $call();
        }

        $configKey = "ai.providers.{$provider}.key";
        $previous = config($configKey);
        $facade = $this->aiFacade();

        config([$configKey => $key]);
        $facade::forgetInstance($provider);

        try {
            return $call();
        } finally {
            config([$configKey => $previous]);
            $facade::forgetInstance($provider);
        }
    }

    /**
     * Read the response without naming an SDK type. A payload the provider
     * could not encode decodes to an empty array rather than throwing, and
     * the caller decides what that means.
     */
    private function completion(mixed $response): StructuredCompletion
    {
        $steps = is_object($response) ? ($response->steps ?? null) : null;
        $last = is_object($steps) && method_exists($steps, 'last') ? $steps->last() : null;

        return new StructuredCompletion(
            fields: (array) $response->toArray(),
            promptTokens: (int) ($response->usage->promptTokens ?? 0),
            completionTokens: (int) ($response->usage->completionTokens ?? 0),
            truncated: is_object($last) && ($last->finishReason->value ?? null) === 'length',
        );
    }

    private function agentClass(): string
    {
        return TranslationAgent::class;
    }

    private function agentFunction(): string
    {
        return 'Laravel\\Ai\\agent';
    }

    private function aiFacade(): string
    {
        return 'Laravel\\Ai\\Ai';
    }

    private function labClass(): string
    {
        return 'Laravel\\Ai\\Enums\\Lab';
    }

    private function rateLimitedExceptionClass(): string
    {
        return 'Laravel\\Ai\\Exceptions\\RateLimitedException';
    }

    private function insufficientCreditsExceptionClass(): string
    {
        return 'Laravel\\Ai\\Exceptions\\InsufficientCreditsException';
    }

    private function providerOverloadedExceptionClass(): string
    {
        return 'Laravel\\Ai\\Exceptions\\ProviderOverloadedException';
    }

    private function providerConnectionExceptionClass(): string
    {
        return 'Laravel\\Ai\\Exceptions\\ProviderConnectionException';
    }
}
