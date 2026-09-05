<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Database\Factories;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Models\ArticleRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleRevision>
 */
class ArticleRevisionFactory extends Factory
{
    protected $model = ArticleRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'en',
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'format' => ArticleFormat::Markdown,
            'reason' => RevisionReason::Manual,
            'user_id' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['reason' => RevisionReason::Manual]);
    }

    public function import(): static
    {
        return $this->state(fn (): array => ['reason' => RevisionReason::Import]);
    }

    public function aiRewrite(): static
    {
        return $this->state(fn (): array => ['reason' => RevisionReason::AiRewrite]);
    }

    public function restore(): static
    {
        return $this->state(fn (): array => ['reason' => RevisionReason::Restore]);
    }

    public function byUser(int $userId): static
    {
        return $this->state(fn (): array => ['user_id' => $userId]);
    }
}
