<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sync;

/**
 * What one codex:import run should do: which slugs (empty means all),
 * which language (null means every language a file exists for), whether
 * an article already in the database is overwritten, whether anything is
 * written at all, and which user id the writes and revisions carry.
 */
final readonly class ImportOptions
{
    /**
     * @param  list<string>  $only
     */
    public function __construct(
        public array $only = [],
        public ?string $locale = null,
        public bool $force = false,
        public bool $dryRun = false,
        public ?int $userId = null,
    ) {}
}
