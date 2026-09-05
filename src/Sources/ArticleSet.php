<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;

/**
 * An immutable set of articles keyed by slug that answers the four content
 * queries (tree, find-by-slug, find-by-context, all-for-search) the same way
 * for every source. A source only has to produce the set; the composite
 * source folds several sets with fold().
 *
 * Groups are folders that hold articles but have no article of their own;
 * they appear in the tree as nodes without an article and never shadow an
 * article with the same slug.
 */
final readonly class ArticleSet
{
    /**
     * @var array<string, ArticleData> keyed by slug, sorted by slug
     */
    public array $articles;

    /**
     * @var array<string, string> slug => label, sorted by slug, never a slug that is also an article
     */
    public array $groups;

    /**
     * @var list<SourceWarning>
     */
    public array $warnings;

    /**
     * @param  array<string, ArticleData>  $articles
     * @param  array<string, string>  $groups
     * @param  list<SourceWarning>  $warnings
     */
    public function __construct(array $articles, array $groups = [], array $warnings = [])
    {
        ksort($articles);
        $groups = array_diff_key($groups, $articles);
        ksort($groups);

        $this->articles = $articles;
        $this->groups = $groups;
        $this->warnings = $warnings;
    }

    /**
     * Later sets win per slug, whole article: a slug in a later set replaces
     * the earlier article for every locale. Groups are unioned, then any
     * group whose slug became an article is dropped; warnings are
     * concatenated in set order.
     */
    public static function fold(self ...$sets): self
    {
        if ($sets === []) {
            return new self([]);
        }

        $sets = array_values($sets);

        return new self(
            array_replace(...array_map(fn (self $set): array => $set->articles, $sets)),
            array_replace(...array_map(fn (self $set): array => $set->groups, $sets)),
            array_merge(...array_map(fn (self $set): array => $set->warnings, $sets)),
        );
    }

    /**
     * @return array<string, ArticleData>
     */
    public function all(): array
    {
        return $this->articles;
    }

    public function findBySlug(string $slug): ?ArticleData
    {
        return $this->articles[$slug] ?? null;
    }

    /**
     * A slug whose parent is neither an article nor a group becomes a root.
     * Siblings are ordered by (order, slug); groups sit at order zero.
     *
     * @return list<TreeNode>
     */
    public function tree(): array
    {
        /** @var array<string, array{article: ArticleData|null, order: int}> $nodes */
        $nodes = [];

        foreach (array_keys($this->groups) as $slug) {
            $nodes[(string) $slug] = ['article' => null, 'order' => 0];
        }

        foreach ($this->articles as $article) {
            $nodes[$article->slug] = ['article' => $article, 'order' => $article->order];
        }

        /** @var array<string, list<string>> $childrenOf */
        $childrenOf = [];

        foreach (array_keys($nodes) as $slug) {
            $slug = (string) $slug;
            $parent = SlugPath::parentOf($slug);
            $parentKey = $parent !== null && isset($nodes[$parent]) ? $parent : '';
            $childrenOf[$parentKey][] = $slug;
        }

        foreach ($childrenOf as &$siblings) {
            usort(
                $siblings,
                fn (string $a, string $b): int => [$nodes[$a]['order'], $a] <=> [$nodes[$b]['order'], $b],
            );
        }
        unset($siblings);

        return $this->buildNodes('', $nodes, $childrenOf);
    }

    /**
     * Exact match on (type, key, panelId); an article matches at most once,
     * at the lowest sort order among its matching contexts.
     *
     * @return list<ArticleData>
     */
    public function findByContext(ContextType $type, string $key, ?string $panelId = null): array
    {
        /** @var list<array{int, int, string, ArticleData}> $matches */
        $matches = [];

        foreach ($this->articles as $article) {
            $sortOrder = null;

            foreach ($article->contexts as $context) {
                if ($context->type !== $type || $context->key !== $key || $context->panelId !== $panelId) {
                    continue;
                }

                $sortOrder = $sortOrder === null ? $context->sortOrder : min($sortOrder, $context->sortOrder);
            }

            if ($sortOrder !== null) {
                $matches[] = [$sortOrder, $article->order, $article->slug, $article];
            }
        }

        usort($matches, fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(fn (array $match): ArticleData => $match[3], $matches);
    }

    /**
     * One document per (slug, locale), slugs in order then locales in order,
     * with the keywords folded into the text.
     *
     * @return list<SearchDocument>
     */
    public function allForSearch(): array
    {
        $documents = [];

        foreach ($this->articles as $article) {
            $translations = $article->translations;
            ksort($translations);

            foreach ($translations as $translation) {
                $documents[] = SearchDocument::fromTranslation($article, $translation);
            }
        }

        return $documents;
    }

    /**
     * @return list<SourceWarning>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param  array<string, array{article: ArticleData|null, order: int}>  $nodes
     * @param  array<string, list<string>>  $childrenOf
     *
     * @return list<TreeNode>
     */
    private function buildNodes(string $parentKey, array $nodes, array $childrenOf): array
    {
        $result = [];

        foreach ($childrenOf[$parentKey] ?? [] as $slug) {
            $result[] = new TreeNode(
                $slug,
                SlugPath::humanise(SlugPath::lastSegment($slug)),
                $nodes[$slug]['article'],
                $this->buildNodes($slug, $nodes, $childrenOf),
            );
        }

        return $result;
    }
}
