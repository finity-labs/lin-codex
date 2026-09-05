<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Contexts\PatternMatcher;
use FinityLabs\LinCodex\Data\ContextData;

dataset('lin-codex url patterns', [
    'one segment' => ['/users/*', '/users/1', true],
    'one segment does not cross a slash' => ['/users/*', '/users/1/edit', false],
    'one segment needs a segment' => ['/users/*', '/users', false],
    'any depth includes zero' => ['/users/**', '/users', true],
    'any depth crosses slashes' => ['/users/**', '/users/1/edit', true],
    'one segment in the middle' => ['/users/*/edit', '/users/1/edit', true],
    'one segment in the middle does not span two' => ['/users/*/edit', '/users/1/2/edit', false],
    'any depth in the middle spans two' => ['/users/**/edit', '/users/1/2/edit', true],
    'any depth in the middle spans zero' => ['/users/**/edit', '/users/edit', true],
    'root exact' => ['/', '/', true],
    'root does not match a child' => ['/', '/users', false],
    'catch-all matches the root' => ['/**', '/', true],
    'catch-all matches any depth' => ['/**', '/anything/deep', true],
    'dot is literal' => ['/users.php', '/usersXphp', false],
    'parentheses are literal' => ['/a(b)', '/a(b)', true],
    'plus is literal' => ['/a+b', '/a+b', true],
    'plus is not a quantifier' => ['/a+b', '/aab', false],
    'pattern is normalised' => ['users/1/', '/users/1', true],
    'case-sensitive' => ['/Users', '/users', false],
]);

dataset('lin-codex route patterns', [
    'exact' => ['users.index', 'users.index', true],
    'trailing wildcard' => ['users.*', 'users.index', true],
    'trailing wildcard needs the prefix' => ['users.*', 'users', false],
    'trailing wildcard is a prefix match' => ['users.*', 'usersx.index', false],
    'bare star matches everything' => ['*', 'anything', true],
    'non-trailing star is literal' => ['users.*.edit', 'users.1.edit', false],
    'non-trailing star matches itself' => ['users.*.edit', 'users.*.edit', true],
    'exact does not prefix-match' => ['users.index', 'users.index.x', false],
]);

dataset('lin-codex wildcard contexts', [
    'class is never a wildcard' => ['class:App\X', false],
    'route trailing star' => ['route:users.*', true],
    'route exact' => ['route:users.index', false],
    'route star elsewhere' => ['route:a*b', false],
    'url star' => ['url:/users/*', true],
    'url exact' => ['url:/users', false],
    'url catch-all' => ['url:/**', true],
]);

dataset('lin-codex page matches', [
    'class exact' => ['class:App\Pages\Users', true],
    'class with leading backslash' => ['class:\App\Pages\Users', true],
    'class other' => ['class:App\Pages\Base', false],
    'route wildcard' => ['route:users.*', true],
    'route other' => ['route:other', false],
    'url one segment' => ['url:/users/*', true],
    'url other' => ['url:/users', false],
]);

dataset('lin-codex blank page matches', [
    'route catch-all needs a route name' => ['route:*', false],
    'class needs a page class' => ['class:App\X', false],
    'url root' => ['url:/', true],
    'url catch-all' => ['url:/**', true],
]);

it('normalises paths to a leading slash, no trailing slash and no empty segments', function (string $path, string $expected): void {
    expect(PatternMatcher::normalisePath($path))->toBe($expected);
})->with([
    ['users/1', '/users/1'],
    ['/users/1/', '/users/1'],
    ['', '/'],
    ['/', '/'],
    ['//users//', '/users'],
    ['a b/c', '/a b/c'],
    ['/./x', '/./x'],
]);

it('matches url patterns with * for one segment and ** for any depth', function (string $pattern, string $path, bool $expected): void {
    expect(PatternMatcher::urlMatches($pattern, $path))->toBe($expected);
})->with('lin-codex url patterns');

it('matches route patterns exactly or by a trailing star', function (string $pattern, string $name, bool $expected): void {
    expect(PatternMatcher::routeMatches($pattern, $name))->toBe($expected);
})->with('lin-codex route patterns');

it('strips the leading backslash from a class name', function (): void {
    expect(PatternMatcher::normaliseClass('\App\Pages\Users'))->toBe('App\Pages\Users')
        ->and(PatternMatcher::normaliseClass('App\Pages\Users'))->toBe('App\Pages\Users');
});

it('tells wildcard contexts from exact ones', function (string $context, bool $expected): void {
    expect(PatternMatcher::isWildcard(ContextData::fromString($context)))->toBe($expected);
})->with('lin-codex wildcard contexts');

it('dispatches a context to the matching page field', function (string $context, bool $expected): void {
    $page = new PageContext('users.index', '/users/1', 'App\Pages\Users', null);

    expect(PatternMatcher::matches(ContextData::fromString($context), $page))->toBe($expected);
})->with('lin-codex page matches');

it('matches nothing against a null page field', function (string $context, bool $expected): void {
    $page = new PageContext(null, '/', null, null);

    expect(PatternMatcher::matches(ContextData::fromString($context), $page))->toBe($expected);
})->with('lin-codex blank page matches');

it('does not backtrack catastrophically on many wildcards', function (): void {
    $pattern = '/'.str_repeat('*/', 30).'x';
    $path = '/'.str_repeat('a/', 30).'y';

    $start = hrtime(true);
    $result = PatternMatcher::urlMatches($pattern, $path);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    expect($result)->toBeFalse()
        ->and($elapsedMs)->toBeLessThan(500);
});
