<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contexts;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Enums\ContextType;

/**
 * An in-PHP index over every (slug, context) pair of a source's all() map,
 * with keys pre-normalised so matching is a straight comparison. Built per
 * call: the file set behind all() is already memoized and the database set
 * is fresh by design, so a cross-request cache would only go stale.
 *
 * candidates() returns the entries for one panel id whose key matches the
 * page, sorted by the tuple [wildcard ? 1 : 0, type order (class 0, route 1,
 * url 2), the context row's sortOrder, slug] and then reduced to one match
 * per article, keeping the first. ContentSource::findByContext() stays an
 * exact triple match and is not used above this index.
 */
final readonly class ContextIndex
{
    /**
     * @param  list<array{slug: string, context: ContextData}>  $entries
     */
    private function __construct(
        private array $entries,
    ) {}

    /**
     * @param  array<string, ArticleData>  $articles  the source's all() map
     */
    public static function fromArticles(array $articles): self
    {
        $entries = [];

        foreach ($articles as $article) {
            foreach ($article->contexts as $context) {
                $entries[] = ['slug' => $article->slug, 'context' => self::normalise($context)];
            }
        }

        return new self($entries);
    }

    /**
     * Entries whose panelId === $panelId (strict; null matches only panel-less
     * contexts) and whose key matches the page, ordered exact before wildcard,
     * then class, route, url, then sortOrder, then slug; one match per slug.
     *
     * @return list<ContextMatch>
     */
    public function candidates(PageContext $page, ?string $panelId): array
    {
        $matches = [];

        foreach ($this->entries as $entry) {
            $context = $entry['context'];

            if ($context->panelId !== $panelId || ! PatternMatcher::matches($context, $page)) {
                continue;
            }

            $wildcard = PatternMatcher::isWildcard($context);
            $typeOrder = match ($context->type) {
                ContextType::PageClass => 0,
                ContextType::Route => 1,
                ContextType::Url => 2,
            };

            $matches[] = [[$wildcard ? 1 : 0, $typeOrder, $context->sortOrder, $entry['slug']], new ContextMatch($entry['slug'], $context, ! $wildcard)];
        }

        usort($matches, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $result = [];
        $seen = [];

        foreach ($matches as [, $match]) {
            if (isset($seen[$match->slug])) {
                continue;
            }

            $seen[$match->slug] = true;
            $result[] = $match;
        }

        return $result;
    }

    /**
     * Number of (slug, context) entries, not of articles.
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Every panel id that appears in a context, first-seen order, so coverage
     * can ask "matched in any panel".
     *
     * @return list<string>
     */
    public function panelIds(): array
    {
        $ids = [];

        foreach ($this->entries as $entry) {
            $panelId = $entry['context']->panelId;

            if ($panelId !== null && ! in_array($panelId, $ids, true)) {
                $ids[] = $panelId;
            }
        }

        return $ids;
    }

    /**
     * Class keys lose their leading backslash and url keys take the canonical
     * path form; route keys are kept as written.
     */
    private static function normalise(ContextData $context): ContextData
    {
        $key = match ($context->type) {
            ContextType::PageClass => PatternMatcher::normaliseClass($context->key),
            ContextType::Url => PatternMatcher::normalisePath($context->key),
            ContextType::Route => $context->key,
        };

        return new ContextData($context->type, $key, $context->panelId, $context->sortOrder);
    }
}
