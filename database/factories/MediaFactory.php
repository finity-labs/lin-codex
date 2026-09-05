<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Database\Factories;

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => config('lin-codex.media.disk', 'public'),
            'path' => config('lin-codex.media.directory', 'codex').'/'.fake()->uuid().'.png',
            'name' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(1_000, 500_000),
            'uploaded_by' => null,
            'article_id' => null,
        ];
    }

    public function forArticle(Article $article): static
    {
        return $this->state(fn (): array => ['article_id' => $article->id]);
    }
}
