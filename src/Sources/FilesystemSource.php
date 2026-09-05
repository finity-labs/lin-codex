<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources;

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Sources\Filesystem\ArticleAssembler;
use FinityLabs\LinCodex\Sources\Filesystem\DocsScanner;
use FinityLabs\LinCodex\Sources\Filesystem\PathFingerprint;
use Illuminate\Support\Facades\Cache;

/**
 * Articles read from the configured docs paths
 * (lin-codex.sources.filesystem.paths), each holding one folder per locale.
 * The per-path sets are folded in config order, so a later path replaces
 * an earlier one per slug, whole article.
 *
 * Freshness: every call computes PathFingerprint::of() per path (one stat
 * per file, no reads) and compares it with the instance memo, then with
 * the cache entry. Only a mismatch rescans; the parsed set is stored with
 * Cache::forever() and no TTL, because the entry is replaced the moment the
 * fingerprint changes. An edited, added or deleted file is therefore seen
 * on the next read with no manual cache clear.
 *
 * The cache key carries the scan version, the docs path, the default
 * locale (it decides which file supplies the shared metadata), the renderer
 * fingerprint (search text is derived through the renderer) and the media
 * prefix (it is baked into the bodies), so a change to any of them starts
 * a fresh entry on its own. flush() forgets cacheKeys() and the memo to
 * force a rescan when the fingerprint's blind spot is hit; codex:cache-clear
 * calls it.
 */
final class FilesystemSource implements ContentSource
{
    /**
     * @var array<string, array{fingerprint: string, set: ArticleSet}>
     */
    private array $memo = [];

    public function __construct(
        private readonly DocsScanner $scanner,
        private readonly ArticleAssembler $assembler,
        private readonly DefaultLocale $defaultLocale,
        private readonly ArticleRenderer $renderer,
    ) {}

    /**
     * @return list<string> config('lin-codex.sources.filesystem.paths') filtered to strings, in order
     */
    public function paths(): array
    {
        $paths = config('lin-codex.sources.filesystem.paths', []);

        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, 'is_string'));
    }

    /**
     * The fold of the per-path sets in config order (later path wins).
     */
    public function set(): ArticleSet
    {
        $defaultLocale = $this->defaultLocale->get();

        $sets = array_map(
            fn (string $docsPath): ArticleSet => $this->setFor($docsPath, $defaultLocale),
            $this->paths(),
        );

        return ArticleSet::fold(...$sets);
    }

    public function cacheKey(string $docsPath): string
    {
        return $this->keyFor($docsPath, $this->defaultLocale->get());
    }

    /**
     * @return list<string> one per configured path; codex:cache-clear forgets these
     */
    public function cacheKeys(): array
    {
        $defaultLocale = $this->defaultLocale->get();

        return array_map(
            fn (string $docsPath): string => $this->keyFor($docsPath, $defaultLocale),
            $this->paths(),
        );
    }

    /**
     * Forget every cached set (one per configured path) on the default store
     * and drop the instance memo. Returns the number of cache entries that
     * existed. codex:cache-clear calls this; a same-second edit that keeps
     * the newest mtime is the one case the fingerprint cannot see, and this
     * is the manual override for it.
     */
    public function flush(): int
    {
        $count = 0;

        foreach ($this->cacheKeys() as $key) {
            if (Cache::forget($key)) {
                $count++;
            }
        }

        $this->memo = [];

        return $count;
    }

    /**
     * @return array<string, ArticleData>
     */
    public function all(): array
    {
        return $this->set()->all();
    }

    public function findBySlug(string $slug): ?ArticleData
    {
        return $this->set()->findBySlug($slug);
    }

    /**
     * @return list<TreeNode>
     */
    public function tree(): array
    {
        return $this->set()->tree();
    }

    /**
     * @return list<ArticleData>
     */
    public function findByContext(ContextType $type, string $key, ?string $panelId = null): array
    {
        return $this->set()->findByContext($type, $key, $panelId);
    }

    /**
     * @return list<SearchDocument>
     */
    public function allForSearch(): array
    {
        return $this->set()->allForSearch();
    }

    /**
     * @return list<SourceWarning>
     */
    public function warnings(): array
    {
        return $this->set()->warnings();
    }

    private function keyFor(string $docsPath, string $defaultLocale): string
    {
        return 'lin-codex:source:fs:'.hash('sha256', implode('|', [
            (string) DocsScanner::SCAN_VERSION,
            $docsPath,
            $defaultLocale,
            $this->renderer->fingerprint(),
            (string) config('lin-codex.routes.media', '/codex/media'),
        ]));
    }

    private function setFor(string $docsPath, string $defaultLocale): ArticleSet
    {
        $live = PathFingerprint::of($docsPath);
        $key = $this->keyFor($docsPath, $defaultLocale);

        if (isset($this->memo[$key]) && $this->memo[$key]['fingerprint'] === $live) {
            return $this->memo[$key]['set'];
        }

        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $live && ($cached['set'] ?? null) instanceof ArticleSet) {
            $this->memo[$key] = ['fingerprint' => $live, 'set' => $cached['set']];

            return $cached['set'];
        }

        $scan = $this->scanner->scan($docsPath);
        $set = $this->assembler->assemble($scan['files'], $defaultLocale, $scan['warnings']);

        Cache::forever($key, ['fingerprint' => $live, 'set' => $set]);
        $this->memo[$key] = ['fingerprint' => $live, 'set' => $set];

        return $set;
    }
}
