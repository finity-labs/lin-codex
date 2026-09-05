<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources;

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;

/**
 * The union of the file source and the database source by slug. Once a
 * slug exists in the database the file article is ignored whole, for every
 * locale and with its contexts, even when the database row has fewer
 * translations than the file. Groups from files yield to a database
 * article with the same slug; warnings from the file source still surface.
 *
 * A database article gains isSection when a file article sits below it,
 * because the database source only knows its own rows. Nothing is memoized
 * here: the file source keeps its own fingerprint-checked cache and the
 * database source reads fresh rows on every call.
 */
final class CompositeSource implements ContentSource
{
    public function __construct(
        private readonly FilesystemSource $files,
        private readonly DatabaseSource $database,
    ) {}

    /**
     * Files first, database second, so the database wins per slug.
     */
    public function set(): ArticleSet
    {
        $set = ArticleSet::fold($this->files->set(), $this->database->set());

        return $this->withSectionsFromUnion($set);
    }

    /**
     * @return array<string, ArticleData>
     */
    public function all(): array
    {
        return $this->set()->all();
    }

    public function findBySlug(string $slug): ?ArticleData
    {
        return $this->set()->findBySlug($slug);
    }

    /**
     * @return list<TreeNode>
     */
    public function tree(): array
    {
        return $this->set()->tree();
    }

    /**
     * @return list<ArticleData>
     */
    public function findByContext(ContextType $type, string $key, ?string $panelId = null): array
    {
        return $this->set()->findByContext($type, $key, $panelId);
    }

    /**
     * @return list<SearchDocument>
     */
    public function allForSearch(): array
    {
        return $this->set()->allForSearch();
    }

    /**
     * @return list<SourceWarning>
     */
    public function warnings(): array
    {
        return $this->set()->warnings();
    }

    /**
     * Mark an article as a section when any slug of the folded set sits
     * below it. Only articles whose flag flips are rebuilt; the set is
     * returned as is when nothing changes.
     */
    private function withSectionsFromUnion(ArticleSet $set): ArticleSet
    {
        $slugs = array_keys($set->articles);
        $articles = $set->articles;
        $changed = false;

        foreach ($articles as $slug => $article) {
            if ($article->isSection || ! $this->hasChild((string) $slug, $slugs)) {
                continue;
            }

            $articles[$slug] = $this->asSection($article);
            $changed = true;
        }

        return $changed ? new ArticleSet($articles, $set->groups, $set->warnings) : $set;
    }

    /**
     * @param  list<string>  $slugs
     */
    private function hasChild(string $slug, array $slugs): bool
    {
        $prefix = $slug.'/';

        foreach ($slugs as $other) {
            if (str_starts_with($other, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function asSection(ArticleData $article): ArticleData
    {
        return new ArticleData(
            slug: $article->slug,
            parentSlug: $article->parentSlug,
            order: $article->order,
            icon: $article->icon,
            format: $article->format,
            visibility: $article->visibility,
            published: $article->published,
            contexts: $article->contexts,
            related: $article->related,
            keywords: $article->keywords,
            translations: $article->translations,
            meta: $article->meta,
            isSection: true,
            sourcePath: $article->sourcePath,
            id: $article->id,
        );
    }
}
