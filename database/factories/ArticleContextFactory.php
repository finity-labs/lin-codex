<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Database\Factories;

use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\ArticleContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleContext>
 */
class ArticleContextFactory extends Factory
{
    protected $model = ArticleContext::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContextType::Route,
            'key' => fake()->unique()->slug(2),
            'panel_id' => null,
            'sort_order' => 0,
        ];
    }

    public function route(string $name): static
    {
        return $this->state(fn (): array => ['type' => ContextType::Route, 'key' => $name]);
    }

    public function pageClass(string $fqcn): static
    {
        return $this->state(fn (): array => ['type' => ContextType::PageClass, 'key' => $fqcn]);
    }

    public function url(string $pattern): static
    {
        return $this->state(fn (): array => ['type' => ContextType::Url, 'key' => $pattern]);
    }

    public function forPanel(string $panelId): static
    {
        return $this->state(fn (): array => ['panel_id' => $panelId]);
    }
}
