<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Models;

use FinityLabs\LinCodex\Database\Factories\ArticleTranslationFactory;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Search\SearchTextIndexer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One locale's title and body for an article.
 *
 * search_text is the folded search blob (SearchText::compose()) kept
 * current by the saving hook: it is filled when null and recomputed when
 * the title, excerpt or body changes. Assign it explicitly on the same
 * save to override; the hook never touches a dirty search_text.
 *
 * The same hook hands an existing translation whose title or body changed
 * to the RevisionManager before indexing, so the previous content is kept
 * as a revision when revisions are enabled; a new translation, an
 * excerpt-only change and a search_text-only change store nothing.
 *
 * @property int $id
 * @property int $article_id
 * @property string $locale
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $search_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Article $article
 */
class ArticleTranslation extends Model
{
    /** @use HasFactory<ArticleTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'excerpt',
        'body',
        'search_text',
    ];

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::saving(function (ArticleTranslation $translation): void {
            if ($translation->exists && $translation->isDirty(['title', 'body'])) {
                app(RevisionManager::class)->recordTranslationChange($translation);
            }

            if ($translation->search_text === null
                || ($translation->isDirty(['title', 'excerpt', 'body']) && ! $translation->isDirty('search_text'))) {
                app(SearchTextIndexer::class)->index($translation);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    protected static function newFactory(): ArticleTranslationFactory
    {
        return ArticleTranslationFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('lin-codex.table_names.article_translations', 'codex_article_translations');
    }
}
