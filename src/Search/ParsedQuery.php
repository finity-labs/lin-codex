<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * A search query after folding. The raw string is what the user typed, the
 * phrase is the folded single-spaced form used for the contiguous-phrase
 * bonus, and the tokens are the deduped folded words.
 *
 * needsLike() applies on every engine so that short and stopword tokens
 * behave the same everywhere: InnoDB indexes nothing under three characters
 * and strips stopwords from a boolean query, which would silently drop the
 * token and widen the hit set. Such a query takes the LIKE path instead.
 */
final readonly class ParsedQuery
{
    /**
     * MySQL's default InnoDB stopword table (36 rows, "the" twice); such a
     * token is dropped from a boolean query, so the whole query takes the
     * LIKE path.
     *
     * @var list<string>
     */
    private const INNODB_STOPWORDS = [
        'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for',
        'from', 'how', 'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the',
        'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www',
    ];

    /**
     * @param  list<string>  $tokens
     */
    public function __construct(
        public string $raw,
        public string $phrase,
        public array $tokens,
    ) {}

    public function needsLike(): bool
    {
        foreach ($this->tokens as $token) {
            if (strlen($token) < 3 || in_array($token, self::INNODB_STOPWORDS, true)) {
                return true;
            }
        }

        return false;
    }
}
