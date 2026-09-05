<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Models;

use FinityLabs\LinCodex\Database\Factories\ArticleFactory;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Search\SearchTextIndexer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A help article identified by its slash-separated slug (for example "users/roles").
 *
 * The slug is the tree's source of truth: the parent of "users/roles" is the
 * article whose slug is "users". The parent_id column is a cache of that
 * relationship, maintained by the saving/saved hooks in booted() through
 * syncParentFromSlug() and relinkChildren(). Bulk writers that bypass model
 * events must call those two methods themselves.
 *
 * The format belongs to the article, not to its translations, so the
 * updating hook hands a format change to the RevisionManager, which stores
 * one revision per translation carrying the previous format when revisions
 * are enabled.
 *
 * @property int $id
 * @property string $slug
 * @property int|null $parent_id
 * @property int $sort_order
 * @property string|null $icon
 * @property ArticleFormat $format
 * @property Visibility $visibility
 * @property bool $is_published
 * @property string|null $source_path
 * @property array<int, string>|null $keywords
 * @property array<int, string>|null $related
 * @property array<string, mixed>|null $meta
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ArticleTranslation> $translations
 * @property-read Collection<int, ArticleContext> $contexts
 * @property-read Collection<int, ArticleRevision> $revisions
 * @property-read Collection<int, Media> $media
 * @property-read Article|null $parent
 * @property-read Collection<int, Article> $children
 * @property-read Model|null $creator
 * @property-read Model|null $updater
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'parent_id',
        'sort_order',
        'icon',
        'format',
        'visibility',
        'is_published',
        'source_path',
        'keywords',
        'related',
        'meta',
        'created_by',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ArticleFormat::class,
            'visibility' => Visibility::class,
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'keywords' => 'array',
            'related' => 'array',
            'meta' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            $article->syncParentFromSlug();
        });

        static::saved(function (Article $article): void {
            if ($article->wasRecentlyCreated || $article->wasChanged('slug')) {
                $article->relinkChildren();
            }
        });

        static::updating(function (Article $article): void {
            if ($article->isDirty('format')) {
                app(RevisionManager::class)->recordFormatChange($article);
            }
        });

        static::updated(function (Article $article): void {
            if ($article->wasChanged(['keywords', 'format'])) {
                $article->reindexTranslations();
            }
        });
    }

    /**
     * Recompute search_text for every translation after the keywords or the
     * body format changed; the translation hook alone cannot see those.
     * Each save() runs the translation hook, whose condition is false
     * because search_text is dirty and non-null, so nothing indexes twice.
     */
    public function reindexTranslations(): void
    {
        $indexer = app(SearchTextIndexer::class);

        foreach ($this->translations()->get() as $translation) {
            $translation->setRelation('article', $this);
            $indexer->index($translation);
            $translation->save();
        }
    }

    /**
     * Point parent_id at the article whose slug is this slug minus its last
     * segment, or null for a root slug. Slug is the source of truth.
     */
    public function syncParentFromSlug(): void
    {
        $this->parent_id = str_contains($this->slug, '/')
            ? static::query()->where('slug', Str::beforeLast($this->slug, '/'))->value('id')
            : null;
    }

    /**
     * Re-cache parent_id on the articles directly below this slug and orphan
     * the rows still pointing here whose slug no longer starts with it.
     * Query-builder updates fire no model events.
     */
    public function relinkChildren(): void
    {
        static::query()
            ->where('slug', 'like', $this->slug.'/%')
            ->where('slug', 'not like', $this->slug.'/%/%')
            ->update(['parent_id' => $this->id]);

        static::query()
            ->where('parent_id', $this->id)
            ->where('slug', 'not like', $this->slug.'/%')
            ->update(['parent_id' => null]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return HasMany<ArticleTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class, 'article_id');
    }

    /**
     * @return HasMany<ArticleContext, $this>
     */
    public function contexts(): HasMany
    {
        return $this->hasMany(ArticleContext::class, 'article_id');
    }

    /**
     * @return HasMany<ArticleRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class, 'article_id');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'article_id');
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'parent_id');
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Article::class, 'parent_id');
    }

    /**
     * The user who created this article.
     *
     * @return BelongsTo<Model, $this>
     */
    public function creator(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'created_by');
    }

    /**
     * The user who last updated this article.
     *
     * @return BelongsTo<Model, $this>
     */
    public function updater(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('lin-codex.table_names.articles', 'codex_articles');
    }
}
