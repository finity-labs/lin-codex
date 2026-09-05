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
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turn revisions on for the current test with the given keep count.
 */
function linCodexRevisionsEnable(int $keep = 10): void
{
    $settings = app(CodexSettings::class);
    $settings->revisions_enabled = true;
    $settings->revisions_keep = $keep;
    $settings->save();
}

/**
 * A markdown article with one en translation titled "Old" whose body is "Old body".
 *
 * @param  array<string, mixed>  $translation
 */
function linCodexRevisionsArticle(array $translation = []): Article
{
    return Article::factory()
        ->markdown()
        ->withTranslation('en', $translation + ['title' => 'Old', 'body' => 'Old body'])
        ->create();
}

/**
 * One translation of an article, read fresh from the database.
 */
function linCodexRevisionsTranslation(Article $article, string $locale = 'en'): ArticleTranslation
{
    return $article->translations()->where('locale', $locale)->firstOrFail();
}

/**
 * Insert a users row and return its id.
 */
function linCodexRevisionsUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Ada',
        'email' => 'ada-'.uniqid().'@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('stores nothing when revisions are disabled', function (): void {
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);

    $translation->title = 'New';
    $translation->save();

    expect($article->revisions()->count())->toBe(0);
});

it('stores the previous title, body and format with reason manual on a title change', function (): void {
    linCodexRevisionsEnable();
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);

    $translation->title = 'New';
    $translation->save();

    $revisions = $article->revisions()->get();

    expect($revisions)->toHaveCount(1);

    $revision = $revisions->first();

    expect($revision?->title)->toBe('Old')
        ->and($revision?->body)->toBe('Old body')
        ->and($revision?->format)->toBe(ArticleFormat::Markdown)
        ->and($revision?->reason)->toBe(RevisionReason::Manual)
        ->and($revision?->locale)->toBe('en')
        ->and($revision?->user_id)->toBeNull()
        ->and($revision?->created_at)->toBeInstanceOf(Carbon::class);

    $translation->body = 'Newer body';
    $translation->save();

    $newest = $article->revisions()->orderByDesc('id')->first();

    expect($article->revisions()->count())->toBe(2)
        ->and($newest?->title)->toBe('New')
        ->and($newest?->body)->toBe('Old body');
});

it('stores nothing on create, on an excerpt-only change or on a search_text-only change', function (): void {
    linCodexRevisionsEnable();
    $article = Article::factory()->markdown()->create();

    $translation = ArticleTranslation::factory()->create(['article_id' => $article->id, 'title' => 'Old', 'body' => 'Old body']);

    expect($article->revisions()->count())->toBe(0);

    $translation->excerpt = 'x';
    $translation->save();

    expect($article->revisions()->count())->toBe(0);

    $translation->search_text = 'x';
    $translation->save();

    expect($article->revisions()->count())->toBe(0);
});

it('stores the previous format for every translation when the article format changes', function (): void {
    linCodexRevisionsEnable();
    $article = linCodexRevisionsArticle();
    ArticleTranslation::factory()->locale('de')->create(['article_id' => $article->id, 'title' => 'Alt', 'body' => 'Alt body']);

    $article->format = ArticleFormat::Html;
    $article->save();

    $revisions = $article->revisions()->orderBy('locale')->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions->pluck('locale')->all())->toBe(['de', 'en'])
        ->and($revisions->pluck('format')->all())->toBe([ArticleFormat::Markdown, ArticleFormat::Markdown])
        ->and($revisions->pluck('reason')->all())->toBe([RevisionReason::Manual, RevisionReason::Manual])
        ->and($revisions->firstWhere('locale', 'en')?->title)->toBe('Old')
        ->and($revisions->firstWhere('locale', 'de')?->body)->toBe('Alt body');

    $translation = linCodexRevisionsTranslation($article);
    $translation->body = 'Changed after the format';
    $translation->save();

    $third = $article->revisions()->orderByDesc('id')->first();

    expect($article->revisions()->count())->toBe(3)
        ->and($third?->format)->toBe(ArticleFormat::Html)
        ->and($third?->body)->toBe('Old body');
});

it('prunes to the keep count per locale in the same save', function (): void {
    linCodexRevisionsEnable(2);
    $article = linCodexRevisionsArticle();
    ArticleTranslation::factory()->locale('de')->create(['article_id' => $article->id, 'title' => 'Alt', 'body' => 'Alt body']);

    $en = linCodexRevisionsTranslation($article);

    foreach (['B1', 'B2', 'B3', 'B4'] as $body) {
        $en->body = $body;
        $en->save();
    }

    $de = linCodexRevisionsTranslation($article, 'de');
    $de->body = 'Neu';
    $de->save();

    $enBodies = $article->revisions()->where('locale', 'en')->orderBy('id')->pluck('body')->all();
    $deBodies = $article->revisions()->where('locale', 'de')->orderBy('id')->pluck('body')->all();

    expect($enBodies)->toBe(['B2', 'B3'])
        ->and($deBodies)->toBe(['Alt body'])
        ->and($article->revisions()->count())->toBe(3);
});

