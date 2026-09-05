<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Enums\SearchField;

/**
 * What the matcher found in one candidate: distinct token hits per field,
 * the field credited with the match, whether the whole phrase appeared
 * contiguously in one field, and how many occurrences beyond the first
 * were seen.
 */
final readonly class MatchDetail
{
    /**
     * @param  array<string, int>  $hits  SearchField key => number of distinct tokens hitting that field
     */
    public function __construct(
        public array $hits,
        public SearchField $matchedField,
        public bool $phrase,
        public int $occurrences,
    ) {}
}
