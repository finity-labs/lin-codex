<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\RenderedArticle;
use Illuminate\Support\Facades\Lang;

const MARKDOWN_BODY = "## Reset a password\n\nBody";
const HTML_BODY = '<h2>Reset a password</h2><p>Body</p><script>x</script>';

it('renders markdown into the value object with ids, toc and plain text', function (): void {
    $article = app(ArticleRenderer::class)->render(MARKDOWN_BODY, ArticleFormat::Markdown, 'en', 'intro');

    expect($article)->toBeInstanceOf(RenderedArticle::class)
        ->and($article->html)->toContain('id="reset-a-password"')
        ->and($article->toc)->toBe([['level' => 2, 'text' => 'Reset a password', 'id' => 'reset-a-password']])
        ->and($article->plainText)->toBe('Reset a password Body');
});

it('renders html into the same shape with the script dropped', function (): void {
    $article = app(ArticleRenderer::class)->render(HTML_BODY, ArticleFormat::Html, 'en', 'intro');

    expect($article)->toBeInstanceOf(RenderedArticle::class)
        ->and($article->html)->toContain('id="reset-a-password"')
        ->and($article->html)->not->toContain('<script')
        ->and($article->toc)->toBe([['level' => 2, 'text' => 'Reset a password', 'id' => 'reset-a-password']])
        ->and($article->plainText)->toBe('Reset a password Body');
});

it('extracts identical plain text from either format', function (): void {
    $renderer = app(ArticleRenderer::class);

    expect($renderer->plainText(MARKDOWN_BODY, ArticleFormat::Markdown, 'en'))
        ->toBe('Reset a password Body')
        ->toBe($renderer->plainText(HTML_BODY, ArticleFormat::Html, 'en'));
});

it('renders an empty body in either format without complaint', function (): void {
    $renderer = app(ArticleRenderer::class);

    foreach ([ArticleFormat::Markdown, ArticleFormat::Html] as $format) {
        $article = $renderer->render('', $format, 'en');

        expect($article->html)->toBe('')
            ->and($article->toc)->toBe([])
            ->and($article->plainText)->toBe('');
    }
});

it('passes the render locale to translations without changing the app locale', function (): void {
    Lang::addLines(['lin-codex.callouts.tip' => 'Tipp'], 'de', 'lin-codex');

    $html = app(ArticleRenderer::class)->render("> [!TIP]\n> x", ArticleFormat::Markdown, 'de', 'intro')->html;

    expect($html)->toContain('Tipp')
        ->and(app()->getLocale())->toBe('en');
});

it('is a container singleton', function (): void {
    expect(app(ArticleRenderer::class))->toBe(app(ArticleRenderer::class));
});

it('gives a renderer with fresh pipelines through freshRenderer()', function (): void {
    $markdown = '[x](http://localhost/x)';
    $stale = app(ArticleRenderer::class);

    expect($stale->renderUncached($markdown, ArticleFormat::Markdown, 'en')->html)->not->toContain('codex-external');

    config()->set('app.url', 'https://other.test');

    $fresh = $this->freshRenderer();

    expect($fresh)->not->toBe($stale)
        ->and($stale->renderUncached($markdown, ArticleFormat::Markdown, 'en')->html)->not->toContain('codex-external')
        ->and($fresh->renderUncached($markdown, ArticleFormat::Markdown, 'en')->html)->toContain('codex-external');
});
