<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

/**
 * Which links point at a file to save rather than a page to read: an href
 * whose path ends in one of `lin-codex.render.download_extensions`. Both
 * pipelines stamp those with a `download` attribute carrying the file name,
 * so a browser saves the document instead of leaving the article for it.
 *
 * The attribute only forces a download for a same-origin URL; a browser
 * opens a cross-origin one as before. It is stamped all the same — the
 * decision is the browser's, and a host may serve its own files from a CDN
 * that sends a content-disposition header of its own.
 */
final class DownloadLink
{
    /** @var list<string> */
    public const DEFAULT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv', 'rtf',
    ];

    /**
     * The file name to save the href under, or null for a link that is not
     * a file: no path, no extension, or one outside the configured list.
     */
    public static function fileName(string $href): ?string
    {
        $path = parse_url(trim($href), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $name = rawurldecode(basename($path));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === '' || ! in_array($extension, self::extensions(), true)) {
            return null;
        }

        return $name;
    }

    /** @return list<string> lower-case, without dots */
    public static function extensions(): array
    {
        $configured = config('lin-codex.render.download_extensions', self::DEFAULT_EXTENSIONS);

        if (! is_array($configured)) {
            return self::DEFAULT_EXTENSIONS;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $extension): string => is_string($extension) ? strtolower(ltrim(trim($extension), '.')) : '',
            $configured,
        ), static fn (string $extension): bool => $extension !== ''));
    }
}
