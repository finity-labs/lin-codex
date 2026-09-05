<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * The answer to one search. The total is the capped hit count; there is no
 * pagination (locked). A throttled result is empty with rateLimited true
 * and the seconds to wait, never an exception: the JSON API maps it to a
 * 429 and the drawer to a message.
 */
final readonly class SearchResult
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public array $hits,
        public string $query,
        public int $total,
        public bool $rateLimited,
        public ?int $retryAfterSeconds,
    ) {}

    public static function empty(string $query): self
    {
        return new self([], $query, 0, false, null);
    }

    public static function throttled(string $query, int $retryAfterSeconds): self
    {
        return new self([], $query, 0, true, $retryAfterSeconds);
    }
}
