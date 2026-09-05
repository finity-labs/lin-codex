<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Html;

use DOMDocument;
use DOMElement;
use DOMXPath;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use FinityLabs\LinCodex\Rendering\HeadingSlugger;

/**
 * The post-sanitize DOM pass that gives HTML-format articles the same deep
 * link treatment as Markdown: every h2-h6 gets a stable slug id (author ids
 * are reserved first so generated ids never collide with them), the
 * codex-anchor permalink, h2/h3 table-of-contents data, relative .md links
 * resolved to help center hrefs, and external hosts marked with target, rel
 * and codex-external. Runs after the sanitizer only; everything it adds is
 * inside the sanitizer allowlist anyway.
 */
final class HeadingPass
{
    /**
     * @return array{html: string, toc: list<array{level: int, text: string, id: string}>}
     */
    public function apply(string $html, string $locale, string $slug): array
    {
        if (trim($html) === '') {
            return ['html' => '', 'toc' => []];
        }

        $dom = $this->load($html);
        $root = $dom->documentElement;

        if (! $root instanceof DOMElement) {
            return ['html' => '', 'toc' => []];
        }

        $xpath = new DOMXPath($dom);
        $toc = $this->applyHeadings($xpath, $root, $locale);
        $this->applyLinks($xpath, $root, $slug);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return ['html' => $out, 'toc' => $toc];
    }

    /**
     * The XML prolog makes libxml read UTF-8, the wrapper div keeps the prolog
     * out of the output and gives one element to iterate, NOIMPLIED and
     * NODEFDTD stop the html/body/doctype wrapping, and libxml 2.9 warns on
     * figure, details and aside, hence the internal error handling.
     */
    private function load(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return list<array{level: int, text: string, id: string}>
     */
    private function applyHeadings(DOMXPath $xpath, DOMElement $root, string $locale): array
    {
        $headings = $this->elements($xpath, './/h2|.//h3|.//h4|.//h5|.//h6', $root);
        $slugger = new HeadingSlugger($locale);

        foreach ($headings as $heading) {
            $existing = $heading->getAttribute('id');

            if ($existing !== '') {
                $slugger->reserve($existing);
            }
        }

        $toc = [];

        foreach ($headings as $heading) {
            $text = trim($heading->textContent);
            $id = $heading->getAttribute('id');

            if ($id === '') {
                $id = $slugger->unique($text);
                $heading->setAttribute('id', $id);
            }

            $level = (int) substr($heading->tagName, 1);

            if ($level <= 3) {
                $toc[] = ['level' => $level, 'text' => $text, 'id' => $id];
            }

            $anchor = $heading->ownerDocument?->createElement('a', '#');

            if ($anchor instanceof DOMElement) {
                $anchor->setAttribute('class', 'codex-anchor');
                $anchor->setAttribute('href', '#'.$id);
                $anchor->setAttribute('aria-label', (string) __('lin-codex::lin-codex.anchor_label', ['heading' => $text], $locale));
                $heading->appendChild($anchor);
            }
        }

        return $toc;
    }

    private function applyLinks(DOMXPath $xpath, DOMElement $root, string $slug): void
    {
        $internalHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'));

        foreach ($this->elements($xpath, './/a[@href]', $root) as $link) {
            $href = $link->getAttribute('href');
            $resolved = ArticlePath::resolve($slug, $href);

            if ($resolved !== null) {
                $link->setAttribute('href', ArticlePath::href($resolved['slug'], $resolved['fragment']));
                $link->setAttribute('data-codex-article', $resolved['slug']);

                continue;
            }

            $host = parse_url($href, PHP_URL_HOST);

            if (! is_string($host) || strtolower($host) === $internalHost) {
                continue;
            }

            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');

            $classes = preg_split('/\s+/', trim($link->getAttribute('class'))) ?: [];
            $classes = array_values(array_filter($classes, static fn (string $token): bool => $token !== ''));

            if (! in_array('codex-external', $classes, true)) {
                $classes[] = 'codex-external';
            }

            $link->setAttribute('class', implode(' ', $classes));
        }
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $expression, DOMElement $context): array
    {
        $list = $xpath->query($expression, $context);
        $elements = [];

        if ($list === false) {
            return [];
        }

        foreach ($list as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
