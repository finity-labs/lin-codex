<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use Illuminate\Support\Facades\Cache;

const LIN_CODEX_GENERATION_MARKDOWN = "# Title\n\nBody.";

function linCodexGenerationKey(ArticleRenderer $renderer): string
{
    return $renderer->cacheKey(LIN_CODEX_GENERATION_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
}

it('starts at generation 1 when nothing is stored', function (): void {
    expect(app(ArticleRenderer::class)->generation())->toBe(1)
        ->and(Cache::has(ArticleRenderer::GENERATION_KEY))->toBeFalse();
});

it('bumps the generation and orphans every cached render', function (): void {
    $renderer = app(ArticleRenderer::class);
    $renderer->render(LIN_CODEX_GENERATION_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
    $first = linCodexGenerationKey($renderer);

    expect(Cache::has($first))->toBeTrue();

    Cache::put($first, new RenderedArticle('<p>stale</p>', [], 'stale', []));

    expect($renderer->bumpGeneration())->toBe(2);

    $second = linCodexGenerationKey($renderer);
    $rendered = $renderer->render(LIN_CODEX_GENERATION_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

    expect($second)->not->toBe($first)
        ->and($rendered->html)->toContain('<h1')
        ->and($rendered->html)->not->toContain('stale')
        ->and(Cache::has($first))->toBeTrue()
        ->and(Cache::has($second))->toBeTrue();
});

it('reads the generation on every call, not from a memo', function (): void {
    $renderer = app(ArticleRenderer::class);
    $before = linCodexGenerationKey($renderer);

    expect($renderer->generation())->toBe(1);

    Cache::put(ArticleRenderer::GENERATION_KEY, 7);

    expect($renderer->generation())->toBe(7)
        ->and(linCodexGenerationKey($renderer))->not->toBe($before);
});

it('uses the configured render store', function (): void {
    config()->set('cache.stores.codex-other', ['driver' => 'array']);
    config()->set('lin-codex.render.cache.store', 'codex-other');

    expect($this->freshRenderer()->bumpGeneration())->toBe(2)
        ->and(Cache::store('codex-other')->get(ArticleRenderer::GENERATION_KEY))->toBe(2)
        ->and(Cache::has(ArticleRenderer::GENERATION_KEY))->toBeFalse();
});
