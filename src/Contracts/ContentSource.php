<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contracts;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;

/**
 * The one read contract every downstream service codes against. A source
 * answers from files, from the codex_* tables, or from a fold of both; the
 * consumer never learns which. Every method returns readonly data objects,
 * never a model, so results are safe to cache and serialize.
 */
interface ContentSource
{
    /**
     * @return array<string, ArticleData> keyed by slug, sorted by slug
     */
    public function all(): array;

    public function findBySlug(string $slug): ?ArticleData;

    /**
     * @return list<TreeNode> root nodes; siblings ordered by (order, slug)
     */
    public function tree(): array;

    /**
     * Exact match on (type, key, panelId): a null panelId matches only
     * contexts without a panel. The "panel plus key first, then key alone"
     * fallback is Phase 4's resolver, which calls this twice. url: wildcards
     * are not expanded here; keys are compared as strings.
     *
     * @return list<ArticleData> ordered by context sortOrder, then article order, then slug
     */
    public function findByContext(ContextType $type, string $key, ?string $panelId = null): array;

    /**
     * @return list<SearchDocument> one per (slug, locale), ordered by slug then locale
     */
    public function allForSearch(): array;

    /**
     * @return list<SourceWarning>
     */
    public function warnings(): array;
}
