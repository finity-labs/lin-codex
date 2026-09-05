<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;

function renderHostile(string $body): string
{
    return (new MarkdownPipeline)->render($body, 'en', 'intro')->html;
}

it('drops block-level raw html entirely', function (): void {
    $html = renderHostile("<script>alert(1)</script>\n\n<div onclick=\"x()\">hi</div>");

    expect($html)->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('<div')
        ->not->toContain('alert(1)')
        ->and(trim($html))->toBe('');
});

it('strips inline raw html tags but keeps their text', function (): void {
    $html = renderHostile('Text <script>alert(1)</script> and <span onclick="x()">hi</span> end');

    expect($html)->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('<span')
        ->toContain('alert(1)')
        ->toContain('hi');
});

it('drops javascript: destinations from links and images', function (): void {
    expect(renderHostile('[JS](javascript:alert(1))'))->toContain('<a>JS</a>')
        ->and(renderHostile('![x](javascript:alert(1))'))->toMatch('/<img[^>]*\bsrc=""/');
});

it('caps blockquote nesting and stays fast on a nesting flood', function (): void {
    $start = hrtime(true);
    $html = renderHostile(str_repeat('>', 20000).' x');
    $seconds = (hrtime(true) - $start) / 1e9;

    expect($seconds)->toBeLessThan(2.0)
        ->and(substr_count($html, '<blockquote>'))->toBeLessThanOrEqual(50);
});

it('renders delimiter and table floods without an exception', function (string $body): void {
    expect(renderHostile($body))->toBeString();
})->with([
    'bracket flood' => str_repeat('[', 20000).'x'.str_repeat(']', 20000),
    'emphasis flood' => str_repeat('*a', 20000),
    'wide table' => str_repeat('| a ', 3000)."|\n".str_repeat('|---', 3000)."|\n|x|\n",
]);

it('reads the nesting limit from config when the environment is built', function (): void {
    config()->set('lin-codex.render.limits.max_nesting_level', 3);

    $html = (new MarkdownPipeline)->render('>>>>> five deep', 'en', 'intro')->html;

    expect(substr_count($html, '<blockquote>'))->toBe(3);
});
