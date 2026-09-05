<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Enums\ArticleFormat;

/**
 * Rewrites relative image references in an article body to the media route
 * ("{media}/{locale}/{path}") by lexical path normalisation. Nothing here
 * touches the disk (no canonical path lookup, no existence check), so a
 * screenshot added after a scan is served without a rescan. Containment is
 * enforced here lexically (an image must sit inside a locale folder) and
 * again by canonical path in the media controller. Absolute, scheme, protocol-relative
 * and fragment targets stay as written; reference-style Markdown images are
 * left alone. Pure.
 *
 * rewrite() and relativise() are a pair: the scanner rewrites a file's
 * relative image paths to media URLs on the way in, and the exporter turns
 * those media URLs back into paths relative to the file it writes on the
 * way out. Both match the same image syntax per format, so a body that
 * went through one comes back unchanged through the other.
 */
final class ImagePathRewriter
{
    private const NOT_RELATIVE = '~^([a-z][a-z0-9+.\-]*:|//|/|#)~i';

    private const MARKDOWN_IMAGE = '/(!\[[^\]]*\]\(\s*<?)([^\s()<>]+)(>?(?:\s+"[^"]*"|\s+\'[^\']*\')?\s*\))/';

    private const HTML_IMAGE = '/(<img\b[^>]*\bsrc=)(["\'])([^"\']*)\2/i';

    /**
     * @param  string  $relativeDir  the article file's folder relative to the docs root with original folder names: "en", "en/02-users"
     * @param  string  $mediaPrefix  config('lin-codex.routes.media'), e.g. "/codex/media"
     */
    public static function rewrite(string $body, ArticleFormat $format, string $relativeDir, string $mediaPrefix): string
    {
        $prefix = rtrim($mediaPrefix, '/');

        return match ($format) {
            ArticleFormat::Markdown => preg_replace_callback(
                self::MARKDOWN_IMAGE,
                static function (array $matches) use ($relativeDir, $prefix): string {
                    $resolved = self::resolve($relativeDir, $matches[2]);

                    return $matches[1].($resolved === null ? $matches[2] : $prefix.'/'.$resolved).$matches[3];
                },
                $body,
            ) ?? $body,
            ArticleFormat::Html => preg_replace_callback(
                self::HTML_IMAGE,
                static function (array $matches) use ($relativeDir, $prefix): string {
                    $resolved = self::resolve($relativeDir, $matches[3]);

                    return $matches[1].$matches[2].($resolved === null ? $matches[3] : $prefix.'/'.$resolved).$matches[2];
                },
                $body,
            ) ?? $body,
        };
    }

    /**
     * The inverse of rewrite(): media route targets under the prefix become
     * paths relative to $relativeDir, anything else is left as written. The
     * second element lists the docs-relative image paths that were rewritten
     * (without query or fragment), in document order, once each, so an export
     * can copy them next to the file.
     *
     * @param  string  $relativeDir  the folder of the file being written, relative to the docs root: "en", "en/02-users"
     * @param  string  $mediaPrefix  config('lin-codex.routes.media'), e.g. "/codex/media"
     *
     * @return array{body: string, images: list<string>}
     */
    public static function relativise(string $body, ArticleFormat $format, string $relativeDir, string $mediaPrefix): array
    {
        $prefix = rtrim($mediaPrefix, '/').'/';

        /** @var list<string> $images */
        $images = [];

        /** @var array<string, true> $seen */
        $seen = [];

        $relativise = static function (string $target) use ($relativeDir, $prefix, &$images, &$seen): string {
            $cut = strcspn($target, '?#');
            $path = substr($target, 0, $cut);
            $suffix = substr($target, $cut);

            if (! str_starts_with($path, $prefix)) {
                return $target;
            }

            $docsRelative = substr($path, strlen($prefix));

            if ($docsRelative === '') {
                return $target;
            }

            if (! isset($seen[$docsRelative])) {
                $seen[$docsRelative] = true;
                $images[] = $docsRelative;
            }

            return self::relativePath($relativeDir, $docsRelative).$suffix;
        };

        $body = match ($format) {
            ArticleFormat::Markdown => preg_replace_callback(
                self::MARKDOWN_IMAGE,
                static fn (array $matches): string => $matches[1].$relativise($matches[2]).$matches[3],
                $body,
            ) ?? $body,
            ArticleFormat::Html => preg_replace_callback(
                self::HTML_IMAGE,
                static fn (array $matches): string => $matches[1].$matches[2].$relativise($matches[3]).$matches[2],
                $body,
            ) ?? $body,
        };

        return ['body' => $body, 'images' => $images];
    }

    /**
     * The normalised docs-relative path ("en/images/reset.png?v=2") or null
     * when the target must stay as written: not a relative path, climbs out
     * of the docs root, or ends up outside a locale folder (fewer than two
     * segments), because the route is {media}/{locale}/{path}.
     */
    public static function resolve(string $relativeDir, string $target): ?string
    {
        if ($target === '' || preg_match(self::NOT_RELATIVE, $target) === 1) {
            return null;
        }

        $cut = strcspn($target, '?#');
        $path = substr($target, 0, $cut);
        $suffix = substr($target, $cut);

        if ($path === '') {
            return null;
        }

        $segments = [];

        foreach (explode('/', $relativeDir.'/'.$path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        if (count($segments) < 2) {
            return null;
        }

        return implode('/', $segments).$suffix;
    }

    /**
     * The path of $toFile relative to $fromDir, both docs-relative: drop the
     * folder segments the two share (never the file name itself), then one
     * ".." per remaining folder of $fromDir followed by the rest of $toFile.
     */
    private static function relativePath(string $fromDir, string $toFile): string
    {
        $from = array_values(array_filter(explode('/', $fromDir), static fn (string $segment): bool => $segment !== ''));
        $to = explode('/', $toFile);
        $common = 0;
        $limit = min(count($from), count($to) - 1);

        while ($common < $limit && $from[$common] === $to[$common]) {
            $common++;
        }

        return str_repeat('../', count($from) - $common).implode('/', array_slice($to, $common));
    }
}
