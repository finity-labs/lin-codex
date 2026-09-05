<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use Symfony\Component\Finder\Finder;

/**
 * The freshness signal of one docs path: "{count}:{newest mtime}" over the
 * *.md and *.html files under it (recursive, dot files and VCS folders
 * ignored), or "missing" when the folder does not exist. One stat per file
 * and no reads, so the file source can afford to compute it on every call
 * and rescan only when it changes.
 *
 * The composition is locked (count plus newest mtime) and has a blind spot:
 * a file replaced within the same second as the newest file, or content
 * changed with the mtime preserved (rsync -t, some deploy tools), is not
 * detected. The codex:cache-clear command (Phase 8) is the manual override.
 * Adding the sum of file sizes would close most of the gap at no extra cost,
 * since every file is stat'ed already, if that is ever wanted.
 */
final class PathFingerprint
{
    public static function of(string $docsPath): string
    {
        if (! is_dir($docsPath)) {
            return 'missing';
        }

        $finder = (new Finder)
            ->files()
            ->in($docsPath)
            ->name(['*.md', '*.html'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true);

        $count = 0;
        $newest = 0;

        foreach ($finder as $file) {
            $count++;
            $newest = max($newest, (int) $file->getMTime());
        }

        return $count.':'.$newest;
    }
}
