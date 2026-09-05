<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

/**
 * One node of the article tree. A node without an article is a group: a
 * folder that holds articles but has no index file of its own.
 *
 * The label is the humanised slug when a source builds the tree and the
 * translated title (or the group's lang-key label) when TreeBuilder does;
 * isFallback marks a default-language stand-in for a translation the
 * viewer's locale does not have.
 */
final readonly class TreeNode
{
    /**
     * @param  list<TreeNode>  $children
     */
    public function __construct(
        public string $slug,
        public string $label,
        public ?ArticleData $article,
        public array $children = [],
        public bool $isFallback = false,
    ) {}

    public function isGroup(): bool
    {
        return $this->article === null;
    }
}
