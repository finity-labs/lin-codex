<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;

/**
 * The registered route for a lin-codex.api.* name, failing loudly when it
 * is missing so the assertions after it read cleanly.
 */
function linCodexApiRoutesNamed(string $name): RegisteredRoute
{
    $route = Route::getRoutes()->getByName('lin-codex.api.'.$name);

    expect($route)->toBeInstanceOf(RegisteredRoute::class, 'route lin-codex.api.'.$name.' is not registered');

    /** @var RegisteredRoute $route */
    return $route;
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('registers the four named routes under the default prefix', function (): void {
    $expected = [
        'tree' => 'codex/api/tree',
        'article' => 'codex/api/articles/{slug}',
        'search' => 'codex/api/search',
        'context' => 'codex/api/context',
    ];

    foreach ($expected as $name => $uri) {
        $route = linCodexApiRoutesNamed($name);

        expect($route->uri())->toBe($uri)
            ->and($route->methods())->toContain('GET');
    }
});

it('runs every api route under the configured middleware group', function (): void {
    foreach (['tree', 'article', 'search', 'context'] as $name) {
        expect(linCodexApiRoutesNamed($name)->middleware())->toBe(['web']);
    }
});

it('builds article urls with slashes in the slug', function (): void {
    expect(route('lin-codex.api.article', ['slug' => 'users/roles']))->toBe('http://localhost/codex/api/articles/users/roles');
});

it('answers every endpoint with a data and meta envelope', function (): void {
    $urls = [
        '/codex/api/tree',
        '/codex/api/articles/intro',
        '/codex/api/search?q=intro',
        '/codex/api/context?path=/',
    ];

    foreach ($urls as $url) {
        $response = $this->getJson($url);

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta' => ['locale', 'defaultLocale']]);

        expect(array_keys($response->json()))->toBe(['data', 'meta'], $url)
            ->and($response->json('meta.locale'))->toBe('en', $url)
            ->and($response->json('meta.defaultLocale'))->toBe('en', $url);
    }
});

it('reaches the article controller through a slug with a slash', function (): void {
    $response = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/articles/users/permissions');

    $response->assertOk();

    expect($response->json('data.slug'))->toBe('users/permissions');
});

it('refuses an empty article slug at the router', function (): void {
    expect($this->get('/codex/api/articles/')->getStatusCode())->toBe(404);
});

it('answers json errors without an accept header', function (): void {
    $response = $this->get('/codex/api/articles/does-not-exist');

    expect($response->getStatusCode())->toBe(404)->not->toBe(403);

    $response->assertExactJson(['message' => 'Article not found.']);
});
