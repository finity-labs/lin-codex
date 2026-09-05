<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\ArticlePath;

it('resolves a sibling article path', function (): void {
    expect(ArticlePath::resolve('users/permissions', 'roles.md'))
        ->toBe(['slug' => 'users/roles', 'fragment' => '']);
});

it('resolves parent traversal and keeps the fragment', function (): void {
    expect(ArticlePath::resolve('users/permissions', '../billing/invoices.md#totals'))
        ->toBe(['slug' => 'billing/invoices', 'fragment' => '#totals']);
});

it('resolves nested and dot-prefixed paths from a top-level article', function (): void {
    expect(ArticlePath::resolve('intro', 'users/roles.md'))
        ->toBe(['slug' => 'users/roles', 'fragment' => ''])
        ->and(ArticlePath::resolve('intro', './roles.MD'))
        ->toBe(['slug' => 'roles', 'fragment' => '']);
});

it('refuses to climb above the article root', function (): void {
    expect(ArticlePath::resolve('intro', '../escape.md'))->toBeNull()
        ->and(ArticlePath::resolve('users/permissions', '../../escape.md'))->toBeNull();
});

it('leaves unresolvable targets alone', function (string $target): void {
    expect(ArticlePath::resolve('users/permissions', $target))->toBeNull();
})->with([
    'absolute url' => 'https://example.com/a.md',
    'protocol relative' => '//cdn/a.md',
    'root relative' => '/abs/a.md',
    'mailto' => 'mailto:x@y.z',
    'fragment only' => '#only',
    'no extension' => 'roles',
    'query string' => 'roles.md?x=1',
    'html extension' => 'roles.html',
    'space in segment' => 'we ird.md',
    'empty' => '',
]);

it('builds the help center href from config at call time', function (): void {
    expect(ArticlePath::href('users/roles', '#x'))->toBe('/help/users/roles#x');

    config()->set('lin-codex.routes.help_center', 'https://app.test/manual/');

    expect(ArticlePath::href('users/roles', '#x'))->toBe('https://app.test/manual/users/roles#x');
});

it('resolves links from a section render slug against its folder', function (): void {
    expect(ArticlePath::resolve('users/index', 'roles.md'))
        ->toBe(['slug' => 'users/roles', 'fragment' => ''])
        ->and(ArticlePath::resolve('users/index', '01-roles.md'))
        ->toBe(['slug' => 'users/roles', 'fragment' => '']);
});

it('strips numeric ordering prefixes from link segments', function (): void {
    expect(ArticlePath::resolve('intro', '02-users/01-roles.md'))
        ->toBe(['slug' => 'users/roles', 'fragment' => ''])
        ->and(ArticlePath::resolve('intro', '2fa.md'))
        ->toBe(['slug' => '2fa', 'fragment' => ''])
        ->and(ArticlePath::resolve('intro', '01-.md'))->toBeNull();
});

it('collapses a trailing index to the folder slug', function (): void {
    expect(ArticlePath::resolve('users/roles', 'index.md#top'))
        ->toBe(['slug' => 'users', 'fragment' => '#top'])
        ->and(ArticlePath::resolve('intro', '02-users/index.md'))
        ->toBe(['slug' => 'users', 'fragment' => ''])
        ->and(ArticlePath::resolve('intro', 'index.md'))->toBeNull()
        ->and(ArticlePath::resolve('users/index', '../index.md'))->toBeNull();
});

it('splits a numeric ordering prefix from a segment', function (string $segment, string $expectedSegment, ?int $expectedOrder): void {
    expect(ArticlePath::stripOrderPrefix($segment))
        ->toBe(['segment' => $expectedSegment, 'order' => $expectedOrder]);
})->with([
    'dash' => ['01-intro', 'intro', 1],
    'dot' => ['10.things', 'things', 10],
    'underscore' => ['3_x', 'x', 3],
    'no separator' => ['2fa', '2fa', null],
    'digits only' => ['007', '007', null],
    'plain' => ['intro', 'intro', null],
    'empty' => ['', '', null],
]);

it('renders sections under an index render slug', function (): void {
    expect(ArticlePath::renderSlug('users', true))->toBe('users/index')
        ->and(ArticlePath::renderSlug('users', false))->toBe('users')
        ->and(ArticlePath::renderSlug('a/b', true))->toBe('a/b/index');
});
