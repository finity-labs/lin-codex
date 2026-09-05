<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\ArticlePath;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;

/**
 * The registered route for a name, failing loudly when it is missing so the
 * assertions after it read cleanly.
 */
function linCodexHelpCenterPrefixNamed(string $name): RegisteredRoute
{
    $route = Route::getRoutes()->getByName($name);

    expect($route)->toBeInstanceOf(RegisteredRoute::class, 'route '.$name.' is not registered');

    /** @var RegisteredRoute $route */
    return $route;
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('moves both routes to the configured prefix and keeps the article links in step', function (): void {
    $index = linCodexHelpCenterPrefixNamed('lin-codex.help-center');
    $article = linCodexHelpCenterPrefixNamed('lin-codex.help-center.article');

    expect($index->uri())->toBe('docs')
        ->and($article->uri())->toBe('docs/{slug}')
        ->and($article->wheres['slug'] ?? null)->toBe('.+')
        ->and($index->middleware())->toBe(['web'])
        ->and($article->middleware())->toBe(['web'])
        ->and(route('lin-codex.help-center'))->toBe('http://localhost/docs')
        ->and(route('lin-codex.help-center.article', ['slug' => 'users/roles']))->toBe('http://localhost/docs/users/roles')
        ->and(ArticlePath::href('users'))->toBe('/docs/users');
});

it('renders an article under the prefix with tree and article links that match the routes', function (): void {
    $response = $this->get('/docs/intro');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->toContain('data-codex-slug="intro"', 'data-codex-tree-node="users"', 'href="/docs/users"');
});

it('no longer answers on the default prefix', function (): void {
    expect($this->get('/help')->getStatusCode())->toBe(404)
        ->and($this->get('/help/intro')->getStatusCode())->toBe(404);
});
