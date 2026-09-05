<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

/**
 * What one SearchReindexer::reindex() call did: the number of translation
 * rows walked (every row is counted, whether or not its search_text
 * changed), the number of articles in the rebuilt in-memory index, and the
 * lin-codex.source mode that decided the index scope. indexedDocuments is
 * null when the source mode never reads the in-memory index (database).
 */
final readonly class ReindexReport
{
    public function __construct(
        public int $translations,
        public ?int $indexedDocuments,
        public string $mode,
    ) {}
}
