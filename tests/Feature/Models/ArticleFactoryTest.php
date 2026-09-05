<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleContext;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Models\Media;
use FinityLabs\LinCodex\Search\SearchText;
use Illuminate\Support\Facades\DB;

it('creates an authenticated published markdown article by default', function (): void {
    $article = Article::factory()->create()->fresh();

    expect($article)->not->toBeNull()
        ->and($article->visibility)->toBe(Visibility::Authenticated)
        ->and($article->is_published)->toBeTrue()
        ->and($article->format)->toBe(ArticleFormat::Markdown)
        ->and($article->sort_order)->toBe(0)
        ->and($article->parent_id)->toBeNull()
        ->and($article->slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
        ->and($article->slug)->not->toContain('/')
        ->and($article->keywords)->toBe([])
        ->and($article->related)->toBe([])
        ->and($article->meta)->toBe([]);
});

it('defaults to authenticated and published from the schema when created without the factory', function (): void {
    $article = Article::query()->create(['slug' => 'bare'])->fresh();

    expect($article)->not->toBeNull()
        ->and($article->visibility)->toBe(Visibility::Authenticated)
        ->and($article->is_published)->toBeTrue()
        ->and($article->format)->toBe(ArticleFormat::Markdown)
        ->and($article->keywords)->toBeNull()
        ->and($article->related)->toBeNull()
        ->and($article->meta)->toBeNull();
});

it('fills keywords, related and meta through the factory states', function (): void {
    $article = Article::factory()
        ->withKeywords(['rbac', 'roles'])
        ->withRelated(['users/roles'])
        ->withMeta(['owner' => 'ops'])
        ->create()
        ->fresh();

    expect($article)->not->toBeNull()
        ->and($article->keywords)->toBe(['rbac', 'roles'])
        ->and($article->related)->toBe(['users/roles'])
        ->and($article->meta)->toBe(['owner' => 'ops'])
        ->and(json_decode((string) DB::table('codex_articles')->value('meta'), true))->toBe(['owner' => 'ops']);
});

it('applies the simple factory states', function (): void {
    expect(Article::factory()->unpublished()->create()->fresh()?->is_published)->toBeFalse()
        ->and(Article::factory()->public()->create()->fresh()?->visibility)->toBe(Visibility::Public)
        ->and(Article::factory()->html()->create()->fresh()?->format)->toBe(ArticleFormat::Html);

    $explicit = Article::factory()->published()->authenticated()->markdown()->create()->fresh();

    expect($explicit)->not->toBeNull()
        ->and($explicit->is_published)->toBeTrue()
        ->and($explicit->visibility)->toBe(Visibility::Authenticated)
        ->and($explicit->format)->toBe(ArticleFormat::Markdown);
});

it('creates a child slug and links parent_id through childOf', function (): void {
    $parent = Article::factory()->create();
    $child = Article::factory()->childOf($parent)->create()->fresh();
    $named = Article::factory()->childOf($parent, 'roles')->create()->fresh();

    expect($child)->not->toBeNull()
        ->and($child->slug)->toStartWith($parent->slug.'/')
        ->and($child->parent_id)->toBe($parent->id)
        ->and($named?->slug)->toBe($parent->slug.'/roles')
        ->and($named?->parent_id)->toBe($parent->id);
});

it('creates translations through withTranslation', function (): void {
    $article = Article::factory()->withTranslation('de', ['title' => 'Rollen'])->create();

    expect($article->translations)->toHaveCount(1);

    $translation = $article->translations->first();

    expect($translation)->toBeInstanceOf(ArticleTranslation::class)
        ->and($translation?->locale)->toBe('de')
        ->and($translation?->title)->toBe('Rollen')
        ->and($translation?->body)->not->toBe('')
        ->and(SearchText::split($translation?->search_text)['title'])->toBe(SearchText::fold((string) $translation?->title));

    $two = Article::factory()->withTranslation()->withTranslation('hu')->create();

    expect($two->translations)->toHaveCount(2)
        ->and($two->translations->pluck('locale')->all())->toBe(['en', 'hu']);
});

it('creates contexts through withContext', function (): void {
    $article = Article::factory()->withContext(ContextType::Route, 'users.index', 'admin', 2)->create();

    expect($article->contexts)->toHaveCount(1);

    $context = $article->contexts->first();

    expect($context)->toBeInstanceOf(ArticleContext::class)
        ->and($context?->type)->toBe(ContextType::Route)
        ->and($context?->key)->toBe('users.index')
        ->and($context?->panel_id)->toBe('admin')
        ->and($context?->sort_order)->toBe(2);

    $class = Article::factory()->withContext(ContextType::PageClass, 'App\\Filament\\Resources\\UserResource')->create();

    expect($class->contexts->first()?->type)->toBe(ContextType::PageClass)
        ->and(DB::table('codex_article_contexts')->where('article_id', $class->id)->value('type'))->toBe(ContextType::PageClass->value);
});

it('creates revisions through withRevisions', function (): void {
    $article = Article::factory()->withRevisions(3, 'en')->create();

    expect($article->revisions)->toHaveCount(3);

    $article->revisions->each(function (ArticleRevision $revision): void {
        expect($revision->reason)->toBe(RevisionReason::Manual)
            ->and($revision->format)->toBe(ArticleFormat::Markdown)
            ->and($revision->locale)->toBe('en')
            ->and($revision->created_at)->not->toBeNull();
    });
});

it('creates media through withMedia', function (): void {
    $article = Article::factory()->withMedia(2)->create();

    expect($article->media)->toHaveCount(2);

    $article->media->each(function (Media $media) use ($article): void {
        expect($media->disk)->toBe(config('lin-codex.media.disk'))
            ->and($media->path)->toStartWith(config('lin-codex.media.directory').'/')
            ->and($media->article_id)->toBe($article->id);
    });
});

it('resolves the default table names on every model', function (): void {
    expect((new Article)->getTable())->toBe('codex_articles')
        ->and((new ArticleTranslation)->getTable())->toBe('codex_article_translations')
        ->and((new ArticleContext)->getTable())->toBe('codex_article_contexts')
        ->and((new ArticleRevision)->getTable())->toBe('codex_article_revisions')
        ->and((new Media)->getTable())->toBe('codex_media');
});

it('writes exactly one row per child table for a fully decorated article', function (): void {
    Article::factory()
        ->withTranslation()
        ->withContext(ContextType::Url, '/users/*')
        ->withRevisions()
        ->withMedia()
        ->create();

    expect(DB::table('codex_articles')->count())->toBe(1)
        ->and(DB::table('codex_article_translations')->count())->toBe(1)
        ->and(DB::table('codex_article_contexts')->count())->toBe(1)
        ->and(DB::table('codex_article_revisions')->count())->toBe(1)
        ->and(DB::table('codex_media')->count())->toBe(1);
});
