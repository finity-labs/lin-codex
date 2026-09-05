<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Cache;

/**
 * What one CacheClearer::clear() call did: the render generation it moved
 * to, the number of file-source entries it forgot, and whether the
 * in-memory search index was cached before it was forgotten. Rendered
 * entries are never counted because the render store cannot be inspected;
 * the generation bump orphans them all.
 */
final readonly class CacheClearReport
{
    public function __construct(
        public int $generation,
        public int $fileEntries,
        public bool $searchIndexWasCached,
    ) {}
}
