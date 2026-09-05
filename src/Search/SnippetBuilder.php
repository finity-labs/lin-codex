<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * Builds the highlighted HTML snippet for a hit.
 *
 * It works on the original, case-preserved text and never on folded
 * offsets: folding changes lengths ("ß" becomes "ss", "Æ" becomes "AE"),
 * so every word is located by the byte offsets of a /u regex match on the
 * original and folded on its own only to size the marked prefix. Every cut
 * lands on a word boundary, so a multibyte character is never split.
 *
 * The typed prefix is marked, the way as-you-type UIs do; switching to
 * whole-word marking is the one line in matchedPrefix() that returns the
 * byte length. The caller picks which text to snippet (body, excerpt, or
 * the excerpt/body start for title-only hits, where marks appear only if a
 * token happens to occur there).
 */
final class SnippetBuilder
{
    private const ELLIPSIS = '…';

    public function build(ParsedQuery $query, string $text, ?int $length = null): string
    {
        $length ??= max(20, (int) config('lin-codex.search.snippet_length', 160));

        if ($text === '') {
            return '';
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $found, PREG_OFFSET_CAPTURE);

        /** @var list<array{word: string, offset: int, prefix: int}> $words prefix = bytes of the matched prefix, 0 when unmatched */
        $words = [];

        foreach ($found[0] as [$word, $offset]) {
            $words[] = ['word' => $word, 'offset' => $offset, 'prefix' => $this->matchedPrefix($word, $query->tokens)];
        }

        [$start, $end] = $this->window($words, $text, $length);

        $out = $start > 0 ? self::ELLIPSIS : '';
        $cursor = $start;

        foreach ($words as $entry) {
            $wordEnd = $entry['offset'] + strlen($entry['word']);

            if ($entry['offset'] < $start || $wordEnd > $end) {
                continue;
            }

            $out .= $this->escape(substr($text, $cursor, $entry['offset'] - $cursor));

            if ($entry['prefix'] > 0) {
                $out .= '<mark>'.$this->escape(substr($entry['word'], 0, $entry['prefix'])).'</mark>'
                    .$this->escape(substr($entry['word'], $entry['prefix']));
            } else {
                $out .= $this->escape($entry['word']);
            }

            $cursor = $wordEnd;
        }

        $out .= $this->escape(substr($text, $cursor, $end - $cursor));

        if ($end < strlen($text)) {
            $out .= self::ELLIPSIS;
        }

        return $out;
    }

    /**
     * The byte range [start, end) to emit: about a third of the length
     * before the first matched word, snapped to word boundaries on both
     * sides. A single word longer than the window is emitted whole.
     *
     * @param  list<array{word: string, offset: int, prefix: int}>  $words
     *
     * @return array{int, int}
     */
    private function window(array $words, string $text, int $length): array
    {
        $textLength = strlen($text);
        $first = null;

        foreach ($words as $index => $entry) {
            if ($entry['prefix'] > 0) {
                $first = $index;

                break;
            }
        }

        $start = $first === null ? 0 : max(0, $words[$first]['offset'] - intdiv($length, 3));

        if ($start > 0) {
            foreach ($words as $entry) {
                if ($entry['offset'] >= $start) {
                    $start = $entry['offset'];

                    break;
                }
            }
        }

        $end = $start + $length;

        if ($end >= $textLength) {
            return [$start, $textLength];
        }

        $lastEnd = null;
        $firstEnd = null;

        foreach ($words as $entry) {
            if ($entry['offset'] < $start) {
                continue;
            }

            $wordEnd = $entry['offset'] + strlen($entry['word']);
            $firstEnd ??= $wordEnd;

            if ($wordEnd <= $end) {
                $lastEnd = $wordEnd;
            }
        }

        if ($lastEnd !== null && $lastEnd > $start) {
            return [$start, $lastEnd];
        }

        return [$start, $firstEnd ?? $textLength];
    }

    /**
     * Byte length of the prefix of the original word that folds to the
     * longest matching token, 0 when no token matches. Characters are
     * consumed one at a time and each contributes the byte length of its
     * own fold (a character folding to nothing adds 0 and is still
     * consumed), so "stras" over "Straße" marks "Straß".
     *
     * @param  list<string>  $tokens
     */
    private function matchedPrefix(string $word, array $tokens): int
    {
        $folded = SearchText::fold($word);

        if ($folded === '') {
            return 0;
        }

        $best = '';

        foreach ($tokens as $token) {
            if (str_starts_with($folded, $token) && strlen($token) > strlen($best)) {
                $best = $token;
            }
        }

        if ($best === '') {
            return 0;
        }

        $target = strlen($best);
        $consumed = 0;
        $bytes = 0;

        foreach (mb_str_split($word) as $character) {
            $bytes += strlen($character);
            $consumed += strlen(SearchText::fold($character));

            if ($consumed >= $target) {
                break;
            }
        }

        return $bytes;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
