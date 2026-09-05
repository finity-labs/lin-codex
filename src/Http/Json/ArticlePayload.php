<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Json;

use FinityLabs\LinCodex\Reading\ReadArticle;

/**
 * The JSON shape of one read article: the rendered HTML with its title,
 * locale, fallback flag, table of contents, breadcrumbs, related entries,
 * icon and last-change time. Mapped field by field so the raw body, the
 * plain text and the article's contexts and meta never leave the server.
 *
 * There is no fallbackNotice key: isFallback is the signal and the client
 * renders the text (locked).
 */
final readonly class ArticlePayload
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public static function make(ReadArticle $read, string $locale, string $defaultLocale): array
    {
        return [
            'data' => [
                'slug' => $read->article->slug,
                'title' => $read->translation->title,
                'excerpt' => $read->translation->excerpt,
                'locale' => $read->locale,
                'isFallback' => $read->isFallback,
                'format' => $read->article->format->key(),
                'html' => $read->rendered->html,
                'toc' => $read->rendered->toc,
                'breadcrumbs' => $read->breadcrumbs,
                'related' => $read->related,
                'icon' => $read->article->icon,
                'updatedAt' => $read->translation->updatedAt,
            ],
            'meta' => [
                'locale' => $locale,
                'defaultLocale' => $defaultLocale,
                'isFallback' => $read->isFallback,
            ],
        ];
    }
}
