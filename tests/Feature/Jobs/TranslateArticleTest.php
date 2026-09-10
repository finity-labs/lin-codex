<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\AiCallFailed;
use FinityLabs\LinCodex\Ai\AiReason;
use FinityLabs\LinCodex\Ai\Contracts\AiClient;
use FinityLabs\LinCodex\Ai\StructuredCompletion;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Events\ArticleTranslated;
use FinityLabs\LinCodex\Jobs\TranslateArticle;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Tests\Fixtures\FakeAiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

/**
 * @param  list<string>  $codes
 */
function linCodexJobsUseLanguages(array $codes, string $default = 'en'): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = $default;
    $settings->save();
}

/** How the prompt names a language: "Deutsch (de)", or the bare code without ext-intl. */
function linCodexJobsLanguage(string $code): string
{
    $display = CodexSettings::languageEntry($code)['display'];

    return $display === $code ? $code : "{$display} ({$code})";
}

/**
 * Insert a users row and return its id: a revision's user_id is a foreign
 * key, so the dispatching admin has to exist for the attribution row.
 */
function linCodexJobsUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Grace',
        'email' => 'grace-'.uniqid().'@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function linCodexJobsEnableRevisions(): void
{
    $settings = app(CodexSettings::class);
    $settings->revisions_enabled = true;
    $settings->save();
}

/** A fake seam answering the German then the Hungarian call. */
function linCodexJobsFake(): FakeAiClient
{
    return (new FakeAiClient)
        ->push(new StructuredCompletion(['title' => 'Zurücksetzen', 'excerpt' => '', 'body' => "# Zurücksetzen\n\nText."], 10, 20))
        ->push(new StructuredCompletion(['title' => 'Visszaállítás', 'excerpt' => '', 'body' => "# Visszaállítás\n\nSzöveg."], 30, 40));
}

function linCodexJobsBind(FakeAiClient $fake): FakeAiClient
{
    app()->instance(AiClient::class, $fake);

    return $fake;
}

beforeEach(function (): void {
    linCodexJobsUseLanguages(['en', 'de', 'hu']);
    $this->enableAi();
    config(['ai.providers.anthropic.key' => null]);

    $this->userId = linCodexJobsUser();

    $this->article = Article::factory()->withTranslation('en', [
        'title' => 'Reset a password',
        'excerpt' => null,
        'body' => "# Reset\n\nText.",
    ])->create();
});

it('dispatches to the default queue with the locales, the user, one try and a sized timeout', function (): void {
    Queue::fake();

    TranslateArticle::dispatch($this->article->id, ['de', 'hu'], $this->userId);

    Queue::assertPushed(TranslateArticle::class, fn (TranslateArticle $job): bool => $job->articleId === $this->article->id
        && $job->locales === ['de', 'hu']
        && $job->userId === $this->userId
        && $job->queue === null
        && $job->tries === 1
        && $job->timeout === 2 * 120 + 30);
});

it('dispatches to lin-codex.ai.queue and sizes the timeout from the settings', function (): void {
    config(['lin-codex.ai.queue' => 'translations']);
    $this->enableAi(['timeout' => 30]);

    Queue::fake();

    TranslateArticle::dispatch($this->article->id, ['de'], $this->userId);

    Queue::assertPushed(TranslateArticle::class, fn (TranslateArticle $job): bool => $job->queue === 'translations'
        && $job->timeout === 60);
});

it('writes each translated locale through the core and reports it', function (): void {
    Event::fake([ArticleTranslated::class]);
    $fake = linCodexJobsBind(linCodexJobsFake());

    dispatch_sync(new TranslateArticle($this->article->id, ['de', 'hu'], $this->userId));

    $de = ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'de')->first();
    $hu = ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'hu')->first();

    expect($de)->not->toBeNull()
        ->and($de->title)->toBe('Zurücksetzen')
        ->and($de->body)->toBe("# Zurücksetzen\n\nText.")
        ->and($de->search_text)->not->toBeNull()
        ->and($hu)->not->toBeNull()
        ->and($hu->title)->toBe('Visszaállítás')
        ->and($hu->body)->toBe("# Visszaállítás\n\nSzöveg.")
        ->and($hu->search_text)->not->toBeNull();

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->articleId === $this->article->id
        && $e->userId === $this->userId
        && $e->report->translatedLocales() === ['de', 'hu']
        && $e->report->promptTokens() === 40
        && $e->report->completionTokens() === 60);

    expect($fake->requests)->toHaveCount(2)
        ->and($fake->requests[0]->prompt)->toContain('into '.linCodexJobsLanguage('de'))
        ->and($fake->requests[1]->prompt)->toContain('into '.linCodexJobsLanguage('hu'));
});

it('skips a locale filled since dispatch', function (): void {
    Event::fake([ArticleTranslated::class]);
    $fake = linCodexJobsBind(linCodexJobsFake());

    ArticleTranslation::query()->create([
        'article_id' => $this->article->id,
        'locale' => 'hu',
        'title' => 'Kész',
        'excerpt' => null,
        'body' => 'Kész szöveg.',
    ]);

    dispatch_sync(new TranslateArticle($this->article->id, ['de', 'hu'], $this->userId));

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->report->skippedLocales() === ['hu']
        && $e->report->translatedLocales() === ['de']);

    expect($fake->requests)->toHaveCount(1);
});

