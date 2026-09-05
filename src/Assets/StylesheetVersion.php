<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Assets;

/**
 * Where the prebuilt stylesheet lives and the hash that versions its URL.
 *
 * The hash is memoised per process (a singleton) and never written to a
 * cache store, so a cache clear has nothing to bust here. A copy published
 * under public/vendor/lin-codex is linked with the same "?v=" value, taken
 * from the package file, so an upgrade without a re-publish shows up as a
 * hash that no longer matches the published bytes.
 */
final class StylesheetVersion
{
    private ?string $hash = null;

    public function path(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/codex.css';
    }

    public function hash(): string
    {
        return $this->hash ??= (hash_file('xxh128', $this->path()) ?: '0');
    }
}
