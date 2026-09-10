<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which configured languages an article is still missing.
 *
 * The rule is one sentence: a candidate locale is missing when the article
 * has no translation row for it, or has one whose title or body is blank
 * after trim. "Filled" means a non-blank title AND a non-blank body, because
 * a row with a title and no body is what the tab action leaves behind when
 * somebody starts a translation and walks away, and that is exactly the row
 * an admin wants the AI to finish.
 *
 * The default locale is never a candidate: it is the source the translation
 * is made from, so an empty default row is a content problem, not a missing
 * translation. Candidates keep the order of CodexSettings::$languages, so a
 * picker and a report list languages the way the admin arranged them.
 *
 * for() reads the loaded translations relation when there is one and queries
 * only otherwise, so a bulk action that eager-loads pays one query for the
 * whole selection while the job's per-locale re-check, running on a freshly
 * fetched article, still sees rows written since dispatch.
 */
final class MissingTranslations
{
    public function __construct(private readonly LocaleResolver $locales) {}

    /**
     * The configured non-default locales, in settings order.
     *
     * @return list<string>
     */
    public function candidates(): array
    {
        return array_values(array_diff($this->locales->languages(), [$this->locales->defaultLocale()]));
    }

    /**
     * Candidates the article lacks: no row, or a row whose trimmed title or
     * body is ''.
     *
     * @return list<string>
     */
    public function for(Article $article): array
    {
        $present = [];

        foreach ($this->rows($article) as $row) {
            if (trim((string) $row->title) !== '' && trim((string) $row->body) !== '') {
                $present[] = $row->locale;
            }
        }

        return array_values(array_filter(
            $this->candidates(),
            fn (string $code): bool => ! in_array($code, $present, true),
        ));
    }

    /**
     * True when $locale is a candidate the article lacks; false for the
     * default locale and for an unconfigured locale.
     */
    public function isMissing(Article $article, string $locale): bool
    {
        return in_array($locale, $this->for($article), true);
    }

    /**
     * @return Collection<int, ArticleTranslation>
     */
    private function rows(Article $article): Collection
    {
        if ($article->relationLoaded('translations')) {
            return $article->translations;
        }

        return $article->translations()->get(['locale', 'title', 'body']);
    }
}
