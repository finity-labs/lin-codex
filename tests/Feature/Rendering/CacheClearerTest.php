<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Cache\CacheClearer;
use FinityLabs\LinCodex\Cache\CacheClearReport;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Cache;

const LIN_CODEX_CLEARER_MARKDOWN = "# Clear me\n\nBody.";

beforeEach(function (): void {
    config()->set('lin-codex.source', 'filesystem');
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs')]);
    $this->forgetSources();
});

/**
 * @return list<bool> one Cache::has() per configured path
 */
function linCodexClearerCachedKeys(): array
{
    return array_map(static fn (string $key): bool => Cache::has($key), app(FilesystemSource::class)->cacheKeys());
}

it('bumps the render generation, flushes the file sources, forgets the search index and reports each', function (): void {
    $renderer = app(ArticleRenderer::class);
    $renderer->render(LIN_CODEX_CLEARER_MARKDOWN, ArticleFormat::Markdown, 'en', 'clear');
    $renderKey = $renderer->cacheKey(LIN_CODEX_CLEARER_MARKDOWN, ArticleFormat::Markdown, 'en', 'clear');
    app(FilesystemSource::class)->set();
    Cache::forever(InMemoryIndex::CACHE_KEY, ['hash' => 'x', 'documents' => []]);

    expect(Cache::has($renderKey))->toBeTrue()
        ->and(linCodexClearerCachedKeys())->toBe([true]);

    $report = app(CacheClearer::class)->clear();

    expect($report)->toBeInstanceOf(CacheClearReport::class)
        ->and($report->generation)->toBe(2)
        ->and($report->fileEntries)->toBe(1)
        ->and($report->searchIndexWasCached)->toBeTrue()
        ->and(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse()
        ->and(linCodexClearerCachedKeys())->toBe([false])
        ->and($renderer->cacheKey(LIN_CODEX_CLEARER_MARKDOWN, ArticleFormat::Markdown, 'en', 'clear'))->not->toBe($renderKey);

    linCodexAssertNoModels($report);
});

it('reports nothing cached when cold', function (): void {
    $report = app(CacheClearer::class)->clear();

    expect($report->generation)->toBe(2)
        ->and($report->fileEntries)->toBe(0)
        ->and($report->searchIndexWasCached)->toBeFalse();
});

it('clears twice without error', function (): void {
    $clearer = app(CacheClearer::class);

    expect($clearer->clear()->generation)->toBe(2)
        ->and($clearer->clear()->generation)->toBe(3)
        ->and(app(ArticleRenderer::class)->generation())->toBe(3);
});
