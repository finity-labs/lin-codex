<?php

declare(strict_types=1);

/**
 * The shared visibility matrix: which sources, which viewers, and which
 * articles of tests/Fixtures/docs-visibility each viewer may see. Every read
 * path (reader, tree, context resolver, media route, search and later the
 * JSON API) is driven through the same rows so none of them can drift.
 *
 * Dataset closures run while Pest collects tests, before the application
 * boots, so the datasets hold plain descriptors only. Seeding the database
 * twins of the fixture happens inside the test body through
 * linCodexLeakSeedDatabase(). Phase 5 (search) and Phase 6 (API) reuse these
 * datasets by name.
 */

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;

dataset('lin-codex sources', [
    'filesystem' => ['filesystem'],
    'database' => ['database'],
    'composite' => ['composite'],
]);

dataset('lin-codex viewers', [
    'guest' => ['guest'],
    'user' => ['user'],
]);

/*
 * name => [slug, guest sees?, user sees?, search query], read under the "en"
 * locale with the seeded settings (languages [en], default en, ShowDefault).
 * The query is the row's title, which the fixture files and the database
 * twins both carry in their search text.
 */
dataset('lin-codex leak articles', [
    'public published' => ['public-published', true, true, 'Public published'],
    'public unpublished' => ['public-unpublished', false, false, 'Public unpublished'],
    'authenticated published' => ['auth-published', false, true, 'Authenticated published'],
    'authenticated unpublished' => ['auth-unpublished', false, false, 'Authenticated unpublished'],
    'public child of an authenticated section' => ['internal/public-child', false, true, 'Public child of internal'],
    'public child of an unpublished section' => ['draft/child', false, false, 'Draft child'],
    'public child of a folder group' => ['group/public-child', true, true, 'Public child of a group'],
    'article that exists only in de, read under en' => ['only-de', false, false, 'Nur auf Deutsch'],
    'missing slug' => ['does-not-exist', false, false, 'does not exist'],
]);

/**
 * Database twins of tests/Fixtures/docs-visibility: the same slugs, states
 * and contexts, with bodies carrying the media URLs the file source would
 * have written, so the media gate sees the same references from every
 * source. Parents are created before their children so the model's saving
 * hook links parent_id.
 */
function linCodexLeakSeedDatabase(): void
{
    $body = static fn (string $title, string ...$images): string => '# '.$title."\n\n".implode(
        "\n\n",
        array_map(static fn (string $image): string => '![Shot](/codex/media/en/images/'.$image.'.png)', $images),
    );

    /** @var list<array{0: string, 1: string, 2: bool, 3: bool, 4: list<string>, 5: ?string}> $rows slug, title, public, published, images, de title */
    $rows = [
        ['public-published', 'Public published', true, true, ['public-published'], 'Öffentlich veröffentlicht'],
        ['public-unpublished', 'Public unpublished', true, false, ['public-unpublished'], null],
        ['auth-published', 'Authenticated published', false, true, ['auth-published', 'shared'], null],
        ['auth-unpublished', 'Authenticated unpublished', false, false, ['auth-unpublished'], null],
        ['internal', 'Internal', false, true, ['internal'], 'Intern'],
        ['internal/public-child', 'Public child of internal', true, true, ['internal-public-child'], null],
        ['draft', 'Draft', true, false, ['draft'], null],
        ['draft/child', 'Draft child', true, true, ['draft-child'], null],
        ['group/public-child', 'Public child of a group', true, true, ['group-public-child'], 'Öffentliches Kind einer Gruppe'],
        ['only-en', 'Only in English', true, true, ['only-en'], null],
        ['shared', 'Shared', true, true, ['shared'], null],
    ];

    foreach ($rows as [$slug, $title, $public, $published, $images, $deTitle]) {
        $factory = Article::factory()->state(['slug' => $slug, 'sort_order' => 0]);
        $factory = $public ? $factory->public() : $factory->authenticated();
        $factory = $published ? $factory->published() : $factory->unpublished();
        $factory = $factory
            ->withTranslation('en', ['title' => $title, 'body' => $body($title, ...$images)])
            ->withContext(ContextType::Url, '/leak/'.$slug);

        if ($deTitle !== null) {
            $factory = $factory->withTranslation('de', ['title' => $deTitle, 'body' => $deTitle]);
        }

        $factory->create();
    }

    Article::factory()
        ->public()
        ->published()
        ->state(['slug' => 'only-de', 'sort_order' => 0])
        ->withTranslation('de', ['title' => 'Nur auf Deutsch', 'body' => "# Nur auf Deutsch\n\nDiesen Artikel gibt es nur auf Deutsch."])
        ->withContext(ContextType::Url, '/leak/only-de')
        ->create();
}

/**
 * Every slug in a tree, depth first.
 *
 * @param  list<TreeNode>  $nodes
 *
 * @return list<string>
 */
function linCodexLeakTreeSlugs(array $nodes): array
{
    $slugs = [];

    foreach ($nodes as $node) {
        $slugs[] = $node->slug;

        foreach (linCodexLeakTreeSlugs($node->children) as $slug) {
            $slugs[] = $slug;
        }
    }

    return $slugs;
}

/**
 * @param  list<ArticleData>  $articles
 *
 * @return list<string>
 */
function linCodexLeakSlugs(array $articles): array
{
    return array_map(static fn (ArticleData $article): string => $article->slug, $articles);
}
