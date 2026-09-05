<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * flush() is proven on a temp copy of the docs fixture so a file can be
 * edited while its mtime is pinned, the one change the fingerprint cannot
 * see. The override fixture is the second configured path.
 */
beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/lin-codex-flush-'.uniqid();
    File::copyDirectory($this->fixtureDocsPath(), $this->tmp);
    clearstatcache(true);

    config()->set('lin-codex.source', 'filesystem');
    config()->set('lin-codex.sources.filesystem.paths', [$this->tmp, $this->fixtureDocsPath('docs-override')]);
    $this->forgetSources();
    $this->source = app(FilesystemSource::class);
    $this->file = $this->tmp.'/en/05-duplicate.md';
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

function linCodexFlushTitleOf(FilesystemSource $source, string $slug): ?string
{
    return $source->findBySlug($slug)?->translation('en')?->title;
}

/**
 * @return list<bool> one Cache::has() per configured path, in config order
 */
function linCodexFlushCachedKeys(FilesystemSource $source): array
{
    return array_map(static fn (string $key): bool => Cache::has($key), $source->cacheKeys());
}

it('forgets every docs path entry and the memo', function (): void {
    $this->source->set();

    expect(linCodexFlushCachedKeys($this->source))->toBe([true, true])
        ->and(linCodexFlushTitleOf($this->source, 'duplicate'))->toBe('First');

    $mtime = (int) filemtime($this->file);
    file_put_contents($this->file, "---\ntitle: Flushed\n---\n\nx\n");
    touch($this->file, $mtime);
    clearstatcache(true);

    expect(linCodexFlushTitleOf($this->source, 'duplicate'))->toBe('First');

    expect($this->source->flush())->toBe(2)
        ->and(linCodexFlushCachedKeys($this->source))->toBe([false, false]);

    expect(linCodexFlushTitleOf($this->source, 'duplicate'))->toBe('Flushed')
        ->and(linCodexFlushCachedKeys($this->source))->toBe([true, true]);
});

it('returns zero when nothing is cached', function (): void {
    expect($this->source->flush())->toBe(0)
        ->and(linCodexFlushCachedKeys($this->source))->toBe([false, false]);
});
