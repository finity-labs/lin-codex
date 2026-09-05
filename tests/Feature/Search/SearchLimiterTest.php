<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Search\SearchLimiter;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

function linCodexLimiterUser(int $id = 1): Viewer
{
    return Viewer::authenticated(new GenericUser(['id' => $id]));
}

/**
 * Rebind the current request so the limiter sees another client address.
 */
function linCodexLimiterRequestFrom(string $ip): void
{
    app()->instance('request', Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]));
}

beforeEach(function (): void {
    $this->limiter = fn (): SearchLimiter => app(SearchLimiter::class);
});

it('lets a guest search under the limit, then refuses without recording a hit', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 2);

    expect(($this->limiter)()->check(Viewer::guest()))->toBeNull()
        ->and(($this->limiter)()->check(Viewer::guest()))->toBeNull();

    $retry = ($this->limiter)()->check(Viewer::guest());

    expect($retry)->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(60)
        ->and(($this->limiter)()->check(Viewer::guest()))->toBeInt()
        ->and(app(RateLimiter::class)->attempts(($this->limiter)()->keyFor(Viewer::guest())))->toBe(2);
});

it('lets the viewer through again when the window expires', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 1);

    expect(($this->limiter)()->check(Viewer::guest()))->toBeNull()
        ->and(($this->limiter)()->check(Viewer::guest()))->toBeInt();

    $this->travel(61)->seconds();

    expect(($this->limiter)()->check(Viewer::guest()))->toBeNull();
});

it('keeps the user tier separate from the guest tier and users apart from each other', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 1);
    config()->set('lin-codex.search.rate_limit.user', 3);

    expect(($this->limiter)()->check(Viewer::guest()))->toBeNull()
        ->and(($this->limiter)()->check(Viewer::guest()))->toBeInt();

    expect(($this->limiter)()->check(linCodexLimiterUser()))->toBeNull()
        ->and(($this->limiter)()->check(linCodexLimiterUser()))->toBeNull()
        ->and(($this->limiter)()->check(linCodexLimiterUser()))->toBeNull()
        ->and(($this->limiter)()->check(linCodexLimiterUser()))->toBeInt()
        ->and(($this->limiter)()->check(linCodexLimiterUser(2)))->toBeNull();
});

it('keys users by auth identifier and guests by client address', function (): void {
    expect(($this->limiter)()->keyFor(linCodexLimiterUser(7)))->toBe('codex-search:user:7');

    linCodexLimiterRequestFrom('10.0.0.1');

    expect(($this->limiter)()->keyFor(Viewer::guest()))->toBe('codex-search:ip:10.0.0.1');
});

it('counts guests from different addresses independently', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 1);

    linCodexLimiterRequestFrom('10.0.0.1');

    expect(($this->limiter)()->check(Viewer::guest()))->toBeNull()
        ->and(($this->limiter)()->check(Viewer::guest()))->toBeInt();

    linCodexLimiterRequestFrom('10.0.0.2');

    expect(($this->limiter)()->check(Viewer::guest()))->toBeNull()
        ->and(app(RateLimiter::class)->attempts('codex-search:ip:10.0.0.1'))->toBe(1)
        ->and(app(RateLimiter::class)->attempts('codex-search:ip:10.0.0.2'))->toBe(1);
});

it('disables a tier set to null and writes nothing for it', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', null);
    config()->set('lin-codex.search.rate_limit.user', 1);

    for ($i = 0; $i < 50; $i++) {
        expect(($this->limiter)()->check(Viewer::guest()))->toBeNull();
    }

    expect(app(RateLimiter::class)->attempts('codex-search:ip:127.0.0.1'))->toBe(0)
        ->and(app(RateLimiter::class)->attempts(($this->limiter)()->keyFor(Viewer::guest())))->toBe(0)
        ->and(($this->limiter)()->check(linCodexLimiterUser()))->toBeNull()
        ->and(($this->limiter)()->check(linCodexLimiterUser()))->toBeInt();
});

it('blocks immediately when the limit is zero', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 0);

    expect(($this->limiter)()->check(Viewer::guest()))->toBeInt();
});

it('honours the shipped defaults: thirty guest searches a minute', function (): void {
    for ($i = 0; $i < 30; $i++) {
        expect(($this->limiter)()->check(Viewer::guest()))->toBeNull();
    }

    expect(($this->limiter)()->check(Viewer::guest()))->toBeInt();
});

it('reports the seconds left in the window, never less than one', function (): void {
    config()->set('lin-codex.search.rate_limit.guest', 1);

    ($this->limiter)()->check(Viewer::guest());

    expect(($this->limiter)()->check(Viewer::guest()))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(60);

    $this->travel(30)->seconds();

    expect(($this->limiter)()->check(Viewer::guest()))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(30);
});
