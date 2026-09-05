<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\Filesystem\FrontMatter;
use FinityLabs\LinCodex\Sources\Filesystem\FrontMatterWriter;

/**
 * @param  array<string, mixed>  $overrides
 */
function linCodexWriterArticle(array $overrides = []): ArticleData
{
    $defaults = [
        'slug' => 'users/roles',
        'parentSlug' => 'users',
        'order' => 1,
        'icon' => 'heroicon-o-key',
        'format' => ArticleFormat::Markdown,
        'visibility' => Visibility::Public,
        'published' => true,
        'contexts' => [
            new ContextData(ContextType::Route, 'users.index', null, 0),
            new ContextData(ContextType::Url, '/admin/users/*', null, 1),
            new ContextData(ContextType::PageClass, 'App\Filament\Resources\UserResource', 'admin', 2),
        ],
        'related' => ['users'],
        'keywords' => ['rbac', 'sign in'],
        'translations' => [],
        'meta' => ['owner' => 'docs-team', 'review' => ['cycle' => 'quarterly']],
    ];

    return new ArticleData(...array_merge($defaults, $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function linCodexWriterTranslation(array $overrides = []): TranslationData
{
    $defaults = [
        'locale' => 'en',
        'title' => 'Roles',
        'excerpt' => 'Who may do what.',
        'body' => "Roles group permissions.\n",
        'searchText' => null,
    ];

    return new TranslationData(...array_merge($defaults, $overrides));
}

function linCodexWriterWrite(ArticleData $article, ?TranslationData $translation = null, bool $primary = true, string $target = 'en/02-users/01-roles.md'): string
{
    $translation ??= linCodexWriterTranslation();

    return (new FrontMatterWriter)->write($article, $translation, $translation->body, $primary, $target);
}

/**
 * The lines between the fences, so a test can pin position without the body.
 *
 * @return list<string>
 */
function linCodexWriterFrontMatterLines(string $written): array
{
    $split = FrontMatter::split($written);

    return explode("\n", (string) $split['yaml']);
}

it('writes the keys in the fixed order and only those with a value', function (): void {
    $expected = <<<'MD'
    ---
    title: Roles
    excerpt: 'Who may do what.'
    icon: heroicon-o-key
    visibility: public
    contexts:
      - 'route:users.index'
      - 'url:/admin/users/*'
      - 'admin:class:App\Filament\Resources\UserResource'
    related:
      - users
    keywords:
      - rbac
      - 'sign in'
    owner: docs-team
    review:
      cycle: quarterly
    ---

    Roles group permissions.

    MD;

    expect(linCodexWriterWrite(linCodexWriterArticle()))->toBe($expected);
});

it('writes order when it differs from the filename prefix', function (string $target, int $order, ?string $expectedLine): void {
    $lines = linCodexWriterFrontMatterLines(linCodexWriterWrite(linCodexWriterArticle(['order' => $order]), target: $target));
    $orderLines = array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'order:')));

    if ($expectedLine === null) {
        expect($orderLines)->toBe([]);

        return;
    }

    expect($orderLines)->toBe([$expectedLine])
        ->and(array_search($expectedLine, $lines, true))->toBe(array_search('icon: heroicon-o-key', $lines, true) + 1)
        ->and($lines[array_search($expectedLine, $lines, true) + 1])->toBe('visibility: public');
})->with([
    'no prefix, order 1' => ['en/02-users/roles.md', 1, 'order: 1'],
    'prefix 1, order 3' => ['en/02-users/01-roles.md', 3, 'order: 3'],
    'prefix 1, order 1' => ['en/02-users/01-roles.md', 1, null],
    'no prefix, order 0' => ['en/02-users/roles.md', 0, null],
]);

it('writes slug only when the last segment differs from the path', function (): void {
    $renamed = linCodexWriterFrontMatterLines(linCodexWriterWrite(linCodexWriterArticle(['slug' => 'users/questions']), target: 'en/02-users/faq.md'));
    $same = linCodexWriterWrite(linCodexWriterArticle(['slug' => 'users/faq']), target: 'en/02-users/faq.md');

    expect($renamed[0])->toBe('title: Roles')
        ->and($renamed[1])->toBe("excerpt: 'Who may do what.'")
        ->and($renamed[2])->toBe('slug: questions')
        ->and($same)->not->toContain('slug:');
});

it('writes format only when it differs from the extension', function (ArticleFormat $format, string $target, ?string $expectedLine): void {
    $lines = linCodexWriterFrontMatterLines(linCodexWriterWrite(linCodexWriterArticle(['format' => $format]), target: $target));
    $formatLines = array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'format:')));

    if ($expectedLine === null) {
        expect($formatLines)->toBe([]);

        return;
    }

    $position = array_search($expectedLine, $lines, true);

    expect($formatLines)->toBe([$expectedLine])
        ->and($lines[$position - 1])->toBe("  - 'sign in'")
        ->and($lines[$position + 1])->toBe('owner: docs-team');
})->with([
    'html in .md' => [ArticleFormat::Html, 'x.md', 'format: html'],
    'html in .html' => [ArticleFormat::Html, 'x.html', null],
    'markdown in .html' => [ArticleFormat::Markdown, 'x.html', 'format: markdown'],
    'markdown in .md' => [ArticleFormat::Markdown, 'x.md', null],
]);

