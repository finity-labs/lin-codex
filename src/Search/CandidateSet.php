<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Enums\SearchStrategy;

/**
 * The candidates the database pre-filter produced and which branch
 * produced them: FullText when the engine's index answered, Like when the
 * portable LIKE query did, including after the zero-row retry. The hit set
 * is the same either way because the PHP matcher decides; the strategy is
 * exposed so a test can prove the index was used.
 */
final readonly class CandidateSet
{
    /**
     * @param  list<Candidate>  $candidates
     */
    public function __construct(
        public array $candidates,
        public SearchStrategy $strategy,
    ) {}
}
