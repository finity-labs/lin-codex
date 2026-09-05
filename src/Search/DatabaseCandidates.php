<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\SearchStrategy;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use Illuminate\Database\Query\Builder;

/**
 * The database pre-filter of a search: one query over the translations
 * joined to their articles that returns the (slug, locale) pairs whose
 * search_text may match the query. SQL is a superset filter only; the PHP
 * matcher (Matcher::matches()) re-verifies every candidate, which is why
 * the engine switch and the LIKE retry can change the cost of a search but
 * never its result set.
 *
 * The clause order is the locked scoping rule: is_published, then
 * visibility (guests see public rows only), then locale, then the match
 * clause, so no full-text clause ever runs over rows the viewer may not
 * see. Ancestor visibility (a hidden section hides its subtree) is not
 * expressible in SQL, so the caller passes the gate-filtered all() map and
 * candidates are built from it through LocaleResolver::pick(); only
 * entries with an id (database articles) are considered and no candidate
 * is ever built from a model.
 *
 * The match clause per driver, when lin-codex.search.engine is "fulltext":
 * - MySQL and MariaDB: MATCH ... AGAINST ('+token* ...' IN BOOLEAN MODE)
 *   through whereFullText(['mode' => 'boolean']).
 * - PostgreSQL: to_tsvector('lang', search_text) @@ to_tsquery('lang', 'token:* & ...')
 *   through whereRaw(): Laravel 11 has no 'raw' mode for whereFullText,
 *   and the language must be interpolated (after validation, see
 *   pgsqlLanguage()) rather than bound, because a bound regconfig would
 *   not match the GIN expression index the migration built.
 * - SQLite, or a query that needsLike(): one LIKE '% token%' per token;
 *   the leading space in every segment of search_text makes this a
 *   word-start match.
 * With the default engine ("like", or any value other than "fulltext")
 * every driver takes the LIKE clause; the index the migration built simply
 * sits unused until the engine is switched.
 * Tokens are [a-z0-9]+ by construction (SearchText::fold()), so boolean
 * mode operators and to_tsquery syntax errors are impossible.
 *
 * A full-text query that returns no rows is retried once with LIKE: the
 * engines drop tokens the index does not carry (InnoDB indexes nothing
 * over innodb_ft_max_token_size) and the retry keeps the result set
 * engine-independent. Rows are capped at lin-codex.search.candidates
 * (200) ordered by slug then locale: enough for a help centre, bounded
 * for the PHP matcher; a query matching more rows than the cap ranks only
 * the first slugs, which is the accepted v1 trade-off.
 */
final class DatabaseCandidates
{
    public function __construct(private readonly LocaleResolver $locales) {}

    /**
     * @param  array<string, ArticleData>  $visible  the gate-filtered all() map (ArticleGate::filter already applied)
     */
    public function find(ParsedQuery $query, Viewer $viewer, string $locale, array $visible): CandidateSet
    {
        $strategy = $this->strategyFor($query);
        $locales = $this->localesFor($locale);
        $matched = $this->matched($query, $viewer, $locales, $strategy);

        if ($matched === [] && $strategy === SearchStrategy::FullText) {
            $strategy = SearchStrategy::Like;
            $matched = $this->matched($query, $viewer, $locales, $strategy);
        }

        $candidates = [];

        foreach ($visible as $slug => $article) {
            if ($article->id === null) {
                continue;
            }

            $choice = $this->locales->pick($article, $locale);

            if ($choice === null || ! isset($matched[$slug][$choice->translation->locale]) || $choice->translation->searchText === null) {
                continue;
            }

            $candidates[] = new Candidate($article, $choice, SearchText::split($choice->translation->searchText));
        }

        return new CandidateSet($candidates, $strategy);
    }

