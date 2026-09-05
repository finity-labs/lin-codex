<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use FinityLabs\LinCodex\Rendering\RendererFingerprint;
use Illuminate\Support\Facades\Cache;

const CACHED_MARKDOWN = "## Reset a password\n\nSee [Roles](roles.md) and <https://example.com/x>.";

describe('hits', function (): void {
    it('stores the first render and serves the second from the cache', function (): void {
        $renderer = app(ArticleRenderer::class);
        $key = $renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect(Cache::has($key))->toBeFalse();

        $first = $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
        $second = $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect(Cache::has($key))->toBeTrue()
            ->and($second)->toEqual($first);
    });

    it('reads through the cache under a deterministic key', function (): void {
        $renderer = app(ArticleRenderer::class);
        $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        Cache::put($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'), new RenderedArticle('<p>cached</p>', [], 'cached', []));

        expect($renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro')->html)->toBe('<p>cached</p>')
            ->and($renderer->plainText(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'))->toBe('cached');
    });
});

describe('misses', function (): void {
    it('renders fresh when one character of the body changes', function (): void {
        $renderer = app(ArticleRenderer::class);
        $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        Cache::put($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'), new RenderedArticle('<p>cached</p>', [], 'cached', []));

        $edited = $renderer->render(CACHED_MARKDOWN.'!', ArticleFormat::Markdown, 'en', 'intro');

        expect($renderer->cacheKey(CACHED_MARKDOWN.'!', ArticleFormat::Markdown, 'en', 'intro'))
            ->not->toBe($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'))
            ->and($edited->html)->toContain('id="reset-a-password"')
            ->and($edited->html)->not->toContain('cached');
    });

    it('misses when the locale, the slug or the format changes', function (): void {
        $renderer = app(ArticleRenderer::class);
        $base = $renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'de', 'intro'))->not->toBe($base)
            ->and($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'users/intro'))->not->toBe($base)
            ->and($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Html, 'en', 'intro'))->not->toBe($base);

        $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect(Cache::has($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'de', 'intro')))->toBeFalse()
            ->and(Cache::has($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'users/intro')))->toBeFalse()
            ->and(Cache::has($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Html, 'en', 'intro')))->toBeFalse();
    });

    it('changes the key when the help center prefix changes', function (): void {
        $before = app(ArticleRenderer::class)->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        config()->set('lin-codex.routes.help_center', '/manual');

        expect($this->freshRenderer()->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'))->not->toBe($before);
    });

    it('changes the key when a parser limit changes', function (): void {
        $before = app(ArticleRenderer::class)->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        config()->set('lin-codex.render.limits.max_nesting_level', 10);

        expect($this->freshRenderer()->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'))->not->toBe($before);
    });

    it('changes the key when the app url host changes', function (): void {
        $before = app(ArticleRenderer::class)->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        config()->set('app.url', 'https://other.test');

        expect($this->freshRenderer()->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro'))->not->toBe($before);
    });

    it('changes the key when the sanitizer config changes', function (): void {
        $before = app(ArticleRenderer::class)->cacheKey('<p>x</p>', ArticleFormat::Html, 'en', 'intro');

        config()->set('lin-codex.render.sanitizer.max_input_length', 5000);

        expect($this->freshRenderer()->cacheKey('<p>x</p>', ArticleFormat::Html, 'en', 'intro'))->not->toBe($before);
    });
});

describe('ttl and store', function (): void {
    it('bypasses the cache when the ttl is zero', function (): void {
        config()->set('lin-codex.render.cache.ttl', 0);

        $renderer = app(ArticleRenderer::class);
        $key = $renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        $first = $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
        expect(Cache::has($key))->toBeFalse();

        $second = $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
        expect(Cache::has($key))->toBeFalse()
            ->and($second)->toEqual($first)
            ->and($first->html)->toContain('id="reset-a-password"');
    });

    it('never hands a non-positive ttl to the store', function (): void {
        config()->set('lin-codex.render.cache.ttl', -5);

        $renderer = app(ArticleRenderer::class);
        $key = $renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');
        Cache::forever($key, new RenderedArticle('<p>cached</p>', [], 'cached', []));

        expect($renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro')->html)->not->toBe('<p>cached</p>')
            ->and(Cache::has($key))->toBeTrue();
    });

    it('stores with an integer ttl', function (): void {
        config()->set('lin-codex.render.cache.ttl', 60);

        $renderer = app(ArticleRenderer::class);
        $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect(Cache::has($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro')))->toBeTrue();
    });

    it('accepts a string ttl from the environment', function (): void {
        config()->set('lin-codex.render.cache.ttl', '120');

        $renderer = app(ArticleRenderer::class);
        $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect(Cache::has($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro')))->toBeTrue();
    });

    it('renders through a named store', function (): void {
        config()->set('lin-codex.render.cache.store', 'array');

        $renderer = app(ArticleRenderer::class);
        $article = $renderer->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro');

        expect($article->html)->toContain('id="reset-a-password"')
            ->and(Cache::store('array')->has($renderer->cacheKey(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'intro')))->toBeTrue();
    });
});

describe('serialization', function (): void {
    it('round-trips a rendered article through serialize()', function (): void {
        $original = app(ArticleRenderer::class)->render(CACHED_MARKDOWN, ArticleFormat::Markdown, 'en', 'x');

        $restored = unserialize(serialize($original));

        expect($restored)->toBeInstanceOf(RenderedArticle::class)
            ->toEqual($original)
            ->and($restored->html)->toBe($original->html)
            ->and($restored->toc)->toBe($original->toc)
            ->and($restored->plainText)->toBe($original->plainText)
            ->and($restored->metadata)->toBe($original->metadata);
    });
});

describe('fingerprint', function (): void {
    it('is a stable sha256 over a scalar-only input', function (): void {
        $fingerprint = new RendererFingerprint(app(MarkdownPipeline::class), app(HtmlPipeline::class));

        expect(RendererFingerprint::MARKUP_VERSION)->toBeInt()
            ->and($fingerprint->hash())->toMatch('/^[0-9a-f]{64}$/')
            ->toBe($fingerprint->hash())
            ->and(json_encode($fingerprint->input(), JSON_THROW_ON_ERROR))->not->toContain('Closure');
    });

    it('covers every extension the markdown environment registers', function (): void {
        $input = (new RendererFingerprint(app(MarkdownPipeline::class), app(HtmlPipeline::class)))->input();

        expect($input['markdown']['extensions'])->toBe(app(MarkdownPipeline::class)->extensionClasses())
            ->and($input['markup_version'])->toBe(RendererFingerprint::MARKUP_VERSION);
    });
});
