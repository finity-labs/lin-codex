<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('points every foreign key at the overridden tables', function (string $table, array $columns, string $target): void {
    $hasFk = fn (string $table, array $columns, string $target): bool => collect(Schema::getForeignKeys($table))
        ->contains(fn (array $fk): bool => $fk['columns'] === $columns && $fk['foreign_table'] === $target);

    expect($hasFk($table, $columns, $target))
        ->toBeTrue(sprintf('%s(%s) should reference %s', $table, implode(',', $columns), $target));
})->with([
    'kb_articles.created_by -> members' => ['kb_articles', ['created_by'], 'members'],
    'kb_articles.updated_by -> members' => ['kb_articles', ['updated_by'], 'members'],
    'kb_articles.parent_id -> kb_articles' => ['kb_articles', ['parent_id'], 'kb_articles'],
    'kb_translations.article_id -> kb_articles' => ['kb_translations', ['article_id'], 'kb_articles'],
    'kb_contexts.article_id -> kb_articles' => ['kb_contexts', ['article_id'], 'kb_articles'],
    'kb_revisions.article_id -> kb_articles' => ['kb_revisions', ['article_id'], 'kb_articles'],
    'kb_revisions.user_id -> members' => ['kb_revisions', ['user_id'], 'members'],
    'kb_files.uploaded_by -> members' => ['kb_files', ['uploaded_by'], 'members'],
    'kb_files.article_id -> kb_articles' => ['kb_files', ['article_id'], 'kb_articles'],
]);

it('never references a default table name from any foreign key', function (): void {
    $targets = collect(['kb_articles', 'kb_translations', 'kb_contexts', 'kb_revisions', 'kb_files'])
        ->flatMap(fn (string $table): array => Schema::getForeignKeys($table))
        ->pluck('foreign_table')
        ->unique()
        ->values()
        ->all();

    expect($targets)->not->toContain('users')
        ->and(array_filter($targets, fn (string $target): bool => str_starts_with($target, 'codex_')))->toBe([])
        ->and($targets)->toContain('members')
        ->and($targets)->toContain('kb_articles');
});

it('nulls created_by when the member row is deleted', function (): void {
    $memberId = DB::table('members')->insertGetId([
        'name' => 'Ann',
        'email' => 'ann@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $article = Article::factory()->create(['created_by' => $memberId]);

    expect($article->fresh()?->created_by)->toBe($memberId);

    DB::table('members')->where('id', $memberId)->delete();

    expect($article->fresh()?->created_by)->toBeNull();
});
