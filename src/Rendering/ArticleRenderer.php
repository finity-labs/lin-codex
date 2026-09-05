<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The one entry point for turning an article body into safe HTML, TOC data
 * and search text. Switches on the article format between the Markdown and
 * HTML pipelines and caches the result under a key derived from the content
 * hash, the format, the locale, the slug, the renderer fingerprint and the
 * render generation.
 *
 * Cache TTL semantics (lin-codex.render.cache.ttl): null keeps entries
 * forever, which is safe because the key changes whenever the body or the
 * renderer configuration changes, so an entry can only ever be orphaned,
 * never stale. An integer is a lifetime in seconds and acts as a memory
 * bound. Zero (or a negative number) bypasses the cache entirely and is
 * never passed to the store, because Laravel treats a non-positive TTL as a
 * delete.
 *
 * The generation is an integer kept under GENERATION_KEY on the render
 * store (default 1 when absent). It is part of every cache key, so
 * bumpGeneration() orphans every rendered article at once without the
 * store having to support tags or prefix deletes; codex:cache-clear is the
 * caller. It is read on every render call rather than memoised, so a bump
 * from a console process is seen by a long-running worker on its next
 * render; the cost is one cache read per render call.
 */
final class ArticleRenderer
{
    public const GENERATION_KEY = 'lin-codex:render:generation';

    private ?string $fingerprint = null;

    public function __construct(
        private readonly MarkdownPipeline $markdown,
        private readonly HtmlPipeline $html,
    ) {}

    public function render(string $body, ArticleFormat $format, string $locale, string $slug = ''): RenderedArticle
    {
        $ttl = config('lin-codex.render.cache.ttl');

        if ($ttl !== null && (int) $ttl <= 0) {
            return $this->renderUncached($body, $format, $locale, $slug);
        }

        return $this->store()->remember(
            $this->cacheKey($body, $format, $locale, $slug),
            $ttl === null ? null : (int) $ttl,
            fn (): RenderedArticle => $this->renderUncached($body, $format, $locale, $slug),
        );
    }

    public function renderUncached(string $body, ArticleFormat $format, string $locale, string $slug = ''): RenderedArticle
    {
        return match ($format) {
            ArticleFormat::Markdown => $this->markdown->render($body, $locale, $slug),
            ArticleFormat::Html => $this->html->render($body, $locale, $slug),
        };
    }

    /**
     * Search text for either format; goes through the cache like render().
     */
    public function plainText(string $body, ArticleFormat $format, string $locale, string $slug = ''): string
    {
        return $this->render($body, $format, $locale, $slug)->plainText;
    }

    public function cacheKey(string $body, ArticleFormat $format, string $locale, string $slug = ''): string
    {
        return RenderCacheKey::make($this->fingerprint(), $this->generation(), $body, $format, $locale, $slug);
    }

    /**
     * Memoized per instance: config cannot change under a request, and the
     * pipelines memoize their own config the same way. Tests that change
     * config use a fresh renderer.
     */
    public function fingerprint(): string
    {
        return $this->fingerprint ??= (new RendererFingerprint($this->markdown, $this->html))->hash();
    }

    /**
     * The current render generation, read from the render store per call
     * and never memoised, so a bump from a console process is seen by a
     * long-running worker on its next render. Never below 1.
     */
    public function generation(): int
    {
        return max(1, (int) $this->store()->get(self::GENERATION_KEY, 1));
    }

    /**
     * Increment the generation and store it forever on the render store,
     * orphaning every cached render at once. Returns the new generation.
     */
    public function bumpGeneration(): int
    {
        $next = $this->generation() + 1;
        $this->store()->forever(self::GENERATION_KEY, $next);

        return $next;
    }

    /**
     * The render store: lin-codex.render.cache.store when set, else the
     * default cache store. Rendered articles and the generation live here.
     */
    private function store(): Repository
    {
        $store = config('lin-codex.render.cache.store');

        return Cache::store(is_string($store) ? $store : null);
    }
}
