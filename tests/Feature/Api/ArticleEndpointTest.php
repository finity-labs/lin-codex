<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;

/**
 * @param  list<string>  $codes
 */
function linCodexApiArticleUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('returns the locked payload for a public article', function (): void {
    $response = $this->getJson('/codex/api/articles/intro');

    $response->assertOk();

    $json = $response->json();

    expect(array_keys($json['data']))->toBe(['slug', 'title', 'excerpt', 'locale', 'isFallback', 'format', 'html', 'toc', 'breadcrumbs', 'related', 'icon', 'updatedAt'])
        ->and(array_keys($json['meta']))->toBe(['locale', 'defaultLocale', 'isFallback'])
        ->and($json['data']['slug'])->toBe('intro')
        ->and($json['data']['title'])->toBe('Introduction')
        ->and($json['data']['excerpt'])->toBe('What Codex does and where to start.')
        ->and($json['data']['locale'])->toBe('en')
        ->and($json['data']['isFallback'])->toBeFalse()
        ->and($json['data']['format'])->toBe('markdown')
        ->and($json['data']['html'])->toContain('Welcome to the help center')
        ->and($json['data']['toc'])->toBe([])
        ->and($json['data']['breadcrumbs'])->toBe([])
        ->and($json['data']['related'])->toBe([['slug' => 'users', 'title' => 'Users']])
        ->and($json['data']['icon'])->toBe('heroicon-o-book-open')
        ->and($json['data']['updatedAt'])->toBeNull()
        ->and($json['meta'])->toBe(['locale' => 'en', 'defaultLocale' => 'en', 'isFallback' => false]);
});

it('returns rendered html for an html article with breadcrumbs', function (): void {
    $response = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/articles/users/permissions');

    $response->assertOk();

    expect($response->json('data.format'))->toBe('html')
        ->and($response->json('data.breadcrumbs'))->toBe([['slug' => 'users', 'title' => 'Users']])
        ->and($response->json('data.html'))->toContain('<code>users.delete</code>');
});

it('answers 404 for missing, hidden and unpublished articles alike', function (string $slug): void {
    $response = $this->getJson('/codex/api/articles/'.$slug);

    expect($response->getStatusCode())->toBe(404)->not->toBe(403);

    $response->assertExactJson(['message' => 'Article not found.']);
})->with(['does-not-exist', 'users/permissions', 'users/roles']);

it('never exposes the raw source or internals', function (): void {
    $data = $this->getJson('/codex/api/articles/intro')->json('data');

    foreach (['body', 'plainText', 'searchText', 'contexts', 'meta', 'sourcePath', 'keywords', 'visibility', 'published'] as $key) {
        expect($data)->not->toHaveKey($key);
    }
});

it('serves the requested translation when it exists', function (): void {
    linCodexApiArticleUseLanguages(['en', 'de']);

    $response = $this->getJson('/codex/api/articles/intro?locale=de');

    $response->assertOk();

    expect($response->json('data.locale'))->toBe('de')
        ->and($response->json('data.title'))->toBe('Einführung')
        ->and($response->json('data.isFallback'))->toBeFalse()
        ->and($response->json('meta.locale'))->toBe('de')
        ->and($response->json('meta.isFallback'))->toBeFalse();
});

it('falls back to the default language with the flag set', function (): void {
    linCodexApiArticleUseLanguages(['en', 'de']);

    // invoices.md declares no visibility, so it is authenticated-only and has no de file.
    $response = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/articles/billing/invoice-history?locale=de');

    $response->assertOk();

    expect($response->json('data.locale'))->toBe('en')
        ->and($response->json('data.isFallback'))->toBeTrue()
        ->and($response->json('meta.locale'))->toBe('de')
        ->and($response->json('meta.isFallback'))->toBeTrue();
});

it('hides an untranslated article under Hide', function (): void {
    linCodexApiArticleUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

    $response = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/articles/billing/invoice-history?locale=de');

    expect($response->getStatusCode())->toBe(404);

    $response->assertExactJson(['message' => 'Article not found.']);
});

it('treats an unsupported locale as a missing translation, not as an error', function (): void {
    linCodexApiArticleUseLanguages(['en']);

    $response = $this->getJson('/codex/api/articles/intro?locale=xx');

    $response->assertOk();

    expect($response->json('data.locale'))->toBe('en')
        ->and($response->json('data.isFallback'))->toBeTrue()
        ->and($response->json('meta.locale'))->toBe('xx');
});

it('treats a non-string locale as absent', function (): void {
    $response = $this->getJson('/codex/api/articles/intro?locale[]=de');

    $response->assertOk();

    expect($response->json('meta.locale'))->toBe('en')
        ->and($response->json('data.isFallback'))->toBeFalse();
});

it('serves an article that exists only in another language under that language', function (): void {
    linCodexApiArticleUseLanguages(['en', 'de']);

    expect($this->getJson('/codex/api/articles/nur-deutsch')->getStatusCode())->toBe(404);

    $response = $this->getJson('/codex/api/articles/nur-deutsch?locale=de');

    $response->assertOk();

    expect($response->json('data.locale'))->toBe('de');
});

it('carries updatedAt for a database article', function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();

    Article::factory()->public()->published()->state(['slug' => 'stamped'])
        ->withTranslation('en', ['title' => 'Stamped'])
        ->create();

    $response = $this->getJson('/codex/api/articles/stamped');

    $response->assertOk();

    $expected = ArticleTranslation::query()->firstOrFail()->updated_at?->toIso8601String();

    expect($expected)->toBeString()
        ->and($response->json('data.updatedAt'))->toBe($expected)
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});
