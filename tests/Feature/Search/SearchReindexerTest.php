<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Search\ReindexReport;
use FinityLabs\LinCodex\Search\SearchReindexer;
use FinityLabs\LinCodex\Search\SearchText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const LIN_CODEX_REINDEXER_STAMP = '2024-01-01 00:00:00';

/**
 * An article and one en translation written with the query builder, so no
 * model event runs: the row is exactly what a seeder or a migration leaves
 * behind. Returns the translation id.
 */
function linCodexReindexerRawRow(string $slug, string $title, string $body, ?string $searchText = null): int
{
    $articleId = DB::table((new Article)->getTable())->insertGetId([
        'slug' => $slug,
        'created_at' => LIN_CODEX_REINDEXER_STAMP,
        'updated_at' => LIN_CODEX_REINDEXER_STAMP,
    ]);

    return DB::table((new ArticleTranslation)->getTable())->insertGetId([
        'article_id' => $articleId,
        'locale' => 'en',
        'title' => $title,
        'body' => $body,
        'search_text' => $searchText,
        'created_at' => LIN_CODEX_REINDEXER_STAMP,
        'updated_at' => LIN_CODEX_REINDEXER_STAMP,
    ]);
}

/**
 * @return array{search_text: string|null, updated_at: string}
 */
function linCodexReindexerRow(int $translationId): array
{
    $row = DB::table((new ArticleTranslation)->getTable())->where('id', $translationId)->first();

    expect($row)->not->toBeNull();

    return [
        'search_text' => is_string($row->search_text) ? $row->search_text : null,
        'updated_at' => (string) $row->updated_at,
    ];
}

function linCodexReindexerUseSource(string $mode): void
{
    config()->set('lin-codex.source', $mode);
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath('docs')]);
    test()->forgetSources();
    Cache::forget(InMemoryIndex::CACHE_KEY);
}

function linCodexReindexerSlugCount(): int
{
    return count(app(InMemoryIndex::class)->documents(app(ContentSource::class)->all(), false));
}

describe('translation rows', function (): void {
    beforeEach(function (): void {
        linCodexReindexerUseSource('database');
    });

    it('fills search_text for rows written with the query builder without touching updated_at', function (): void {
        $id = linCodexReindexerRawRow('raw', 'Raw Title', 'Raw body words');

        expect(linCodexReindexerRow($id)['search_text'])->toBeNull();

        $report = app(SearchReindexer::class)->reindex();
        $row = linCodexReindexerRow($id);

        expect($report)->toBeInstanceOf(ReindexReport::class)
            ->and($report->translations)->toBe(1)
            ->and($row['search_text'])->not->toBeNull()
            ->and(SearchText::split((string) $row['search_text'])['title'])->toBe('raw title')
            ->and(SearchText::split((string) $row['search_text'])['body'])->toBe('raw body words')
            ->and($row['updated_at'])->toBe(LIN_CODEX_REINDEXER_STAMP);

        linCodexAssertNoModels($report);
    });

    it('recomputes an outdated search_text', function (): void {
        $id = linCodexReindexerRawRow('outdated', 'Outdated Title', 'Body', 'stale');

        app(SearchReindexer::class)->reindex();
        $row = linCodexReindexerRow($id);

        expect($row['search_text'])->not->toBe('stale')
            ->and(SearchText::split((string) $row['search_text'])['title'])->toBe('outdated title')
            ->and($row['updated_at'])->toBe(LIN_CODEX_REINDEXER_STAMP);
    });

    it('leaves a correct search_text alone but still counts it', function (): void {
        $article = Article::factory()->public()->published()->withTranslation('en', ['title' => 'Hooked'])->create();
        $translation = $article->translations()->firstOrFail();
        $before = linCodexReindexerRow($translation->id);

        expect($before['search_text'])->not->toBeNull();

        $report = app(SearchReindexer::class)->reindex();

        expect($report->translations)->toBe(1)
            ->and(linCodexReindexerRow($translation->id))->toBe($before);
    });

    it('walks in chunks and reports progress', function (): void {
        foreach (range(1, 5) as $n) {
            linCodexReindexerRawRow('chunk-'.$n, 'Chunk '.$n, 'Body '.$n);
        }

        $seen = [];
        $report = app(SearchReindexer::class)->reindex(progress: function (int $done) use (&$seen): void {
            $seen[] = $done;
        }, chunk: 2);

        expect($seen)->toBe([1, 2, 3, 4, 5])
            ->and($report->translations)->toBe(5);

        foreach (range(1, 5) as $n) {
            $text = DB::table((new ArticleTranslation)->getTable())->where('title', 'Chunk '.$n)->value('search_text');

            expect(SearchText::split((string) $text)['title'])->toBe('chunk '.$n);
        }
    });
});

describe('in-memory index', function (): void {
    it('rebuilds the in-memory index for a filesystem source', function (): void {
        linCodexReindexerUseSource('filesystem');

        expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse();

        $report = app(SearchReindexer::class)->reindex();

        expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeTrue()
            ->and($report->mode)->toBe('filesystem')
            ->and($report->indexedDocuments)->toBeGreaterThan(0)
            ->and($report->indexedDocuments)->toBe(linCodexReindexerSlugCount())
            ->and($report->translations)->toBe(0);
    });

    it('skips the in-memory index in database mode', function (): void {
        linCodexReindexerUseSource('database');

        $report = app(SearchReindexer::class)->reindex();

        expect($report->mode)->toBe('database')
            ->and($report->indexedDocuments)->toBeNull()
            ->and(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse();
    });

    it('indexes only file-only articles in composite mode', function (): void {
        linCodexReindexerUseSource('filesystem');
        $fileSlugs = linCodexReindexerSlugCount();

        linCodexReindexerUseSource('composite');
        Article::factory()->public()->published()->state(['slug' => 'intro'])->withTranslation('en', ['title' => 'DB intro'])->create();

        $report = app(SearchReindexer::class)->reindex();
        $cached = Cache::get(InMemoryIndex::CACHE_KEY);

        expect($report->mode)->toBe('composite')
            ->and($report->indexedDocuments)->toBe($fileSlugs - 1)
            ->and($report->translations)->toBe(1)
            ->and(Cache::has(InMemoryIndex::CACHE_KEY))->toBeTrue()
            ->and(array_column($cached['documents'], 'slug'))->not->toContain('intro');
    });
});

it('reports zero translations when the translations table is absent', function (): void {
    linCodexReindexerUseSource('filesystem');

    Schema::disableForeignKeyConstraints();
    Schema::drop((new ArticleTranslation)->getTable());
    Schema::enableForeignKeyConstraints();

    $report = app(SearchReindexer::class)->reindex();

    expect($report->translations)->toBe(0)
        ->and($report->mode)->toBe('filesystem')
        ->and($report->indexedDocuments)->toBeGreaterThan(0);

    $this->markPackageSchemaDirty();
});