it('records an attributed Manual revision when it overwrites an empty row with revisions on', function (): void {
    linCodexJobsEnableRevisions();
    Event::fake([ArticleTranslated::class]);
    linCodexJobsBind(linCodexJobsFake());

    ArticleTranslation::query()->create([
        'article_id' => $this->article->id,
        'locale' => 'de',
        'title' => 'Alt',
        'excerpt' => null,
        'body' => '',
    ]);

    dispatch_sync(new TranslateArticle($this->article->id, ['de'], $this->userId));

    $row = ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'de')->first();
    $revisions = ArticleRevision::query()->where('article_id', $this->article->id)->where('locale', 'de')->get();

    expect($row->title)->toBe('Zurücksetzen')
        ->and($row->body)->toBe("# Zurücksetzen\n\nText.")
        ->and($revisions)->toHaveCount(1)
        ->and($revisions[0]->reason)->toBe(RevisionReason::Manual)
        ->and($revisions[0]->user_id)->toBe($this->userId)
        ->and($revisions[0]->title)->toBe('Alt')
        ->and($revisions[0]->body)->toBe('');
});

it('records no revision for a brand-new locale row', function (): void {
    linCodexJobsEnableRevisions();
    Event::fake([ArticleTranslated::class]);
    linCodexJobsBind(linCodexJobsFake());

    dispatch_sync(new TranslateArticle($this->article->id, ['de'], $this->userId));

    expect(ArticleRevision::query()->where('article_id', $this->article->id)->count())->toBe(0)
        ->and(ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'de')->exists())->toBeTrue();
});

it('leaves a failed locale missing with its reason and continues', function (): void {
    Event::fake([ArticleTranslated::class]);

    linCodexJobsBind((new FakeAiClient)
        ->push(new AiCallFailed(AiReason::RATE_LIMITED))
        ->push(new StructuredCompletion(['title' => 'Visszaállítás', 'excerpt' => '', 'body' => "# Visszaállítás\n\nSzöveg."], 30, 40)));

    dispatch_sync(new TranslateArticle($this->article->id, ['de', 'hu'], $this->userId));

    expect(ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'de')->exists())->toBeFalse()
        ->and(ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'hu')->exists())->toBeTrue();

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->report->failedLocales() === ['de' => AiReason::RATE_LIMITED]
        && $e->report->translatedLocales() === ['hu']);
});

it('reports every locale unavailable when AI is off at run time', function (): void {
    Event::fake([ArticleTranslated::class]);
    $fake = linCodexJobsBind(linCodexJobsFake());

    $job = new TranslateArticle($this->article->id, ['de', 'hu'], $this->userId);

    $this->enableAi(['enabled' => false]);

    dispatch_sync($job);

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->report->failedLocales() === [
        'de' => AiReason::UNAVAILABLE,
        'hu' => AiReason::UNAVAILABLE,
    ]);

    expect($fake->requests)->toBe([])
        ->and(ArticleTranslation::query()->where('article_id', $this->article->id)->count())->toBe(1);
});

it('reports a deleted article without throwing', function (): void {
    Event::fake([ArticleTranslated::class]);
    linCodexJobsBind(linCodexJobsFake());

    dispatch_sync(new TranslateArticle(999999, ['de'], $this->userId));

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->articleId === 999999
        && $e->report->failedLocales() === ['de' => AiReason::UNKNOWN]);
});

it('completes inline on the sync driver', function (): void {
    linCodexJobsBind(linCodexJobsFake());

    TranslateArticle::dispatch($this->article->id, ['de'], $this->userId);

    expect(ArticleTranslation::query()->where('article_id', $this->article->id)->where('locale', 'de')->exists())->toBeTrue();
});

it('serializes without a model', function (): void {
    $payload = serialize(new TranslateArticle($this->article->id, ['de'], $this->userId));

    expect($payload)->not->toContain('Models\\Article');

    $job = unserialize($payload);

    expect($job)->toBeInstanceOf(TranslateArticle::class)
        ->and($job->articleId)->toBe($this->article->id)
        ->and($job->locales)->toBe(['de'])
        ->and($job->userId)->toBe($this->userId)
        ->and($job->timeout)->toBe(150);
});

it('reports a throwable the translator raises before recording the failure', function (): void {
    Event::fake([ArticleTranslated::class]);
    Exceptions::fake();
    $fake = linCodexJobsBind(linCodexJobsFake());

    $blank = Article::factory()->withTranslation('en', [
        'title' => '',
        'excerpt' => null,
        'body' => '',
    ])->create();

    dispatch_sync(new TranslateArticle($blank->id, ['de'], $this->userId));

    Exceptions::assertReported(fn (LogicException $e): bool => str_contains($e->getMessage(), 'nothing to translate'));
    Exceptions::assertReportedCount(1);

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->report->failedLocales() === ['de' => AiReason::UNKNOWN]);

    expect($fake->requests)->toBe([])
        ->and(ArticleTranslation::query()->where('article_id', $blank->id)->where('locale', 'de')->exists())->toBeFalse();
});

it('reports an unknown AI failure once, never twice', function (): void {
    Event::fake([ArticleTranslated::class]);
    Exceptions::fake();
    linCodexJobsBind((new FakeAiClient)->push(new RuntimeException('boom')));

    dispatch_sync(new TranslateArticle($this->article->id, ['de'], $this->userId));

    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'boom');
    Exceptions::assertReportedCount(1);

    Event::assertDispatched(ArticleTranslated::class, fn (ArticleTranslated $e): bool => $e->report->failedLocales() === ['de' => AiReason::UNKNOWN]);
});
