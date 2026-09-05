<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sync;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\DefaultLocale;
use FinityLabs\LinCodex\Sources\Filesystem\FrontMatterWriter;
use FinityLabs\LinCodex\Sources\Filesystem\ImagePathRewriter;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Writes database articles back to files: the service behind codex:export.
 *
 * Every article the database holds is written, published or not, because
 * this is an operator tool. Where a file goes: an article with a recorded
 * docs-relative source_path is written there, under the first configured
 * docs path that already holds that file, else under the first configured
 * path; an absolute source_path under a configured path is made relative
 * first, any other absolute path is ignored; without a source_path the file
 * is {locale}/{slug}.md (.html for HTML articles), or {locale}/{slug}/index.*
 * when the article has children. --path replaces the root for every article.
 * Other locales reuse the default locale's path with the first segment
 * swapped ("en/02-users/index.md" becomes "de/02-users/index.md"), which is
 * where the file source looks for them.
 *
 * Bodies carry media-route URLs in the database; they are turned back into
 * paths relative to the file being written, and the images they name are
 * copied from the first configured docs path that holds them, only when that
 * path is not the root being written to. Nothing is fetched from the media
 * disk: file articles are the only ones that can have images beside them.
 * Front matter comes from FrontMatterWriter, so the default-locale file
 * carries the shared keys and other locales get title and excerpt only, the
 * way the assembler reads them. A file that cannot be written is reported
 * under failed and the run carries on.
 */
final class ArticleExporter
{
    public function __construct(
        private readonly DatabaseSource $database,
        private readonly FilesystemSource $files,
        private readonly FrontMatterWriter $writer,
        private readonly DefaultLocale $defaultLocale,
    ) {}

    /**
     * @throws InvalidArgumentException when no docs path is configured and no path was given
     */
    public function export(ExportOptions $options): SyncReport
    {
        $paths = array_map(static fn (string $path): string => rtrim($path, '/'), $this->files->paths());
        $override = $options->path !== null ? rtrim($options->path, '/') : null;

        if ($override === null && $paths === []) {
            throw new InvalidArgumentException('No docs path configured (lin-codex.sources.filesystem.paths) and no --path given.');
        }

        $report = new SyncReport;
        $set = $this->database->set();

        foreach ($set->warnings() as $warning) {
            $report->warning($warning->message());
        }

        $default = $this->defaultLocale->get();
        $media = (string) config('lin-codex.routes.media', '/codex/media');

        /** @var array<string, array<string, true>> $images root => docs-relative image path => true */
        $images = [];

        foreach ($set->articles as $article) {
            if ($options->only !== [] && ! in_array($article->slug, $options->only, true)) {
                continue;
            }

            $locales = $options->locale === null
                ? $article->locales()
                : array_values(array_intersect($article->locales(), [$options->locale]));

            if ($locales === []) {
                continue;
            }

            $primary = $this->primaryRelativePath($article, $default, $paths);
            $root = $override ?? $this->rootFor($primary, $paths);

            foreach ($locales as $locale) {
                $translation = $article->translation($locale);

                if ($translation === null) {
                    continue;
                }

                $relative = $locale === $default ? $primary : (string) preg_replace('~^[^/]+~', $locale, $primary);
                $relativised = ImagePathRewriter::relativise($translation->body, $article->format, dirname($relative), $media);
                $contents = $this->writer->write($article, $translation, $relativised['body'], $locale === $default, $relative);
                $target = $root.'/'.$relative;
                $existed = is_file($target);

                foreach ($relativised['images'] as $image) {
                    $images[$root][$image] = true;
                }

                if (! $options->dryRun) {
                    try {
                        $this->put($target, $contents);
                    } catch (Throwable $e) {
                        $report->failed($locale, $article->slug, $e->getMessage());

                        continue;
                    }
                }

                $existed ? $report->updated($locale, $article->slug) : $report->created($locale, $article->slug);
            }
        }

        if (! $options->dryRun) {
            $this->copyImages($images, $paths, $report);
        }

        return $report;
    }

    /**
     * The docs-relative path of the default-locale file.
     *
     * @param  list<string>  $paths
     */
    private function primaryRelativePath(ArticleData $article, string $default, array $paths): string
    {
        $source = $article->sourcePath;

        if ($source !== null && $source !== '') {
            $source = str_replace('\\', '/', $source);

            if (! $this->isAbsolute($source)) {
                return ltrim($source, '/');
            }

            foreach ($paths as $path) {
                $prefix = str_replace('\\', '/', $path).'/';

                if (str_starts_with($source, $prefix)) {
                    return substr($source, strlen($prefix));
                }
            }
        }

        return $default.'/'.$article->slug
            .($article->isSection ? '/index' : '')
            .($article->format === ArticleFormat::Html ? '.html' : '.md');
    }

    /**
     * The first configured docs path that already holds the file, else the
     * first configured path. Only called when at least one path exists.
     *
     * @param  list<string>  $paths
     */
    private function rootFor(string $relative, array $paths): string
    {
        foreach ($paths as $path) {
            if (is_file($path.'/'.$relative)) {
                return $path;
            }
        }

        return $paths[0];
    }

    /**
     * @throws RuntimeException when the folder or the file cannot be written
     */
    private function put(string $target, string $contents): void
    {
        File::ensureDirectoryExists(dirname($target));

        if (! is_dir(dirname($target)) || @file_put_contents($target, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write %s.', $target));
        }
    }

    /**
     * Copy every referenced image from the first configured docs path that
     * holds it into the root its article was written to, skipping roots
     * that are that docs path already.
     *
     * @param  array<string, array<string, true>>  $images
     * @param  list<string>  $paths
     */
    private function copyImages(array $images, array $paths, SyncReport $report): void
    {
        foreach ($images as $root => $files) {
            foreach (array_keys($files) as $image) {
                $holder = null;

                foreach ($paths as $path) {
                    if (is_file($path.'/'.$image)) {
                        $holder = $path;

                        break;
                    }
                }

                if ($holder === null) {
                    $report->warning(sprintf('Image %s is referenced but no configured docs path holds it.', $image));

                    continue;
                }

                if (realpath($holder) === realpath((string) $root)) {
                    continue;
                }

                try {
                    File::ensureDirectoryExists(dirname($root.'/'.$image));
                    File::copy($holder.'/'.$image, $root.'/'.$image);
                } catch (Throwable) {
                    $report->warning(sprintf('Image %s could not be copied to %s.', $image, $root));
                }
            }
        }
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:/~', $path) === 1;
    }
}
