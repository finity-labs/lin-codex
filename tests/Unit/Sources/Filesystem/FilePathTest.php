<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Sources\Filesystem\FilePath;

it('derives slug, order and section flag from a relative path', function (string $path, ?array $expected): void {
    expect(FilePath::derive($path))->toBe($expected);
})->with([
    'prefixed file' => ['01-intro.md', ['slug' => 'intro', 'order' => 1, 'isSection' => false, 'normalised' => false]],
    'nested prefixed file' => ['02-users/01-roles.md', ['slug' => 'users/roles', 'order' => 1, 'isSection' => false, 'normalised' => false]],
    'section index' => ['02-users/index.md', ['slug' => 'users', 'order' => 2, 'isSection' => true, 'normalised' => false]],
    'html file' => ['02-users/02-permissions.html', ['slug' => 'users/permissions', 'order' => 2, 'isSection' => false, 'normalised' => false]],
    'unprefixed file in prefixed folder' => ['03-billing/invoices.md', ['slug' => 'billing/invoices', 'order' => 0, 'isSection' => false, 'normalised' => false]],
    'root index' => ['index.md', null],
    'slugs to nothing' => ['???.md', null],
    'nested slugs to nothing' => ['a/???.md', null],
    'spaces and case' => ['Reset Password.md', ['slug' => 'reset-password', 'order' => 0, 'isSection' => false, 'normalised' => true]],
    'cased section' => ['02-Users/Index.md', ['slug' => 'users', 'order' => 2, 'isSection' => true, 'normalised' => true]],
    'digits without separator' => ['2fa.md', ['slug' => '2fa', 'order' => 0, 'isSection' => false, 'normalised' => false]],
    'deep nesting' => ['a/b/c.md', ['slug' => 'a/b/c', 'order' => 0, 'isSection' => false, 'normalised' => false]],
]);

it('picks the format from the extension', function (string $path, ArticleFormat $format): void {
    expect(FilePath::format($path))->toBe($format);
})->with([
    'md' => ['x.md', ArticleFormat::Markdown],
    'upper html' => ['x.HTML', ArticleFormat::Html],
    'nested html' => ['dir/x.html', ArticleFormat::Html],
    'markdown' => ['x.markdown', ArticleFormat::Markdown],
]);

it('recognises locale folders', function (string $name, bool $expected): void {
    expect(FilePath::isLocaleFolder($name))->toBe($expected);
})->with([
    'en' => ['en', true],
    'pt-BR' => ['pt-BR', true],
    'zh_Hant' => ['zh_Hant', true],
    'images' => ['images', false],
    'e' => ['e', false],
    'shared' => ['shared', false],
    '02-users' => ['02-users', false],
]);
