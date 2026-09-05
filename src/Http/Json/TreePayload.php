<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Json;

use FinityLabs\LinCodex\Data\TreeNode;

/**
 * The JSON shape of the article tree: one entry per node with its children
 * nested, mapped field by field so an ArticleData never reaches json_encode
 * (it would expose body, plainText, contexts, meta and sourcePath).
 */
final readonly class TreePayload
{
    /**
     * @param  list<TreeNode>  $nodes
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function make(array $nodes, string $locale, string $defaultLocale): array
    {
        return [
            'data' => self::nodes($nodes),
            'meta' => [
                'locale' => $locale,
                'defaultLocale' => $defaultLocale,
            ],
        ];
    }

    /**
     * @param  list<TreeNode>  $nodes
     *
     * @return list<array<string, mixed>>
     */
    private static function nodes(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $result[] = [
                'slug' => $node->slug,
                'title' => $node->label,
                'icon' => $node->article?->icon,
                'isGroup' => $node->isGroup(),
                'isFallback' => $node->isFallback,
                'hasArticle' => ! $node->isGroup(),
                'children' => self::nodes($node->children),
            ];
        }

        return $result;
    }
}
