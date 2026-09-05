<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sync;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies file articles into the database: the service behind codex:import.
 *
 * The input is FilesystemSource, never the content-source binding: the
 * composite source lets a database slug hide the file article of the same
 * slug, which is exactly the set --force must be able to re-import. The
 * output goes through the models, not the query builder, so every hook
 * runs: Article::saving derives parent_id from the slug (parents are
 * written first because the set is sorted by slug), ArticleTranslation::saving
 * fills search_text, and the RevisionManager stores a revision with reason
 * Import for an existing translation whose title or body changed; the
 * whole run sits in one attributing() scope so nothing is recorded as
 * Manual. A translation that did not change is not dirty and records nothing.
 *
 * Each article is written in its own transaction: a failing one (an unknown
 * --user id, a constraint) is reported under failed for each of its locales
 * and the run carries on with the next slug. source_path is stored
 * docs-relative ("en/02-users/index.md", the longest configured path
 * stripped) so the export can resolve it under any root.
 */
final class ArticleImporter
{
    public function __construct(
        private readonly FilesystemSource $files,
        private readonly RevisionManager $revisions,
    ) {}

    public function import(ImportOptions $options): SyncReport
    {
        $report = new SyncReport;
        $set = $this->files->set();
        $paths = $this->files->paths();

        foreach ($set->warnings() as $warning) {
            $report->warning($warning->message());
        }

        $existing = $set->articles === []
            ? []
            : Article::query()->whereIn('slug', array_keys($set->articles))->pluck('slug')->flip()->all();

        foreach ($set->articles as $article) {
            if ($options->only !== [] && ! in_array($article->slug, $options->only, true)) {
                continue;
            }

            $locales = $options->locale === null
                ? $article->locales()
                : array_values(array_intersect($article->locales(), [$options->locale]));

            if ($locales === []) {
                continue;
            }

            $exists = isset($existing[$article->slug]);

            if ($exists && ! $options->force) {
                foreach ($locales as $locale) {
                    $report->skipped($locale, $article->slug);
                }

                continue;
            }

            if (! $options->dryRun) {
                try {
                    DB::transaction(fn () => $this->revisions->attributing(RevisionReason::Import, $options->userId, fn () => $this->write($article, $locales, $paths, $options)));
                } catch (Throwable $e) {
                    foreach ($locales as $locale) {
                        $report->failed($locale, $article->slug, $e->getMessage());
                    }

                    continue;
                }
            }

            foreach ($locales as $locale) {
                $exists ? $report->updated($locale, $article->slug) : $report->created($locale, $article->slug);
            }
        }

        return $report;
    }

    /**
     * @param  list<string>  $locales
     * @param  list<string>  $paths
     */
    private function write(ArticleData $article, array $locales, array $paths, ImportOptions $options): void
    {
        $model = Article::query()->firstOrNew(['slug' => $article->slug]);

        $model->fill([
            'sort_order' => $article->order,
            'icon' => $article->icon,
            'format' => $article->format,
            'visibility' => $article->visibility,
            'is_published' => $article->published,
            'source_path' => $this->relativePath($article->sourcePath, $paths),
            'keywords' => $article->keywords,
            'related' => $article->related,
            'meta' => $article->meta,
            'updated_by' => $options->userId,
        ]);

        if (! $model->exists) {
            $model->created_by = $options->userId;
        }

        $model->save();

        foreach ($locales as $locale) {
            $translation = $article->translation($locale);

            if ($translation === null) {
                continue;
            }

            ArticleTranslation::query()
                ->firstOrNew(['article_id' => $model->id, 'locale' => $locale])
                ->fill([
                    'title' => $translation->title,
                    'excerpt' => $translation->excerpt,
                    'body' => $translation->body,
                ])
                ->save();
        }

        $model->contexts()->delete();

        $contexts = $article->contexts;
        usort($contexts, static fn ($a, $b): int => $a->sortOrder <=> $b->sortOrder);

        foreach ($contexts as $position => $context) {
            $model->contexts()->create([
                'panel_id' => $context->panelId,
                'type' => $context->type,
                'key' => $context->key,
                'sort_order' => $position,
            ]);
        }
    }

    /**
     * The path relative to the longest configured docs path that holds it;
     * a path under none of them is kept as given, and null stays null.
     *
     * @param  list<string>  $paths
     */
    private function relativePath(?string $absolute, array $paths): ?string
    {
        if ($absolute === null) {
            return null;
        }

        $normalised = str_replace('\\', '/', $absolute);
        $best = null;

        foreach ($paths as $path) {
            $prefix = rtrim(str_replace('\\', '/', $path), '/').'/';

            if (str_starts_with($normalised, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best === null ? $absolute : substr($normalised, strlen($best));
    }
}
