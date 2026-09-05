<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\SearchField;
use FinityLabs\LinCodex\Enums\SearchStrategy;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Search\SearchText;

/**
 * @param  list<string>  $keywords
 */
function linCodexFoldArticle(string $slug, array $keywords, ?int $id = null, ?string $searchText = 'Open the login page'): ArticleData
{
    return new ArticleData(
        slug: $slug,
        parentSlug: null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: $keywords,
        translations: [
            'en' => new TranslationData('en', 'Reset a password', 'How to recover', '# Reset', $searchText),
        ],
        id: $id,
    );
}

it('folds text to lowercase ascii words', function (string $input, string $expected): void {
    expect(SearchText::fold($input))->toBe($expected);
})->with([
    ['Über', 'uber'],
    ['Straße', 'strasse'],
    ['Grüße', 'grusse'],
    ['Öffentlich', 'offentlich'],
    ['Hőség Űrhajó', 'hoseg urhajo'],
    ['Árvíztűrő tükörfúrógép', 'arvizturo tukorfurogep'],
    ['Łódź', 'lodz'],
    ['Ærø', 'aero'],
    ['日本語', ''],
    ['e-mail  +foo -bar "x" (y)', 'e mail foo bar x y'],
    ['Beta1 v2.0', 'beta1 v2 0'],
    ["  line\nbreak\ttab  ", 'line break tab'],
    ['', ''],
]);

it('never applies a language-specific transliteration', function (): void {
    expect(SearchText::fold('Über Straße'))->toBe('uber strasse')
        ->and(SearchText::fold('Über Straße'))->not->toBe('ueber strasse');
});

it('composes the four folded segments with a leading space each', function (): void {
    expect(SearchText::compose('Reset a password', ['credentials', 'login'], 'How to recover', 'Open the login page'))
        ->toBe(" reset a password\n credentials login\n how to recover\n open the login page")
        ->and(SearchText::compose('T', [], null, 'B'))->toBe(" t\n \n \n b");
});

it('splits a composed blob back into its four fields', function (): void {
    $blob = SearchText::compose('Reset a password', ['credentials', 'login'], 'How to recover', 'Open the login page');

    expect(SearchText::split($blob))->toBe([
        'title' => 'reset a password',
        'keywords' => 'credentials login',
        'excerpt' => 'how to recover',
        'body' => 'open the login page',
    ])
        ->and(SearchText::split(' only title'))->toBe(['title' => 'only title', 'keywords' => '', 'excerpt' => '', 'body' => ''])
        ->and(SearchText::split(null))->toBe(['title' => '', 'keywords' => '', 'excerpt' => '', 'body' => ''])
        ->and(SearchText::split(''))->toBe(['title' => '', 'keywords' => '', 'excerpt' => '', 'body' => '']);
});

it('folds line breaks inside a segment so split never sees a fifth segment', function (): void {
    $blob = SearchText::compose('T', [], null, "a\nb");

    expect(substr_count($blob, "\n"))->toBe(3)
        ->and(SearchText::split($blob)['body'])->toBe('a b');
});

it('tokenises folded text', function (): void {
    expect(SearchText::tokens('Reset  a Password'))->toBe(['reset', 'a', 'password'])
        ->and(SearchText::tokens('  '))->toBe([])
        ->and(SearchText::tokens('Über'))->toBe(['uber']);
});

it('exposes the folding version', function (): void {
    expect(SearchText::VERSION)->toBe(1);
});

it('exposes the search field and strategy enums with keys and labels', function (): void {
    expect(SearchField::keys())->toBe(['title', 'keywords', 'excerpt', 'body'])
        ->and(SearchField::Title->key())->toBe('title')
        ->and(SearchField::fromKey('body'))->toBe(SearchField::Body)
        ->and(SearchStrategy::keys())->toBe(['full_text', 'like']);

    foreach ([...SearchField::cases(), ...SearchStrategy::cases()] as $case) {
        $label = $case->label();

        expect($label)->toBeString()->not->toBeEmpty()
            ->and($label)->not->toStartWith('lin-codex::');
    }
});

it('ships the search config block with the locked defaults', function (): void {
    /** @var array<string, mixed> $search */
    $search = config('lin-codex.search');

    expect(array_keys($search))->toBe(['engine', 'min_length', 'limit', 'max_limit', 'candidates', 'snippet_length', 'pgsql_language', 'rate_limit'])
        ->and($search['engine'])->toBeIn(['like', 'fulltext']);

    unset($search['engine']);

    expect($search)->toBe([
        'min_length' => 2,
        'limit' => 10,
        'max_limit' => 50,
        'candidates' => 200,
        'snippet_length' => 160,
        'pgsql_language' => 'simple',
        'rate_limit' => [
            'guest' => 30,
            'user' => 120,
        ],
    ]);
});

/**
 * The engine follows CODEX_SEARCH_ENGINE, so the MySQL and PostgreSQL CI
 * rows export "fulltext" and this default check only runs when the
 * variable is absent from the process.
 */
it('defaults the search engine to like', function (): void {
    expect(config('lin-codex.search.engine'))->toBe('like');
})->skip(fn (): bool => env('CODEX_SEARCH_ENGINE') !== null, 'CODEX_SEARCH_ENGINE is set in this process');

it('bridges an article translation into a search document', function (): void {
    $article = linCodexFoldArticle('x', ['rbac', 'roles'], 7);
    $translation = $article->translation('en');

    $document = SearchDocument::fromTranslation($article, $translation);

    expect($document->slug)->toBe('x')
        ->and($document->locale)->toBe('en')
        ->and($document->title)->toBe('Reset a password')
        ->and($document->excerpt)->toBe('How to recover')
        ->and($document->text)->toBe('Open the login page rbac roles')
        ->and($document->visibility)->toBe(Visibility::Public)
        ->and($document->published)->toBeTrue()
        ->and($document->keywords)->toBe(['rbac', 'roles'])
        ->and($document->body)->toBe('Open the login page')
        ->and($document->articleId)->toBe(7);

    linCodexAssertNoModels($document);

    expect(unserialize(serialize($document)))->toEqual($document);
});

it('bridges a translation without search text and an article without an id', function (): void {
    $article = linCodexFoldArticle('x', ['rbac', 'roles'], null, null);
    $translation = $article->translation('en');

    $document = SearchDocument::fromTranslation($article, $translation);

    expect($document->text)->toBe('rbac roles')
        ->and($document->body)->toBeNull()
        ->and($document->articleId)->toBeNull();
});

it('keeps the phase 3 constructor shape with trailing defaults', function (): void {
    $document = new SearchDocument('s', 'en', 't', null, 'x', Visibility::Public, true);

    expect($document->keywords)->toBe([])
        ->and($document->body)->toBeNull()
        ->and($document->articleId)->toBeNull();
});
