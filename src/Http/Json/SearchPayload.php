<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Json;

use FinityLabs\LinCodex\Search\SearchResult;

/**
 * The JSON shape of a search answer: one entry per hit and a meta block
 * that echoes the query, the capped total, the limit the searcher actually
 * applied and the rate-limit state. Mapped field by field; the matched
 * field is written as its key(), never the backing int.
 */
final readonly class SearchPayload
{
    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function make(SearchResult $result, int $limit, string $locale, string $defaultLocale): array
    {
        $data = [];

        foreach ($result->hits as $hit) {
            $data[] = [
                'slug' => $hit->slug,
                'title' => $hit->title,
                'sectionPath' => $hit->sectionPath,
                'snippet' => $hit->snippet,
                'matchedField' => $hit->matchedField->key(),
                'score' => $hit->score,
                'isFallback' => $hit->isFallback,
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'locale' => $locale,
                'defaultLocale' => $defaultLocale,
                'query' => $result->query,
                'total' => $result->total,
                'limit' => $limit,
                'rateLimited' => $result->rateLimited,
                'retryAfterSeconds' => $result->retryAfterSeconds,
            ],
        ];
    }
}
