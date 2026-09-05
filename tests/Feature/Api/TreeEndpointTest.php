<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;

/**
 * @param  list<string>  $codes
 */
function linCodexApiTreeUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * Every slug in the tree, depth first.
 *
 * @param  list<array<string, mixed>>  $nodes
 *
 * @return list<string>
 */
function linCodexApiTreeSlugs(array $nodes): array
{
    $slugs = [];

    foreach ($nodes as $node) {
        $slugs[] = (string) $node['slug'];

        /** @var list<array<string, mixed>> $children */
        $children = $node['children'];

        foreach (linCodexApiTreeSlugs($children) as $slug) {
            $slugs[] = $slug;
        }
    }

    return $slugs;
}

/**
 * The node with the given slug at any depth, or null.
 *
 * @param  list<array<string, mixed>>  $nodes
 *
 * @return array<string, mixed>|null
 */
function linCodexApiTreeNode(array $nodes, string $slug): ?array
{
    foreach ($nodes as $node) {
        if ($node['slug'] === $slug) {
            return $node;
        }

        /** @var list<array<string, mixed>> $children */
        $children = $node['children'];
        $found = linCodexApiTreeNode($children, $slug);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * Assert every node at every depth carries exactly the locked keys in order.
 *
 * @param  list<array<string, mixed>>  $nodes
 */
function linCodexApiTreeAssertShape(array $nodes): void
{
    foreach ($nodes as $node) {
        expect(array_keys($node))->toBe(['slug', 'title', 'icon', 'isGroup', 'isFallback', 'hasArticle', 'children'], 'node '.$node['slug']);

        /** @var list<array<string, mixed>> $children */
        $children = $node['children'];

        linCodexApiTreeAssertShape($children);
    }
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('returns nodes in the locked shape', function (): void {
    $response = $this->getJson('/codex/api/tree');

    $response->assertOk();

    $json = $response->json();

    expect($json['meta'])->toBe(['locale' => 'en', 'defaultLocale' => 'en']);

    linCodexApiTreeAssertShape($json['data']);

    $intro = linCodexApiTreeNode($json['data'], 'intro');

    expect($intro)->not->toBeNull()
        ->and($intro['title'])->toBe('Introduction')
        ->and($intro['icon'])->toBe('heroicon-o-book-open')
        ->and($intro['isGroup'])->toBeFalse()
        ->and($intro['hasArticle'])->toBeTrue()
        ->and($intro['isFallback'])->toBeFalse()
        ->and($intro['children'])->toBe([]);
});

it('renders a folder without an index file as a group', function (): void {
    // invoices.md declares no visibility, so the billing folder only exists for a signed-in viewer.
    $billing = linCodexApiTreeNode($this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/tree')->json('data'), 'billing');

    expect($billing)->not->toBeNull()
        ->and($billing['isGroup'])->toBeTrue()
        ->and($billing['hasArticle'])->toBeFalse()
        ->and($billing['icon'])->toBeNull()
        ->and($billing['title'])->toBe('Billing')
        ->and(linCodexApiTreeSlugs($billing['children']))->toBe(['billing/invoice-history']);
});

it("applies the viewer's visibility", function (): void {
    $guest = linCodexApiTreeSlugs($this->getJson('/codex/api/tree')->json('data'));

    expect($guest)->toContain('intro', 'users')
        ->not->toContain('users/permissions')
        ->not->toContain('users/roles')
        ->not->toContain('billing/invoice-history');

    $data = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/codex/api/tree')->json('data');
    $users = linCodexApiTreeNode($data, 'users');

    expect(linCodexApiTreeSlugs($data))->toContain('billing/invoice-history')
        ->and($users)->not->toBeNull()
        ->and(linCodexApiTreeSlugs($users['children']))->toContain('users/permissions')
        ->not->toContain('users/roles');
});

it('follows the locale and fallback rules', function (): void {
    linCodexApiTreeUseLanguages(['en', 'de']);

    // Signed in, so the authenticated-only billing/invoice-history (no de file) is in the tree.
    $this->actingAs(new GenericUser(['id' => 1]));

    $response = $this->getJson('/codex/api/tree?locale=de');

    $response->assertOk();

    $data = $response->json('data');
    $intro = linCodexApiTreeNode($data, 'intro');
    $invoices = linCodexApiTreeNode($data, 'billing/invoice-history');

    expect($intro)->not->toBeNull()
        ->and($intro['title'])->toBe('Einführung')
        ->and($intro['isFallback'])->toBeFalse()
        ->and($invoices)->not->toBeNull()
        ->and($invoices['isFallback'])->toBeTrue()
        ->and(linCodexApiTreeSlugs($data))->toContain('nur-deutsch')
        ->and($response->json('meta.locale'))->toBe('de')
        ->and(linCodexApiTreeSlugs($this->getJson('/codex/api/tree')->json('data')))->not->toContain('nur-deutsch');

    linCodexApiTreeUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

    expect(linCodexApiTreeSlugs($this->getJson('/codex/api/tree?locale=de')->json('data')))->not->toContain('billing/invoice-history');
});

it('treats an unsupported locale as fallback', function (): void {
    $response = $this->getJson('/codex/api/tree?locale=xx');

    $response->assertOk();

    $intro = linCodexApiTreeNode($response->json('data'), 'intro');

    expect($intro)->not->toBeNull()
        ->and($intro['isFallback'])->toBeTrue()
        ->and($response->json('meta.locale'))->toBe('xx');
});
