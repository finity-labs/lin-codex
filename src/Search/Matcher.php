<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Enums\SearchField;

/**
 * Decides whether a candidate matches a query and how. A token matches a
 * field at a word start only (prefix semantics through the leading space),
 * and every token must hit some field (AND across fields).
 *
 * The same function verifies database rows after the SQL pre-filter and
 * file documents in memory, which is what makes the hit set identical on
 * every engine: SQL only narrows, the matcher decides.
 */
final class Matcher
{
    /**
     * @param  array{title: string, keywords: string, excerpt: string, body: string}  $fields  folded
     */
    public function matches(ParsedQuery $query, array $fields): ?MatchDetail
    {
        $hits = [];
        $occurrences = 0;
        $phrase = false;
        $unmatched = array_fill_keys($query->tokens, true);

        foreach (SearchField::cases() as $field) {
            $haystack = ' '.($fields[$field->key()] ?? '');
            $count = 0;

            foreach ($query->tokens as $token) {
                $found = substr_count($haystack, ' '.$token);

                if ($found > 0) {
                    $count++;
                    $occurrences += $found - 1;
                    unset($unmatched[$token]);
                }
            }

            $hits[$field->key()] = $count;
            $phrase = $phrase || str_contains($haystack, ' '.$query->phrase);
        }

        if ($unmatched !== []) {
            return null;
        }

        return new MatchDetail($hits, $this->matchedField($hits, count($query->tokens)), $phrase, $occurrences);
    }

    /**
     * The first field holding every token; otherwise the first field with
     * the most hits.
     *
     * @param  array<string, int>  $hits
     */
    private function matchedField(array $hits, int $tokenCount): SearchField
    {
        $best = SearchField::Title;

        foreach (SearchField::cases() as $field) {
            if ($hits[$field->key()] === $tokenCount) {
                return $field;
            }

            if ($hits[$field->key()] > $hits[$best->key()]) {
                $best = $field;
            }
        }

        return $best;
    }
}
