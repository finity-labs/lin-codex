<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Livewire\HelpCenter;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('registers neither help-center route', function (): void {
    expect(Route::has('lin-codex.help-center'))->toBeFalse()
        ->and(Route::has('lin-codex.help-center.article'))->toBeFalse()
        ->and($this->get('/help')->getStatusCode())->toBe(404)
        ->and($this->get('/help/intro')->getStatusCode())->toBe(404);
});

it('keeps the media, API and stylesheet routes', function (): void {
    foreach ([
        'lin-codex.media',
        'lin-codex.api.tree',
        'lin-codex.api.article',
        'lin-codex.api.search',
        'lin-codex.api.context',
        'lin-codex.assets.css',
    ] as $name) {
        expect(Route::getRoutes()->getByName($name))
            ->toBeInstanceOf(RegisteredRoute::class, 'route '.$name.' is not registered');
    }

    $stylesheet = $this->get('/codex/assets/codex.css?v=abc');

    expect($this->get('/codex/media/en/02-users/images/users.png')->getStatusCode())->toBe(200)
        ->and($this->getJson('/codex/api/tree')->getStatusCode())->toBe(200)
        ->and($stylesheet->getStatusCode())->toBe(200)
        ->and((string) $stylesheet->headers->get('Content-Type'))->toStartWith('text/css');
});

it('keeps the help center component and its layout key', function (): void {
    Livewire::test(HelpCenter::class)
        ->assertSeeHtml('data-codex-help-center')
        ->assertSeeHtml('data-codex-tree-node="intro"');

    expect(config('lin-codex.routes.help_center_layout'))->toBeNull();
});
