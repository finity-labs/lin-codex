<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Locale\LocaleResolver;

/**
 * Candidates for file articles, read from the in-memory index.
 *
 * The database path pre-filters rows in SQL; the in-memory counterpart of
 * that pre-filter is the matcher itself, run by the ranker over these
 * candidates. So this class only applies the visibility map (the caller's
 * gate-filtered articles) and the locale rule (one translation per article,
 * picked by LocaleResolver), and hands over the folded fields.
 *
 * $fileOnly is the composite merge rule: the database wins a slug whole, so
 * on a composite install only the articles without a database id are
 * searched here and the caller merges them with the database hits. A file
 * article shadowed by a database row therefore never appears twice.
 */
final class InMemoryCandidates
{
    public function __construct(
        private readonly InMemoryIndex $index,
        private readonly LocaleResolver $locales,
    ) {}

    /**
     * @param  array<string, ArticleData>  $all  the source's all() map (index input)
     * @param  array<string, ArticleData>  $visible  the gate-filtered map (candidate input)
     *
     * @return list<Candidate>
     */
    public function find(string $locale, array $all, array $visible, bool $fileOnly): array
    {
        $index = $this->index->documents($all, $fileOnly);
        $candidates = [];

        foreach ($visible as $slug => $article) {
            if ($fileOnly && $article->id !== null) {
                continue;
            }

            $choice = $this->locales->pick($article, $locale);
            $document = $choice === null ? null : ($index[$slug][$choice->translation->locale] ?? null);

            if ($choice === null || $document === null) {
                continue;
            }

            $candidates[] = new Candidate($article, $choice, [
                'title' => $document->title,
                'keywords' => $document->keywords,
                'excerpt' => $document->excerpt,
                'body' => $document->body,
            ]);
        }

        return $candidates;
    }
}
