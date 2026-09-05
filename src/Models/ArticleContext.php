<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Models;

use FinityLabs\LinCodex\Database\Factories\ArticleContextFactory;
use FinityLabs\LinCodex\Enums\ContextType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Binds an article to a page class, route name, or URL pattern, optionally
 * scoped to one panel. Several articles may share a context; sort_order
 * orders them.
 *
 * @property int $id
 * @property int $article_id
 * @property string|null $panel_id
 * @property ContextType $type
 * @property string $key
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Article $article
 */
class ArticleContext extends Model
{
    /** @use HasFactory<ArticleContextFactory> */
    use HasFactory;

    protected $fillable = [
        'article_id',
        'panel_id',
        'type',
        'key',
        'sort_order',
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
            'type' => ContextType::class,
            'sort_order' => 'integer',
        ];
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

    protected static function newFactory(): ArticleContextFactory
    {
        return ArticleContextFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('lin-codex.table_names.article_contexts', 'codex_article_contexts');
    }
}
