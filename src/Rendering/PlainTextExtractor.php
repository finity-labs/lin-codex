<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

/**
 * Turns rendered article HTML into whitespace-normalised search text. Alt
 * text, captions, callout and step titles, and code stay in; the permalink
 * "#" characters, aria-hidden decoration such as step number badges, and
 * task-list checkboxes drop out. Accent folding is the search layer's job,
 * not this class's.
 */
final class PlainTextExtractor
{
    private const BLOCK_TAGS = 'p|div|li|ul|ol|h[1-6]|tr|td|th|table|thead|tbody|br|hr|figure|figcaption|aside|details|summary|pre|blockquote|section';

    public function fromHtml(string $html): string
    {
        $text = preg_replace('~<a\b[^>]*\bclass="codex-anchor"[^>]*>#</a>~', '', $html) ?? '';
        $text = preg_replace('~<span\b[^>]*\baria-hidden="true"[^>]*>[^<]*</span>~', '', $text) ?? '';
        $text = preg_replace('~<img\b[^>]*\balt="([^"]*)"[^>]*>~i', ' $1 ', $text) ?? '';
        $text = preg_replace('~</?(?:'.self::BLOCK_TAGS.')\b[^>]*>~i', ' ', $text) ?? '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
