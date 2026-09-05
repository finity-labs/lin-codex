<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * A matching candidate with its match detail and the score the ranker
 * gave it. Higher scores come first.
 */
final readonly class ScoredCandidate
{
    public function __construct(
        public Candidate $candidate,
        public MatchDetail $match,
        public int $score,
    ) {}
}
