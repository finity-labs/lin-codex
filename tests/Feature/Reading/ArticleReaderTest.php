<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Reading\ArticleReader;
use FinityLabs\LinCodex\Reading\ReadArticle;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $codes
 */
function linCodexReaderUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * A reader over a source that reads the current config.
 */
function linCodexReader(): ArticleReader
{
    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);

    return app(ArticleReader::class);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');

    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

describe('visibility', function (): void {
    it('reads a public article for a guest with related slugs reduced to the readable ones', function (): void {
        $read = linCodexReader()->read('intro', $this->guest);

        expect($read)->toBeInstanceOf(ReadArticle::class)
            ->and($read->article->slug)->toBe('intro')
            ->and($read->translation->title)->toBe('Introduction')
            ->and($read->locale)->toBe('en')
            ->and($read->isFallback)->toBeFalse()
            ->and($read->rendered)->toBeInstanceOf(RenderedArticle::class)
            ->and($read->rendered->html)->toBeString()->not->toBe('')
            ->and($read->related)->toBe([['slug' => 'users', 'title' => 'Users']])
            ->and($read->breadcrumbs)->toBe([]);
    });

    it('renders a section under its index render slug', function (): void {
        $read = linCodexReader()->read('users', $this->guest);
        $key = app(ArticleRenderer::class)->cacheKey($read->translation->body, ArticleFormat::Markdown, 'en', 'users/index');

        expect($read)->toBeInstanceOf(ReadArticle::class)
            ->and(Cache::has($key))->toBeTrue();
    });

    it('shows an authenticated html article only to a signed-in viewer, with breadcrumbs', function (): void {
        $reader = linCodexReader();
        $read = $reader->read('users/permissions', $this->user);

        expect($reader->read('users/permissions', $this->guest))->toBeNull()
            ->and($read)->toBeInstanceOf(ReadArticle::class)
            ->and($read->translation->title)->toBe('Permissions')
            ->and($read->article->format)->toBe(ArticleFormat::Html)
            ->and($read->rendered->html)->toContain('<code>users.delete</code>')
            ->and($read->breadcrumbs)->toBe([['slug' => 'users', 'title' => 'Users']]);
    });

    it('returns null for unpublished, missing and invalid-visibility articles', function (): void {
        $reader = linCodexReader();

        expect($reader->read('users/roles', $this->guest))->toBeNull()
            ->and($reader->read('users/roles', $this->user))->toBeNull()
            ->and($reader->read('does-not-exist', $this->guest))->toBeNull()
            ->and($reader->read('does-not-exist', $this->user))->toBeNull()
            ->and($reader->read('escaping', $this->guest))->toBeNull()
            ->and($reader->read('escaping', $this->user))->toBeInstanceOf(ReadArticle::class);
    });

    it('answers hidden and missing the same way', function (): void {
        $reader = linCodexReader();

        expect($reader->read('users/permissions', $this->guest))->toBeNull()
            ->and($reader->read('does-not-exist', $this->guest))->toBeNull();
    });
});

describe('locale', function (): void {
    it('picks the exact translation or the flagged default under show default', function (): void {
        linCodexReaderUseLanguages(['en', 'de']);
        $reader = linCodexReader();

        $intro = $reader->read('intro', $this->user, 'de');
        $escaping = $reader->read('escaping', $this->user, 'de');
        $german = $reader->read('nur-deutsch', $this->guest, 'de');

        expect($intro->locale)->toBe('de')
            ->and($intro->translation->title)->toBe('Einführung')
            ->and($intro->isFallback)->toBeFalse()
            ->and($escaping->locale)->toBe('en')
            ->and($escaping->translation->title)->toBe('Escaping')
            ->and($escaping->isFallback)->toBeTrue()
            ->and($reader->read('nur-deutsch', $this->guest, 'en'))->toBeNull()
            ->and($german->locale)->toBe('de')
            ->and($german->translation->title)->toBe('Nur Deutsch');
    });

    it('hides an untranslated article under hide', function (): void {
        linCodexReaderUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

        expect(linCodexReader()->read('escaping', $this->user, 'de'))->toBeNull();
    });

    it('treats a locale outside the list as a missing translation', function (): void {
        $read = linCodexReader()->read('intro', $this->guest, 'de');

        expect($read->locale)->toBe('en')
            ->and($read->isFallback)->toBeTrue();
    });

    it('reads the app locale when none is given', function (): void {
        linCodexReaderUseLanguages(['en', 'de']);
        app()->setLocale('de');

        expect(linCodexReader()->read('intro', $this->user)->locale)->toBe('de');
    });

    it('builds breadcrumbs from the picked titles', function (): void {
        linCodexReaderUseLanguages(['en', 'de']);

        expect(linCodexReader()->read('users/permissions', $this->user, 'de')->breadcrumbs)
            ->toBe([['slug' => 'users', 'title' => 'Benutzer']]);
    });

    it('renders a fallback under the translation locale, never the requested one', function (): void {
        linCodexReaderUseLanguages(['en', 'de']);
        $renderer = app(ArticleRenderer::class);

        $read = linCodexReader()->read('escaping', $this->user, 'de');
        $body = $read->translation->body;

        expect(Cache::has($renderer->cacheKey($body, ArticleFormat::Markdown, 'en', 'escaping')))->toBeTrue()
            ->and(Cache::has($renderer->cacheKey($body, ArticleFormat::Markdown, 'de', 'escaping')))->toBeFalse();
    });

    it('returns a readonly value that survives serialization', function (): void {
        $read = linCodexReader()->read('intro', $this->user);

        linCodexAssertNoModels($read);

        expect(unserialize(serialize($read)))->toEqual($read);
    });
});

describe('database', function (): void {
    beforeEach(function (): void {
        config()->set('lin-codex.source', 'database');
    });

    it('runs one articles query per read of a nested slug', function (): void {
        $users = Article::factory()->public()->state(['slug' => 'users'])->withTranslation('en', ['title' => 'Users', 'body' => '# Users'])->create();
        Article::factory()->public()->childOf($users, 'roles')->withTranslation('en', ['title' => 'Roles'])->create();

        $reader = linCodexReader();

        DB::enableQueryLog();
        $read = $reader->read('users/roles', $this->guest);

        $articlesTable = DB::connection()->getQueryGrammar()->wrapTable('codex_articles');
        $articleQueries = array_filter(DB::getQueryLog(), fn (array $entry): bool => str_contains($entry['query'], $articlesTable));

        expect($read)->toBeInstanceOf(ReadArticle::class)
            ->and($articleQueries)->toHaveCount(1);
    });

    it('filters related slugs by the same visibility and locale rules', function (): void {
        Article::factory()->public()->state(['slug' => 'a'])->withRelated(['b', 'c', 'zzz'])->withTranslation('en', ['title' => 'A'])->create();
        Article::factory()->authenticated()->state(['slug' => 'b'])->withTranslation('en', ['title' => 'B'])->create();
        Article::factory()->public()->state(['slug' => 'c'])->withTranslation('de', ['title' => 'C'])->create();

        $reader = linCodexReader();

        expect($reader->read('a', $this->guest)->related)->toBe([])
            ->and($reader->read('a', $this->user)->related)->toBe([['slug' => 'b', 'title' => 'B']]);
    });
});
