<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Markdown\Callout\CalloutType;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use Illuminate\Support\Facades\Lang;

function renderCallout(string $markdown, string $locale = 'en'): RenderedArticle
{
    return (new MarkdownPipeline)->render($markdown, $locale, 'intro');
}

it('renders a marked blockquote as a callout aside with title and body', function (): void {
    $result = renderCallout("> [!WARNING]\n> Body line");

    expect($result->html)->toBe(implode("\n", [
        '<aside class="codex-callout codex-callout--warning" role="note">',
        '<p class="codex-callout__title"><span class="codex-callout__icon" aria-hidden="true"></span>Warning</p>',
        '<div class="codex-callout__body">',
        '<p>Body line</p>',
        '</div>',
        '</aside>',
        '',
    ]))->and($result->plainText)->toBe('Warning Body line');
});

it('uses the text after the marker as a custom title', function (): void {
    $html = renderCallout("> [!warning] Before you delete a user\n> Body")->html;

    expect($html)->toContain('<span class="codex-callout__icon" aria-hidden="true"></span>Before you delete a user</p>')
        ->and($html)->toContain("<div class=\"codex-callout__body\">\n<p>Body</p>\n</div>")
        ->and($html)->toContain('codex-callout--warning');
});

it('renders a marker-only callout with the default title and an empty body', function (): void {
    $html = renderCallout('> [!Tip]')->html;

    expect($html)->toContain('<span class="codex-callout__icon" aria-hidden="true"></span>Tip</p>')
        ->and($html)->toContain('<div class="codex-callout__body"></div>');
});

it('maps every type to its class suffix', function (string $key): void {
    $html = renderCallout('> [!'.strtoupper($key)."]\n> x")->html;

    expect($html)->toContain('class="codex-callout codex-callout--'.$key.'" role="note"');
})->with(CalloutType::keys());

it('leaves unknown markers and plain quotes as blockquotes', function (string $markdown): void {
    $html = renderCallout($markdown)->html;

    expect($html)->toContain('<blockquote>')
        ->and($html)->not->toContain('codex-callout');
})->with([
    'unknown marker' => "> [!DANGER]\n> x",
    'plain quote' => '> Plain quote',
]);

it('keeps paragraphs, lists and code blocks inside the body', function (): void {
    $html = renderCallout("> [!NOTE]\n> First\n>\n> Second\n>\n> - item\n>\n> ```\n> code\n> ```")->html;

    $body = substr($html, (int) strpos($html, '<div class="codex-callout__body">'));

    expect($body)->toContain('<p>First</p>')
        ->and($body)->toContain('<p>Second</p>')
        ->and($body)->toContain('<li>item</li>')
        ->and($body)->toContain("<pre><code>code\n</code></pre>")
        ->and($body)->toEndWith("</div>\n</aside>\n");
});

it('keeps inline markup after the marker in the body (title is plain text)', function (): void {
    $html = renderCallout("> [!NOTE] See **this**\n> body")->html;

    expect($html)->toContain('<span class="codex-callout__icon" aria-hidden="true"></span>See</p>')
        ->and($html)->toContain("<div class=\"codex-callout__body\">\n<p><strong>this</strong>\nbody</p>");
});

it('translates the default title in the render locale, not the app locale', function (): void {
    Lang::addLines(['lin-codex.callouts.warning' => 'Achtung'], 'de', 'lin-codex');

    $html = renderCallout("> [!WARNING]\n> x", 'de')->html;

    expect($html)->toContain('</span>Achtung</p>')
        ->and(app()->getLocale())->toBe('en');
});
