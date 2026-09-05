<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Sources\CompositeSource;

const LIN_CODEX_COMPOSITE_FILE_SLUG_COUNT = 10;

/** "db-only" and "billing": billing is only a group in the files, so it counts as a database-only slug. */
const LIN_CODEX_COMPOSITE_DATABASE_ONLY_SLUG_COUNT = 2;

/**
 * @param  list<ArticleData>  $articles
 *
 * @return list<string>
 */
function linCodexCompositeSlugs(array $articles): array
{
    return array_map(fn (ArticleData $article): string => $article->slug, $articles);
}

/**
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexCompositeTreeSlugs(array $nodes): array
{
    return array_map(fn (TreeNode $node): string => $node->slug, $nodes);
}

/**
 * @param  list<TreeNode>  $nodes
 */
function linCodexCompositeTreeNode(array $nodes, string $slug): TreeNode
{
    foreach ($nodes as $node) {
        if ($node->slug === $slug) {
            return $node;
        }
    }

    throw new RuntimeException('No tree node '.$slug);
}

function linCodexSeedComposite(): void
{
    Article::factory()
        ->public()
        ->state(['slug' => 'users'])
        ->withTranslation('de', ['title' => 'DB Benutzer', 'search_text' => 'db benutzer'])
        ->withContext(ContextType::Route, 'db.users')
        ->create();

    Article::factory()
        ->public()
        ->state(['slug' => 'intro'])
        ->withTranslation('en', ['title' => 'DB Introduction', 'search_text' => 'db introduction'])
        ->withContext(ContextType::Route, 'home')
        ->create();

    Article::factory()
        ->public()
        ->state(['slug' => 'billing'])
        ->withTranslation('en', ['title' => 'DB Billing'])
        ->create();

    Article::factory()
        ->public()
        ->state(['slug' => 'db-only'])
        ->withTranslation('en', ['title' => 'Only in the database'])
        ->create();
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'composite');

    linCodexSeedComposite();

    $this->source = $this->freshSource();
});

it('is the composite source', function (): void {
    expect($this->source)->toBeInstanceOf(CompositeSource::class);
});

it('hides the file article for every locale once the slug exists in the database', function (): void {
    $users = $this->source->findBySlug('users');

    expect($users)->toBeInstanceOf(ArticleData::class)
        ->and($users->locales())->toBe(['de'])
        ->and($users->translation('de')->title)->toBe('DB Benutzer')
        ->and($users->translation('en'))->toBeNull()
        ->and($users->id)->not->toBeNull()
        ->and($users->isSection)->toBeTrue()
        ->and($users->meta)->toBe([]);

    foreach ($this->source->all() as $article) {
        foreach ($article->translations as $translation) {
            expect($translation->body)->not->toContain('Manage the people');
        }
    }

    expect(linCodexCompositeSlugs($this->source->findByContext(ContextType::Route, 'db.users')))->toBe(['users']);
});

it('replaces the file contexts instead of merging them', function (): void {
    expect($this->source->findByContext(ContextType::Route, 'dashboard'))->toBe([])
        ->and(linCodexCompositeSlugs($this->source->findByContext(ContextType::Route, 'home')))->toBe(['intro']);
});

it('keeps file-only and database-only slugs side by side', function (): void {
    expect($this->source->findBySlug('crlf')->translation('en')->title)->toBe('Windows file')
        ->and($this->source->findBySlug('db-only'))->toBeInstanceOf(ArticleData::class)
        ->and($this->source->findBySlug('db-only')->translation('en')->title)->toBe('Only in the database')
        ->and($this->source->all())->toHaveCount(LIN_CODEX_COMPOSITE_FILE_SLUG_COUNT + LIN_CODEX_COMPOSITE_DATABASE_ONLY_SLUG_COUNT);
});

it('turns a file group into a database article with the file children under it', function (): void {
    $billing = linCodexCompositeTreeNode($this->source->tree(), 'billing');

    expect($billing->article)->toBeInstanceOf(ArticleData::class)
        ->and($billing->article->id)->not->toBeNull()
        ->and(linCodexCompositeTreeSlugs($billing->children))->toBe(['billing/invoice-history'])
        ->and($this->source->set()->groups)->toBe([]);
});

it('keeps the file children under a database parent in the tree', function (): void {
    $users = linCodexCompositeTreeNode($this->source->tree(), 'users');

    expect(linCodexCompositeTreeSlugs($users->children))->toBe(['users/roles', 'users/permissions']);
});

it('still surfaces the file warnings', function (): void {
    expect($this->source->warnings())->toHaveCount(7);
});

it('indexes the database translation and drops the hidden file translation', function (): void {
    $documents = $this->source->allForSearch();
    $keys = array_map(fn (SearchDocument $document): string => $document->slug.'#'.$document->locale, $documents);

    $usersDe = null;

    foreach ($documents as $document) {
        if ($document->slug === 'users' && $document->locale === 'de') {
            $usersDe = $document;
        }
    }

    expect($keys)->toContain('users#de')
        ->and($keys)->not->toContain('users#en')
        ->and($usersDe?->text)->toStartWith('db benutzer');
});

it('survives a serialize round trip', function (): void {
    expect(unserialize(serialize($this->source->set())))->toEqual($this->source->set());
});
