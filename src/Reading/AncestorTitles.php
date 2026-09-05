<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Reading;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Sources\SlugPath;

/**
 * The ancestor walk shared by the reader's breadcrumbs and the search hit's
 * section path, so "Users › Roles" is spelled the same in both places.
 *
 * Ancestor articles are necessarily visible when the article is (the
 * gate's ancestor rule), so only the locale pick can drop one; slugs on the
 * path that are not articles (folder groups) are skipped without a gap.
 */
final class AncestorTitles
{
    /**
     * Ancestor articles of $slug that have a translation under the locale
     * rule, root-first.
     *
     * @param  array<string, ArticleData>  $all  the source's all() map
     *
     * @return list<array{slug: string, title: string}>
     */
    public static function for(string $slug, array $all, string $locale, LocaleResolver $locales): array
    {
        $titles = [];

        for ($parent = SlugPath::parentOf($slug); $parent !== null; $parent = SlugPath::parentOf($parent)) {
            if (! isset($all[$parent])) {
                continue;
            }

            $ancestor = $locales->pick($all[$parent], $locale);

            if ($ancestor !== null) {
                array_unshift($titles, ['slug' => $parent, 'title' => $ancestor->translation->title]);
            }
        }

        return $titles;
    }
}
