<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Coverage;

use FinityLabs\LinCodex\Contexts\ContextIndex;
use FinityLabs\LinCodex\Contexts\ContextMatch;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Contracts\ContentSource;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Str;
use Livewire\LivewireManager;
use Throwable;

/**
 * Which registered pages have a help article and which do not. A route is
 * a page when it answers GET, has a name, and either runs in the "web"
 * middleware group or has StartSession somewhere in its expanded stack;
 * the session clause exists for Filament panels, whose routes list their
 * middleware classes explicitly and never name the group. Names matching
 * a lin-codex.coverage.ignore glob and actions under a
 * lin-codex.coverage.vendor_namespaces prefix are left out.
 *
 * Each page is reduced to a PageContext (route name, URI template with
 * placeholders kept as one segment, page class) and looked up in one
 * ContextIndex over ContentSource::all(): first without a panel, then in
 * every panel id the index knows, so a context scoped to any panel counts.
 * The index is built from all() without the viewer gate and without the
 * published filter on purpose: an unpublished or members-only article is
 * still a mapping, and coverage asks whether a mapping exists.
 *
 * The page class comes from the Livewire route macro's action entry when
 * present (a class, an object, or a component name resolved through the
 * Livewire manager), else from the route's controller class; closures
 * have none and stay in the report.
 */
final class RouteCoverage
{
    public function __construct(
        private readonly Router $router,
        private readonly ContentSource $source,
    ) {}

    /**
     * @return list<RouteCoverageRow> sorted by route name
     */
    public function report(): array
    {
        $index = ContextIndex::fromArticles($this->source->all());
        $panels = [null, ...$index->panelIds()];
        $ignore = $this->configStrings('lin-codex.coverage.ignore');
        $vendors = $this->configStrings('lin-codex.coverage.vendor_namespaces');

        $rows = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();

            if ($name === null || $name === '' || $this->isIgnored($name, $ignore) || ! $this->startsSession($route)) {
                continue;
            }

            $class = $this->pageClass($route);

            if ($class !== null && $this->isVendor($class, $vendors)) {
                continue;
            }

            $uri = $this->uri($route);
            $match = $this->match($index, new PageContext($name, $uri, $class, null), $panels);

            $rows[] = new RouteCoverageRow($name, $uri, $class, $match?->context->toString(), $match?->slug);
        }

        usort($rows, static fn (RouteCoverageRow $a, RouteCoverageRow $b): int => strcmp($a->name, $b->name));

        return $rows;
    }

    /**
     * The first candidate over the panel list: panel-less contexts first,
     * then each known panel in first-seen order.
     *
     * @param  list<?string>  $panels
     */
    private function match(ContextIndex $index, PageContext $page, array $panels): ?ContextMatch
    {
        foreach ($panels as $panel) {
            $candidates = $index->candidates($page, $panel);

            if ($candidates !== []) {
                return $candidates[0];
            }
        }

        return null;
    }

    /**
     * "web" as written on the route, or StartSession in the stack the
     * kernel would run (groups and aliases expanded).
     */
    private function startsSession(Route $route): bool
    {
        return in_array('web', $route->gatherMiddleware(), true)
            || in_array(StartSession::class, $this->router->gatherRouteMiddleware($route), true);
    }

    /**
     * The URI template with a leading slash and optional placeholders made
     * plain, so "/users/{user?}" is one segment like "/users/{user}".
     */
    private function uri(Route $route): string
    {
        $uri = preg_replace('/\{(\w+)\?\}/', '{$1}', $route->uri()) ?? $route->uri();

        return '/'.ltrim($uri, '/');
    }

    private function pageClass(Route $route): ?string
    {
        $component = $route->getAction('livewire_component');

        if (is_object($component)) {
            return $component::class;
        }

        if (is_string($component) && $component !== '') {
            return class_exists($component) ? ltrim($component, '\\') : $this->livewireClass($component);
        }

        $class = $route->getControllerClass();

        return is_string($class) && $class !== '' ? ltrim($class, '\\') : null;
    }

    /**
     * A component registered under a name (the single-file form): ask the
     * Livewire manager for an instance, without mounting it; null when the
     * name is unknown or Livewire is not bound.
     */
    private function livewireClass(string $name): ?string
    {
        if (! app()->bound(LivewireManager::class)) {
            return null;
        }

        try {
            return app(LivewireManager::class)->new($name)::class;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $patterns
     */
    private function isIgnored(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function isVendor(string $class, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($class, ltrim($prefix, '\\'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function configStrings(string $key): array
    {
        $values = config($key, []);

        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }
}
