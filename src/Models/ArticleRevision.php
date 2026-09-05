<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Models;

use FinityLabs\LinCodex\Database\Factories\ArticleRevisionFactory;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable snapshot of one locale's title and body, written whenever a
 * translation changes. Revisions are append-only, so the table carries
 * created_at only.
 *
 * @property int $id
 * @property int $article_id
 * @property string $locale
 * @property string $title
 * @property string $body
 * @property ArticleFormat $format
 * @property RevisionReason $reason
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property-read Article $article
 * @property-read Model|null $user
 */
class ArticleRevision extends Model
{
    /** @use HasFactory<ArticleRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'body',
        'format',
        'reason',
        'user_id',
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
            'reason' => RevisionReason::class,
            'created_at' => 'datetime',
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

    /**
     * The user who authored this revision.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    protected static function newFactory(): ArticleRevisionFactory
    {
        return ArticleRevisionFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('lin-codex.table_names.article_revisions', 'codex_article_revisions');
    }
}
