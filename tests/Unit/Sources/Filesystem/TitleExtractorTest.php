<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Sources\Filesystem\TitleExtractor;

it('consumes a leading atx heading and the blank line after it', function (): void {
    expect(TitleExtractor::extract("# Reset password\n\nBody", ArticleFormat::Markdown))
        ->toBe(['title' => 'Reset password', 'body' => 'Body']);
});

it('finds the first h1 after other content', function (): void {
    expect(TitleExtractor::extract("Intro line\n\n# Title\nBody", ArticleFormat::Markdown))
        ->toBe(['title' => 'Title', 'body' => "Intro line\n\nBody"]);
});

it('drops closing hashes and collapses whitespace', function (): void {
    expect(TitleExtractor::extract("# Reset  password #\n", ArticleFormat::Markdown)['title'])
        ->toBe('Reset password');
});

it('strips inline emphasis and code markers from the title', function (): void {
    expect(TitleExtractor::extract("# Using **roles** and `code`\n", ArticleFormat::Markdown)['title'])
        ->toBe('Using roles and code');
});

it('consumes a setext heading', function (): void {
    expect(TitleExtractor::extract("Setext title\n=====\n\nBody", ArticleFormat::Markdown))
        ->toBe(['title' => 'Setext title', 'body' => 'Body']);
});

it('ignores headings inside code fences', function (): void {
    $result = TitleExtractor::extract("```\n# not a title\n```\n\n# Real\nx", ArticleFormat::Markdown);

    expect($result['title'])->toBe('Real')
        ->and($result['body'])->toContain("```\n# not a title\n```");
});

it('returns null and the body untouched without a level-one heading', function (): void {
    expect(TitleExtractor::extract("## Only h2\n\nBody", ArticleFormat::Markdown))
        ->toBe(['title' => null, 'body' => "## Only h2\n\nBody"]);
});

it('handles crlf line endings', function (): void {
    $result = TitleExtractor::extract("---\r\n# CRLF title\r\n\r\nBody\r\n", ArticleFormat::Markdown);

    expect($result['title'])->toBe('CRLF title')
        ->and(str_contains($result['body'], 'Body'))->toBeTrue()
        ->and(str_contains($result['body'], 'CRLF title'))->toBeFalse();
});

it('consumes an html h1 and decodes entities', function (): void {
    expect(TitleExtractor::extract('<h1 class="x">Permissions &amp; roles</h1>'."\n".'<p>Body</p>', ArticleFormat::Html))
        ->toBe(['title' => 'Permissions & roles', 'body' => '<p>Body</p>']);
});

it('leaves html without a usable h1 alone', function (): void {
    expect(TitleExtractor::extract('<p>No heading</p>', ArticleFormat::Html))
        ->toBe(['title' => null, 'body' => '<p>No heading</p>'])
        ->and(TitleExtractor::extract('<h1></h1><p>x</p>', ArticleFormat::Html))
        ->toBe(['title' => null, 'body' => '<h1></h1><p>x</p>']);
});
