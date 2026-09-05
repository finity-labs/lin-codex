<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleContext;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Models\Media;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('runs under the overridden table names', function (): void {
    expect(config('lin-codex.table_names.articles'))->toBe('kb_articles')
        ->and(config('lin-codex.users_table'))->toBe('members');
});

it('creates the overridden tables and none of the defaults', function (): void {
    foreach (['kb_articles', 'kb_translations', 'kb_contexts', 'kb_revisions', 'kb_files', 'members'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected overridden table {$table} to exist");
    }

    $defaults = [
        'codex_articles',
        'codex_article_translations',
        'codex_article_contexts',
        'codex_article_revisions',
        'codex_media',
        'users',
    ];

    foreach ($defaults as $table) {
        expect(Schema::hasTable($table))->toBeFalse("Default table {$table} must not exist under the override");
    }
});

it('returns the overridden name from getTable()', function (string $model, string $key, string $expected): void {
    expect((new $model)->getTable())->toBe($expected)
        ->and((new $model)->getTable())->toBe(config('lin-codex.table_names.'.$key));
})->with([
    'articles' => [Article::class, 'articles', 'kb_articles'],
    'article_translations' => [ArticleTranslation::class, 'article_translations', 'kb_translations'],
    'article_contexts' => [ArticleContext::class, 'article_contexts', 'kb_contexts'],
    'article_revisions' => [ArticleRevision::class, 'article_revisions', 'kb_revisions'],
    'media' => [Media::class, 'media', 'kb_files'],
]);

it('writes factory rows to the overridden tables', function (): void {
    Article::factory()
        ->withTranslation()
        ->withContext(ContextType::Route, 'users.index')
        ->withRevisions()
        ->withMedia()
        ->create();

    expect(DB::table('kb_articles')->count())->toBe(1)
        ->and(DB::table('kb_translations')->count())->toBe(1)
        ->and(DB::table('kb_contexts')->count())->toBe(1)
        ->and(DB::table('kb_revisions')->count())->toBe(1)
        ->and(DB::table('kb_files')->count())->toBe(1);
});

it('syncs parent_id through the overridden articles table', function (): void {
    $parent = Article::factory()->create(['slug' => 'users']);
    $child = Article::factory()->childOf($parent)->create();

    expect($child->fresh()?->parent_id)->toBe($parent->id)
        ->and(DB::table('kb_articles')->where('parent_id', $parent->id)->count())->toBe(1);
});

it('leaves the seeded settings unaffected by table overrides', function (): void {
    expect(app(CodexSettings::class)->revisions_keep)->toBe(10);
});