it('prunes nothing when disabled even if old revisions exist', function (): void {
    $article = Article::factory()->markdown()->withTranslation('en', ['title' => 'Old', 'body' => 'Old body'])->withRevisions(5)->create();
    $translation = linCodexRevisionsTranslation($article);

    $translation->body = 'Changed';
    $translation->save();

    expect($article->revisions()->count())->toBe(5);
});

it('takes the reason and the user from the attributing scope', function (): void {
    linCodexRevisionsEnable();
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);
    $userId = linCodexRevisionsUser();

    app(RevisionManager::class)->attributing(RevisionReason::Import, $userId, fn (): bool => $translation->fill(['body' => 'Imported'])->save());

    $first = $article->revisions()->orderByDesc('id')->first();

    expect($article->revisions()->count())->toBe(1)
        ->and($first?->reason)->toBe(RevisionReason::Import)
        ->and($first?->user_id)->toBe($userId);

    $translation->body = 'Edited by hand';
    $translation->save();

    $second = $article->revisions()->orderByDesc('id')->first();

    expect($article->revisions()->count())->toBe(2)
        ->and($second?->reason)->toBe(RevisionReason::Manual)
        ->and($second?->user_id)->toBeNull();
});

it('records nothing inside withoutRevisions', function (): void {
    linCodexRevisionsEnable();
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);
    $manager = app(RevisionManager::class);

    $manager->withoutRevisions(fn (): bool => $translation->fill(['body' => 'Silent'])->save());

    expect($article->revisions()->count())->toBe(0);

    $translation->body = 'Loud';
    $translation->save();

    expect($article->revisions()->count())->toBe(1);

    $manager->withoutRevisions(fn (): mixed => $manager->attributing(
        RevisionReason::Import,
        null,
        fn (): bool => $translation->fill(['body' => 'Inner scope wins'])->save(),
    ));

    $newest = $article->revisions()->orderByDesc('id')->first();

    expect($article->revisions()->count())->toBe(2)
        ->and($newest?->reason)->toBe(RevisionReason::Import)
        ->and($newest?->body)->toBe('Loud');
});

it('uses the authenticated viewer as the default author', function (): void {
    linCodexRevisionsEnable();
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);
    $userId = linCodexRevisionsUser();

    $this->actingAs(new GenericUser(['id' => $userId]));

    $translation->body = 'Signed';
    $translation->save();

    $revision = $article->revisions()->first();

    expect($article->revisions()->count())->toBe(1)
        ->and($revision?->user_id)->toBe($userId);
});

it('treats an unseeded settings group as disabled', function (): void {
    DB::table('settings')->where('group', 'lin-codex')->delete();

    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);

    $translation->body = 'Unseeded';
    $translation->save();

    $manager = app(RevisionManager::class);

    expect($article->revisions()->count())->toBe(0)
        ->and($manager->enabled())->toBeFalse()
        ->and($manager->keep())->toBe(10);
});

it('keeps the search-text indexer behaviour', function (): void {
    linCodexRevisionsEnable();
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);

    $translation->body = 'Fresh words for the index';
    $translation->save();

    $fields = SearchText::split($translation->fresh()?->search_text);

    expect($fields['body'])->toContain('fresh words for the index')
        ->and($fields['body'])->not->toContain('old body')
        ->and($article->revisions()->count())->toBe(1)
        ->and($article->revisions()->first()?->body)->toBe('Old body');
});

it('snapshots an existing translation on request and refuses an unsaved one', function (): void {
    $article = linCodexRevisionsArticle();
    $translation = linCodexRevisionsTranslation($article);
    $manager = app(RevisionManager::class);

    $revision = $manager->snapshot($translation, RevisionReason::AiRewrite, null);

    expect($revision)->toBeInstanceOf(ArticleRevision::class)
        ->and($revision->title)->toBe('Old')
        ->and($revision->body)->toBe('Old body')
        ->and($revision->format)->toBe(ArticleFormat::Markdown)
        ->and($revision->reason)->toBe(RevisionReason::AiRewrite)
        ->and($article->revisions()->count())->toBe(1);

    expect(fn (): ArticleRevision => $manager->snapshot(new ArticleTranslation(['locale' => 'en', 'title' => 'T', 'body' => 'B']), RevisionReason::Manual, null))
        ->toThrow(LogicException::class);
});
