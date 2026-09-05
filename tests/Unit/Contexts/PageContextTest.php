<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contexts\PageContext;

function linCodexPageFull(): PageContext
{
    return new PageContext('users.show', 'users/1/', '\App\Pages\Users', 'admin');
}

it('normalises every field in the constructor', function (): void {
    $page = linCodexPageFull();

    expect($page->routeName)->toBe('users.show')
        ->and($page->path)->toBe('/users/1')
        ->and($page->pageClass)->toBe('App\Pages\Users')
        ->and($page->panelId)->toBe('admin');
});

it('coerces blank strings to null and an empty path to the root', function (): void {
    $page = new PageContext('', '', '', '');

    expect($page->routeName)->toBeNull()
        ->and($page->path)->toBe('/')
        ->and($page->pageClass)->toBeNull()
        ->and($page->panelId)->toBeNull();
});

it('serialises to the route, path, class, panel array in that order', function (): void {
    expect(linCodexPageFull()->toArray())->toBe([
        'route' => 'users.show',
        'path' => '/users/1',
        'class' => 'App\Pages\Users',
        'panel' => 'admin',
    ]);
});

it('round-trips through toArray() and fromArray()', function (): void {
    $page = linCodexPageFull();

    expect(PageContext::fromArray($page->toArray()))->toEqual($page);
});

it('builds a root page from an empty array', function (): void {
    expect(PageContext::fromArray([]))->toEqual(new PageContext(null, '/'));
});

it('ignores non-string and empty array values', function (): void {
    $page = PageContext::fromArray(['route' => 5, 'path' => 'x', 'class' => '', 'panel' => ['a']]);

    expect($page)->toEqual(new PageContext(null, '/x'));
});

it('survives serialize() and holds no models', function (): void {
    $page = linCodexPageFull();

    expect(unserialize(serialize($page)))->toEqual($page);

    linCodexAssertNoModels($page);
});
