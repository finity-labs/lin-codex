<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Every slug in the tree, depth first, including groups.
 *
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexContractTreeSlugs(array $nodes): array
{
    $slugs = [];

    foreach ($nodes as $node) {
        expect($node)->toBeInstanceOf(TreeNode::class);

        $slugs[] = $node->slug;
        $slugs = [...$slugs, ...linCodexContractTreeSlugs($node->children)];
    }

    return $slugs;
}

/**
 * @param  list<ArticleData>  $articles
 *
 * @return list<string>
 */
function linCodexContractSlugs(array $articles): array
{
    return array_map(fn (ArticleData $article): string => $article->slug, $articles);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);

    Article::factory()
        ->public()
        ->state(['slug' => 'db-article'])
        ->withTranslation('en', ['title' => 'Database article', 'search_text' => 'database article'])
        ->withTranslation('de', ['title' => 'Datenbankartikel', 'search_text' => 'datenbankartikel'])
        ->withContext(ContextType::Route, 'db.index')
        ->create();

    Article::factory()
        ->public()
        ->state(['slug' => 'users'])
        ->withTranslation('de', ['title' => 'DB Benutzer', 'search_text' => 'db benutzer'])
        ->create();
});

it('returns sorted, readonly, serializable data from every bound source', function (string $name, string $class): void {
    config()->set('lin-codex.source', $name);
    $source = $this->freshSource();

    expect($source)->toBeInstanceOf($class);

    // all(): keyed by slug, sorted; translations keyed by locale
    $all = $source->all();
    $slugs = array_keys($all);
    $sorted = $slugs;
    sort($sorted);

    expect($all)->not->toBe([])
        ->and($slugs)->toBe($sorted);

    foreach ($all as $slug => $article) {
        expect($article)->toBeInstanceOf(ArticleData::class)
            ->and($article->slug)->toBe($slug);

        foreach ($article->translations as $locale => $translation) {
            expect($translation)->toBeInstanceOf(TranslationData::class)
                ->and($translation->locale)->toBe($locale);
        }
    }

    // findBySlug(): the first article by value, null for a stranger
    $first = array_key_first($all);

    expect($source->findBySlug($first))->toEqual($all[$first])
        ->and($source->findBySlug('does-not-exist'))->toBeNull();

    // tree(): every node is an article or a group of the set, no article twice
    $set = $source->set();
    $tree = $source->tree();
    $treeSlugs = linCodexContractTreeSlugs($tree);
    $known = [...array_keys($set->articles), ...array_keys($set->groups)];

    $articleSlugsInTree = array_values(array_intersect($treeSlugs, array_keys($set->articles)));

    expect(array_is_list($tree))->toBeTrue()
        ->and(array_diff($treeSlugs, $known))->toBe([])
        ->and(array_unique($articleSlugsInTree))->toHaveCount(count($articleSlugsInTree))
        ->and($articleSlugsInTree)->toHaveCount(count($set->articles));

    // findByContext(): the seed's route only in the database-backed sources, the file route only where files load
    $expectDb = $name === 'filesystem' ? [] : ['db-article'];
    $expectFile = $name === 'database' ? [] : ['intro'];

    expect(linCodexContractSlugs($source->findByContext(ContextType::Route, 'db.index')))->toBe($expectDb)
        ->and(linCodexContractSlugs($source->findByContext(ContextType::Route, 'dashboard')))->toBe($expectFile);

    // allForSearch(): one document per translation
    $translationCount = array_sum(array_map(fn (ArticleData $article): int => count($article->translations), $all));

    expect($source->allForSearch())->toHaveCount($translationCount);

    // warnings(): a list of SourceWarning, none from the database
    $warnings = $source->warnings();

    expect(array_is_list($warnings))->toBeTrue()
        ->and($warnings)->toHaveCount($name === 'database' ? 0 : 7)
        ->and($warnings)->each->toBeInstanceOf(SourceWarning::class);

    // No model, no closure, nothing mutable anywhere in the graph; and it all serializes
    $results = [
        'all' => $all,
        'findBySlug' => $source->findBySlug($first),
        'tree' => $tree,
        'findByContext' => [
            ...$source->findByContext(ContextType::Route, 'db.index'),
            ...$source->findByContext(ContextType::Route, 'dashboard'),
        ],
        'allForSearch' => $source->allForSearch(),
        'warnings' => $warnings,
    ];

    linCodexAssertNoModels($results);

    foreach ($all as $article) {
        expect($article)->not->toBeInstanceOf(Model::class);
    }

    expect(unserialize(serialize($results)))->toEqual($results);
})->with([
    'filesystem' => ['filesystem', FilesystemSource::class],
    'database' => ['database', DatabaseSource::class],
    'composite' => ['composite', CompositeSource::class],
]);
