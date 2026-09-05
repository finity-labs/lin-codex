<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\Media;
use Illuminate\Support\Facades\DB;

it('cascades child rows and nulls media and children on article delete', function (): void {
    $article = Article::factory()
        ->withTranslation()
        ->withContext(ContextType::Route, 'users.index')
        ->withRevisions(2)
        ->withMedia()
        ->create(['slug' => 'users']);
    $child = Article::factory()->childOf($article, 'roles')->create();
    $media = $article->media->first();

    expect($child->fresh()?->parent_id)->toBe($article->id)
        ->and(DB::table('codex_article_translations')->where('article_id', $article->id)->count())->toBe(1)
        ->and(DB::table('codex_article_contexts')->where('article_id', $article->id)->count())->toBe(1)
        ->and(DB::table('codex_article_revisions')->where('article_id', $article->id)->count())->toBe(2);

    $article->delete();

    expect(DB::table('codex_article_translations')->where('article_id', $article->id)->count())->toBe(0)
        ->and(DB::table('codex_article_contexts')->where('article_id', $article->id)->count())->toBe(0)
        ->and(DB::table('codex_article_revisions')->where('article_id', $article->id)->count())->toBe(0)
        ->and(Media::query()->count())->toBe(1)
        ->and($media?->fresh()?->article_id)->toBeNull()
        ->and($child->fresh())->not->toBeNull()
        ->and($child->fresh()?->parent_id)->toBeNull();
});

it('nulls created_by when the author is deleted', function (): void {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $article = Article::factory()->create(['created_by' => $userId, 'updated_by' => $userId]);

    expect($article->fresh()?->created_by)->toBe($userId)
        ->and($article->creator?->getKey())->toBe($userId);

    DB::table('users')->where('id', $userId)->delete();

    expect($article->fresh()?->created_by)->toBeNull()
        ->and($article->fresh()?->updated_by)->toBeNull();
});
