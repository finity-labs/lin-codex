<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources;

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The content source over the codex_* tables. Every call runs one
 * eager-loaded query and maps the rows to readonly data; nothing is
 * memoized on purpose, so a save earlier in the same request is visible on
 * the next read. Callers that want one snapshot call set() once and keep it.
 *
 * The `keywords`, `related` and `meta` JSON columns map straight through,
 * null as an empty array, so a database article carries the same metadata a
 * file article would. Translations carry `updatedAt` from the row's
 * `updated_at`. There is no Schema::hasTable() guard: a file-only install
 * sets lin-codex.source to "filesystem" instead.
 */
final class DatabaseSource implements ContentSource
{
    public function set(): ArticleSet
    {
        $rows = Article::query()
            ->with(['translations', 'contexts' => function (Relation $query): void {
                $query->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('slug')
            ->get();

        $slugs = array_map(fn (Article $row): string => $row->slug, $rows->all());

        $articles = [];

        foreach ($rows as $row) {
            $articles[$row->slug] = $this->map($row, $slugs);
        }

        return new ArticleSet($articles);
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
     * Uses only the eager-loaded relations; the parent comes from the slug,
     * and an article is a section when any other slug sits below it.
     *
     * @param  list<string>  $slugs
     */
    private function map(Article $row, array $slugs): ArticleData
    {
        $prefix = $row->slug.'/';
        $isSection = false;

        foreach ($slugs as $other) {
            if (str_starts_with($other, $prefix)) {
                $isSection = true;

                break;
            }
        }

        $contexts = [];

        foreach ($row->contexts as $context) {
            $contexts[] = new ContextData($context->type, $context->key, $context->panel_id, $context->sort_order);
        }

        $translations = [];

        foreach ($row->translations as $translation) {
            $translations[$translation->locale] = new TranslationData(
                $translation->locale,
                $translation->title,
                $translation->excerpt,
                $translation->body,
                $translation->search_text,
                updatedAt: $translation->updated_at?->toIso8601String(),
            );
        }

        return new ArticleData(
            slug: $row->slug,
            parentSlug: SlugPath::parentOf($row->slug),
            order: $row->sort_order,
            icon: $row->icon,
            format: $row->format,
            visibility: $row->visibility,
            published: $row->is_published,
            contexts: $contexts,
            related: $this->stringList($row->related),
            keywords: $this->stringList($row->keywords),
            translations: $translations,
            meta: is_array($row->meta) ? $row->meta : [],
            isSection: $isSection,
            sourcePath: $row->source_path,
            id: $row->id,
        );
    }

    /**
     * The string entries of a JSON list column, reindexed; null or anything
     * that is not an array is an empty list.
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
