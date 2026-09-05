<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('records created_at and carries no updated_at attribute', function (): void {
    $article = Article::factory()->withRevisions()->create();
    $revision = $article->revisions->first()?->fresh();

    expect($revision)->toBeInstanceOf(ArticleRevision::class)
        ->and($revision?->created_at)->toBeInstanceOf(Carbon::class)
        ->and(array_key_exists('updated_at', $revision?->getAttributes() ?? []))->toBeFalse()
        ->and(ArticleRevision::UPDATED_AT)->toBeNull();
});

it('casts format and reason to enums and honours the reason states', function (): void {
    $article = Article::factory()->withRevisions()->create();
    $manual = $article->revisions->first()?->fresh();

    expect($manual?->format)->toBe(ArticleFormat::Markdown)
        ->and($manual?->reason)->toBe(RevisionReason::Manual);

    $import = ArticleRevision::factory()->import()->create(['article_id' => $article->id])->fresh();
    $rewrite = ArticleRevision::factory()->aiRewrite()->create(['article_id' => $article->id])->fresh();

    expect($import?->reason)->toBe(RevisionReason::Import)
        ->and($rewrite?->reason)->toBe(RevisionReason::AiRewrite)
        ->and($article->revisions()->count())->toBe(3);
});

it('links a revision to its author through byUser', function (): void {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $article = Article::factory()->create();
    $revision = ArticleRevision::factory()->byUser($userId)->create(['article_id' => $article->id])->fresh();

    expect($revision?->user_id)->toBe($userId)
        ->and($revision?->user)->toBeInstanceOf(User::class)
        ->and($revision?->user?->getKey())->toBe($userId)
        ->and($revision?->article?->is($article))->toBeTrue();
});
