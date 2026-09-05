<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Models;

use FinityLabs\LinCodex\Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An uploaded file (usually an image) stored on the configured media disk.
 * The article link is optional and survives article deletion as null.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime_type
 * @property int $size
 * @property int|null $uploaded_by
 * @property int|null $article_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Article|null $article
 * @property-read Model|null $uploader
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'name',
        'mime_type',
        'size',
        'uploaded_by',
        'article_id',
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
            'size' => 'integer',
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
     * The user who uploaded this file.
     *
     * @return BelongsTo<Model, $this>
     */
    public function uploader(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'uploaded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('lin-codex.table_names.media', 'codex_media');
    }
}
