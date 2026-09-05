<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use FinityLabs\LinCodex\Rendering\RenderedArticle;

function renderContainer(string $markdown): RenderedArticle
{
    return (new MarkdownPipeline)->render($markdown, 'en', 'intro');
}

it('renders a steps fence around an ordered list as codex-steps', function (): void {
    $result = renderContainer(":::steps\n1. Open the users page\n\n   Second paragraph.\n\n2. Click **Add**\n:::");

    expect($result->html)->toBe(implode("\n", [
        '<ol class="codex-steps">',
        '<li class="codex-step">',
        '<span class="codex-step__number" aria-hidden="true">1</span>',
        '<div class="codex-step__title">Open the users page</div>',
        '<div class="codex-step__body">',
        '<p>Second paragraph.</p>',
        '</div>',
        '</li>',
        '<li class="codex-step">',
        '<span class="codex-step__number" aria-hidden="true">2</span>',
        '<div class="codex-step__title">Click <strong>Add</strong></div>',
        '<div class="codex-step__body"></div>',
        '</li>',
        '</ol>',
        '',
    ]))->and($result->html)->not->toContain('<li>')
        ->and($result->plainText)->toBe('Open the users page Second paragraph. Click Add');
});

it('honours the list start number', function (): void {
    $html = renderContainer(":::steps\n3. Three\n4. Four\n:::")->html;

    expect($html)->toContain('<ol class="codex-steps" start="3">')
        ->and($html)->toContain('<span class="codex-step__number" aria-hidden="true">3</span>')
        ->and($html)->toContain('<span class="codex-step__number" aria-hidden="true">4</span>');
});

it('renders steps content without an ordered list unchanged', function (): void {
    $html = renderContainer(":::steps\nJust text\n\n- bullet\n:::")->html;

    expect($html)->toBe("<p>Just text</p>\n<ul>\n<li>bullet</li>\n</ul>\n")
        ->and($html)->not->toContain('codex-');
});

it('renders a details fence with the argument as summary', function (): void {
    $html = renderContainer(":::details Advanced options\nHidden text\n:::")->html;

    expect($html)->toBe("<details class=\"codex-details\"><summary>Advanced options</summary>\n<p>Hidden text</p>\n</details>\n");
});

it('uses the translated default summary and escapes custom ones', function (): void {
    expect(renderContainer(":::details\nx\n:::")->html)->toContain('<summary>Details</summary>')
        ->and(renderContainer(":::details A & B <c>\nx\n:::")->html)->toContain('<summary>A &amp; B &lt;c&gt;</summary>');
});

it('closes a nested details with its own fence without closing the steps', function (string $open, string $close): void {
    $html = renderContainer("{$open}\n1. Step\n\n   :::details More\n   Hidden\n   :::\n\n2. Next\n{$close}")->html;

    $firstBody = substr($html, (int) strpos($html, '<div class="codex-step__body">'), (int) strpos($html, '</li>'));

    expect($firstBody)->toContain('<details class="codex-details"><summary>More</summary>')
        ->and($firstBody)->toContain('<p>Hidden</p>')
        ->and($html)->toContain('<span class="codex-step__number" aria-hidden="true">2</span>')
        ->and($html)->toContain('<div class="codex-step__title">Next</div>')
        ->and(substr_count($html, '<li class="codex-step">'))->toBe(2)
        ->and($html)->not->toContain(':::');
})->with([
    'longer outer fence' => ['::::steps', '::::'],
    'equal fences' => [':::steps', ':::'],
]);

it('renders unknown containers without a wrapper', function (): void {
    $html = renderContainer(":::unknown\n\nText\n\n:::")->html;

    expect($html)->toBe("<p>Text</p>\n");
});

it('closes an unclosed container at the end of the document', function (): void {
    $html = renderContainer(":::steps\n1. One\n2. Two\n")->html;

    expect(substr_count($html, '<li class="codex-step">'))->toBe(2)
        ->and($html)->toContain('<div class="codex-step__title">Two</div>');
});

it('leaves a bare fence with no open container as paragraph text', function (): void {
    expect(renderContainer("Some text\n:::\nmore")->html)->toBe("<p>Some text\n:::\nmore</p>\n");
});

it('opens with up to three spaces of indentation and not with four', function (): void {
    expect(renderContainer("   :::steps\n1. One\n:::")->html)->toContain('<ol class="codex-steps">')
        ->and(renderContainer("    :::steps\n1. One\n:::")->html)->toStartWith('<pre><code>:::steps');
});

it('keeps figure alt text and captions in the plain text', function (): void {
    expect(renderContainer("![Alt](/storage/codex/a.png \"Caption\")\n\n![Alt](/storage/codex/b.png)")->plainText)->toBe('Alt Caption Alt');
});
