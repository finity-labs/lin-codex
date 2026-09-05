<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use Symfony\Component\Finder\Finder;

/**
 * Walks one docs path ("{path}/{locale}/**.md|html") into ArticleFile
 * records plus the warnings raised while reading: a file whose front matter
 * does not parse (skipped), a root index file (skipped) and a file name that
 * had to be normalised to a slug (loaded). No merging happens here; the
 * assembler does that per slug.
 *
 * Directory walking uses Symfony Finder directly: it is a hard requirement
 * of laravel/framework on every supported Laravel (11 to 13), so it is
 * always installed next to this package, and it gives natural sort, relative
 * pathnames and dot-file skipping for free.
 *
 * Locale folders are the immediate subfolders whose name matches
 * FilePath::LOCALE_PATTERN, not the languages list in the settings: a folder
 * for a language the settings do not know yet still loads, and Phase 4
 * decides what to show. Any other folder ("images", "shared") is skipped
 * silently.
 *
 * SCAN_VERSION is part of the file source's cache key. Bump it whenever the
 * parsing rules change (here, in the assembler or in the pure toolkit), so
 * an entry cached under the old rules is not served after an upgrade.
 */
final class DocsScanner
{
    public const SCAN_VERSION = 1;

    /**
     * @return array{files: list<ArticleFile>, warnings: list<SourceWarning>}
     */
    public function scan(string $docsPath): array
    {
        if (! is_dir($docsPath)) {
            return ['files' => [], 'warnings' => []];
        }

        $files = [];
        $warnings = [];

        $localeFolders = (new Finder)
            ->directories()
            ->in($docsPath)
            ->depth(0)
            ->sortByName();

        foreach ($localeFolders as $folder) {
            $locale = $folder->getFilename();

            if (! FilePath::isLocaleFolder($locale)) {
                continue;
            }

            $this->scanLocale($folder->getPathname(), $locale, $files, $warnings);
        }

        return ['files' => $files, 'warnings' => $warnings];
    }

    /**
     * @param  list<ArticleFile>  $files
     * @param  list<SourceWarning>  $warnings
     */
    private function scanLocale(string $localeDir, string $locale, array &$files, array &$warnings): void
    {
        $finder = (new Finder)
            ->files()
            ->in($localeDir)
            ->name(['*.md', '*.html'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->sortByName(true);

        foreach ($finder as $file) {
            $path = $file->getPathname();
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $derived = FilePath::derive($relative);

            if ($derived === null) {
                $warnings[] = new SourceWarning(
                    SourceWarningKind::InvalidSlug,
                    $path,
                    null,
                    $locale,
                    sprintf('"%s" has no usable slug and was skipped.', $relative),
                );

                continue;
            }

            $read = FrontMatter::read($file->getContents());

            if ($read['error'] !== null) {
                $warnings[] = new SourceWarning(SourceWarningKind::InvalidFrontMatter, $path, $derived['slug'], $locale, $read['error']);

                continue;
            }

            if ($derived['normalised']) {
                $warnings[] = new SourceWarning(
                    SourceWarningKind::InvalidSlug,
                    $path,
                    $derived['slug'],
                    $locale,
                    sprintf('file name normalised to slug "%s"', $derived['slug']),
                );
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePath());

            $files[] = new ArticleFile(
                $path,
                $locale,
                $relativePath !== '' ? $locale.'/'.$relativePath : $locale,
                $derived['slug'],
                $derived['order'],
                $derived['isSection'],
                FilePath::format($relative),
                $read['data'],
                ltrim($read['body'], "\r\n"),
            );
        }
    }
}
