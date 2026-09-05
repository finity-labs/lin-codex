<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\RenderCacheKey;

it('is the prefix followed by a sha256 hex digest', function (): void {
    $key = RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Markdown, 'en', 'a');

    expect($key)
        ->toStartWith('lin-codex:render:')
        ->toHaveLength(17 + 64)
        ->and(substr($key, 17))->toMatch('/^[0-9a-f]{64}$/');
});

it('is deterministic for the same arguments', function (): void {
    expect(RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Markdown, 'en', 'a'))
        ->toBe(RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Markdown, 'en', 'a'));
});

it('changes when any single argument changes', function (): void {
    $base = RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Markdown, 'en', 'a');

    expect(RenderCacheKey::make('fp2', 1, 'body', ArticleFormat::Markdown, 'en', 'a'))->not->toBe($base)
        ->and(RenderCacheKey::make('fp', 2, 'body', ArticleFormat::Markdown, 'en', 'a'))->not->toBe($base)
        ->and(RenderCacheKey::make('fp', 1, 'body!', ArticleFormat::Markdown, 'en', 'a'))->not->toBe($base)
        ->and(RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Html, 'en', 'a'))->not->toBe($base)
        ->and(RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Markdown, 'de', 'a'))->not->toBe($base)
        ->and(RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Markdown, 'en', 'b'))->not->toBe($base);
});

it('never contains the body or the raw enum value', function (): void {
    $body = 'a very recognisable body';
    $key = RenderCacheKey::make('fp', 1, $body, ArticleFormat::Markdown, 'en', 'a');

    expect($key)->not->toContain($body)
        ->and($key)->not->toContain('|');
});

it('keys the format by its word, not its integer backing value', function (): void {
    $expected = 'lin-codex:render:'.hash('sha256', implode('|', ['fp', '1', 'html', 'en', 'a', hash('sha256', 'body')]));

    expect(RenderCacheKey::make('fp', 1, 'body', ArticleFormat::Html, 'en', 'a'))->toBe($expected);
});
