<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\SearchField;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Reading\AncestorTitles;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;

/**
 * The one search entry: what the JSON API endpoint and the help drawer
 * call. Everything below it (parser, limiter, the two candidate paths,
 * matcher, ranker, snippets) is composed here and nowhere else.
 *
 * The steps run in a fixed order for a reason each:
 * - the minimum length comes before the limiter, so typing one character
 *   costs nothing and never counts against the viewer;
 * - the limiter comes before ContentSource::all(), so a throttled viewer
 *   never loads the corpus; a throttled result is a value flagged
 *   rateLimited with the seconds to wait, never an exception (the API maps
 *   it to 429, the drawer to a message);
 * - all() is called exactly once per search, then the gate filters the map
 *   (a hidden section takes its subtree with it), then the locale is
 *   resolved, and only then are candidates collected, so no full-text
 *   clause and no index lookup ever sees an article the viewer may not
 *   read;
 * - SQL and the in-memory index are pre-filters only; the ranker's matcher
 *   re-verifies every candidate in PHP, which is what makes the hit set,
 *   the order and the snippets identical on MySQL, MariaDB, PostgreSQL,
 *   SQLite and a file-only install.
 *
 * lin-codex.source picks the candidate paths: "database" reads the
 * database pre-filter only, "composite" reads it and the in-memory index
 * restricted to file-only articles, anything else reads the index over the
 * whole map. The composite merge cannot produce duplicates because all()
 * is keyed by slug and the database wins a slug whole, so the file-only
 * pass skips every slug that carries a database id.
 *
 * The total is the capped hit count; there is no pagination. Stateless
 * and auto-wired per resolution, never a provider singleton: a singleton
 * would capture the source and the request.
 */
final class Searcher
{
    public function __construct(
        private readonly ContentSource $source,
        private readonly ArticleGate $gate,
        private readonly LocaleResolver $locales,
        private readonly QueryParser $parser,
        private readonly SearchLimiter $limiter,
        private readonly DatabaseCandidates $database,
        private readonly InMemoryCandidates $memory,
        private readonly Ranker $ranker,
        private readonly SnippetBuilder $snippets,
        private readonly ArticleRenderer $renderer,
    ) {}

    /**
     * @param  string|null  $locale  null follows the app locale
     * @param  int|null  $limit  null takes lin-codex.search.limit; always capped at lin-codex.search.max_limit
     */
    public function search(string $query, Viewer $viewer, ?string $locale = null, ?int $limit = null): SearchResult
    {
        $parsed = $this->parser->parse($query);

        if ($parsed === null) {
            return SearchResult::empty($query);
        }

        $retryAfter = $this->limiter->check($viewer);

        if ($retryAfter !== null) {
            return SearchResult::throttled($query, $retryAfter);
        }

        $all = $this->source->all();
        $visible = $this->gate->filter($all, $viewer);
        $locale = $this->locales->resolve($locale);

        $candidates = match ((string) config('lin-codex.source', 'filesystem')) {
            'database' => $this->database->find($parsed, $viewer, $locale, $visible)->candidates,
            'composite' => [
                ...$this->database->find($parsed, $viewer, $locale, $visible)->candidates,
                ...$this->memory->find($locale, $all, $visible, true),
            ],
            default => $this->memory->find($locale, $all, $visible, false),
        };

        $scored = array_slice($this->ranker->rank($candidates, $parsed), 0, $this->effectiveLimit($limit));
        $hits = array_map(fn (ScoredCandidate $scored): SearchHit => $this->hit($scored, $parsed, $all, $locale), $scored);

        return new SearchResult($hits, $query, count($hits), false, null);
    }

    /**
     * The limit a search with this argument runs under: the argument, else
     * lin-codex.search.limit, never below one and never above
     * lin-codex.search.max_limit. Public so the JSON API reports the clamp
     * it applied in meta.limit without repeating the formula.
     */
    public function effectiveLimit(?int $limit): int
    {
        $default = (int) config('lin-codex.search.limit', 10);
        $max = (int) config('lin-codex.search.max_limit', 50);

        return min(max(1, $limit ?? $default), max(1, $max));
    }

    /**
     * One hit: the section path from the shared ancestor walk and a snippet
     * over the text the matched field points at. A body hit snippets the
     * body, an excerpt hit the excerpt, and a title or keyword hit shows the
     * excerpt when there is one (unmarked unless a token happens to occur
     * there), else the start of the body.
     *
     * @param  array<string, ArticleData>  $all  the source's all() map
     */
    private function hit(ScoredCandidate $scored, ParsedQuery $parsed, array $all, string $locale): SearchHit
    {
        $article = $scored->candidate->article;
        $translation = $scored->candidate->choice->translation;
        $field = $scored->match->matchedField;

        $text = match ($field) {
            SearchField::Body => $this->bodyText($article, $translation),
            SearchField::Excerpt => (string) $translation->excerpt,
            default => $translation->excerpt !== null && $translation->excerpt !== ''
                ? $translation->excerpt
                : $this->bodyText($article, $translation),
        };

        return new SearchHit(
            $article->slug,
            $translation->title,
            array_column(AncestorTitles::for($article->slug, $all, $locale, $this->locales), 'title'),
            $this->snippets->build($parsed, $text),
            $field,
            $scored->score,
            $scored->candidate->choice->isFallback,
        );
    }

    /**
     * The plain body text a snippet is cut from. File articles carry it in
     * searchText already (extracted at scan time); database articles carry
     * the folded search_text blob there, which cannot be shown, so the body
     * is rendered through the render cache under the same slug the indexer
     * and the reader use, which makes it a cache hit whenever either ran.
     * Only called for the hits that need a body snippet.
     */
    private function bodyText(ArticleData $article, TranslationData $translation): string
    {
        if ($article->id === null) {
            return (string) $translation->searchText;
        }

        return $this->renderer->plainText(
            $translation->body,
            $article->format,
            $translation->locale,
            ArticlePath::renderSlug($article->slug, $article->isSection),
        );
    }
}
