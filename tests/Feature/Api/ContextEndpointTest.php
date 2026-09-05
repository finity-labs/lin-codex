<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;

/**
 * @param  list<string>  $codes
 */
function linCodexApiContextUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * The slugs of a context response's data, in order.
 *
 * @param  list<array<string, mixed>>  $data
 *
 * @return list<string>
 */
function linCodexApiContextSlugs(array $data): array
{
    return array_values(array_map(static fn (array $entry): string => (string) $entry['slug'], $data));
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it("resolves the page's articles from route and path and echoes the context", function (): void {
    $response = $this->getJson('/codex/api/context?route=dashboard&path=/dashboard');

    $response->assertOk();

    expect($response->json('data'))->toBe([
        ['slug' => 'intro', 'title' => 'Introduction', 'excerpt' => 'What Codex does and where to start.', 'isFallback' => false],
    ])->and($response->json('meta'))->toBe([
        'locale' => 'en',
        'defaultLocale' => 'en',
        'route' => 'dashboard',
        'path' => '/dashboard',
        'class' => null,
        'panel' => null,
    ]);
});

it('matches url wildcards and normalises the path', function (): void {
    $response = $this->getJson('/codex/api/context?path=/welcome/hello/');

    $response->assertOk();

    expect(linCodexApiContextSlugs($response->json('data')))->toBe(['intro'])
        ->and($response->json('meta.path'))->toBe('/welcome/hello')
        ->and($this->getJson('/codex/api/context?path=welcome/hello')->json('meta.path'))->toBe('/welcome/hello');
});

it('matches a page class with a panel and strips the leading backslash', function (): void {
    $query = http_build_query(['class' => '\\App\\Filament\\Pages\\Dashboard', 'panel' => 'admin', 'path' => '/x']);
    $response = $this->getJson('/codex/api/context?'.$query);

    $response->assertOk();

    expect(linCodexApiContextSlugs($response->json('data')))->toBe(['intro'])
        ->and($response->json('meta.class'))->toBe('App\Filament\Pages\Dashboard')
        ->and($response->json('meta.panel'))->toBe('admin');
});

it('resolves the root path for a bare request and never the api route itself', function (): void {
    $response = $this->getJson('/codex/api/context');

    $response->assertOk();

    expect($response->json('meta.route'))->toBeNull()->not->toBe('lin-codex.api.context')
        ->and($response->json('meta.path'))->toBe('/')
        ->and($response->json('meta.class'))->toBeNull()
        ->and($response->json('meta.panel'))->toBeNull()
        ->and(linCodexApiContextSlugs($response->json('data')))->toBe(['intro']);
});

it('returns an empty list for a page without articles', function (): void {
    $response = $this->getJson('/codex/api/context?path=/nowhere&route=nothing.here');

    $response->assertOk();

    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.route'))->toBe('nothing.here')
        ->and($response->json('meta.path'))->toBe('/nowhere');
});

it('ignores non-string parameters', function (): void {
    $response = $this->getJson('/codex/api/context?panel[]=x&route[]=y&path=/');

    $response->assertOk();

    expect($response->json('meta.panel'))->toBeNull()
        ->and($response->json('meta.route'))->toBeNull();
});

it("applies the viewer's visibility", function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
    $this->forgetSources();

    // Guest requests first: actingAs() keeps the viewer signed in for the rest of the test.
    expect($this->getJson('/codex/api/context?path=/leak/auth-published')->json('data'))->toBe([])
        ->and(linCodexApiContextSlugs($this->getJson('/codex/api/context?path=/leak/public-published')->json('data')))->toBe(['public-published']);

    $user = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/context?path=/leak/auth-published');

    expect(linCodexApiContextSlugs($user->json('data')))->toBe(['auth-published']);
});

it('follows the locale rule', function (): void {
    linCodexApiContextUseLanguages(['en', 'de']);

    $german = $this->getJson('/codex/api/context?path=/&locale=de');

    $german->assertOk();

    expect($german->json('data'))->toBe([
        ['slug' => 'intro', 'title' => 'Einführung', 'excerpt' => 'Was Codex macht und wo man anfängt.', 'isFallback' => false],
    ])->and($german->json('meta.locale'))->toBe('de');

    $unsupported = $this->getJson('/codex/api/context?path=/&locale=xx');

    expect($unsupported->json('data.0.title'))->toBe('Introduction')
        ->and($unsupported->json('data.0.isFallback'))->toBeTrue()
        ->and($unsupported->json('meta.locale'))->toBe('xx');

    linCodexApiContextUseLanguages(['en', 'de'], FallbackBehaviour::Hide);
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
    $this->forgetSources();

    expect($this->getJson('/codex/api/context?path=/leak/only-en&locale=de')->json('data'))->toBe([]);
});
