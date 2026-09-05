<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Enums\SearchField;

/**
 * One search result row.
 *
 * The section path is the ancestor article titles root-first, so the UI
 * can print a "Users › Roles" line. The snippet is HTML with <mark> around
 * the matched prefixes and everything else escaped; it is built in PHP, so
 * it is identical on every driver. The matched field is the field the
 * ranker credited, and isFallback marks a default-language stand-in for a
 * translation the reader asked for but the article does not have.
 */
final readonly class SearchHit
{
    /**
     * @param  list<string>  $sectionPath  ancestor article titles root-first ("Users" for users/roles)
     */
    public function __construct(
        public string $slug,
        public string $title,
        public array $sectionPath,
        public string $snippet,
        public SearchField $matchedField,
        public int $score,
        public bool $isFallback,
    ) {}
}
