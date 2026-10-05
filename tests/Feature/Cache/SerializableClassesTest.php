<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use FinityLabs\LinCodex\Search\IndexedDocument;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Sources\ArticleSet;
use FinityLabs\LinCodex\Sources\Filesystem\PathFingerprint;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Cache;

/*
 * Laravel 13's skeleton ships config/cache.php with
 * 'serializable_classes' => false, and every store that serializes then
 * reads with unserialize($value, ['allowed_classes' => false]): a cached PHP
 * object comes back as __PHP_Incomplete_Class. Every lin-codex cache is run
 * here through such a store, an array store that serializes under that
 * setting, which is the assumed default from now on. A framework older than
 * the setting hands objects back whole, so each test also walks what was
 * written and fails on any object at all.
 */

const LIN_CODEX_STRICT_MARKDOWN = "## Reset a password\n\nSee [Roles](roles.md) and <https://example.com/x>.";

beforeEach(function (): void {
    config()->set('cache.serializable_classes', false);
    config()->set('cache.stores.strict', ['driver' => 'array', 'serialize' => true]);
    config()->set('cache.default', 'strict');
});

function linCodexStrictKey(ArticleRenderer $renderer): string
{
    return $renderer->cacheKey(LIN_CODEX_STRICT_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
}

function linCodexStrictRender(ArticleRenderer $renderer): RenderedArticle
{
    return $renderer->render(LIN_CODEX_STRICT_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
}

describe('render cache', function (): void {
    it('writes plain data and serves the second render from the store', function (): void {
        $renderer = app(ArticleRenderer::class);
        $key = linCodexStrictKey($renderer);

        $first = linCodexStrictRender($renderer);

        expect($first->html)->toContain('id="reset-a-password"');
        linCodexAssertPlainData(Cache::get($key));

        expect(linCodexStrictRender($renderer))->toEqual($first);

        Cache::forever($key, (new RenderedArticle('<p>cached</p>', [], 'cached', []))->toArray());

        expect(linCodexStrictRender($renderer)->html)->toBe('<p>cached</p>')
            ->and($renderer->plainText(LIN_CODEX_STRICT_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'))->toBe('cached');
    });

    it('re-renders over a legacy object entry under the same key', function (): void {
        $renderer = app(ArticleRenderer::class);
        $key = linCodexStrictKey($renderer);

        Cache::forever($key, new RenderedArticle('<p>legacy</p>', [], 'legacy', []));

        $rendered = linCodexStrictRender($renderer);

        expect($rendered->html)->toContain('id="reset-a-password"')
            ->and($rendered->html)->not->toContain('legacy');
        linCodexAssertPlainData(Cache::get($key));
        expect(RenderedArticle::fromArray(Cache::get($key)))->toEqual($rendered);
    });

    it('re-renders over a malformed array entry', function (): void {
        $renderer = app(ArticleRenderer::class);
        $key = linCodexStrictKey($renderer);

        Cache::forever($key, ['html' => '<p>poisoned</p>', 'toc' => 'not a list']);

        expect(linCodexStrictRender($renderer)->html)->toContain('id="reset-a-password"')
            ->and(Cache::get($key)['toc'])->toBeArray();
    });
});

describe('file source cache', function (): void {
    beforeEach(function (): void {
        config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
        $this->forgetSources();
    });

    it('writes plain data and serves the parsed set from the store across instances', function (): void {
        $source = app(FilesystemSource::class);
        $key = $source->cacheKey($this->fixtureDocsPath());

        expect($source->findBySlug('intro'))->not->toBeNull();
        linCodexAssertPlainData(Cache::get($key));

        $planted = new ArticleData(
            slug: 'planted',
            parentSlug: null,
            order: 0,
            icon: null,
            format: ArticleFormat::Markdown,
            visibility: Visibility::Public,
            published: true,
            contexts: [],
            related: [],
            keywords: [],
            translations: ['en' => new TranslationData('en', 'Planted', null, 'Planted body.', 'Planted body.')],
        );

        Cache::forever($key, [
            'fingerprint' => PathFingerprint::of($this->fixtureDocsPath()),
            'set' => (new ArticleSet(['planted' => $planted]))->toArray(),
        ]);

        $this->app->forgetInstance(FilesystemSource::class);
        $fresh = app(FilesystemSource::class);

        expect($fresh->findBySlug('planted'))->toEqual($planted)
            ->and($fresh->findBySlug('intro'))->toBeNull();
    });

    it('rescans over a legacy object entry under the same key', function (): void {
        $source = app(FilesystemSource::class);
        $key = $source->cacheKey($this->fixtureDocsPath());

        Cache::forever($key, ['fingerprint' => PathFingerprint::of($this->fixtureDocsPath()), 'set' => new ArticleSet([])]);

        $all = $source->all();

        expect($all)->toHaveKey('intro');
        linCodexAssertPlainData(Cache::get($key));
        expect(ArticleSet::fromArray(Cache::get($key)['set'])->all())->toEqual($all);
    });
});

describe('search index cache', function (): void {
    beforeEach(function (): void {
        config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
        $this->forgetSources();
        $this->all = app(FilesystemSource::class)->all();
    });

    it('writes plain data and serves the folded documents from the store across instances', function (): void {
        $documents = app(InMemoryIndex::class)->documents($this->all, false);

        expect($documents)->toHaveKey('intro');
        linCodexAssertPlainData(Cache::get(InMemoryIndex::CACHE_KEY));

        $entry = Cache::get(InMemoryIndex::CACHE_KEY);

        foreach ($entry['documents'] as $position => $document) {
            if ($document['slug'] === 'intro' && $document['locale'] === 'en') {
                $entry['documents'][$position]['title'] = 'plantedword';
            }
        }

        Cache::forever(InMemoryIndex::CACHE_KEY, $entry);

        expect(app(InMemoryIndex::class)->documents($this->all, false)['intro']['en']->title)->toBe('plantedword');
    });

    it('rebuilds over a legacy object entry under the same key', function (): void {
        $documents = app(InMemoryIndex::class)->documents($this->all, false);
        $entry = Cache::get(InMemoryIndex::CACHE_KEY);
        $entry['documents'] = array_map(
            static fn (array $document): IndexedDocument => IndexedDocument::fromArray($document),
            $entry['documents'],
        );

        Cache::forever(InMemoryIndex::CACHE_KEY, $entry);

        expect(app(InMemoryIndex::class)->documents($this->all, false))->toEqual($documents)
            ->and($documents)->toHaveKey('intro');
        linCodexAssertPlainData(Cache::get(InMemoryIndex::CACHE_KEY));
    });
});
