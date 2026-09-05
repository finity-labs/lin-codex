<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Builds the sanitizer HTML-format articles pass through. This is the single
 * security boundary for that format: it runs on render, never on save, so a
 * stricter allowlist applies to everything already stored. Kept: the CONTEXT
 * element list with headings, tables, figures, details, aside, pre/code, plus
 * every attribute the Markdown renderer itself emits. Dropped with their
 * content: script, style, iframe, object, embed and form controls. Event
 * handlers and style attributes never reach the allowlist. No video embeds in
 * v1; an iframe host allowlist can be added later without a breaking change.
 *
 * Config is read inside the methods, never in constants, and the builder is
 * immutable, so every call returns a fresh configuration.
 */
final class SanitizerFactory
{
    public static function make(): HtmlSanitizer
    {
        return new HtmlSanitizer(self::config());
    }

    public static function config(): HtmlSanitizerConfig
    {
        // Elements first: allowAttribute('class', '*') expands the wildcard to
        // the elements allowed at that moment and removes it everywhere else.
        return (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('details')
            ->allowElement('summary')
            ->allowElement('aside')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('form')
            ->dropElement('input')
            ->dropElement('button')
            ->dropElement('select')
            ->dropElement('textarea')
            ->allowAttribute('class', '*')
            ->allowAttribute('id', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])
            ->allowAttribute('data-codex-lightbox', ['img'])
            ->allowAttribute('data-codex-article', ['a'])
            ->allowAttribute('loading', ['img'])
            ->allowAttribute('decoding', ['img'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('role', ['aside'])
            ->allowAttribute('aria-hidden', ['span', 'a'])
            ->allowAttribute('aria-label', ['a'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new CodexClassSanitizer)
            ->withMaxInputLength((int) config('lin-codex.render.sanitizer.max_input_length', -1));
    }
}
