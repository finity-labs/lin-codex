<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Auth\Viewer;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

/**
 * The search rate limit, applied inside the search service so the JSON API
 * and the help drawer share one counter (locked decision) instead of each
 * throttling on its own. Guests are keyed by client address (the host's
 * trusted-proxy setup decides what Request::ip() returns), users by their
 * auth identifier, both under the codex-search: prefix with a 60-second
 * window from lin-codex.search.rate_limit.{guest,user}.
 *
 * A tier set to null is disabled and touches the cache not at all; a tier
 * of zero refuses every search up front, because the framework limiter
 * treats "0 of 0 attempts" as a fresh window and would let the first one
 * through. Over the limit, check() returns the seconds until the window
 * opens and records nothing; the searcher turns that into an empty result
 * flagged rateLimited, never an exception. The class is resolved per search
 * and never a singleton, so the injected request is the current one.
 */
final class SearchLimiter
{
    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Request $request,
    ) {}

    /**
     * Null when the search may run (a hit was recorded); the retry-after
     * seconds when it may not (nothing recorded).
     */
    public function check(Viewer $viewer): ?int
    {
        $max = config('lin-codex.search.rate_limit.'.($viewer->isAuthenticated ? 'user' : 'guest'));

        if ($max === null) {
            return null;
        }

        if ((int) $max <= 0) {
            return self::DECAY_SECONDS;
        }

        $key = $this->keyFor($viewer);

        if ($this->limiter->tooManyAttempts($key, (int) $max)) {
            return max(1, $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, self::DECAY_SECONDS);

        return null;
    }

    /**
     * 'codex-search:user:{id}' for a signed-in viewer, else
     * 'codex-search:ip:{address}' with 0.0.0.0 when no address is known.
     */
    public function keyFor(Viewer $viewer): string
    {
        if ($viewer->isAuthenticated && $viewer->user !== null) {
            return 'codex-search:user:'.$viewer->user->getAuthIdentifier();
        }

        return 'codex-search:ip:'.($this->request->ip() ?? '0.0.0.0');
    }
}
