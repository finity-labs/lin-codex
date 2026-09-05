<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

use FinityLabs\LinCodex\Enums\ArticleFormat;

/**
 * Cache key for one rendered article. The key embeds the content hash, so an
 * edit produces a new key and the old entry is simply orphaned; the renderer
 * fingerprint does the same for config and extension changes. Locale and
 * slug are part of the key because translated titles and resolved article
 * links depend on them.
 *
 * The generation is a counter held in the render cache store and bumped by
 * codex:cache-clear (ArticleRenderer::bumpGeneration()). A bump orphans every
 * entry at once, which is how a store without tags or prefix scans "forgets"
 * rendered HTML; the ttl, when set, bounds how long the orphans linger.
 */
final class RenderCacheKey
{
    public const PREFIX = 'lin-codex:render:';

    /**
     * @return string the prefix followed by a 64-character hex sha256
     */
    public static function make(string $fingerprint, int $generation, string $body, ArticleFormat $format, string $locale, string $slug): string
    {
        $parts = [
            $fingerprint,
            (string) $generation,
            $format->key(),
            $locale,
            $slug,
            hash('sha256', $body),
        ];

        return self::PREFIX.hash('sha256', implode('|', $parts));
    }
}
