<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Database\Factories;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'sort_order' => 0,
            'icon' => null,
            'format' => ArticleFormat::Markdown,
            'visibility' => Visibility::Authenticated,
            'is_published' => true,
            'source_path' => null,
            'keywords' => [],
            'related' => [],
            'meta' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Column states
    |--------------------------------------------------------------------------
    */

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }

    public function public(): static
    {
        return $this->state(fn (): array => ['visibility' => Visibility::Public]);
    }

    public function authenticated(): static
    {
        return $this->state(fn (): array => ['visibility' => Visibility::Authenticated]);
    }

    public function markdown(): static
    {
        return $this->state(fn (): array => ['format' => ArticleFormat::Markdown]);
    }

    public function html(): static
    {
        return $this->state(fn (): array => ['format' => ArticleFormat::Html]);
    }

    /**
     * @param  list<string>  $keywords
     */
    public function withKeywords(array $keywords): static
    {
        return $this->state(fn (): array => ['keywords' => $keywords]);
    }

    /**
     * @param  list<string>  $related  slugs
     */
    public function withRelated(array $related): static
    {
        return $this->state(fn (): array => ['related' => $related]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        return $this->state(fn (): array => ['meta' => $meta]);
    }

    /**
     * Nest the article under $parent by slug; the model's saving hook sets parent_id.
     */
    public function childOf(Article $parent, ?string $segment = null): static
    {
        return $this->state(fn (): array => [
            'slug' => $parent->slug.'/'.($segment ?? Str::slug(fake()->unique()->words(2, true))),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationship states
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->has(
            ArticleTranslationFactory::new()->state(fn (): array => ['locale' => $locale] + $attributes),
            'translations',
        );
    }

    public function withContext(ContextType $type, string $key, ?string $panelId = null, int $sortOrder = 0): static
    {
        return $this->has(
            ArticleContextFactory::new()->state(fn (): array => [
                'type' => $type,
                'key' => $key,
                'panel_id' => $panelId,
                'sort_order' => $sortOrder,
            ]),
            'contexts',
        );
    }

    public function withRevisions(int $count = 1, string $locale = 'en'): static
    {
        return $this->has(
            ArticleRevisionFactory::new()->count($count)->state(fn (): array => ['locale' => $locale]),
            'revisions',
        );
    }

    public function withMedia(int $count = 1): static
    {
        return $this->has(MediaFactory::new()->count($count), 'media');
    }
}
