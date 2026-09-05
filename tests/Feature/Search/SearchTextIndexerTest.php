<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Search\SearchText;
use FinityLabs\LinCodex\Search\SearchTextIndexer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A public published article with one en translation.
 *
 * @param  array<string, mixed>  $translation
 * @param  array<string, mixed>  $article
 */
function linCodexIndexerArticle(array $translation = [], array $article = []): Article
{
    return Article::factory()
        ->public()
        ->published()
        ->state($article)
        ->withTranslation('en', $translation)
        ->create();
}

/**
 * The search_text column of one translation, read fresh from the database.
 */
function linCodexIndexerText(Article $article, string $locale = 'en'): ?string
{
    $value = ArticleTranslation::query()
        ->where('article_id', $article->id)
        ->where('locale', $locale)
        ->value('search_text');

    return is_string($value) ? $value : null;
}

it('fills search_text from the title, keywords, excerpt and plain body on create', function (): void {
    $article = linCodexIndexerArticle([
        'title' => 'Reset a password',
        'excerpt' => 'How to recover',
        'body' => "# Reset\n\nOpen the **login** page and paste the token.",
    ], ['keywords' => ['credentials', 'login']]);

    expect(SearchText::split(linCodexIndexerText($article)))->toBe([
        'title' => 'reset a password',
        'keywords' => 'credentials login',
        'excerpt' => 'how to recover',
        'body' => 'reset open the login page and paste the token',
    ]);
});

it('folds accents in the title and the body', function (): void {
    $article = linCodexIndexerArticle(['title' => 'Über Straße', 'body' => 'Hőség és űrhajó']);

    $fields = SearchText::split(linCodexIndexerText($article));

    expect($fields['title'])->toBe('uber strasse')
        ->and($fields['body'])->toContain('hoseg es urhajo');
});

it('extracts the plain text of an HTML article without scripts or tags', function (): void {
    $article = Article::factory()
        ->public()
        ->published()
        ->html()
        ->withTranslation('en', ['title' => 'Html', 'body' => '<h2>Alpha</h2><p>Bravo <script>alert(1)</script> charlie</p>'])
        ->create();

    expect(SearchText::split(linCodexIndexerText($article))['body'])->toBe('alpha bravo charlie');
});

it('keeps an explicitly assigned search_text', function (): void {
    $article = linCodexIndexerArticle(['search_text' => 'kept as is']);

    expect(linCodexIndexerText($article))->toBe('kept as is');

    $other = Article::factory()->public()->published()->create();
    $translation = ArticleTranslation::factory()->withSearchText()->create(['article_id' => $other->id]);

    expect(linCodexIndexerText($other))->toBe($translation->search_text)
        ->and($translation->search_text)->not->toStartWith(' ');
});

it('fills a search_text that is set to null on update', function (): void {
    $article = linCodexIndexerArticle(['title' => 'Explicit first', 'search_text' => 'x']);
    $translation = $article->translations()->firstOrFail();

    $translation->update(['search_text' => null]);

    $text = linCodexIndexerText($article);

    expect($text)->not->toBeNull()
        ->and(SearchText::split($text)['title'])->toBe('explicit first');
});

it('recomputes when the title or the body changes and keeps an explicit value on the same save', function (): void {
    $article = linCodexIndexerArticle(['title' => 'Old title', 'body' => 'Old body']);
    $translation = $article->translations()->firstOrFail();

    $translation->update(['title' => 'New title']);

    expect(SearchText::split(linCodexIndexerText($article))['title'])->toBe('new title');

    $translation->update(['body' => 'Fresh body text']);

    expect(SearchText::split(linCodexIndexerText($article))['body'])->toBe('fresh body text');

    $translation->update(['title' => 'A', 'search_text' => 'explicit']);

    expect(linCodexIndexerText($article))->toBe('explicit');
});

it('leaves search_text alone when only the locale changes', function (): void {
    $article = linCodexIndexerArticle(['title' => 'Same']);
    $translation = $article->translations()->firstOrFail();
    $before = linCodexIndexerText($article);

    $translation->update(['locale' => 'de']);

    expect($before)->not->toBeNull()
        ->and(linCodexIndexerText($article, 'de'))->toBe($before);
});

it('re-indexes every translation when the article keywords change', function (): void {
    $article = linCodexIndexerArticle(['title' => 'Keywords'], ['keywords' => ['old']]);

    expect(SearchText::split(linCodexIndexerText($article))['keywords'])->toBe('old');

    $article->update(['keywords' => ['newword']]);

    expect(SearchText::split(linCodexIndexerText($article))['keywords'])->toBe('newword');
});

it('re-indexes when the article format changes and not when only the slug changes', function (): void {
    $article = linCodexIndexerArticle(['title' => 'Format change', 'body' => 'Some body']);
    $translation = $article->translations()->firstOrFail();

    DB::table((new ArticleTranslation)->getTable())->where('id', $translation->id)->update(['search_text' => 'stale']);

    $article->update(['format' => ArticleFormat::Html]);

    $text = linCodexIndexerText($article);

    expect($text)->not->toBe('stale')
        ->and(SearchText::split($text)['title'])->toBe('format change');

    $article->update(['slug' => 'other']);

    expect(linCodexIndexerText($article))->toBe($text);
});

it('indexes a translation whose article has null keywords', function (): void {
    $bare = Article::query()->create(['slug' => 'bare']);
    ArticleTranslation::factory()->create(['article_id' => $bare->id, 'title' => 'Bare title']);

    $fields = SearchText::split(linCodexIndexerText($bare));

    expect($fields['title'])->toBe('bare title')
        ->and($fields['keywords'])->toBe('');
});

it('indexes an unsaved translation without an article and without a query', function (): void {
    $translation = new ArticleTranslation(['locale' => 'en', 'title' => 'T', 'body' => '# T']);
    $plain = app(ArticleRenderer::class)->plainText('# T', ArticleFormat::Markdown, 'en');

    DB::enableQueryLog();
    app(SearchTextIndexer::class)->index($translation);

    expect(DB::getQueryLog())->toBe([])
        ->and($translation->search_text)->toBe(SearchText::compose('T', [], null, $plain));
});

it('warms the render cache under the same slug the reader uses for a section', function (): void {
    Article::factory()->public()->published()->state(['slug' => 'users/roles'])->withTranslation('en', ['title' => 'Roles'])->create();
    Article::factory()->public()->published()->state(['slug' => 'users'])->withTranslation('en', ['title' => 'Users', 'body' => '# Users'])->create();

    expect(Cache::has(app(ArticleRenderer::class)->cacheKey('# Users', ArticleFormat::Markdown, 'en', 'users/index')))->toBeTrue();
});

it('leaves search_text null for rows written with the query builder', function (): void {
    $article = Article::factory()->public()->published()->create();

    DB::table((new ArticleTranslation)->getTable())->insert([
        'article_id' => $article->id,
        'locale' => 'en',
        'title' => 'Raw',
        'body' => 'Raw body',
        'search_text' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(linCodexIndexerText($article))->toBeNull();
});
