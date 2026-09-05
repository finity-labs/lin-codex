<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sync;

/**
 * What one codex:export run should do: which slugs (empty means all),
 * which language (null means every language the article has), the folder
 * to write under instead of the configured docs path, and whether anything
 * is written at all.
 */
final readonly class ExportOptions
{
    /**
     * @param  list<string>  $only
     */
    public function __construct(
        public array $only = [],
        public ?string $locale = null,
        public ?string $path = null,
        public bool $dryRun = false,
    ) {}
}
