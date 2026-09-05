<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Locale\TranslationChoice;

/**
 * An article, the translation chosen by the locale rule, and its four
 * folded fields: from SearchText::split() for database rows, from the
 * in-memory index for file articles. This is what the matcher verifies
 * and the ranker orders.
 */
final readonly class Candidate
{
    /**
     * @param  array{title: string, keywords: string, excerpt: string, body: string}  $fields  folded
     */
    public function __construct(
        public ArticleData $article,
        public TranslationChoice $choice,
        public array $fields,
    ) {}
}
