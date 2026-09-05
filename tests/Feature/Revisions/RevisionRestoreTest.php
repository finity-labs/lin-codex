<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Search\SearchText;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\DB;

/**
 * Set the revisions switch for the current test.
 */
function linCodexRestoreSetEnabled(bool $enabled): void
{
    $settings = app(CodexSettings::class);
    $settings->revisions_enabled = $enabled;
    $settings->save();
}

/**
 * A markdown article whose en translation reads "New" / "New body", plus one
 * manual revision holding "Old" / "Old body" in the given format.
 *
 * @return array{0: Article, 1: ArticleTranslation, 2: ArticleRevision}
 */
function linCodexRestoreFixture(ArticleFormat $revisionFormat = ArticleFormat::Markdown, string $locale = 'en'): array
{
    $article = Article::factory()
        ->markdown()
        ->withTranslation($locale, ['title' => 'New', 'body' => 'New body'])
        ->create();

    $translation = $article->translations()->where('locale', $locale)->firstOrFail();

    $revision = ArticleRevision::factory()->manual()->create([
        'article_id' => $article->id,
        'locale' => $locale,
        'title' => 'Old',
        'body' => 'Old body',
        'format' => $revisionFormat,
    ]);

    return [$article, $translation, $revision];
}

/**
 * Insert a users row and return its id.
 */
function linCodexRestoreUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Grace',
        'email' => 'grace-'.uniqid().'@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    linCodexRestoreSetEnabled(true);
});

it('snapshots the current content with reason manual and the given user, then swaps title and body', function (): void {
    [$article, , $revision] = linCodexRestoreFixture();
    $userId = linCodexRestoreUser();

    $restored = app(RevisionManager::class)->restore($revision, $userId);

    $newest = $article->revisions()->orderByDesc('id')->first();

    expect($restored->title)->toBe('Old')
        ->and($restored->body)->toBe('Old body')
        ->and($restored->fresh()?->title)->toBe('Old')
        ->and($restored->fresh()?->body)->toBe('Old body')
        ->and($article->revisions()->count())->toBe(2)
        ->and($newest?->title)->toBe('New')
        ->and($newest?->body)->toBe('New body')
        ->and($newest?->reason)->toBe(RevisionReason::Manual)
        ->and($newest?->user_id)->toBe($userId)
        ->and($newest?->is($revision))->toBeFalse();
});

it('restores the format onto the article without a further revision', function (): void {
    [$article, , $revision] = linCodexRestoreFixture(ArticleFormat::Html);

    app(RevisionManager::class)->restore($revision, null);

    expect($article->fresh()?->format)->toBe(ArticleFormat::Html)
        ->and($article->revisions()->count())->toBe(2)
        ->and($article->revisions()->orderByDesc('id')->first()?->format)->toBe(ArticleFormat::Markdown);
});

it('recreates a deleted translation from its revision', function (): void {
    [$article, $translation, $revision] = linCodexRestoreFixture(ArticleFormat::Markdown, 'de');

    $translation->delete();

    expect($article->translations()->where('locale', 'de')->exists())->toBeFalse();

    $restored = app(RevisionManager::class)->restore($revision, null);
    $row = $article->translations()->where('locale', 'de')->first();

    expect($restored->exists)->toBeTrue()
        ->and($row?->title)->toBe('Old')
        ->and($row?->body)->toBe('Old body')
        ->and($row?->is($restored))->toBeTrue()
        ->and($article->revisions()->count())->toBe(1);
});

it('re-indexes search_text after the swap', function (): void {
    [, , $revision] = linCodexRestoreFixture();

    $restored = app(RevisionManager::class)->restore($revision, null);
    $fields = SearchText::split($restored->fresh()?->search_text);

    expect($fields['title'])->toBe('old')
        ->and($fields['body'])->toContain('old body')
        ->and($fields['body'])->not->toContain('new body');
});

it('snapshots even when revisions are disabled because the caller asked for it', function (): void {
    linCodexRestoreSetEnabled(false);
    [$article, , $revision] = linCodexRestoreFixture(ArticleFormat::Html);

    $restored = app(RevisionManager::class)->restore($revision, null);

    expect($restored->fresh()?->title)->toBe('Old')
        ->and($article->fresh()?->format)->toBe(ArticleFormat::Html)
        ->and($article->revisions()->count())->toBe(2)
        ->and($article->revisions()->orderByDesc('id')->first()?->title)->toBe('New');
});
