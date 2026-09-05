<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * Turns the typed query into a ParsedQuery, or null when it is too short.
 *
 * Folding strips every operator (+ - " ( ) * ~ < > @ & | : !) because only
 * [a-z0-9] survives, so MySQL boolean mode and PostgreSQL to_tsquery can
 * never see a syntax error or an OR; AND semantics come from the matcher.
 * The minimum length is measured on the folded text, and the token cap
 * keeps both the SQL and the score bounded.
 */
final class QueryParser
{
    public const MAX_TOKENS = 16;

    public function parse(string $query): ?ParsedQuery
    {
        $folded = SearchText::fold($query);

        if (strlen($folded) < max(1, (int) config('lin-codex.search.min_length', 2))) {
            return null;
        }

        $tokens = array_slice(array_values(array_unique(explode(' ', $folded))), 0, self::MAX_TOKENS);

        return new ParsedQuery($query, $folded, $tokens);
    }
}
