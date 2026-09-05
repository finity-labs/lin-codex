<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Contexts\RequestContextDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function linCodexDetector(): RequestContextDetector
{
    return app(RequestContextDetector::class);
}

it('captures the normalised path and no route name when no router ran', function (): void {
    $page = linCodexDetector()->detect(Request::create('/users/1/'));

    expect($page)->toEqual(new PageContext(null, '/users/1'));
});

it('drops the query string, keeps the root and decodes the path', function (string $uri, string $expected): void {
    expect(linCodexDetector()->detect(Request::create($uri))->path)->toBe($expected);
})->with([
    'query string' => ['/users/1?x=1', '/users/1'],
    'root' => ['/', '/'],
    'percent-encoded' => ['/a%20b/c', '/a b/c'],
]);

it('takes the page class and panel id the host passes', function (): void {
    $page = linCodexDetector()->detect(Request::create('/x'), '\App\Pages\Users', 'admin');

    expect($page->pageClass)->toBe('App\Pages\Users')
        ->and($page->panelId)->toBe('admin');
});

it('treats a blank page class and panel id as absent', function (): void {
    $page = linCodexDetector()->detect(Request::create('/x'), '', '');

    expect($page->pageClass)->toBeNull()
        ->and($page->panelId)->toBeNull();
});

it('captures the route name of a named route', function (): void {
    Route::get('/users/{id}', fn (Request $request) => app(RequestContextDetector::class)->detect($request, 'App\Pages\Users', 'admin')->toArray())->name('users.show');

    $this->getJson('/users/7')
        ->assertOk()
        ->assertExactJson(['route' => 'users.show', 'path' => '/users/7', 'class' => 'App\Pages\Users', 'panel' => 'admin']);
});

it('leaves the route name null on an unnamed route', function (): void {
    Route::get('/plain', fn (Request $request) => app(RequestContextDetector::class)->detect($request)->toArray());

    $this->getJson('/plain')
        ->assertOk()
        ->assertJsonPath('route', null)
        ->assertJsonPath('path', '/plain');
});

it('reads only the request it is given, never the container request', function (): void {
    Route::get('/served', fn () => app(RequestContextDetector::class)->detect(Request::create('/from-argument'))->toArray());

    $this->getJson('/served')
        ->assertOk()
        ->assertJsonPath('path', '/from-argument')
        ->assertJsonPath('route', null);
});
