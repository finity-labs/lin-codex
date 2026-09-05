<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Search;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use Illuminate\Support\Facades\Cache;

/**
 * The folded documents of the file articles, kept in the cache so a search
 * never folds the corpus per keystroke: folding every title, keyword,
 * excerpt and body on each request is the cost this class exists to avoid.
 *
 * One fixed key on the default store holds ['hash' => ..., 'documents' =>
 * list<IndexedDocument>]. The hash covers the search documents themselves
 * (plus SearchText::VERSION) rather than a docs-path fingerprint, so a
 * composite union or a custom ContentSource is indexed just as well; when
 * it differs the entry is replaced, so an edit is seen on the next search
 * with no manual clear, and a corrupt or foreign entry is simply rebuilt.
 * The codex:cache-clear command (Phase 8) forgets CACHE_KEY.
 *
 * The caller passes the source's all() map, so building the index never
 * queries the source a second time. A per-instance memo skips the cache
 * read when the same instance is asked twice in one request; the class is
 * auto-wired per resolution, never a singleton.
 */
final class InMemoryIndex
{
    public const CACHE_KEY = 'lin-codex:search:index';

    /**
     * @var array<string, array<string, array<string, IndexedDocument>>> hash => slug => locale => document
     */
    private array $memo = [];

    /**
     * Folded documents for the articles in $all (only those with a null id
     * when $fileOnly), from the cache when the content hash matches,
     * rebuilt otherwise.
     *
     * @param  array<string, ArticleData>  $all
     *
     * @return array<string, array<string, IndexedDocument>> slug => locale => document
     */
    public function documents(array $all, bool $fileOnly): array
    {
        $documents = [];

        foreach ($all as $article) {
            if ($fileOnly && $article->id !== null) {
                continue;
            }

            $translations = $article->translations;
            ksort($translations);

            foreach ($translations as $translation) {
                $documents[] = SearchDocument::fromTranslation($article, $translation);
            }
        }

        $hash = self::hashFor($documents);

        if (isset($this->memo[$hash])) {
            return $this->memo[$hash];
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['hash'] ?? null) === $hash && is_array($cached['documents'] ?? null)) {
            return $this->memo[$hash] = $this->keyed($cached['documents']);
        }

        $indexed = array_map(static fn (SearchDocument $document): IndexedDocument => new IndexedDocument(
            $document->slug,
            $document->locale,
            $document->articleId,
            SearchText::fold($document->title),
            SearchText::fold(implode(' ', $document->keywords)),
            SearchText::fold((string) $document->excerpt),
            SearchText::fold((string) $document->body),
        ), $documents);

        Cache::forever(self::CACHE_KEY, ['hash' => $hash, 'documents' => $indexed]);

        return $this->memo[$hash] = $this->keyed($indexed);
    }

    /**
     * The content hash of a document list; the folding version is part of
     * it so a changed fold() rule starts a fresh entry on its own.
     *
     * @param  list<SearchDocument>  $documents
     */
    public static function hashFor(array $documents): string
    {
        return hash('xxh128', SearchText::VERSION.'|'.serialize($documents));
    }

    /**
     * slug => locale => document; entries that are not IndexedDocument
     * instances (a stale entry from another version) are skipped.
     *
     * @param  array<mixed>  $documents
     *
     * @return array<string, array<string, IndexedDocument>>
     */
    private function keyed(array $documents): array
    {
        $keyed = [];

        foreach ($documents as $document) {
            if ($document instanceof IndexedDocument) {
                $keyed[$document->slug][$document->locale] = $document;
            }
        }

        return $keyed;
    }
}
