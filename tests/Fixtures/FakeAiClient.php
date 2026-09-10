<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests\Fixtures;

use Closure;
use FinityLabs\LinCodex\Ai\Contracts\AiClient;
use FinityLabs\LinCodex\Ai\StructuredCompletion;
use FinityLabs\LinCodex\Ai\StructuredRequest;
use LogicException;
use Throwable;

/**
 * A hand-written `AiClient` for everything above the seam.
 *
 * Queue the completions (or the failures) a test expects, bind it with
 * `app()->instance(AiClient::class, $fake)` and read `$requests` afterwards
 * to see what the translator or the job actually asked for. No SDK needed,
 * so these tests run on every CI row.
 *
 * Seam tests are the exception: they exercise `LaravelAiClient` itself and
 * fake the SDK's own gateway through `TranslationAgent::fake()`.
 */
final class FakeAiClient implements AiClient
{
    /** @var list<StructuredRequest> */
    public array $requests = [];

    /** @var list<StructuredCompletion|Throwable> */
    private array $queue = [];

    private ?Closure $answer = null;

    /** @var array<string, string> */
    private array $labels;

    /**
     * @param  array<string, string>  $labels
     * @param  string|null  $connection  what testConnection() answers: null for a working connection
     */
    public function __construct(
        public bool $installed = true,
        array $labels = ['anthropic' => 'Anthropic', 'openai' => 'OpenAI', 'ollama' => 'Ollama'],
        public ?string $connection = null,
    ) {
        $this->labels = $labels;
    }

    /**
     * A fake that answers one call with these fields.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function completing(array $fields, int $promptTokens = 0, int $completionTokens = 0, bool $truncated = false): self
    {
        return (new self)->push(new StructuredCompletion($fields, $promptTokens, $completionTokens, $truncated));
    }

    /** Queue one more answer, or one failure to throw. */
    public function push(StructuredCompletion|Throwable $next): self
    {
        $this->queue[] = $next;

        return $this;
    }

    /** Answer every call from a closure instead of the queue. */
    public function answering(Closure $answer): self
    {
        $this->answer = $answer;

        return $this;
    }

    public function installed(): bool
    {
        return $this->installed;
    }

    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        return $this->installed ? $this->labels : [];
    }

    /**
     * @return array{default: string, cheapest: string, smartest: string}
     */
    public function tierModels(string $provider): array
    {
        return [
            'default' => 'fake-default',
            'cheapest' => 'fake-cheapest',
            'smartest' => 'fake-smartest',
        ];
    }

    public function structured(StructuredRequest $request): StructuredCompletion
    {
        $this->requests[] = $request;

        if ($this->answer instanceof Closure) {
            return ($this->answer)($request);
        }

        if ($this->queue === []) {
            throw new LogicException('FakeAiClient has no completion queued for: '.$request->prompt);
        }

        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function testConnection(string $provider, ?string $model, ?string $apiKey): ?string
    {
        return $this->connection;
    }
}
