<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * An article and one en translation written with the query builder, so
 * the saving hook never fills search_text. Returns the translation id.
 */
function linCodexReindexCommandRawRow(string $slug, string $title, string $body): int
{
    $articleId = DB::table((new Article)->getTable())->insertGetId([
        'slug' => $slug,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ]);

    return DB::table((new ArticleTranslation)->getTable())->insertGetId([
        'article_id' => $articleId,
        'locale' => 'en',
        'title' => $title,
        'body' => $body,
        'search_text' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ]);
}

function linCodexReindexCommandSearchText(int $translationId): ?string
{
    $row = DB::table((new ArticleTranslation)->getTable())->where('id', $translationId)->first();

    expect($row)->not->toBeNull();

    return is_string($row->search_text) ? $row->search_text : null;
}

function linCodexReindexCommandUseSource(string $mode): void
{
    config()->set('lin-codex.source', $mode);
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath('docs')]);
    test()->forgetSources();
    Cache::forget(InMemoryIndex::CACHE_KEY);
}

it('is discovered under its name', function (): void {
    expect(Artisan::all())->toHaveKey('codex:reindex');
});

it('fills search_text for rows written with the query builder and reports the count', function (): void {
    linCodexReindexCommandUseSource('database');
    $id = linCodexReindexCommandRawRow('raw', 'Raw title', "# Raw\n\nSeeded body.");

    expect(linCodexReindexCommandSearchText($id))->toBeNull();

    $this->artisan('codex:reindex')
        ->expectsOutputToContain('1 translation')
        ->assertExitCode(0);

    expect(linCodexReindexCommandSearchText($id))->not->toBeNull()
        ->and((string) linCodexReindexCommandSearchText($id))->toContain('seeded');
});

it('reports the in-memory index per source mode', function (): void {
    linCodexReindexCommandUseSource('filesystem');

    expect(Artisan::call('codex:reindex'))->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('0 translations indexed')
        ->and($output)->toMatch('/In-memory index rebuilt with [1-9]\d* documents/')
        ->and(Cache::has(InMemoryIndex::CACHE_KEY))->toBeTrue();

    linCodexReindexCommandUseSource('database');

    $this->artisan('codex:reindex')
        ->expectsOutputToContain('In-memory index skipped (database source)')
        ->assertExitCode(0);

    expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse();
});
