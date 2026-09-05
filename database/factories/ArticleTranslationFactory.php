<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Database\Factories;

use FinityLabs\LinCodex\Models\ArticleTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleTranslation>
 */
class ArticleTranslationFactory extends Factory
{
    protected $model = ArticleTranslation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'en',
            'title' => fake()->sentence(4),
            'excerpt' => fake()->optional()->sentence(),
            'body' => '# '.fake()->sentence(3)."\n\n".fake()->paragraphs(2, true),
            'search_text' => null,
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(fn (): array => ['locale' => $locale]);
    }

    public function withSearchText(): static
    {
        return $this->state(fn (): array => ['search_text' => fake()->paragraph()]);
    }
}
