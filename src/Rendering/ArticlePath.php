<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

/**
 * Resolves relative Markdown file links ("roles.md", "../users/roles.md#x")
 * against the slug of the article being rendered and turns the resulting
 * slug into a help center URL. Pure: no state, no constructor.
 *
 * Numeric ordering prefixes are stripped from every segment, so
 * "[Roles](01-roles.md)" and "[Roles](roles.md)" are the same link, and a
 * trailing "index" collapses to the folder slug, so "[Users](02-users/index.md)"
 * points at the "users" section article.
 */
final class ArticlePath
{
    /**
     * Resolve a link target written as a relative .md path. Returns null
     * when the target is not an article link and must stay as written:
     * absolute or root-relative URLs, fragments, queries, non-.md files,
     * odd segment characters, or traversal above the article root.
     *
     * @return array{slug: string, fragment: string}|null
     */
    public static function resolve(string $currentSlug, string $target): ?array
    {
        if (preg_match('~^([a-z][a-z0-9+.\-]*:|//|/)~i', $target) === 1) {
            return null;
        }

        $hashPosition = strpos($target, '#');
        $path = $hashPosition === false ? $target : substr($target, 0, $hashPosition);
        $fragment = $hashPosition === false ? '' : substr($target, $hashPosition);

        if ($path === '' || str_contains($path, '?')) {
            return null;
        }

        if (strtolower(substr($path, -3)) !== '.md') {
            return null;
        }

        $path = substr($path, 0, -3);

        $segments = explode('/', $currentSlug);
        array_pop($segments);

        foreach (explode('/', $path) as $segment) {
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

            if (preg_match('/^\d+[-_.]$/', $segment) === 1) {
                return null;
            }

            $segment = self::stripOrderPrefix($segment)['segment'];

            if (preg_match('/^[a-z0-9][a-z0-9-]*$/i', $segment) !== 1) {
                return null;
            }

            $segments[] = strtolower($segment);
        }

        if ($segments !== [] && end($segments) === 'index') {
            array_pop($segments);
        }

        if ($segments === []) {
            return null;
        }

        return ['slug' => implode('/', $segments), 'fragment' => $fragment];
    }

    /**
     * Strip a numeric ordering prefix from a file or folder segment:
     * "01-intro" gives intro/1, "10.things" gives things/10. The separator
     * (-, _ or .) and a non-empty remainder are both required, so "2fa" and
     * "007" are not prefixes. The same rule applies to file names on disk
     * (FilePath::derive()) and to link targets, so both agree on the slug.
     *
     * @return array{segment: string, order: ?int}
     */
    public static function stripOrderPrefix(string $segment): array
    {
        if (preg_match('/^(\d+)[-_.](.+)$/', $segment, $matches) === 1) {
            return ['segment' => $matches[2], 'order' => (int) $matches[1]];
        }

        return ['segment' => $segment, 'order' => null];
    }

    /**
     * The slug a section article (index.md) renders under. Sections render
     * under "{slug}/index" so resolve() uses the folder as the link base
     * and "[Roles](roles.md)" inside users/index.md points at users/roles.
     * The file source passes this to plainText() and Phase 4 must pass the
     * same to render(); the render cache key includes it, which is intended.
     */
    public static function renderSlug(string $slug, bool $isSection): string
    {
        return $isSection ? $slug.'/index' : $slug;
    }

    /**
     * The help center URL for a slug, read from config at call time.
     *
     * The optional fragment is a heading id as the renderer writes it, with
     * or without the leading #; it lands as one #id so a field hint's help
     * center fallback (Phase 4 in fin-codex) and the renderer's own article
     * links build the same URL.
     */
    public static function href(string $slug, ?string $fragment = null): string
    {
        $prefix = (string) config('lin-codex.routes.help_center', '/help');
        $id = ltrim((string) $fragment, '#');

        return rtrim($prefix, '/').'/'.$slug.($id === '' ? '' : '#'.$id);
    }
}
