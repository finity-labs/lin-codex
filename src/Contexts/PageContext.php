<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contexts;

/**
 * What a page is reduced to for context matching: the route name when the
 * route is named, the request path, and the page class and panel id when
 * the host knows them. Captured once, at mount, by RequestContextDetector.
 *
 * Every instance is canonical: the path is normalised (leading slash, no
 * trailing slash), the class carries no leading backslash, and blank
 * strings are null. The array form (route, path, class, panel) is the
 * contract a Livewire component holds as locked state and the contract the
 * JSON API reads from the query string.
 */
final readonly class PageContext
{
    public ?string $routeName;

    public string $path;

    public ?string $pageClass;

    public ?string $panelId;

    public function __construct(?string $routeName, string $path, ?string $pageClass = null, ?string $panelId = null)
    {
        $this->routeName = self::blank($routeName);
        $this->path = PatternMatcher::normalisePath($path);
        $this->pageClass = $pageClass === null ? null : self::blank(PatternMatcher::normaliseClass($pageClass));
        $this->panelId = self::blank($panelId);
    }

    /**
     * @return array{route: ?string, path: string, class: ?string, panel: ?string}
     */
    public function toArray(): array
    {
        return [
            'route' => $this->routeName,
            'path' => $this->path,
            'class' => $this->pageClass,
            'panel' => $this->panelId,
        ];
    }

    /**
     * Non-string or empty values become null; a missing path becomes `/`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (mixed $value): ?string => is_string($value) ? $value : null;

        return new self(
            $string($data['route'] ?? null),
            $string($data['path'] ?? null) ?? '/',
            $string($data['class'] ?? null),
            $string($data['panel'] ?? null),
        );
    }

    private static function blank(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
