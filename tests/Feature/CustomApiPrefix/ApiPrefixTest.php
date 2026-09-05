<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;

/**
 * The registered route for a lin-codex.api.* name, failing loudly when it
 * is missing so the assertions after it read cleanly.
 */
function linCodexApiPrefixNamed(string $name): RegisteredRoute
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

it('registers the routes under the configured prefix and middleware group', function (): void {
    $expected = [
        'tree' => 'help-api/tree',
        'article' => 'help-api/articles/{slug}',
        'search' => 'help-api/search',
        'context' => 'help-api/context',
    ];

    foreach ($expected as $name => $uri) {
        $route = linCodexApiPrefixNamed($name);

        expect($route->uri())->toBe($uri)
            ->and($route->middleware())->toBe(['api']);
    }

    expect(route('lin-codex.api.article', ['slug' => 'a/b']))->toBe('http://localhost/help-api/articles/a/b');

    $response = $this->getJson('/help-api/tree');

    $response->assertOk();

    expect(array_keys($response->json()))->toBe(['data', 'meta'])
        ->and($this->getJson('/codex/api/tree')->getStatusCode())->toBe(404);
});