    /**
     * The strategy the engine, the driver and the query call for before any
     * retry: FullText only when lin-codex.search.engine is "fulltext", the
     * query has no short or stopword token and the model's connection is
     * MySQL, MariaDB or PostgreSQL; Like otherwise, including for an
     * unknown engine value.
     */
    public function strategyFor(ParsedQuery $query): SearchStrategy
    {
        if (config('lin-codex.search.engine', 'like') !== 'fulltext' || $query->needsLike()) {
            return SearchStrategy::Like;
        }

        return in_array($this->driver(), ['mysql', 'mariadb', 'pgsql'], true) ? SearchStrategy::FullText : SearchStrategy::Like;
    }

    /**
     * Validated lin-codex.search.pgsql_language, 'simple' when unset or
     * not /^[a-z_]+$/. The same rule the migration applies, so the query
     * and the index agree.
     */
    public static function pgsqlLanguage(): string
    {
        $language = config('lin-codex.search.pgsql_language');

        return is_string($language) && preg_match('/^[a-z_]+$/', $language) === 1 ? $language : 'simple';
    }

    /**
     * The driver of the model's connection, so a host that puts the codex
     * tables on another connection is honoured.
     */
    private function driver(): string
    {
        return (new ArticleTranslation)->getConnection()->getDriverName();
    }

    /**
     * The requested locale, plus the default locale when the fallback rule
     * may show it; pick() decides per article which one stands.
     *
     * @return list<string>
     */
    private function localesFor(string $locale): array
    {
        $locales = [$locale];

        if ($this->locales->fallback() === FallbackBehaviour::ShowDefault) {
            $locales[] = $this->locales->defaultLocale();
        }

        return array_values(array_unique($locales));
    }

    /**
     * The (slug, locale) pairs the SQL pre-filter returns for the strategy.
     * The query is a base query (toBase()) that selects only the slug and
     * the locale, so no model is ever hydrated on the search path.
     *
     * @param  list<string>  $locales
     *
     * @return array<string, array<string, true>>
     */
    private function matched(ParsedQuery $query, Viewer $viewer, array $locales, SearchStrategy $strategy): array
    {
        $t = (new ArticleTranslation)->getTable();
        $a = (new Article)->getTable();
        $column = $t.'.search_text';
        $driver = $this->driver();

        $rows = ArticleTranslation::query()
            ->toBase()
            ->select([$t.'.locale', $a.'.slug'])
            ->join($a, $a.'.id', '=', $t.'.article_id')
            ->where($a.'.is_published', true)
            ->when(! $viewer->isAuthenticated, fn (Builder $q): Builder => $q->where($a.'.visibility', Visibility::Public))
            ->whereIn($t.'.locale', $locales)
            ->where(fn (Builder $q) => $this->applyMatch($q, $driver, $column, $query, $strategy))
            ->orderBy($a.'.slug')
            ->orderBy($t.'.locale')
            ->limit(max(1, (int) config('lin-codex.search.candidates', 200)))
            ->get();

        $matched = [];

        foreach ($rows as $row) {
            $matched[(string) $row->slug][(string) $row->locale] = true;
        }

        return $matched;
    }

    /**
     * The match clause for the driver and the strategy.
     */
    private function applyMatch(Builder $q, string $driver, string $column, ParsedQuery $query, SearchStrategy $strategy): void
    {
        if ($strategy === SearchStrategy::FullText && in_array($driver, ['mysql', 'mariadb'], true)) {
            $q->whereFullText(
                $column,
                implode(' ', array_map(static fn (string $token): string => '+'.$token.'*', $query->tokens)),
                ['mode' => 'boolean'],
            );

            return;
        }

        if ($strategy === SearchStrategy::FullText && $driver === 'pgsql') {
            $language = self::pgsqlLanguage();

            $q->whereRaw(
                sprintf("to_tsvector('%1\$s', %2\$s) @@ to_tsquery('%1\$s', ?)", $language, $q->getGrammar()->wrap($column)),
                [implode(' & ', array_map(static fn (string $token): string => $token.':*', $query->tokens))],
            );

            return;
        }

        foreach ($query->tokens as $token) {
            $q->where($column, 'like', '% '.$token.'%');
        }
    }
}
