<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Reading;

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Sources\SlugPath;

/**
 * The article tree one viewer sees in one locale. Built from a single
 * ContentSource::all() call: the gate filters the map first (a hidden
 * section takes its subtree with it), then the locale rule picks a
 * translation per surviving article (under Hide an untranslated article
 * drops out), and only then are folder groups derived from the slug paths of
 * what survived.
 *
 * An ancestor slug that is an article but was dropped is not a node, so its
 * kept descendants climb to the nearest ancestor that is a node, else the
 * root. That is the locked re-parenting rule. Groups are only derived for
 * ancestor slugs that are not articles at all, which is why a database
 * orphan ("orphan/child" with no "orphan" row) gets an "orphan" group here
 * while ArticleSet::tree() makes it a root: the builder mirrors the file
 * assembler so both sources produce the same shape.
 *
 * Group labels come from lin-codex::lin-codex.groups.<full slug> when the
 * key exists for the locale, else the humanised last segment.
 */
final class TreeBuilder
{
    public function __construct(
        private readonly ContentSource $source,
        private readonly ArticleGate $gate,
        private readonly LocaleResolver $locales,
    ) {}

    /**
     * @return list<TreeNode>
     */
    public function build(Viewer $viewer, ?string $locale = null): array
    {
        $all = $this->source->all();
        $visible = $this->gate->filter($all, $viewer);
        $locale = $this->locales->resolve($locale);

        /** @var array<string, array{article: ?ArticleData, order: int, label: string, isFallback: bool}> $nodes */
        $nodes = [];

        foreach ($visible as $slug => $article) {
            $choice = $this->locales->pick($article, $locale);

            if ($choice === null) {
                continue;
            }

            $nodes[$slug] = [
                'article' => $article,
                'order' => $article->order,
                'label' => $choice->translation->title,
                'isFallback' => $choice->isFallback,
            ];

            for ($parent = SlugPath::parentOf($slug); $parent !== null; $parent = SlugPath::parentOf($parent)) {
                if (! isset($all[$parent]) && ! isset($nodes[$parent])) {
                    $nodes[$parent] = [
                        'article' => null,
                        'order' => 0,
                        'label' => $this->groupLabel($parent, $locale),
                        'isFallback' => false,
                    ];
                }
            }
        }

        /** @var array<string, list<string>> $childrenOf */
        $childrenOf = [];

        foreach (array_keys($nodes) as $slug) {
            $slug = (string) $slug;
            $parent = SlugPath::parentOf($slug);

            while ($parent !== null && ! isset($nodes[$parent])) {
                $parent = SlugPath::parentOf($parent);
            }

            $childrenOf[$parent ?? ''][] = $slug;
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
     * @param  array<string, array{article: ?ArticleData, order: int, label: string, isFallback: bool}>  $nodes
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
                $nodes[$slug]['label'],
                $nodes[$slug]['article'],
                $this->buildNodes($slug, $nodes, $childrenOf),
                $nodes[$slug]['isFallback'],
            );
        }

        return $result;
    }

    /**
     * The lang line for the full group slug when one exists for the locale
     * (a missing line comes back as the key itself), else the humanised
     * last segment.
     */
    private function groupLabel(string $slug, string $locale): string
    {
        $key = 'lin-codex::lin-codex.groups.'.$slug;
        $label = __($key, [], $locale);

        if (is_string($label) && $label !== $key) {
            return $label;
        }

        return SlugPath::humanise(SlugPath::lastSegment($slug));
    }
}
