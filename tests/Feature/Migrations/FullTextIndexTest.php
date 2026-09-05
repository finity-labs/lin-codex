<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The definition of the PostgreSQL full-text index, straight from the catalog.
 */
function linCodexFullTextPgsqlIndexDefinition(): string
{
    $row = DB::selectOne('select indexdef from pg_indexes where indexname = ?', ['codex_article_translations_search_text_fulltext']);

    return (string) ($row->indexdef ?? '');
}

it('creates a fulltext index on search_text on mysql and mariadb', function () {
    expect(Schema::hasIndex('codex_article_translations', ['search_text'], 'fulltext'))->toBeTrue();
})->skip(fn (): bool => ! in_array($this->databaseDriver(), ['mysql', 'mariadb'], true), 'needs mysql or mariadb');

it('creates a gin expression index on search_text on pgsql', function () {
    $index = collect(Schema::getIndexes('codex_article_translations'))
        ->firstWhere('name', 'codex_article_translations_search_text_fulltext');

    expect($index)->not->toBeNull()
        ->and($index['type'])->toBe('gin')
        ->and(linCodexFullTextPgsqlIndexDefinition())->toContain("to_tsvector('simple'::regconfig");
})->skip(fn (): bool => $this->databaseDriver() !== 'pgsql', 'needs pgsql');

it('builds the pgsql index with the configured language and falls back to simple for a bad name', function () {
    $this->markPackageSchemaDirty();
    $migration = $this->migration('create_codex_article_translations_table');

    config()->set('lin-codex.search.pgsql_language', 'german');
    $migration->down();
    $migration->up();

    expect(linCodexFullTextPgsqlIndexDefinition())->toContain("'german'::regconfig");

    config()->set('lin-codex.search.pgsql_language', 'no such; drop');
    $migration->down();
    $migration->up();

    expect(linCodexFullTextPgsqlIndexDefinition())->toContain("'simple'::regconfig");
})->skip(fn (): bool => $this->databaseDriver() !== 'pgsql', 'needs pgsql');

it('keeps the LIKE path available on every driver', function () {
    expect(DB::table('codex_article_translations')->where('search_text', 'like', '% nothing%')->count())->toBe(0);
});
