<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Settings\CodexSettings;

/**
 * @param  list<string>  $codes
 */
function linCodexApiSearchUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * The hit for a slug in a search response's data, or null.
 *
 * @param  list<array<string, mixed>>  $hits
 *
 * @return array<string, mixed>|null
 */
function linCodexApiSearchHit(array $hits, string $slug): ?array
{
    foreach ($hits as $hit) {
        if ($hit['slug'] === $slug) {
            return $hit;
        }
    }

    return null;
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-search')]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('returns hits in the locked shape', function (): void {
    $response = $this->getJson('/codex/api/search?q=reset');

    $response->assertOk();

    $json = $response->json();

    expect(array_keys($json['meta']))->toBe(['locale', 'defaultLocale', 'query', 'total', 'limit', 'rateLimited', 'retryAfterSeconds'])
        ->and($json['meta']['query'])->toBe('reset')
        ->and($json['meta']['total'])->toBe(count($json['data']))->toBeGreaterThan(0)
        ->and($json['meta']['limit'])->toBe(10)
        ->and($json['meta']['rateLimited'])->toBeFalse()
        ->and($json['meta']['retryAfterSeconds'])->toBeNull()
        ->and(array_column($json['data'], 'slug'))->toContain('password-reset');

    foreach ($json['data'] as $hit) {
        expect(array_keys($hit))->toBe(['slug', 'title', 'sectionPath', 'snippet', 'matchedField', 'score', 'isFallback'], 'hit '.$hit['slug'])
            ->and($hit['matchedField'])->toBeString()->toBeIn(['title', 'keywords', 'excerpt', 'body'])
            ->and($hit['snippet'])->toBeString();
    }

    // A title hit shows the excerpt unmarked (the searcher's rule); a body hit marks the typed token.
    $reset = linCodexApiSearchHit($json['data'], 'password-reset');

    expect($reset)->not->toBeNull()
        ->and($reset['matchedField'])->toBe('title')
        ->and($reset['snippet'])->toBe(e('How to recover an account when the password was forgotten.'))
        ->not->toContain('<mark>');

    $token = linCodexApiSearchHit($this->getJson('/codex/api/search?q=token')->json('data'), 'password-reset');

    expect($token)->not->toBeNull()
        ->and($token['matchedField'])->toBe('body')
        ->and($token['snippet'])->toContain('<mark>token</mark>');
});

it('answers 422 for a missing or blank query', function (string $url): void {
    $response = $this->getJson($url);

    expect($response->getStatusCode())->toBe(422);

    $response->assertExactJson(['message' => 'The q parameter is required.']);
})->with(['/codex/api/search', '/codex/api/search?q=', '/codex/api/search?q=%20%20']);

it('answers 422 for a limit that is not a whole number', function (string $limit): void {
    $response = $this->getJson('/codex/api/search?q=reset&limit='.$limit);

    expect($response->getStatusCode())->toBe(422);

    $response->assertExactJson(['message' => 'The limit parameter must be a whole number.']);
})->with(['abc', '1.5', '-']);

it('treats an empty limit as absent', function (): void {
    $response = $this->getJson('/codex/api/search?q=reset&limit=');

    $response->assertOk();

    expect($response->json('meta.limit'))->toBe(10);
});

it('returns an empty result below the minimum length without an error', function (): void {
    $response = $this->getJson('/codex/api/search?q=r');

    $response->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0)
        ->and($response->json('meta.rateLimited'))->toBeFalse();
});

it('reports the clamped limit', function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();

    Article::factory()->count(60)->public()->published()
        ->withTranslation('en', ['title' => 'Cap row', 'excerpt' => null, 'body' => 'cap words'])
        ->create();

    $default = $this->getJson('/codex/api/search?q=cap');

    expect($default->json('meta.limit'))->toBe(10)
        ->and($default->json('data'))->toHaveCount(10);

    $three = $this->getJson('/codex/api/search?q=cap&limit=3');

    expect($three->json('meta.limit'))->toBe(3)
        ->and($three->json('data'))->toHaveCount(3);

    $capped = $this->getJson('/codex/api/search?q=cap&limit=99');

    expect($capped->json('meta.limit'))->toBe(50)
        ->and($capped->json('data'))->toHaveCount(50);

    $floor = $this->getJson('/codex/api/search?q=cap&limit=0');

    expect($floor->json('meta.limit'))->toBe(1)
        ->and($floor->json('data'))->toHaveCount(1);

    config()->set('lin-codex.search.max_limit', 20);

    expect($this->getJson('/codex/api/search?q=cap&limit=99')->json('meta.limit'))->toBe(20);
});

it('answers 429 with a Retry-After header when the limiter refuses and recovers after the window', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 1);

    $this->getJson('/codex/api/search?q=reset')->assertOk();

    $throttled = $this->getJson('/codex/api/search?q=reset');

    expect($throttled->getStatusCode())->toBe(429);

    $throttled->assertHeader('Retry-After');

    $header = $throttled->headers->get('Retry-After');

    expect($header)->toBeString()->toMatch('/^\d+$/');

    $seconds = (int) $header;

    expect($seconds)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(60)
        ->and($throttled->json())->not->toHaveKey('data');

    $throttled->assertExactJson(['message' => 'Too many searches. Try again in '.$seconds.' seconds.']);

    $this->travel(61)->seconds();

    $this->getJson('/codex/api/search?q=reset')->assertOk();
});

it('keeps the locale in meta and honours fallback flags', function (): void {
    linCodexApiSearchUseLanguages(['en', 'de']);

    $german = $this->getJson('/codex/api/search?q=passwort&locale=de');

    $german->assertOk();

    $reset = linCodexApiSearchHit($german->json('data'), 'password-reset');

    expect($german->json('meta.locale'))->toBe('de')
        ->and($reset)->not->toBeNull()
        ->and($reset['isFallback'])->toBeFalse();

    $english = linCodexApiSearchHit($this->getJson('/codex/api/search?q=english&locale=de')->json('data'), 'only-english');

    expect($english)->not->toBeNull()
        ->and($english['isFallback'])->toBeTrue();
});
