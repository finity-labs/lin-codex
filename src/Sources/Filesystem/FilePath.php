<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use Illuminate\Support\Str;

/**
 * Derives an article's slug, order and section flag from its path inside
 * a locale folder. Numeric prefixes ("02-users/01-roles.md") order files
 * and are stripped with the same rule ArticlePath applies to link targets,
 * so a link written with the prefix lands on the same slug. Pure.
 */
final class FilePath
{
    /**
     * A locale folder name: en, de, pt-BR, zh_Hant. Shared by the scanner
     * (which folders are locales) and the media route (which first path
     * segment is a locale).
     */
    public const LOCALE_PATTERN = '[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*';

    public static function isLocaleFolder(string $name): bool
    {
        return preg_match('/^'.self::LOCALE_PATTERN.'$/', $name) === 1;
    }

    /**
     * From a path relative to the locale folder ("02-users/01-roles.md",
     * forward or back slashes). An index file names its folder's section,
     * so "02-users/index.md" is the "users" section with order 2. Null for
     * a root index file or a segment that slugs to nothing; normalised is
     * true when any segment was not already in slug form.
     *
     * @return array{slug: string, order: int, isSection: bool, normalised: bool}|null
     */
    public static function derive(string $relativePath): ?array
    {
        $path = trim(str_replace('\\', '/', $relativePath), '/');

        if ($path === '') {
            return null;
        }

        $parts = explode('/', $path);
        $lastIndex = count($parts) - 1;
        $parts[$lastIndex] = pathinfo($parts[$lastIndex], PATHINFO_FILENAME);

        $slugs = [];
        $orders = [];
        $normalised = false;

        foreach ($parts as $part) {
            $stripped = ArticlePath::stripOrderPrefix($part);
            $slug = Str::slug($stripped['segment']);

            if ($slug === '') {
                return null;
            }

            if ($slug !== $stripped['segment']) {
                $normalised = true;
            }

            $slugs[] = $slug;
            $orders[] = $stripped['order'];
        }

        $isSection = false;

        if ($slugs[count($slugs) - 1] === 'index') {
            array_pop($slugs);
            array_pop($orders);
            $isSection = true;
        }

        if ($slugs === []) {
            return null;
        }

        return [
            'slug' => implode('/', $slugs),
            'order' => $orders[count($orders) - 1] ?? 0,
            'isSection' => $isSection,
            'normalised' => $normalised,
        ];
    }

    /**
     * The article format from the file extension: .html (any case) is HTML,
     * everything else is Markdown.
     */
    public static function format(string $relativePath): ArticleFormat
    {
        return strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'html'
            ? ArticleFormat::Html
            : ArticleFormat::Markdown;
    }
}
