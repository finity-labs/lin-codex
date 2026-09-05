<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Enums\ArticleFormat;

/**
 * One article file as the scanner found it: where it lives, which locale
 * folder it sits in, what its path says about slug and order, and its raw
 * front matter and body. Nothing is normalised yet; the assembler merges
 * the locale files of one slug into an ArticleData and applies the rules.
 */
final readonly class ArticleFile
{
    /**
     * @param  string  $path  absolute file path
     * @param  string  $locale  locale folder name
     * @param  string  $relativeDir  "en" or "en/02-users": locale plus the file's folder relative to the locale folder, original names (the media route serves original paths)
     * @param  string  $slug  path-derived (FilePath::derive), before any front matter slug override
     * @param  int  $order  prefix-derived, 0 when none
     * @param  ArticleFormat  $format  from the extension
     * @param  array<string, mixed>  $frontMatter  raw parsed mapping, not normalised
     * @param  string  $body  after FrontMatter::read() with leading blank lines removed, before H1 removal and image rewrite
     */
    public function __construct(
        public string $path,
        public string $locale,
        public string $relativeDir,
        public string $slug,
        public int $order,
        public bool $isSection,
        public ArticleFormat $format,
        public array $frontMatter,
        public string $body,
    ) {}
}
