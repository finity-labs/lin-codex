<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Auth;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\SlugPath;
use InvalidArgumentException;

/**
 * The one visibility rule. An article is visible to a viewer when it is
 * published, its visibility is public or the viewer is authenticated, the
 * lin-codex.auth.gate hook allows it, and every ancestor article on its slug
 * path passes the same rule. A hidden parent therefore hides its whole
 * subtree on every read path: reader, tree, context lookup, search, media.
 *
 * Folder groups (ancestor slugs that are not articles) hide nothing. The
 * hook can only veto: it runs after the published and visibility checks and
 * only ever sees articles those two checks let through.
 *
 * Both methods take the source's all() map so the ancestor walk is array
 * lookups, never queries.
 */
final class ArticleGate
{
    /**
     * @param  array<string, ArticleData>  $articles  the source's all() map, keyed by slug
     */
    public function allows(ArticleData $article, Viewer $viewer, array $articles): bool
    {
        $memo = [];

        return $this->allowsWithMemo($article, $viewer, $articles, $this->hook(), $memo);
    }

    /**
     * @param  array<string, ArticleData>  $articles
     *
     * @return array<string, ArticleData> same keys and order, hidden entries removed
     */
    public function filter(array $articles, Viewer $viewer): array
    {
        $hook = $this->hook();
        $memo = [];
        $result = [];

        foreach ($articles as $slug => $article) {
            if ($this->allowsWithMemo($article, $viewer, $articles, $hook, $memo)) {
                $result[$slug] = $article;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, ArticleData>  $articles
     * @param  array<string, bool>  $memo  verdict per slug, shared across one allows() or filter() call
     */
    private function allowsWithMemo(ArticleData $article, Viewer $viewer, array $articles, ?callable $hook, array &$memo): bool
    {
        if (isset($memo[$article->slug])) {
            return $memo[$article->slug];
        }

        $allowed = $this->own($article, $viewer, $hook);
        $parent = SlugPath::parentOf($article->slug);

        while ($allowed && $parent !== null) {
            if (isset($articles[$parent])) {
                $allowed = $this->allowsWithMemo($articles[$parent], $viewer, $articles, $hook, $memo);

                break;
            }

            $parent = SlugPath::parentOf($parent);
        }

        return $memo[$article->slug] = $allowed;
    }

    private function own(ArticleData $article, Viewer $viewer, ?callable $hook): bool
    {
        if (! $article->published) {
            return false;
        }

        if ($article->visibility === Visibility::Authenticated && ! $viewer->isAuthenticated) {
            return false;
        }

        return $hook === null || (bool) $hook($viewer, $article);
    }

    /**
     * The lin-codex.auth.gate hook, resolved at call time: null, an
     * invokable class name resolved through the container, or a callable.
     */
    private function hook(): ?callable
    {
        $hook = config('lin-codex.auth.gate');

        if ($hook === null) {
            return null;
        }

        if (is_string($hook)) {
            $hook = app($hook);
        }

        if (! is_callable($hook)) {
            throw new InvalidArgumentException('lin-codex.auth.gate must be null, an invokable class name or a callable.');
        }

        return $hook;
    }
}
