<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

const LIN_CODEX_CACHE_CLEAR_COMMAND_MARKDOWN = "# Clear me\n\nBody.";

beforeEach(function (): void {
    config()->set('lin-codex.source', 'filesystem');
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs')]);
    $this->forgetSources();
});

/**
 * @return list<bool> one Cache::has() per configured docs path
 */
function linCodexCacheClearCommandCachedKeys(): array
{
    return array_map(static fn (string $key): bool => Cache::has($key), app(FilesystemSource::class)->cacheKeys());
}

it('is discovered under its name', function (): void {
    expect(Artisan::all())->toHaveKey('codex:cache-clear');
});

it('clears every package cache and reports each line', function (): void {
    $renderer = app(ArticleRenderer::class);
    $renderer->render(LIN_CODEX_CACHE_CLEAR_COMMAND_MARKDOWN, ArticleFormat::Markdown, 'en', 'clear');
    $renderKey = $renderer->cacheKey(LIN_CODEX_CACHE_CLEAR_COMMAND_MARKDOWN, ArticleFormat::Markdown, 'en', 'clear');
    app(FilesystemSource::class)->set();
    Cache::forever(InMemoryIndex::CACHE_KEY, ['hash' => 'x', 'documents' => []]);

    expect(Cache::has($renderKey))->toBeTrue()
        ->and(linCodexCacheClearCommandCachedKeys())->toBe([true]);

    expect(Artisan::call('codex:cache-clear'))->toBe(0);

    $output = Artisan::output();

    expect($output)->toMatch('/Rendered HTML .*generation 2 \(old entries expire with their ttl\)/')
        ->and($output)->toMatch('/File sources .*1 entry forgotten/')
        ->and($output)->toMatch('/Search index .*forgotten/')
        ->and($output)->toMatch('/Context index .*not cached \(rebuilt per request\)/')
        ->and($output)->toMatch('/Stylesheet hash .*not cached \(in memory only\)/')
        ->and(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse()
        ->and(linCodexCacheClearCommandCachedKeys())->toBe([false])
        ->and(app(ArticleRenderer::class)->generation())->toBe(2)
        ->and($renderer->cacheKey(LIN_CODEX_CACHE_CLEAR_COMMAND_MARKDOWN, ArticleFormat::Markdown, 'en', 'clear'))->not->toBe($renderKey);
});

it('says nothing cached when cold', function (): void {
    $this->artisan('codex:cache-clear')
        ->expectsOutputToContain('Rendered HTML')
        ->expectsOutputToContain('File sources')
        ->expectsOutputToContain('Search index')
        ->expectsOutputToContain('Context index')
        ->expectsOutputToContain('Stylesheet hash')
        ->assertExitCode(0);

    expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse()
        ->and(linCodexCacheClearCommandCachedKeys())->toBe([false])
        ->and(app(ArticleRenderer::class)->generation())->toBe(2);

    Artisan::call('codex:cache-clear');
    $output = Artisan::output();

    expect($output)->toContain('generation 3')
        ->and($output)->toMatch('/File sources .*nothing cached/')
        ->and($output)->toMatch('/Search index .*nothing cached/')
        ->and(substr_count($output, 'nothing cached'))->toBe(2);
});
