<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Sources\Filesystem\ImagePathRewriter;

const LIN_CODEX_RELATIVISE_PREFIX = '/codex/media';

/**
 * @return array{body: string, images: list<string>}
 */
function linCodexRelativise(string $body, string $relativeDir, ArticleFormat $format = ArticleFormat::Markdown, string $prefix = LIN_CODEX_RELATIVISE_PREFIX): array
{
    return ImagePathRewriter::relativise($body, $format, $relativeDir, $prefix);
}

it('turns a media url in the same folder back into a relative path', function (): void {
    $result = linCodexRelativise('![Reset](/codex/media/en/images/reset.png "The reset screen")', 'en');

    expect($result['body'])->toBe('![Reset](images/reset.png "The reset screen")')
        ->and($result['images'])->toBe(['en/images/reset.png']);
});

it('climbs to a parent folder with ..', function (string $relativeDir, string $expected): void {
    $result = linCodexRelativise('![Reset](/codex/media/en/images/reset.png)', $relativeDir);

    expect($result['body'])->toBe('![Reset]('.$expected.')')
        ->and($result['images'])->toBe(['en/images/reset.png']);
})->with([
    'one level' => ['en/02-users', '../images/reset.png'],
    'two levels' => ['en/02-users/03-deep', '../../images/reset.png'],
]);

it('keeps a query or fragment suffix on the target and reports the path without it', function (string $suffix): void {
    $result = linCodexRelativise('![A](/codex/media/en/images/a.png'.$suffix.')', 'en');

    expect($result['body'])->toBe('![A](images/a.png'.$suffix.')')
        ->and($result['images'])->toBe(['en/images/a.png']);
})->with([
    'query' => '?v=2',
    'fragment' => '#frag',
]);

it('leaves absolute urls, other roots, data uris, fragments and already relative targets alone', function (): void {
    $body = "![A](https://x/y.png)\n![B](/other/x.png)\n![C](data:image/png;base64,AA)\n![D](#top)\n![E](images/local.png)";

    $result = linCodexRelativise($body, 'en');

    expect($result['body'])->toBe($body)
        ->and($result['images'])->toBe([]);
});

it('rewrites html img src in double and single quotes', function (): void {
    $body = '<img src="/codex/media/en/02-users/images/users.png" alt="Users"><img src=\'/codex/media/en/02-users/images/users.png\'>';

    $result = linCodexRelativise($body, 'en/02-users', ArticleFormat::Html);

    expect($result['body'])->toBe('<img src="images/users.png" alt="Users"><img src=\'images/users.png\'>')
        ->and($result['images'])->toBe(['en/02-users/images/users.png']);
});

it('only relativises the syntax of the given format', function (): void {
    $markdown = '![A](/codex/media/en/images/x.png)';
    $html = '<img src="/codex/media/en/images/x.png">';

    expect(linCodexRelativise($markdown, 'en', ArticleFormat::Html))->toBe(['body' => $markdown, 'images' => []])
        ->and(linCodexRelativise($html, 'en', ArticleFormat::Markdown))->toBe(['body' => $html, 'images' => []]);
});

it('honours a custom media prefix with or without a trailing slash', function (string $prefix): void {
    $result = linCodexRelativise('![A](/help-media/en/images/a.png)', 'en', ArticleFormat::Markdown, $prefix);

    expect($result['body'])->toBe('![A](images/a.png)')
        ->and($result['images'])->toBe(['en/images/a.png']);
})->with([
    'trailing slash' => '/help-media/',
    'no trailing slash' => '/help-media',
]);

it('is the inverse of rewrite', function (string $body, string $relativeDir, ArticleFormat $format): void {
    $rewritten = ImagePathRewriter::rewrite($body, $format, $relativeDir, LIN_CODEX_RELATIVISE_PREFIX);
    $relativised = ImagePathRewriter::relativise($rewritten, $format, $relativeDir, LIN_CODEX_RELATIVISE_PREFIX);

    expect($rewritten)->not->toBe($body)
        ->and($relativised['body'])->toBe($body)
        ->and(ImagePathRewriter::rewrite($relativised['body'], $format, $relativeDir, LIN_CODEX_RELATIVISE_PREFIX))->toBe($rewritten);
})->with([
    'same folder' => ['![A](images/a.png)', 'en', ArticleFormat::Markdown],
    'parent folder with title' => ['![B](../images/b.png "T")', 'en/02-users', ArticleFormat::Markdown],
    'two images in one paragraph' => ['See ![A](images/a.png) and ![B](../images/b.png "T") here.', 'en/02-users', ArticleFormat::Markdown],
    'html body' => ["<p><img src='c.png'></p>", 'en/02-users', ArticleFormat::Html],
]);

it('rewrites an already rewritten body back to itself', function (): void {
    $rewritten = "![A](/codex/media/en/images/a.png)\n<img src=\"x.png\">\n![B](/codex/media/en/02-users/images/b.png?v=1)";

    $relativised = ImagePathRewriter::relativise($rewritten, ArticleFormat::Markdown, 'en', LIN_CODEX_RELATIVISE_PREFIX);

    expect($relativised['body'])->toBe("![A](images/a.png)\n<img src=\"x.png\">\n![B](02-users/images/b.png?v=1)")
        ->and(ImagePathRewriter::rewrite($relativised['body'], ArticleFormat::Markdown, 'en', LIN_CODEX_RELATIVISE_PREFIX))->toBe($rewritten);
});

it('crosses locales lexically', function (): void {
    $result = linCodexRelativise('![X](/codex/media/de/images/x.png)', 'en/02-users');

    expect($result['body'])->toBe('![X](../../de/images/x.png)')
        ->and($result['images'])->toBe(['de/images/x.png'])
        ->and(ImagePathRewriter::rewrite($result['body'], ArticleFormat::Markdown, 'en/02-users', LIN_CODEX_RELATIVISE_PREFIX))
        ->toBe('![X](/codex/media/de/images/x.png)');
});

it('reports each image once, in document order', function (): void {
    $body = "![B](/codex/media/en/images/b.png)\n![A](/codex/media/en/images/a.png?v=2)\n![B again](/codex/media/en/images/b.png#x)\n![C](/codex/media/en/02-users/images/c.png)";

    $result = linCodexRelativise($body, 'en');

    expect($result['images'])->toBe(['en/images/b.png', 'en/images/a.png', 'en/02-users/images/c.png']);
});
