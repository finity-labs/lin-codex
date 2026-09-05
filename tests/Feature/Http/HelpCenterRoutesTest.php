<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * The registered route for a name, failing loudly when it is missing so the
 * assertions after it read cleanly.
 */
function linCodexHelpCenterRoutesNamed(string $name): RegisteredRoute
{
    $route = Route::getRoutes()->getByName($name);

    expect($route)->toBeInstanceOf(RegisteredRoute::class, 'route '.$name.' is not registered');

    /** @var RegisteredRoute $route */
    return $route;
}

/**
 * The page title the help center passes to its layout: the article title
 * (or the help center label) and the app name.
 */
function linCodexHelpCenterRoutesTitle(string $title): string
{
    return '<title>'.$title.' · '.config('app.name').'</title>';
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('registers both named routes on the configured middleware', function (): void {
    $index = linCodexHelpCenterRoutesNamed('lin-codex.help-center');
    $article = linCodexHelpCenterRoutesNamed('lin-codex.help-center.article');

    expect($index->uri())->toBe('help')
        ->and($index->methods())->toContain('GET')
        ->and($index->middleware())->toBe(['web'])
        ->and($article->uri())->toBe('help/{slug}')
        ->and($article->methods())->toContain('GET')
        ->and($article->wheres['slug'] ?? null)->toBe('.+')
        ->and($article->middleware())->toBe(['web'])
        ->and(route('lin-codex.help-center'))->toBe('http://localhost/help')
        ->and(route('lin-codex.help-center.article', ['slug' => 'users/roles']))->toBe('http://localhost/help/users/roles')
        ->and(linCodexHelpCenterRoutesNamed('lin-codex.assets.css')->middleware())->toBe([]);
});

it('renders the help center page in the package layout', function (): void {
    $response = $this->get('/help');
    $content = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and(stripos($content, '<!doctype html>'))->not->toBeFalse()
        ->and($content)->toContain(
            linCodexHelpCenterRoutesTitle(__('lin-codex::lin-codex.ui.help_center')),
            '/codex/assets/codex.css?v=',
            'codex-help-center-header__app',
            'href="http://localhost"',
            'data-codex-help-center',
            'data-codex-tree-node="intro"',
            'data-codex-tree-node="users"',
            __('lin-codex::lin-codex.ui.pick_a_topic'),
        )
        ->and($content)->not->toContain('users/permissions');
});

it('renders an article page with breadcrumbs and body', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $response = $this->get('/help/users/permissions');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->toContain(
            'data-codex-slug="users/permissions"',
            'codex-breadcrumb',
            'users.delete',
            'codex-tree__article--active',
        );
});

it('renders the on-this-page column, the lightbox hook and in-place links for a guest', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-ui')]);
    $this->forgetSources();

    $response = $this->get('/help/guide');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->toContain(
            linCodexHelpCenterRoutesTitle('Guide'),
            'data-codex-slug="guide"',
            'codex-help-center__toc',
            'href="#third-step"',
            'data-codex-lightbox',
            'data-codex-article="tips"',
        );
});

it('answers a hidden or missing slug with the not-found line, never 403', function (string $slug): void {
    $response = $this->get('/help/'.$slug);

    expect($response->getStatusCode())->toBe(200)->not->toBe(403)
        ->and((string) $response->getContent())->toContain(__('lin-codex::lin-codex.ui.not_found'), 'data-codex-tree-node="intro"')
        ->and((string) $response->getContent())->not->toContain('data-codex-slug=');
})->with(['users/permissions', 'users/roles', 'does-not-exist']);

it('uses a host component layout when configured', function (): void {
    View::addNamespace('zz', __DIR__.'/../../Fixtures/views');
    config()->set('lin-codex.routes.help_center_layout', 'zz::host-layout');

    $response = $this->get('/help');
    $content = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($content)->toContain('id="host-layout-marker"', 'data-codex-help-center', 'data-codex-tree-node="intro"', linCodexHelpCenterRoutesTitle(__('lin-codex::lin-codex.ui.help_center')))
        ->and($content)->not->toContain('codex-help-center-header')
        ->and($content)->not->toContain('/codex/assets/codex.css');
});

it('falls back to the package layout for an empty layout name', function (): void {
    config()->set('lin-codex.routes.help_center_layout', '');

    $response = $this->get('/help');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->toContain('codex-help-center-header', '/codex/assets/codex.css?v=', 'data-codex-help-center');
});
