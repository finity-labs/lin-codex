<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use Closure;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The service behind codex:reindex, run at the end of codex:install.
 *
 * The database walk recomputes search_text for every translation row in
 * chunks through SearchTextIndexer, so rows written with the query builder
 * (seeders, migrations, imports) get the blob the saving hook never gave
 * them and rows indexed under an older SearchText version are refreshed.
 * Each row is saved with timestamps off: an index refresh is not an edit,
 * and updated_at is what editors and the export see. save() is a no-op
 * when the recomputed blob equals the stored one, and the saving hook
 * keeps a dirty non-null search_text, so the recomputed value always
 * lands. Indexing renders every body through ArticleRenderer, which warms
 * the render cache under the slug the reader uses as a side effect.
 *
 * The in-memory index is then rebuilt following Searcher's source rule so
 * the entry written is the one a search will read: database mode never
 * reads it (skipped, reported as null), composite mode indexes file-only
 * articles (the database wins a slug whole), any other mode indexes every
 * article the source returns. The entry is forgotten first so a stale or
 * foreign entry is replaced even when the content hash happens to match.
 */
final class SearchReindexer
{
    public function __construct(
        private readonly SearchTextIndexer $indexer,
        private readonly ContentSource $source,
        private readonly InMemoryIndex $memory,
    ) {}

    /**
     * @param  (Closure(int $indexed): void)|null  $progress  called after every row with the running total
     * @param  int  $chunk  rows per chunk of the translation walk
     */
    public function reindex(?Closure $progress = null, int $chunk = 200): ReindexReport
    {
        $count = 0;
        $table = (new ArticleTranslation)->getTable();

        if (Schema::hasTable($table)) {
            ArticleTranslation::query()
                ->with('article')
                ->orderBy('id')
                ->chunkById(max(1, $chunk), function (Collection $rows) use (&$count, $progress): void {
                    /** @var ArticleTranslation $translation */
                    foreach ($rows as $translation) {
                        $this->indexer->index($translation);
                        $translation->timestamps = false;
                        $translation->save();
                        $count++;
                        $progress?->__invoke($count);
                    }
                });
        }

        $mode = (string) config('lin-codex.source', 'composite');

        $documents = match ($mode) {
            'database' => null,
            'composite' => $this->rebuild(true),
            default => $this->rebuild(false),
        };

        return new ReindexReport($count, $documents, $mode);
    }

    /**
     * Forget and rebuild the in-memory index; returns the number of
     * articles (slugs) it now holds.
     */
    private function rebuild(bool $fileOnly): int
    {
        Cache::forget(InMemoryIndex::CACHE_KEY);

        return count($this->memory->documents($this->source->all(), $fileOnly));
    }
}
