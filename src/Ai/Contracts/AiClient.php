<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Ai\Contracts;

use FinityLabs\LinCodex\Ai\AiCallFailed;
use FinityLabs\LinCodex\Ai\StructuredCompletion;
use FinityLabs\LinCodex\Ai\StructuredRequest;

/**
 * Everything lin-codex asks of an AI provider.
 *
 * The real implementation is `Ai\LaravelAiClient`, the one class that names
 * symbols from the optional laravel/ai SDK; the service provider binds it.
 * Tests bind `Tests\Fixtures\FakeAiClient` instead, which is why the
 * translator, the job and fin-codex depend on this interface and never on
 * the SDK.
 */
interface AiClient
{
    /** The laravel/ai SDK is installed, whatever the settings say. */
    public function installed(): bool;

    /**
     * Offered providers the installed SDK knows, Lab value => Lab case name
     * ('anthropic' => 'Anthropic', 'xai' => 'xAI'); [] without the SDK.
     *
     * @return array<string, string>
     */
    public function providers(): array;

    /**
     * The provider's three tier models by their real names.
     *
     *
     * @throws AiCallFailed with reason "unavailable" without the SDK or for a provider that is not offered
     *
     * @return array{default: string, cheapest: string, smartest: string}
     */
    public function tierModels(string $provider): array;

    /**
     * One structured call: the agent is built with the request's instructions
     * and field schema, the stored key (when any) is injected for this call
     * only, and the decoded fields come back with the usage and a truncation
     * flag.
     *
     * @throws AiCallFailed carrying the reason key
     */
    public function structured(StructuredRequest $request): StructuredCompletion;

    /**
     * One round trip ("Reply with the single word OK.", 10 s).
     *
     * Null on success, else the reason key. The settings page shows the key's
     * label, so a failure is an answer here rather than an exception.
     */
    public function testConnection(string $provider, ?string $model, ?string $apiKey): ?string;
}
