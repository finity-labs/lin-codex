<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\AiCallFailed;
use FinityLabs\LinCodex\Ai\StructuredCompletion;
use FinityLabs\LinCodex\Ai\StructuredRequest;
use FinityLabs\LinCodex\Tests\Fixtures\FakeAiClient;

function linCodexAiRequest(string $prompt = 'Translate.'): StructuredRequest
{
    return new StructuredRequest(
        instructions: 'Be formal.',
        prompt: $prompt,
        fields: ['title' => 'The title', 'excerpt' => 'The excerpt', 'body' => 'The body'],
        provider: 'anthropic',
        model: null,
        timeout: 120,
        apiKey: null,
    );
}

it('answers one queued completion and records the request', function (): void {
    $fake = FakeAiClient::completing(['title' => 'T', 'excerpt' => '', 'body' => 'B'], 10, 20);

    $completion = $fake->structured(linCodexAiRequest());

    expect($completion->fields)->toBe(['title' => 'T', 'excerpt' => '', 'body' => 'B'])
        ->and($completion->promptTokens)->toBe(10)
        ->and($completion->completionTokens)->toBe(20)
        ->and($completion->truncated)->toBeFalse()
        ->and($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->prompt)->toBe('Translate.');
});

it('refuses a call it has no answer for', function (): void {
    $fake = FakeAiClient::completing(['title' => 'T']);

    $fake->structured(linCodexAiRequest());
    $fake->structured(linCodexAiRequest('Second.'));
})->throws(LogicException::class, 'FakeAiClient has no completion queued for: Second.');

it('throws a queued failure', function (): void {
    $fake = FakeAiClient::completing(['title' => 'T'])->push(new AiCallFailed('rate_limited'));

    $fake->structured(linCodexAiRequest());

    try {
        $fake->structured(linCodexAiRequest('Second.'));
        $this->fail('the queued failure was not thrown');
    } catch (AiCallFailed $e) {
        expect($e->reason)->toBe('rate_limited');
    }
});

it('lets a closure answer every call', function (): void {
    $fake = (new FakeAiClient)
        ->push(new StructuredCompletion(['title' => 'from the queue']))
        ->answering(fn (StructuredRequest $request): StructuredCompletion => new StructuredCompletion(['title' => strtoupper($request->prompt)]));

    expect($fake->structured(linCodexAiRequest('one'))->string('title'))->toBe('ONE')
        ->and($fake->structured(linCodexAiRequest('two'))->string('title'))->toBe('TWO')
        ->and($fake->requests)->toHaveCount(2);
});

it('reports what its constructor was given', function (): void {
    $fake = new FakeAiClient(installed: true, labels: ['anthropic' => 'Anthropic'], connection: null);

    expect($fake->installed())->toBeTrue()
        ->and($fake->providers())->toBe(['anthropic' => 'Anthropic'])
        ->and($fake->testConnection('anthropic', null, 'sk'))->toBeNull()
        ->and($fake->tierModels('anthropic'))->toBe([
            'default' => 'fake-default',
            'cheapest' => 'fake-cheapest',
            'smartest' => 'fake-smartest',
        ]);

    $missing = new FakeAiClient(installed: false, connection: 'rate_limited');

    expect($missing->installed())->toBeFalse()
        ->and($missing->providers())->toBe([])
        ->and($missing->testConnection('anthropic', null, null))->toBe('rate_limited');
});

it('reads a string field off a completion', function (): void {
    $completion = new StructuredCompletion(['title' => 'Titel', 'count' => 3]);

    expect($completion->string('title'))->toBe('Titel')
        ->and($completion->string('count'))->toBeNull()
        ->and($completion->string('missing'))->toBeNull();
});
