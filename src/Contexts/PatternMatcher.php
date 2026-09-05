<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contexts;

use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Enums\ContextType;

/**
 * The context pattern language, in three lines: a `class:` key matches the
 * exact class name (leading backslash ignored, no inheritance walk); a
 * `route:` key matches the exact route name or, with a trailing `*`, any
 * name with that prefix; a `url:` key matches the normalised request path
 * only, where `*` stands for exactly one segment and `**` for any depth,
 * including none.
 *
 * The framework's own glob helper was not used because its `*` crosses `/`,
 * so `/users/*` would also match `/users/1/edit`. The translation here is
 * `preg_quote()` followed by three replacements; replacing the quoted `**`
 * before the quoted `*` is load-bearing, otherwise `**` becomes two
 * single-segment wildcards.
 */
final class PatternMatcher
{
    /**
     * `users/1` -> `/users/1`; `/users/1/` -> `/users/1`; `` -> `/`;
     * `//users//` -> `/users`. Never decodes and never resolves dots.
     */
    public static function normalisePath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        return '/'.implode('/', $segments);
    }

    public static function normaliseClass(string $class): string
    {
        return ltrim($class, '\\');
    }

    /**
     * @param  string  $path  already normalised; the pattern is normalised here
     */
    public static function urlMatches(string $pattern, string $path): bool
    {
        $pattern = self::normalisePath($pattern);

        if ($pattern === $path) {
            return true;
        }

        $regex = preg_quote($pattern, '~');
        $regex = str_replace('/\*\*', '(?:/.*)?', $regex);
        $regex = str_replace('\*\*', '.*', $regex);
        $regex = str_replace('\*', '[^/]*', $regex);

        return preg_match('~^'.$regex.'$~', $path) === 1;
    }

    /**
     * Exact, or a prefix match when the pattern ends in `*`. A `*` anywhere
     * else is literal.
     */
    public static function routeMatches(string $pattern, string $name): bool
    {
        return $pattern === $name
            || (str_ends_with($pattern, '*') && str_starts_with($name, substr($pattern, 0, -1)));
    }

    /**
     * Whether the context sorts after exact matches: a class key never does,
     * a route key with a trailing `*` does, a url key with any `*` does.
     */
    public static function isWildcard(ContextData $context): bool
    {
        return match ($context->type) {
            ContextType::PageClass => false,
            ContextType::Route => str_ends_with($context->key, '*'),
            ContextType::Url => str_contains($context->key, '*'),
        };
    }

    /**
     * Dispatch by type; a null page field matches nothing.
     */
    public static function matches(ContextData $context, PageContext $page): bool
    {
        return match ($context->type) {
            ContextType::PageClass => $page->pageClass !== null && self::normaliseClass($context->key) === $page->pageClass,
            ContextType::Route => $page->routeName !== null && self::routeMatches($context->key, $page->routeName),
            ContextType::Url => self::urlMatches($context->key, $page->path),
        };
    }
}
