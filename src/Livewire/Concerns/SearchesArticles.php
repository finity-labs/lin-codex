<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Livewire\Concerns;

use FinityLabs\LinCodex\Search\Searcher;
use FinityLabs\LinCodex\Search\SearchResult;

/**
 * The search box shared by the drawer and the help center: the bound query
 * and the call into the Searcher for the current viewer and the captured
 * locale. Requires CapturesPageHelp for viewer() and $locale. What happens
 * when the query changes (updatedQuery()) is component-specific and lives
 * in each component.
 */
trait SearchesArticles
{
    public string $query = '';

    protected function searchResult(?int $limit = null): SearchResult
    {
        return app(Searcher::class)->search($this->query, $this->viewer(), $this->locale, $limit);
    }

    protected function searchMinLength(): int
    {
        return max(1, (int) config('lin-codex.search.min_length', 2));
    }

    protected function hasSearchQuery(): bool
    {
        return mb_strlen(trim($this->query)) >= $this->searchMinLength();
    }
}
