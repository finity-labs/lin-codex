<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * Orders matching candidates, computed in PHP on every driver so the order
 * is identical whatever engine pre-filtered the rows.
 *
 * Fields are tiers: title > keywords > excerpt > body. With the query capped
 * at 16 tokens, 16 * BODY + PHRASE + MAX_OCCURRENCES = 169 < EXCERPT, so no
 * lower tier can ever outscore a single hit in a higher one. Ties break by
 * folded title, then slug (locked decision). Non-matching candidates are
 * dropped.
 */
final class Ranker
{
    private const TITLE = 10_000_000;

    private const KEYWORDS = 100_000;

    private const EXCERPT = 1_000;

    private const BODY = 10;

    private const PHRASE = 5;

    private const MAX_OCCURRENCES = 4;

    public function __construct(private readonly Matcher $matcher) {}

    /**
     * @param  list<Candidate>  $candidates
     *
     * @return list<ScoredCandidate> best first, non-matching dropped
     */
    public function rank(array $candidates, ParsedQuery $query): array
    {
        $scored = [];

        foreach ($candidates as $candidate) {
            $match = $this->matcher->matches($query, $candidate->fields);

            if ($match === null) {
                continue;
            }

            $score = $match->hits['title'] * self::TITLE
                + $match->hits['keywords'] * self::KEYWORDS
                + $match->hits['excerpt'] * self::EXCERPT
                + $match->hits['body'] * self::BODY
                + ($match->phrase ? self::PHRASE : 0)
                + min($match->occurrences, self::MAX_OCCURRENCES);

            $scored[] = new ScoredCandidate($candidate, $match, $score);
        }

        usort($scored, static function (ScoredCandidate $a, ScoredCandidate $b): int {
            if ($a->score !== $b->score) {
                return $b->score <=> $a->score;
            }

            $byTitle = $a->candidate->fields['title'] <=> $b->candidate->fields['title'];

            if ($byTitle !== 0) {
                return $byTitle;
            }

            return $a->candidate->article->slug <=> $b->candidate->article->slug;
        });

        return $scored;
    }
}
