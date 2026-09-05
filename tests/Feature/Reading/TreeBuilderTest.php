<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Reading\TreeBuilder;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $codes
 */
function linCodexTreeUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * Depth-first slugs of a node list.
 *
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexTreeFlatten(array $nodes): array
{
    $slugs = [];

    foreach ($nodes as $node) {
        $slugs[] = $node->slug;

        foreach (linCodexTreeFlatten($node->children) as $child) {
            $slugs[] = $child;
        }
    }

    return $slugs;
}

/**
 * @param  list<TreeNode>  $nodes
 */
function linCodexTreeFind(array $nodes, string $slug): TreeNode
{
    foreach ($nodes as $node) {
        if ($node->slug === $slug) {
            return $node;
        }

        foreach ($node->children as $child) {
            if ($child->slug === $slug || str_starts_with($slug, $child->slug.'/')) {
                return linCodexTreeFind([$child], $slug);
            }
        }
    }

    throw new RuntimeException('No tree node for '.$slug);
}

/**
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexTreeRootSlugs(array $nodes): array
{
    return array_map(fn (TreeNode $node): string => $node->slug, $nodes);
}

/**
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexTreeChildSlugs(array $nodes, string $slug): array
{
    return linCodexTreeRootSlugs(linCodexTreeFind($nodes, $slug)->children);
}

/**
 * A builder over a source that reads the current config.
 */
function linCodexTreeBuilder(): TreeBuilder
{
    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);

    return app(TreeBuilder::class);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');

    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

describe('tree node', function (): void {
    it('defaults isFallback to false and treats a node without an article as a group', function (): void {
        $node = new TreeNode('a', 'A', null);

        expect($node->isFallback)->toBeFalse()
            ->and($node->isGroup())->toBeTrue()
            ->and($node->children)->toBe([]);
    });
});

describe('filesystem docs', function (): void {
    it('shows a guest only the public articles', function (): void {
        $nodes = linCodexTreeBuilder()->build($this->guest, 'en');
        $intro = linCodexTreeFind($nodes, 'intro');

        expect(linCodexTreeRootSlugs($nodes))->toBe(['intro', 'users'])
            ->and(linCodexTreeChildSlugs($nodes, 'users'))->toBe([])
            ->and($intro->label)->toBe('Introduction')
            ->and($intro->isFallback)->toBeFalse()
            ->and($intro->isGroup())->toBeFalse()
            ->and($intro->article?->slug)->toBe('intro');
    });

    it('shows a signed-in viewer every published article with derived groups', function (): void {
        $nodes = linCodexTreeBuilder()->build($this->user, 'en');
        $billing = linCodexTreeFind($nodes, 'billing');

        expect(linCodexTreeRootSlugs($nodes))->toBe(['billing', 'escaping', 'no-title', 'intro', 'users', 'crlf', 'duplicate'])
            ->and($billing->isGroup())->toBeTrue()
            ->and($billing->article)->toBeNull()
            ->and($billing->label)->toBe('Billing')
            ->and($billing->isFallback)->toBeFalse()
            ->and(linCodexTreeChildSlugs($nodes, 'billing'))->toBe(['billing/invoice-history'])
            ->and(linCodexTreeChildSlugs($nodes, 'users'))->toBe(['users/permissions'])
            ->and(linCodexTreeFind($nodes, 'no-title')->label)->toBe('No title')
            ->and(linCodexTreeFind($nodes, 'crlf')->label)->toBe('Windows file')
            ->and(linCodexTreeFlatten($nodes))->not->toContain('nur-deutsch');
    });

    it('uses translated titles and flags default-language stand-ins', function (): void {
        linCodexTreeUseLanguages(['en', 'de']);

        $nodes = linCodexTreeBuilder()->build($this->user, 'de');

        expect(linCodexTreeRootSlugs($nodes))->toBe(['billing', 'escaping', 'no-title', 'nur-deutsch', 'intro', 'users', 'crlf', 'duplicate'])
            ->and(linCodexTreeFind($nodes, 'intro')->label)->toBe('Einführung')
            ->and(linCodexTreeFind($nodes, 'intro')->isFallback)->toBeFalse()
            ->and(linCodexTreeFind($nodes, 'users')->label)->toBe('Benutzer')
            ->and(linCodexTreeFind($nodes, 'users')->isFallback)->toBeFalse()
            ->and(linCodexTreeFind($nodes, 'escaping')->label)->toBe('Escaping')
            ->and(linCodexTreeFind($nodes, 'escaping')->isFallback)->toBeTrue()
            ->and(linCodexTreeFind($nodes, 'nur-deutsch')->label)->toBe('Nur Deutsch')
            ->and(linCodexTreeFind($nodes, 'nur-deutsch')->isFallback)->toBeFalse()
            ->and(linCodexTreeFind($nodes, 'users/permissions')->label)->toBe('Permissions')
            ->and(linCodexTreeFind($nodes, 'users/permissions')->isFallback)->toBeTrue();
    });

    it('drops untranslated articles and their groups under hide', function (): void {
        linCodexTreeUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

        $nodes = linCodexTreeBuilder()->build($this->user, 'de');

        expect(linCodexTreeRootSlugs($nodes))->toBe(['nur-deutsch', 'intro', 'users'])
            ->and(linCodexTreeChildSlugs($nodes, 'users'))->toBe([])
            ->and(linCodexTreeFlatten($nodes))->not->toContain('billing');
    });

    it('labels a group from the lang key when one exists for the locale', function (): void {
        linCodexTreeUseLanguages(['en', 'de']);
        app('translator')->addLines(['lin-codex.groups.billing' => 'Rechnungen'], 'de', 'lin-codex');

        $german = linCodexTreeBuilder()->build($this->user, 'de');
        $english = linCodexTreeBuilder()->build($this->user, 'en');

        expect(linCodexTreeFind($german, 'billing')->label)->toBe('Rechnungen')
            ->and(linCodexTreeFind($english, 'billing')->label)->toBe('Billing');
    });

    it('reads the app locale when none is given', function (): void {
        linCodexTreeUseLanguages(['en', 'de']);
        app()->setLocale('de');

        $nodes = linCodexTreeBuilder()->build($this->user);

        expect(linCodexTreeFind($nodes, 'intro')->label)->toBe('Einführung');
    });

    it('returns readonly nodes that survive serialization', function (): void {
        $nodes = linCodexTreeBuilder()->build($this->user);

        linCodexAssertNoModels($nodes);

        expect(unserialize(serialize($nodes)))->toEqual($nodes);
    });
});

