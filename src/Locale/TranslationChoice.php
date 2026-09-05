<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Locale;

use FinityLabs\LinCodex\Data\TranslationData;

/**
 * Which translation of an article to show, and whether it is the
 * default-language stand-in for a translation the reader asked for but the
 * article does not have (the UI shows the fallback notice in that case).
 */
final readonly class TranslationChoice
{
    public function __construct(
        public TranslationData $translation,
        public bool $isFallback,
    ) {}
}
