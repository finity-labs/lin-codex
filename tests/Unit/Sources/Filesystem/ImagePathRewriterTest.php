<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Sources\Filesystem\ImagePathRewriter;

it('resolves relative image targets lexically inside the docs tree', function (string $dir, string $target, string $expected): void {
    expect(ImagePathRewriter::resolve($dir, $target))->toBe($expected);
})->with([
    'same folder' => ['en', 'images/reset.png', 'en/images/reset.png'],
    'parent folder' => ['en/02-users', '../images/reset.png', 'en/images/reset.png'],
    'section folder' => ['en/02-users', 'images/users.png', 'en/02-users/images/users.png'],
    'sibling locale' => ['de', '../en/images/reset.png', 'en/images/reset.png'],
    'query kept' => ['en', 'images/reset.png?v=2', 'en/images/reset.png?v=2'],
    'dot segments' => ['en', './images/./reset.png', 'en/images/reset.png'],
]);

it('leaves targets that escape or are not relative paths alone', function (string $target): void {
    expect(ImagePathRewriter::resolve('en', $target))->toBeNull();
})->with([
    'escapes the tree' => '../../secret.png',
    'escapes the locale folder' => '../secret.png',
    'root relative' => '/storage/codex/a.png',
    'absolute url' => 'https://example.com/a.png',
    'protocol relative' => '//cdn/a.png',
    'data uri' => 'data:image/png;base64,xx',
    'fragment' => '#frag',
    'empty' => '',
]);

it('rewrites markdown images and keeps titles and angle brackets', function (): void {
    $body = "![A](images/reset.png)\n![B](images/reset.png \"Cap\")\n![C](<images/reset.png> 'Cap')\n![D](/abs.png)\n![E](../../out.png)";

    expect(ImagePathRewriter::rewrite($body, ArticleFormat::Markdown, 'en', '/codex/media'))
        ->toBe("![A](/codex/media/en/images/reset.png)\n![B](/codex/media/en/images/reset.png \"Cap\")\n![C](</codex/media/en/images/reset.png> 'Cap')\n![D](/abs.png)\n![E](../../out.png)");
});

it('rewrites html img sources and keeps the quote style', function (): void {
    $body = '<img src="../images/reset.png" alt="x"><img src=\'a.png\'><img src="/abs.png">';

    expect(ImagePathRewriter::rewrite($body, ArticleFormat::Html, 'en/02-users', '/codex/media/'))
        ->toBe('<img src="/codex/media/en/images/reset.png" alt="x"><img src=\'/codex/media/en/02-users/a.png\'><img src="/abs.png">');
});

it('only rewrites the syntax of the given format', function (): void {
    expect(ImagePathRewriter::rewrite('<img src="images/x.png">', ArticleFormat::Markdown, 'en', '/codex/media'))
        ->toBe('<img src="images/x.png">')
        ->and(ImagePathRewriter::rewrite('![A](images/x.png)', ArticleFormat::Html, 'en', '/codex/media'))
        ->toBe('![A](images/x.png)');
});
