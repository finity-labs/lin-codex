<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in implementation used to prove that lin-codex.source accepts a
 * class name.
 */
final class BindingTestSource implements ContentSource
{
    public function all(): array
    {
        return [];
    }

    public function findBySlug(string $slug): ?ArticleData
    {
        return null;
    }

    public function tree(): array
    {
        return [];
    }

    public function findByContext(ContextType $type, string $key, ?string $panelId = null): array
    {
        return [];
    }

    public function allForSearch(): array
    {
        return [];
    }

    public function warnings(): array
    {
        return [];
    }
}

const LIN_CODEX_BINDING_FIXTURE_SLUGS = [
    'billing/invoice-history',
    'crlf',
    'duplicate',
    'escaping',
    'intro',
    'no-title',
    'nur-deutsch',
    'users',
    'users/permissions',
    'users/roles',
];

/**
 * Drop the package tables in dependency order, as a files-only install
 * that never ran the migrations would look.
 */
function linCodexDropCodexTables(): void
{
    foreach (['codex_media', 'codex_article_revisions', 'codex_article_contexts', 'codex_article_translations', 'codex_articles'] as $table) {
        Schema::drop($table);
    }
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
});

it('binds the composite source by default', function (): void {
    expect(config('lin-codex.source'))->toBe('composite')
        ->and($this->freshSource())->toBeInstanceOf(CompositeSource::class);
});

it('binds the implementation named by lin-codex.source', function (string $name, string $class): void {
    config()->set('lin-codex.source', $name);

    expect($this->freshSource())->toBeInstanceOf($class);
})->with([
    'filesystem' => ['filesystem', FilesystemSource::class],
    'database' => ['database', DatabaseSource::class],
    'composite' => ['composite', CompositeSource::class],
]);

it('resolves the contract and the concrete sources as singletons', function (): void {
    $this->forgetSources();

    expect(app(ContentSource::class))->toBe(app(ContentSource::class))
        ->and(app(FilesystemSource::class))->toBe(app(FilesystemSource::class))
        ->and(app(DatabaseSource::class))->toBe(app(DatabaseSource::class))
        ->and(app(CompositeSource::class))->toBe(app(CompositeSource::class));
});

it('accepts the name of a class implementing the contract', function (): void {
    config()->set('lin-codex.source', BindingTestSource::class);

    expect($this->freshSource())->toBeInstanceOf(BindingTestSource::class);
});

it('rejects a value that is neither a known name nor a content source class', function (string $value): void {
    config()->set('lin-codex.source', $value);

    expect(fn () => $this->freshSource())
        ->toThrow(InvalidArgumentException::class, $value);
})->with(['nope', stdClass::class]);

it('runs no query while resolving the composite source', function (): void {
    DB::enableQueryLog();

    $this->freshSource();

    expect(DB::getQueryLog())->toBe([]);
});

it('resolves without the codex tables and only fails on the first read', function (): void {
    linCodexDropCodexTables();

    $source = $this->freshSource();

    expect($source)->toBeInstanceOf(CompositeSource::class)
        ->and(fn () => $source->all())->toThrow(QueryException::class);
});

it('serves files without the codex tables when the source is filesystem', function (): void {
    linCodexDropCodexTables();
    config()->set('lin-codex.source', 'filesystem');

    $source = $this->freshSource();

    expect($source)->toBeInstanceOf(FilesystemSource::class)
        ->and(array_keys($source->all()))->toBe(LIN_CODEX_BINDING_FIXTURE_SLUGS);
});
