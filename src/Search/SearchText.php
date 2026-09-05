<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use Illuminate\Support\Str;

/**
 * The one folding rule shared by the search_text column, the in-memory
 * index for file articles, the query and the post-SQL verification. Because
 * every side folds the same way, "uber" finds "Über" on every engine.
 *
 * fold() lowercases with mb_strtolower(), transliterates with Str::ascii()
 * and collapses every run that is not [a-z0-9] into one space. Str::ascii()
 * is deliberately given no language: the "de" rules would write "ue" for
 * "ü" and "uber" would no longer find "über". Scripts without a Latin
 * transliteration (CJK, for example) fold to nothing; that is a documented
 * limitation of v1.
 *
 * compose() writes the four folded segments (title, keywords, excerpt,
 * body) joined by a newline, each starting with one space. The column
 * itself records the field boundaries, so split() restores them without a
 * side column, and the leading space makes "LIKE '% token%'" a word-start
 * match on every engine. Bump VERSION whenever fold() or compose() changes;
 * it is hashed into the in-memory index key.
 */
final class SearchText
{
    public const VERSION = 1;

    public static function fold(string $text): string
    {
        $ascii = Str::ascii(mb_strtolower($text));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '');
    }

    /**
     * @param  list<string>  $keywords
     */
    public static function compose(string $title, array $keywords, ?string $excerpt, string $body): string
    {
        return implode("\n", array_map(
            static fn (string $segment): string => ' '.self::fold($segment),
            [$title, implode(' ', $keywords), (string) $excerpt, $body],
        ));
    }

    /**
     * @return array{title: string, keywords: string, excerpt: string, body: string}
     */
    public static function split(?string $blob): array
    {
        $parts = array_pad(explode("\n", (string) $blob, 4), 4, '');

        return [
            'title' => trim($parts[0]),
            'keywords' => trim($parts[1]),
            'excerpt' => trim($parts[2]),
            'body' => trim($parts[3]),
        ];
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $folded = self::fold($text);

        return $folded === '' ? [] : explode(' ', $folded);
    }
}
