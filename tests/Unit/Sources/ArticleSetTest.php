<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\ArticleSet;
use FinityLabs\LinCodex\Sources\SlugPath;

/**
 * @param  list<ContextData>  $contexts
 * @param  array<string, TranslationData>  $translations
 * @param  list<string>  $keywords
 */
function linCodexSetArticle(
    string $slug,
    int $order = 0,
    array $contexts = [],
    array $translations = [],
    array $keywords = [],
    bool $published = true,
): ArticleData {
    return new ArticleData(
        slug: $slug,
        parentSlug: SlugPath::parentOf($slug),
        order: $order,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: $published,
        contexts: $contexts,
        related: [],
        keywords: $keywords,
        translations: $translations,
    );
}

/**
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexTreeSlugs(array $nodes): array
{
    return array_map(fn (TreeNode $node): string => $node->slug, $nodes);
}

it('sorts articles by slug in the constructor', function (): void {
    $set = new ArticleSet(['b' => linCodexSetArticle('b'), 'a' => linCodexSetArticle('a')]);

    expect(array_keys($set->all()))->toBe(['a', 'b']);
});

it('drops a group whose slug is also an article', function (): void {
    $set = new ArticleSet(['users' => linCodexSetArticle('users')], ['users' => 'Users', 'billing' => 'Billing']);

    expect($set->groups)->toBe(['billing' => 'Billing']);
});

it('finds an article by slug', function (): void {
    $users = linCodexSetArticle('users');
    $set = new ArticleSet(['users' => $users]);

    expect($set->findBySlug('users'))->toBe($users)
        ->and($set->findBySlug('nope'))->toBeNull();
});

it('builds the tree from slugs with groups at order zero and orphans at the root', function (): void {
    $set = new ArticleSet([
        'intro' => linCodexSetArticle('intro', 1),
        'users' => linCodexSetArticle('users', 2),
        'users/roles' => linCodexSetArticle('users/roles', 1),
        'users/permissions' => linCodexSetArticle('users/permissions', 2),
        'billing/invoices' => linCodexSetArticle('billing/invoices', 5),
        'orphan/child' => linCodexSetArticle('orphan/child', 0),
    ], ['billing' => 'Billing']);

    $roots = $set->tree();

    expect(linCodexTreeSlugs($roots))->toBe(['billing', 'orphan/child', 'intro', 'users']);

    [$billing, $orphanChild, $intro, $users] = $roots;

    expect($billing->article)->toBeNull()
        ->and($billing->label)->toBe('Billing')
        ->and(linCodexTreeSlugs($billing->children))->toBe(['billing/invoices'])
        ->and($orphanChild->label)->toBe('Child')
        ->and($orphanChild->article)->toBe($set->findBySlug('orphan/child'))
        ->and($orphanChild->children)->toBe([])
        ->and($intro->label)->toBe('Intro')
        ->and($intro->children)->toBe([])
        ->and($users->label)->toBe('Users')
        ->and($users->article)->toBe($set->findBySlug('users'))
        ->and(linCodexTreeSlugs($users->children))->toBe(['users/roles', 'users/permissions']);

    $walk = function (array $nodes) use (&$walk): void {
        foreach ($nodes as $node) {
            expect($node->label)->toBe(SlugPath::humanise(SlugPath::lastSegment($node->slug)));
            $walk($node->children);
        }
    };

    $walk($roots);
});

it('finds articles by exact context and orders them by context sort order', function (): void {
    $set = new ArticleSet([
        'b' => linCodexSetArticle('b', 0, [new ContextData(ContextType::Route, 'users.index', null, 2)]),
        'c' => linCodexSetArticle('c', 0, [new ContextData(ContextType::Route, 'users.index', null, 1)]),
        'a' => linCodexSetArticle('a', 0, [new ContextData(ContextType::Route, 'users.index', 'admin', 0)]),
        'd' => linCodexSetArticle('d', 0, [new ContextData(ContextType::Route, 'other')]),
    ]);

    $slugs = fn (array $articles): array => array_map(fn (ArticleData $article): string => $article->slug, $articles);

    expect($slugs($set->findByContext(ContextType::Route, 'users.index')))->toBe(['c', 'b'])
        ->and($slugs($set->findByContext(ContextType::Route, 'users.index', 'admin')))->toBe(['a'])
        ->and($set->findByContext(ContextType::Url, 'users.index'))->toBe([]);
});

it('orders articles sharing a context sort order and article order by slug', function (): void {
    $set = new ArticleSet([
        'zeta' => linCodexSetArticle('zeta', 0, [new ContextData(ContextType::Url, '/x')]),
        'alpha' => linCodexSetArticle('alpha', 0, [new ContextData(ContextType::Url, '/x')]),
    ]);

    $found = $set->findByContext(ContextType::Url, '/x');

    expect(array_map(fn (ArticleData $article): string => $article->slug, $found))->toBe(['alpha', 'zeta']);
});

it('emits one search document per locale with keywords folded into the text', function (): void {
    $set = new ArticleSet([
        'x' => linCodexSetArticle('x', 0, [], [
            'en' => new TranslationData('en', 'Title en', 'Excerpt en', 'body', 'hello world'),
            'de' => new TranslationData('de', 'Titel de', null, 'inhalt', null),
        ], ['rbac', 'roles'], false),
    ]);

    $documents = $set->allForSearch();

    expect($documents)->toHaveCount(2)
        ->and($documents[0]->locale)->toBe('de')
        ->and($documents[0]->slug)->toBe('x')
        ->and($documents[0]->title)->toBe('Titel de')
        ->and($documents[0]->excerpt)->toBeNull()
        ->and($documents[0]->text)->toBe('rbac roles')
        ->and($documents[0]->visibility)->toBe(Visibility::Public)
        ->and($documents[0]->published)->toBeFalse()
        ->and($documents[1]->locale)->toBe('en')
        ->and($documents[1]->title)->toBe('Title en')
        ->and($documents[1]->excerpt)->toBe('Excerpt en')
        ->and($documents[1]->text)->toBe('hello world rbac roles')
        ->and($documents[1]->visibility)->toBe(Visibility::Public)
        ->and($documents[1]->published)->toBeFalse();
});

it('folds sets so a later set replaces an earlier one per slug and hides its groups', function (): void {
    $introA = linCodexSetArticle('intro');
    $introB = linCodexSetArticle('intro', 9);
    $warningA = new SourceWarning(SourceWarningKind::InvalidSlug, '/a', null, null, 'a');
    $warningB = new SourceWarning(SourceWarningKind::DuplicateSlug, '/b', null, null, 'b');

    $a = new ArticleSet(
        ['intro' => $introA, 'only-a' => linCodexSetArticle('only-a')],
        ['users' => 'Users', 'billing' => 'Billing'],
        [$warningA],
    );
    $b = new ArticleSet(
        ['intro' => $introB, 'users' => linCodexSetArticle('users'), 'only-b' => linCodexSetArticle('only-b')],
        ['reports' => 'Reports'],
        [$warningB],
    );

    $folded = ArticleSet::fold($a, $b);

    expect(array_keys($folded->all()))->toBe(['intro', 'only-a', 'only-b', 'users'])
        ->and($folded->findBySlug('intro'))->toBe($introB)
        ->and($folded->groups)->toBe(['billing' => 'Billing', 'reports' => 'Reports'])
        ->and($folded->warnings())->toBe([$warningA, $warningB]);
});

it('folds nothing into an empty set and one set into itself', function (): void {
    $a = new ArticleSet(
        ['intro' => linCodexSetArticle('intro')],
        ['users' => 'Users'],
        [new SourceWarning(SourceWarningKind::InvalidSlug, '/a', null, null, 'a')],
    );

    $empty = ArticleSet::fold();
    $same = ArticleSet::fold($a);

    expect($empty->all())->toBe([])
        ->and($empty->groups)->toBe([])
        ->and($empty->warnings())->toBe([])
        ->and($empty->tree())->toBe([])
        ->and($empty->allForSearch())->toBe([])
        ->and($same->all())->toBe($a->all())
        ->and($same->groups)->toBe($a->groups)
        ->and($same->warnings())->toBe($a->warnings());
});

it('survives a serialize round trip', function (): void {
    $set = new ArticleSet(
        ['users' => linCodexSetArticle('users', 1, [new ContextData(ContextType::Route, 'users.index')], [
            'en' => new TranslationData('en', 'Users', null, 'body', 'users'),
        ], ['people'])],
        ['billing' => 'Billing'],
        [new SourceWarning(SourceWarningKind::UnknownKey, '/x.md', 'users', 'en', 'parent')],
    );

    expect(unserialize(serialize($set)))->toEqual($set);
});
