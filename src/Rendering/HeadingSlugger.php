<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering;

use Illuminate\Support\Str;

/**
 * Builds heading ids from heading text: a locale-aware ASCII slug, a
 * "section" fallback when nothing survives, and -2, -3 suffixes for
 * duplicates within one document. Shared by the Markdown and HTML pipelines
 * so both produce identical ids for identical text.
 */
final class HeadingSlugger
{
    /** @var array<string, true> */
    private array $used = [];

    public function __construct(private readonly string $locale = 'en') {}

    /**
     * The two-letter language Str::slug transliterates for; junk falls back to English.
     */
    public static function language(string $locale): string
    {
        return preg_match('/^[a-z]{2}/i', $locale, $matches) === 1 ? strtolower($matches[0]) : 'en';
    }

    public function base(string $text, int $maxLength = 255): string
    {
        $slug = Str::slug($text, '-', self::language($this->locale));
        $slug = rtrim(mb_substr($slug, 0, $maxLength), '-');

        return $slug === '' ? 'section' : $slug;
    }

    /**
     * The base slug, or base-2, base-3 ... when it is already taken; the returned id is registered.
     */
    public function unique(string $text, int $maxLength = 255): string
    {
        $base = $this->base($text, $maxLength);

        if (! isset($this->used[$base])) {
            $this->used[$base] = true;

            return $base;
        }

        $suffix = 2;

        while (isset($this->used[$base.'-'.$suffix])) {
            $suffix++;
        }

        $this->used[$base.'-'.$suffix] = true;

        return $base.'-'.$suffix;
    }

    /**
     * Mark an author-supplied id as taken so generated ids never collide with it.
     */
    public function reserve(string $id): void
    {
        $this->used[$id] = true;
    }

    public function reset(): void
    {
        $this->used = [];
    }
}