describe('database', function (): void {
    beforeEach(function (): void {
        config()->set('lin-codex.source', 'database');
    });

    it('gives a database orphan a group ancestor', function (): void {
        Article::factory()->public()->state(['slug' => 'orphan/child'])->withTranslation('en', ['title' => 'Child'])->create();

        $nodes = linCodexTreeBuilder()->build($this->user, 'en');
        $orphan = linCodexTreeFind($nodes, 'orphan');

        expect(linCodexTreeRootSlugs($nodes))->toBe(['orphan'])
            ->and($orphan->isGroup())->toBeTrue()
            ->and($orphan->label)->toBe('Orphan')
            ->and(linCodexTreeChildSlugs($nodes, 'orphan'))->toBe(['orphan/child']);
    });

    it('re-parents a translated child under the nearest visible ancestor when hide drops its section', function (): void {
        $section = Article::factory()->public()->state(['slug' => 'section'])->withTranslation('en', ['title' => 'Section'])->create();
        Article::factory()->public()->childOf($section, 'child')
            ->withTranslation('en', ['title' => 'Child'])
            ->withTranslation('de', ['title' => 'Kind'])
            ->create();

        linCodexTreeUseLanguages(['en', 'de'], FallbackBehaviour::Hide);
        $hidden = linCodexTreeBuilder()->build($this->user, 'de');

        expect(linCodexTreeRootSlugs($hidden))->toBe(['section/child'])
            ->and($hidden[0]->label)->toBe('Kind');

        linCodexTreeUseLanguages(['en', 'de'], FallbackBehaviour::ShowDefault);
        $shown = linCodexTreeBuilder()->build($this->user, 'de');

        expect(linCodexTreeRootSlugs($shown))->toBe(['section'])
            ->and($shown[0]->label)->toBe('Section')
            ->and($shown[0]->isFallback)->toBeTrue()
            ->and(linCodexTreeChildSlugs($shown, 'section'))->toBe(['section/child'])
            ->and(linCodexTreeFind($shown, 'section/child')->label)->toBe('Kind')
            ->and(linCodexTreeFind($shown, 'section/child')->isFallback)->toBeFalse();
    });

    it('orders siblings by order then slug with groups first', function (): void {
        Article::factory()->public()->state(['slug' => 'b', 'sort_order' => 1])->withTranslation('en', ['title' => 'B'])->create();
        Article::factory()->public()->state(['slug' => 'a', 'sort_order' => 2])->withTranslation('en', ['title' => 'A'])->create();
        Article::factory()->public()->state(['slug' => 'c', 'sort_order' => 1])->withTranslation('en', ['title' => 'C'])->create();
        Article::factory()->public()->state(['slug' => 'g/x', 'sort_order' => 0])->withTranslation('en', ['title' => 'X'])->create();

        expect(linCodexTreeRootSlugs(linCodexTreeBuilder()->build($this->user, 'en')))->toBe(['g', 'b', 'c', 'a']);
    });

    it('hides the public child of an authenticated section from guests', function (): void {
        $internal = Article::factory()->authenticated()->state(['slug' => 'internal'])->withTranslation('en', ['title' => 'Internal'])->create();
        Article::factory()->public()->childOf($internal, 'pub')->withTranslation('en', ['title' => 'Pub'])->create();

        $forGuest = linCodexTreeBuilder()->build($this->guest, 'en');
        $forUser = linCodexTreeBuilder()->build($this->user, 'en');

        expect($forGuest)->toBe([])
            ->and(linCodexTreeRootSlugs($forUser))->toBe(['internal'])
            ->and(linCodexTreeChildSlugs($forUser, 'internal'))->toBe(['internal/pub']);
    });

    it('runs one articles query per build', function (): void {
        $internal = Article::factory()->authenticated()->state(['slug' => 'internal'])->withTranslation('en', ['title' => 'Internal'])->create();
        Article::factory()->public()->childOf($internal, 'pub')->withTranslation('en', ['title' => 'Pub'])->create();

        $builder = linCodexTreeBuilder();

        DB::enableQueryLog();
        $builder->build($this->user, 'en');

        $articlesTable = DB::connection()->getQueryGrammar()->wrapTable('codex_articles');
        $articleQueries = array_filter(DB::getQueryLog(), fn (array $entry): bool => str_contains($entry['query'], $articlesTable));

        expect($articleQueries)->toHaveCount(1);
    });
});
