<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;

/**
 * @param  list<string>  $codes
 */
function linCodexApiLocaleUseLanguages(array $codes, FallbackBehaviour $fallback): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = 'en';
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * The JSON tree node with the given slug, searched depth first, or null.
 *
 * @param  list<array<string, mixed>>  $nodes
 *
 * @return array<string, mixed>|null
 */
function linCodexApiLocaleFindNode(array $nodes, string $slug): ?array
{
    foreach ($nodes as $node) {
        if ($node['slug'] === $slug) {
            return $node;
        }

        /** @var list<array<string, mixed>> $children */
        $children = $node['children'];
        $found = linCodexApiLocaleFindNode($children, $slug);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/*
 * The docs fixture's billing/invoice-history declares no visibility, so the
 * file source treats it as authenticated-only: the cases that read it sign
 * in first, exactly as ArticleReaderTest and TreeBuilderTest do.
 */
beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('applies the fallback setting to the article endpoint', function (FallbackBehaviour $fallback): void {
    linCodexApiLocaleUseLanguages(['en', 'de'], $fallback);
    $this->actingAs(new GenericUser(['id' => 1]));

    $response = $this->getJson('/codex/api/articles/billing/invoice-history?locale=de');

    if ($fallback === FallbackBehaviour::Hide) {
        expect($response->getStatusCode())->toBe(404);

        $response->assertExactJson(['message' => 'Article not found.']);

        return;
    }

    $response->assertOk();

    expect($response->json('data.slug'))->toBe('billing/invoice-history')
        ->and($response->json('data.locale'))->toBe('en')
        ->and($response->json('data.isFallback'))->toBeTrue()
        ->and($response->json('meta.isFallback'))->toBeTrue()
        ->and($response->json('meta.locale'))->toBe('de')
        ->and($response->json('meta.defaultLocale'))->toBe('en');
})->with(['show default' => [FallbackBehaviour::ShowDefault], 'hide' => [FallbackBehaviour::Hide]]);

it('applies the fallback setting to the tree endpoint', function (FallbackBehaviour $fallback): void {
    linCodexApiLocaleUseLanguages(['en', 'de'], $fallback);
    $this->actingAs(new GenericUser(['id' => 1]));

    /** @var list<array<string, mixed>> $tree */
    $tree = $this->getJson('/codex/api/tree?locale=de')->assertOk()->json('data');

    $invoices = linCodexApiLocaleFindNode($tree, 'billing/invoice-history');
    $intro = linCodexApiLocaleFindNode($tree, 'intro');

    expect($intro)->not->toBeNull()
        ->and($intro['isFallback'] ?? null)->toBeFalse()
        ->and($intro['title'] ?? null)->toBe('Einführung');

    if ($fallback === FallbackBehaviour::Hide) {
        expect($invoices)->toBeNull();

        return;
    }

    expect($invoices)->not->toBeNull()
        ->and($invoices['isFallback'] ?? null)->toBeTrue()
        ->and($invoices['title'] ?? null)->toBe('Invoices');
})->with(['show default' => [FallbackBehaviour::ShowDefault], 'hide' => [FallbackBehaviour::Hide]]);

it('applies the fallback setting to the context endpoint', function (FallbackBehaviour $fallback): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
    $this->forgetSources();

    linCodexApiLocaleUseLanguages(['en', 'de'], $fallback);

    $response = $this->getJson('/codex/api/context?path=/leak/only-en&locale=de')->assertOk();

    expect($response->json('meta.locale'))->toBe('de')
        ->and($response->json('meta.path'))->toBe('/leak/only-en');

    if ($fallback === FallbackBehaviour::Hide) {
        expect($response->json('data'))->toBe([]);

        return;
    }

    expect($response->json('data.*.slug'))->toBe(['only-en'])
        ->and($response->json('data.0.isFallback'))->toBeTrue()
        ->and($response->json('data.0.title'))->toBe('Only in English');
})->with(['show default' => [FallbackBehaviour::ShowDefault], 'hide' => [FallbackBehaviour::Hide]]);

it('applies the fallback setting to the search endpoint', function (FallbackBehaviour $fallback): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
    $this->forgetSources();

    linCodexApiLocaleUseLanguages(['en', 'de'], $fallback);

    $response = $this->getJson('/codex/api/search?'.http_build_query(['q' => 'Only in English', 'locale' => 'de']))->assertOk();

    /** @var list<array<string, mixed>> $hits */
    $hits = $response->json('data');
    $onlyEn = array_values(array_filter($hits, fn (array $hit): bool => $hit['slug'] === 'only-en'));

    expect($response->json('meta.locale'))->toBe('de');

    if ($fallback === FallbackBehaviour::Hide) {
        expect($onlyEn)->toBe([]);

        return;
    }

    expect($onlyEn)->toHaveCount(1)
        ->and($onlyEn[0]['isFallback'])->toBeTrue()
        ->and($onlyEn[0]['title'])->toBe('Only in English');
})->with(['show default' => [FallbackBehaviour::ShowDefault], 'hide' => [FallbackBehaviour::Hide]]);

it('keeps meta.locale as the requested locale and data.locale as the served one', function (): void {
    linCodexApiLocaleUseLanguages(['en', 'de'], FallbackBehaviour::ShowDefault);
    $this->actingAs(new GenericUser(['id' => 1]));

    $fallback = $this->getJson('/codex/api/articles/billing/invoice-history?locale=de')->assertOk();
    $exact = $this->getJson('/codex/api/articles/intro?locale=de')->assertOk();
    $appLocale = $this->getJson('/codex/api/articles/intro')->assertOk();

    expect($fallback->json('meta.locale'))->toBe('de')
        ->and($fallback->json('data.locale'))->toBe('en')
        ->and($fallback->json('data.isFallback'))->toBeTrue()
        ->and($exact->json('meta.locale'))->toBe('de')
        ->and($exact->json('data.locale'))->toBe('de')
        ->and($exact->json('data.isFallback'))->toBeFalse()
        ->and($exact->json('data.title'))->toBe('Einführung')
        ->and($appLocale->json('meta.locale'))->toBe(app()->getLocale())
        ->and($appLocale->json('meta.locale'))->toBe('en')
        ->and($appLocale->json('data.locale'))->toBe('en')
        ->and($appLocale->json('data.isFallback'))->toBeFalse();
});
