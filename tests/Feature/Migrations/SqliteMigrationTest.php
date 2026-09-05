<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the five codex tables with their default names', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'codex_articles',
    'codex_article_translations',
    'codex_article_contexts',
    'codex_article_revisions',
    'codex_media',
]);

it('enforces foreign keys on the sqlite testing connection', function () {
    $pragma = DB::select('PRAGMA foreign_keys');

    expect((int) $pragma[0]->foreign_keys)->toBe(1);
})->skip(fn (): bool => $this->databaseDriver() !== 'sqlite', 'sqlite only');

it('creates no index on search_text for sqlite', function () {
    expect(Schema::hasIndex('codex_article_translations', ['search_text']))->toBeFalse()
        ->and(Schema::hasIndex('codex_article_translations', ['search_text'], 'fulltext'))->toBeFalse();
})->skip(fn (): bool => $this->databaseDriver() !== 'sqlite', 'sqlite only');

it('describes the in-memory sqlite connection by default', function () {
    expect(TestCase::databaseConnectionConfig())->toBe([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
})->skip(fn (): bool => $this->databaseDriver() !== 'sqlite', 'sqlite only');

it('reports the driver of the testing connection', function () {
    expect($this->databaseDriver())->toBe(DB::connection()->getDriverName());
});

it('drops and recreates the package schema between tests without leftovers', function () {
    $this->migration('create_codex_media_table')->down();

    expect(Schema::hasTable('codex_media'))->toBeFalse();

    Schema::dropIfExists('codex_media');

    expect(Schema::hasTable('codex_media'))->toBeFalse();
});

it('creates the unique indexes', function () {
    expect(Schema::hasIndex('codex_articles', ['slug'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('codex_article_translations', ['article_id', 'locale'], 'unique'))->toBeTrue();
});

it('adds the json metadata columns to articles', function () {
    expect(Schema::hasColumns('codex_articles', ['keywords', 'related', 'meta']))->toBeTrue();
});

it('creates the context lookup indexes', function () {
    expect(Schema::hasIndex('codex_article_contexts', ['type', 'key']))->toBeTrue()
        ->and(Schema::hasIndex('codex_article_contexts', ['panel_id', 'type', 'key']))->toBeTrue()
        ->and(Schema::hasIndex('codex_article_contexts', ['article_id']))->toBeTrue();
});

it('creates the article ordering and revision pruning indexes', function () {
    expect(Schema::hasIndex('codex_articles', ['parent_id', 'sort_order']))->toBeTrue()
        ->and(Schema::hasIndex('codex_article_revisions', ['article_id', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('codex_media', ['article_id']))->toBeTrue();
});

it('points the article author and parent foreign keys at users and articles', function () {
    $keys = collect(Schema::getForeignKeys('codex_articles'));

    expect($keys->contains(fn (array $fk) => $fk['columns'] === ['created_by'] && $fk['foreign_table'] === 'users'))->toBeTrue()
        ->and($keys->contains(fn (array $fk) => $fk['columns'] === ['updated_by'] && $fk['foreign_table'] === 'users'))->toBeTrue()
        ->and($keys->contains(fn (array $fk) => $fk['columns'] === ['parent_id'] && $fk['foreign_table'] === 'codex_articles'))->toBeTrue();
});

it('points the revision user foreign key at users', function () {
    $keys = collect(Schema::getForeignKeys('codex_article_revisions'));

    expect($keys->contains(fn (array $fk) => $fk['columns'] === ['user_id'] && $fk['foreign_table'] === 'users'))->toBeTrue()
        ->and($keys->contains(fn (array $fk) => $fk['columns'] === ['article_id'] && $fk['foreign_table'] === 'codex_articles'))->toBeTrue();
});

it('points the media foreign keys at users and articles', function () {
    $keys = collect(Schema::getForeignKeys('codex_media'));

    expect($keys->contains(fn (array $fk) => $fk['columns'] === ['uploaded_by'] && $fk['foreign_table'] === 'users'))->toBeTrue()
        ->and($keys->contains(fn (array $fk) => $fk['columns'] === ['article_id'] && $fk['foreign_table'] === 'codex_articles'))->toBeTrue();
});

it('gives revisions a created_at column but no updated_at', function () {
    $columns = collect(Schema::getColumns('codex_article_revisions'))->pluck('name');

    expect($columns)->toContain('created_at')
        ->and($columns)->not->toContain('updated_at');
});

it('drops every table when the migrations run down in reverse order', function () {
    foreach (array_reverse(TestCase::PACKAGE_MIGRATIONS) as $file) {
        $this->migration($file)->down();
    }

    foreach (['codex_articles', 'codex_article_translations', 'codex_article_contexts', 'codex_article_revisions', 'codex_media'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