it('writes published only when false and visibility always', function (): void {
    $unpublished = linCodexWriterFrontMatterLines(linCodexWriterWrite(linCodexWriterArticle(['visibility' => Visibility::Authenticated, 'published' => false])));
    $published = linCodexWriterWrite(linCodexWriterArticle());

    $position = array_search('visibility: authenticated', $unpublished, true);

    expect($position)->toBeInt()
        ->and($unpublished[$position + 1])->toBe('published: false')
        ->and($published)->toContain("\nvisibility: public\n")
        ->and($published)->not->toContain('published:');
});

it('writes only title and excerpt for a non-default-locale file', function (): void {
    $withExcerpt = linCodexWriterWrite(linCodexWriterArticle(), linCodexWriterTranslation(['locale' => 'de', 'title' => 'Rollen', 'excerpt' => 'Wer was darf.', 'body' => "Rollen bündeln Berechtigungen.\n"]), primary: false, target: 'de/02-users/01-roles.md');
    $titleOnly = linCodexWriterWrite(linCodexWriterArticle(), linCodexWriterTranslation(['locale' => 'de', 'title' => 'Rollen', 'excerpt' => null, 'body' => "Rollen bündeln Berechtigungen.\n"]), primary: false, target: 'de/02-users/01-roles.md');

    expect($withExcerpt)->toBe("---\ntitle: Rollen\nexcerpt: 'Wer was darf.'\n---\n\nRollen bündeln Berechtigungen.\n")
        ->and($titleOnly)->toBe("---\ntitle: Rollen\n---\n\nRollen bündeln Berechtigungen.\n");
});

it('never writes empty arrays or nulls', function (): void {
    $article = linCodexWriterArticle(['icon' => null, 'contexts' => [], 'related' => [], 'keywords' => [], 'meta' => []]);
    $written = linCodexWriterWrite($article, linCodexWriterTranslation(['excerpt' => null]));

    expect($written)->toBe("---\ntitle: Roles\nvisibility: public\n---\n\nRoles group permissions.\n")
        ->and($written)->not->toContain('[]')
        ->and($written)->not->toContain('{  }')
        ->and($written)->not->toContain('null');
});

it('puts meta keys last and never writes a meta key that collides with a known key', function (): void {
    $written = linCodexWriterWrite(linCodexWriterArticle(['meta' => ['title' => 'x', 'zeta' => 1, 'alpha' => 2]]));
    $lines = linCodexWriterFrontMatterLines($written);

    expect(preg_match_all('/^title:/m', $written))->toBe(1)
        ->and($lines[0])->toBe('title: Roles')
        ->and(array_slice($lines, -2))->toBe(['zeta: 1', 'alpha: 2'])
        ->and($lines[count($lines) - 3])->toBe("  - 'sign in'")
        ->and($written)->not->toContain('title: x');
});

it('quotes so that the parser reads the same values back', function (string $title, ?string $excerpt, array $keywords): void {
    $writer = new FrontMatterWriter;
    $article = linCodexWriterArticle(['keywords' => $keywords]);
    $translation = linCodexWriterTranslation(['title' => $title, 'excerpt' => $excerpt]);

    $data = $writer->data($article, $translation, true, 'en/02-users/01-roles.md');
    $read = FrontMatter::read($writer->write($article, $translation, $translation->body, true, 'en/02-users/01-roles.md'));

    expect($read['error'])->toBeNull()
        ->and($read['data'])->toBe($data)
        ->and($read['data']['title'])->toBe($title)
        ->and($read['body'])->toBe("\nRoles group permissions.\n");
})->with([
    'colon in the title' => ['Users: overview', 'yes', ['#1 tip']],
    'apostrophe' => ["It's", '2024-01-01', ['true', '123', 'a#b', ' lead', '-dash']],
]);

it('normalises the body to exactly one trailing newline and one blank line after the fence', function (string $body, string $expectedTail): void {
    $written = linCodexWriterWrite(linCodexWriterArticle(), linCodexWriterTranslation(['body' => $body]));

    expect($written)->toEndWith("---\n\n".$expectedTail);
})->with([
    'extra trailing newlines' => ["x\n\n\n", "x\n"],
    'no trailing newline' => ['x', "x\n"],
    'crlf tail' => ["x\r\n", "x\n"],
    'multi-line body' => ["One.\n\nTwo.\n\n\nThree.\n", "One.\n\nTwo.\n\n\nThree.\n"],
]);

it('writes contexts in sortOrder order with the panel prefix', function (): void {
    $article = linCodexWriterArticle(['contexts' => [
        new ContextData(ContextType::PageClass, 'App\Filament\Resources\UserResource', 'admin', 2),
        new ContextData(ContextType::Route, 'users.index', null, 0),
        new ContextData(ContextType::Url, '/admin/users/*', null, 1),
    ]]);

    $data = (new FrontMatterWriter)->data($article, linCodexWriterTranslation(), true, 'en/02-users/01-roles.md');

    expect($data['contexts'])->toBe(['route:users.index', 'url:/admin/users/*', 'admin:class:App\Filament\Resources\UserResource']);
});

it('renders a data array and body without touching the values', function (): void {
    $writer = new FrontMatterWriter;

    expect($writer->render(['title' => 'A', 'keywords' => ['x y']], "Body\n"))
        ->toBe("---\ntitle: A\nkeywords:\n  - 'x y'\n---\n\nBody\n");
});
