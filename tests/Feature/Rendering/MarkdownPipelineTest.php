<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

it('strips valid front matter from the output and exposes it as metadata', function (): void {
    $result = (new MarkdownPipeline)->render("---\ntitle: Hello\norder: 2\n---\n\n# Body", 'en', 'intro');

    expect($result->html)->not->toContain('title:')
        ->not->toContain('---')
        ->toContain('<h1>Body</h1>')
        ->and($result->metadata['front_matter'])->toBe(['title' => 'Hello', 'order' => 2])
        ->and($result->metadata['warnings'])->toBe([]);
});

it('still renders when the front matter is invalid yaml and reports a warning', function (): void {
    $result = (new MarkdownPipeline)->render("---\ntitle: [unclosed\n---\n\n# Body", 'en', 'intro');

    expect($result->html)->toContain('<h1>Body</h1>')
        ->not->toContain('---')
        ->and($result->metadata['front_matter'])->toBeNull()
        ->and($result->metadata['warnings'])->toBe(['invalid_front_matter']);
});

it('reports null front matter for a body without any', function (): void {
    $result = (new MarkdownPipeline)->render("# Body\n\nText.", 'en', 'intro');

    expect($result->metadata['front_matter'])->toBeNull()
        ->and($result->metadata['warnings'])->toBe([]);
});

it('detects external links against the configured app url', function (): void {
    config()->set('app.url', 'https://app.test');

    $html = (new MarkdownPipeline)->render('[A](https://app.test/x) [B](http://localhost/x)', 'en', 'intro')->html;

    expect($html)->toMatch('~<a href="https://app\.test/x">A</a>~')
        ->toMatch('~<a[^>]*target="_blank"[^>]*href="http://localhost/x">B</a>~');
});

it('slugs heading ids for the render locale and resets between documents', function (): void {
    $pipeline = new MarkdownPipeline;
    $body = '## Ärger über Straße';

    expect($pipeline->render($body, 'de', 'intro')->html)->toContain('id="aerger-ueber-strasse"')
        ->and($pipeline->render($body, 'en', 'intro')->html)->toContain('id="arger-uber-strasse"')
        ->and($pipeline->render($body, 'en', 'intro')->html)->toContain('id="arger-uber-strasse"');
});

it('extracts plain text from the rendered html', function (): void {
    $result = (new MarkdownPipeline)->render("## Reset a password\n\nSome **bold** text.", 'en', 'intro');

    expect($result->plainText)->toBe('Reset a password Some bold text.');
});

it('exposes the extension list and a json-safe config for fingerprinting', function (): void {
    $pipeline = new MarkdownPipeline;

    expect($pipeline->extensionClasses())->toContain(HeadingPermalinkExtension::class)
        ->toContain(ExternalLinkExtension::class)
        ->and(json_encode($pipeline->environmentConfig()))->not->toBeFalse();
});
