<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Set the keep count for the current test, leaving the enabled switch alone.
 */
function linCodexRevisionCommandsKeep(int $keep): void
{
    $settings = app(CodexSettings::class);
    $settings->revisions_keep = $keep;
    $settings->save();
}

/**
 * Two articles: A with five en revisions, B with three en and two de
 * revisions. Twelve rows in total.
 *
 * @return array{0: Article, 1: Article}
 */
function linCodexRevisionCommandsArticles(): array
{
    $a = Article::factory()->withRevisions(5, 'en')->create(['slug' => 'alpha']);
    $b = Article::factory()->withRevisions(3, 'en')->create(['slug' => 'bravo']);

    ArticleRevision::factory()->count(2)->create(['article_id' => $b->id, 'locale' => 'de']);

    return [$a, $b];
}

/**
 * The revision ids of one article and locale, oldest first.
 *
 * @return list<int>
 */
function linCodexRevisionCommandsIds(Article $article, string $locale): array
{
    return $article->revisions()->where('locale', $locale)->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
}

/**
 * Insert a users row and return its id.
 */
function linCodexRevisionCommandsUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Linus',
        'email' => 'linus-'.uniqid().'@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('registers the commands discovered under src/Commands', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('codex:revisions:prune')
        ->and($commands)->toHaveKey('codex:revisions:restore');
});

it('prunes every article and locale to the keep count', function (): void {
    linCodexRevisionCommandsKeep(2);
    [$a, $b] = linCodexRevisionCommandsArticles();

    $newestA = array_slice(linCodexRevisionCommandsIds($a, 'en'), -2);
    $newestBEn = array_slice(linCodexRevisionCommandsIds($b, 'en'), -2);
    $newestBDe = linCodexRevisionCommandsIds($b, 'de');

    $this->artisan('codex:revisions:prune')
        ->expectsOutputToContain('Removed 4 revisions')
        ->assertExitCode(0);

    expect(linCodexRevisionCommandsIds($a, 'en'))->toBe($newestA)
        ->and(linCodexRevisionCommandsIds($b, 'en'))->toBe($newestBEn)
        ->and(linCodexRevisionCommandsIds($b, 'de'))->toBe($newestBDe)
        ->and(ArticleRevision::query()->count())->toBe(6);
});

it('--keep overrides for the run and leaves the settings alone', function (): void {
    linCodexRevisionCommandsKeep(2);
    [$a, $b] = linCodexRevisionCommandsArticles();

    $this->artisan('codex:revisions:prune', ['--keep' => '1'])
        ->expectsOutputToContain('Removed 7 revisions')
        ->assertExitCode(0);

    expect($a->revisions()->where('locale', 'en')->count())->toBe(1)
        ->and($b->revisions()->where('locale', 'en')->count())->toBe(1)
        ->and($b->revisions()->where('locale', 'de')->count())->toBe(1)
        ->and(app(CodexSettings::class)->refresh()->revisions_keep)->toBe(2);

    $this->artisan('codex:revisions:prune', ['--keep' => '-1'])
        ->expectsOutputToContain('--keep must be a whole number of zero or more.')
        ->assertExitCode(1);

    expect(ArticleRevision::query()->count())->toBe(3);
});

it('reports zero when nothing exceeds the keep count', function (): void {
    linCodexRevisionCommandsKeep(10);
    linCodexRevisionCommandsArticles();

    $this->artisan('codex:revisions:prune')
        ->expectsOutputToContain('Removed 0 revisions')
        ->assertExitCode(0);

    expect(ArticleRevision::query()->count())->toBe(10);
});

it('restores a revision by id and records the snapshot author from --user', function (): void {
    $userId = linCodexRevisionCommandsUser();
    $article = Article::factory()->markdown()->withTranslation('en', ['title' => 'New', 'body' => 'New body'])->create(['slug' => 'guide/intro']);
    $revision = ArticleRevision::factory()->manual()->create([
        'article_id' => $article->id,
        'locale' => 'en',
        'title' => 'Old',
        'body' => 'Old body',
        'format' => ArticleFormat::Markdown,
    ]);

    $this->artisan('codex:revisions:restore', ['revision' => (string) $revision->id, '--user' => (string) $userId])
        ->expectsOutputToContain(sprintf('Restored revision %d (en) of guide/intro', $revision->id))
        ->assertExitCode(0);

    $translation = $article->translations()->where('locale', 'en')->first();
    $newest = $article->revisions()->orderByDesc('id')->first();

    expect($translation?->title)->toBe('Old')
        ->and($translation?->body)->toBe('Old body')
        ->and($article->revisions()->count())->toBe(2)
        ->and($newest?->title)->toBe('New')
        ->and($newest?->user_id)->toBe($userId);
});

it('fails on an unknown revision id', function (): void {
    $this->artisan('codex:revisions:restore', ['revision' => '999999'])
        ->expectsOutputToContain('Revision 999999 not found')
        ->assertExitCode(1);
});

it('fails cleanly when --user is not a known user', function (): void {
    $article = Article::factory()->markdown()->withTranslation('en', ['title' => 'New', 'body' => 'New body'])->create();
    $revision = ArticleRevision::factory()->manual()->create(['article_id' => $article->id, 'locale' => 'en', 'title' => 'Old', 'body' => 'Old body']);

    $this->artisan('codex:revisions:restore', ['revision' => (string) $revision->id, '--user' => '424242'])
        ->assertExitCode(1);

    expect($article->translations()->where('locale', 'en')->first()?->title)->toBe('New')
        ->and($article->revisions()->count())->toBe(1);
});
