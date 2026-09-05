<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Cache;

use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Cache;

/**
 * The whole sequence behind codex:cache-clear, reusable from uninstall:
 * bump the render generation (orphaning every rendered article on the
 * render store), flush the file sources (fingerprints and parsed sets on
 * the default store) and forget the in-memory search index under
 * InMemoryIndex::CACHE_KEY on the default store, where InMemoryIndex
 * writes it.
 *
 * Nothing here touches the context index (rebuilt per request) or the
 * stylesheet hash (in memory only).
 */
final class CacheClearer
{
    public function __construct(
        private readonly ArticleRenderer $renderer,
        private readonly FilesystemSource $files,
    ) {}

    public function clear(): CacheClearReport
    {
        $generation = $this->renderer->bumpGeneration();
        $fileEntries = $this->files->flush();
        $searchIndexWasCached = Cache::forget(InMemoryIndex::CACHE_KEY);

        return new CacheClearReport($generation, $fileEntries, $searchIndexWasCached);
    }
}
