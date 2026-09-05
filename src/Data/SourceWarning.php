<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use FinityLabs\LinCodex\Enums\SourceWarningKind;

/**
 * Something a source noticed and worked around while reading its content:
 * a file it skipped, a value it replaced with the default, a context it
 * dropped. Warnings never stop a read; they are reported so an author can
 * fix the input.
 */
final readonly class SourceWarning
{
    public function __construct(
        public SourceWarningKind $kind,
        public ?string $path,
        public ?string $slug,
        public ?string $locale,
        public string $detail,
    ) {}

    public function message(): string
    {
        return (string) __('lin-codex::lin-codex.source_warnings.'.$this->kind->key(), [
            'detail' => $this->detail,
            'path' => (string) $this->path,
        ]);
    }
}
