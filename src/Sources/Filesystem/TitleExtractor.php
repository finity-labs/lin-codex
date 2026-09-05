<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Enums\ArticleFormat;

/**
 * Takes the first level-one heading out of an article body to use as its
 * title. Only called when the front matter carries no title; the heading is
 * consumed so the title does not render twice. Markdown headings inside
 * code fences are skipped. Pure.
 */
final class TitleExtractor
{
    private const FENCE = '/^\s{0,3}(`{3,}|~{3,})/';

    private const ATX_H1 = '/^[ \t]{0,3}#[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*$/';

    private const SETEXT_UNDERLINE = '/^[ \t]{0,3}=+[ \t]*$/';

    private const HTML_H1 = '/<h1\b[^>]*>(.*?)<\/h1>\s*/is';

    /**
     * @return array{title: ?string, body: string}
     */
    public static function extract(string $body, ArticleFormat $format): array
    {
        return match ($format) {
            ArticleFormat::Markdown => self::extractMarkdown($body),
            ArticleFormat::Html => self::extractHtml($body),
        };
    }

    /**
     * @return array{title: ?string, body: string}
     */
    private static function extractMarkdown(string $body): array
    {
        $eol = str_contains($body, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $body);
        $inFence = false;

        foreach ($lines as $index => $line) {
            if (preg_match(self::FENCE, $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if ($inFence) {
                continue;
            }

            if (preg_match(self::ATX_H1, $line, $matches) === 1) {
                $title = self::cleanMarkdownTitle($matches[1]);

                return $title === ''
                    ? ['title' => null, 'body' => $body]
                    : ['title' => $title, 'body' => self::removeLines($lines, $index, 1, $eol)];
            }

            $next = $lines[$index + 1] ?? null;

            if (trim($line) !== '' && $next !== null && preg_match(self::SETEXT_UNDERLINE, $next) === 1) {
                $title = self::cleanMarkdownTitle($line);

                return $title === ''
                    ? ['title' => null, 'body' => $body]
                    : ['title' => $title, 'body' => self::removeLines($lines, $index, 2, $eol)];
            }
        }

        return ['title' => null, 'body' => $body];
    }

    /**
     * @return array{title: ?string, body: string}
     */
    private static function extractHtml(string $body): array
    {
        if (preg_match(self::HTML_H1, $body, $matches) !== 1) {
            return ['title' => null, 'body' => $body];
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($title === '') {
            return ['title' => null, 'body' => $body];
        }

        return ['title' => $title, 'body' => preg_replace(self::HTML_H1, '', $body, 1) ?? $body];
    }

    private static function cleanMarkdownTitle(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['*', '_', '`'], '', $raw)) ?? '');
    }

    /**
     * Remove the heading line(s) and one following blank line.
     *
     * @param  list<string>  $lines
     */
    private static function removeLines(array $lines, int $from, int $count, string $eol): string
    {
        if (isset($lines[$from + $count]) && trim($lines[$from + $count]) === '') {
            $count++;
        }

        array_splice($lines, $from, $count);

        return implode($eol, $lines);
    }
}
