<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\ArticleSet;
use FinityLabs\LinCodex\Sources\Filesystem\PathFingerprint;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Freshness is proven on a temp copy of the fixture tree so files can be
 * edited, added and removed. Nothing waits on the clock: touch() with an explicit timestamp
 * sidesteps the one-second mtime granularity.
 */
beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/lin-codex-docs-'.uniqid();
    File::copyDirectory($this->fixtureDocsPath(), $this->tmp);
    clearstatcache(true);

    config()->set('lin-codex.sources.filesystem.paths', [$this->tmp]);
    $this->app->forgetInstance(FilesystemSource::class);
    $this->source = app(FilesystemSource::class);
    $this->file = $this->tmp.'/en/05-duplicate.md';
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

function linCodexFreshFilesystemSource(): FilesystemSource
{
    app()->forgetInstance(FilesystemSource::class);

    return app(FilesystemSource::class);
}

function linCodexTitleOf(FilesystemSource $source, string $slug): ?string
{
    return $source->findBySlug($slug)?->translation('en')?->title;
}

it('picks up an edited file with a bumped mtime on the same instance', function (): void {
    expect(linCodexTitleOf($this->source, 'duplicate'))->toBe('First');

    file_put_contents($this->file, "---\ntitle: Edited\n---\n\nChanged.\n");
    touch($this->file, (int) filemtime($this->file) + 5);
    clearstatcache(true);

    expect(linCodexTitleOf($this->source, 'duplicate'))->toBe('Edited');
});

it('misses an edit that preserves the mtime until the cache entry is forgotten', function (): void {
    expect(linCodexTitleOf($this->source, 'duplicate'))->toBe('First');

    $mtime = (int) filemtime($this->file);
    file_put_contents($this->file, "---\ntitle: Stale\n---\n\nx\n");
    touch($this->file, $mtime);
    clearstatcache(true);

    expect(linCodexTitleOf($this->source, 'duplicate'))->toBe('First');

    Cache::forget($this->source->cacheKey($this->tmp));

    expect(linCodexTitleOf(linCodexFreshFilesystemSource(), 'duplicate'))->toBe('Stale');
});

it('picks up an added file on the same instance', function (): void {
    expect($this->source->findBySlug('new'))->toBeNull();

    file_put_contents($this->tmp.'/en/07-new.md', "---\ntitle: New\n---\n\nx\n");
    clearstatcache(true);

    $added = $this->source->findBySlug('new');

    expect($added)->not->toBeNull()
        ->and($added->order)->toBe(7)
        ->and($added->translation('en')->title)->toBe('New');
});

it('drops a deleted file on the same instance', function (): void {
    expect($this->source->findBySlug('no-title'))->not->toBeNull();

    unlink($this->tmp.'/en/no-title.md');
    clearstatcache(true);

    expect($this->source->findBySlug('no-title'))->toBeNull();
});

it('serves a matching cache entry across instances and rescans on a fingerprint mismatch', function (): void {
    $this->source->all();
    $key = $this->source->cacheKey($this->tmp);

    expect(Cache::has($key))->toBeTrue();

    $planted = new ArticleData(
        slug: 'planted',
        parentSlug: null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: [],
        translations: ['en' => new TranslationData('en', 'Planted', null, 'Planted body.', 'Planted body.')],
    );

    Cache::forever($key, ['fingerprint' => PathFingerprint::of($this->tmp), 'set' => new ArticleSet(['planted' => $planted])]);

    $fresh = linCodexFreshFilesystemSource();

    expect($fresh->findBySlug('planted'))->not->toBeNull()
        ->and($fresh->findBySlug('intro'))->toBeNull();

    touch($this->file, (int) filemtime($this->file) + 5);
    clearstatcache(true);

    expect($fresh->findBySlug('intro'))->not->toBeNull()
        ->and($fresh->findBySlug('planted'))->toBeNull();
});

it('ignores a corrupt cache entry', function (): void {
    Cache::forever($this->source->cacheKey($this->tmp), 'garbage');

    expect(array_keys(linCodexFreshFilesystemSource()->all()))->toHaveCount(10);
});
