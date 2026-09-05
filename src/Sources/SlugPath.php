<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources;

use Illuminate\Support\Str;

/**
 * Slugs are slash paths of kebab-case segments ("users/reset-password") and
 * the article tree is derived from them: the parent of a slug is the slug
 * minus its last segment. This is the one place the segment rule lives;
 * sources and importers validate through it rather than with their own
 * pattern.
 */
final class SlugPath
{
    public static function parentOf(string $slug): ?string
    {
        return str_contains($slug, '/') ? Str::beforeLast($slug, '/') : null;
    }

    public static function lastSegment(string $slug): string
    {
        return Str::afterLast($slug, '/');
    }

    /**
     * A fallback label for a segment: "reset-password" becomes "Reset password".
     */
    public static function humanise(string $segment): string
    {
        return Str::ucfirst(str_replace(['-', '_'], ' ', $segment));
    }

    public static function isValidSegment(string $segment): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $segment) === 1;
    }
}
