<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Coverage;

/**
 * One route in the coverage report: its name, URI template, the page class
 * the report derived (controller or Livewire page component, null for a
 * closure), and the context that matched it with the article's slug, both
 * null when no article claims the route.
 */
final readonly class RouteCoverageRow
{
    public function __construct(
        public string $name,
        public string $uri,
        public ?string $pageClass,
        public ?string $matchedBy,
        public ?string $slug,
    ) {}

    public function covered(): bool
    {
        return $this->slug !== null;
    }

    /**
     * @return array{name: string, uri: string, pageClass: ?string, matchedBy: ?string, slug: ?string, covered: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'uri' => $this->uri,
            'pageClass' => $this->pageClass,
            'matchedBy' => $this->matchedBy,
            'slug' => $this->slug,
            'covered' => $this->covered(),
        ];
    }
}
