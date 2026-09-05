<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Testing\TestResponse;

const LIN_CODEX_GATE_PREFIX = '/codex/media/en/images/';

function linCodexMediaUser(): GenericUser
{
    return new GenericUser(['id' => 1]);
}

/**
 * A hidden image answers exactly like a missing one: 404, never 403.
 */
function linCodexMediaAssertHidden(TestResponse $response): void
{
    $response->assertNotFound();

    expect($response->getStatusCode())->not->toBe(403);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-visibility')]);
    config()->set('lin-codex.source', 'filesystem');

    $this->forgetSources();
});

it('hides an image referenced only by an authenticated article from guests', function (): void {
    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'auth-published.png'));

    $this->actingAs(linCodexMediaUser());

    $this->get(LIN_CODEX_GATE_PREFIX.'auth-published.png')->assertOk();
});

it('serves an image referenced by a public and an authenticated article to everyone', function (): void {
    $this->get(LIN_CODEX_GATE_PREFIX.'shared.png')->assertOk();

    $this->actingAs(linCodexMediaUser());

    $this->get(LIN_CODEX_GATE_PREFIX.'shared.png')->assertOk();
});

it('serves an unreferenced image to a guest', function (): void {
    $this->get(LIN_CODEX_GATE_PREFIX.'unreferenced.png')->assertOk();
});

it('hides an image referenced only by an unpublished article from everyone', function (): void {
    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'public-unpublished.png'));
    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'auth-unpublished.png'));

    $this->actingAs(linCodexMediaUser());

    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'public-unpublished.png'));
    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'auth-unpublished.png'));
});

it('hides the image of a public child of an authenticated section from guests', function (): void {
    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'internal-public-child.png'));

    $this->actingAs(linCodexMediaUser());

    $this->get(LIN_CODEX_GATE_PREFIX.'internal-public-child.png')->assertOk();
});

it('hides the image of a public child of an unpublished section from everyone', function (): void {
    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'draft-child.png'));

    $this->actingAs(linCodexMediaUser());

    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'draft-child.png'));
});

it('gates a traversal that stays inside the docs path by its normalised key', function (): void {
    linCodexMediaAssertHidden($this->get('/codex/media/de/../en/images/auth-published.png'));

    $this->actingAs(linCodexMediaUser());

    $this->get('/codex/media/de/../en/images/auth-published.png')->assertOk();
});

it('reads the owners from database bodies under the database source', function (): void {
    config()->set('lin-codex.source', 'database');
    linCodexLeakSeedDatabase();
    $this->forgetSources();

    linCodexMediaAssertHidden($this->get(LIN_CODEX_GATE_PREFIX.'auth-published.png'));

    $this->actingAs(linCodexMediaUser());

    $this->get(LIN_CODEX_GATE_PREFIX.'auth-published.png')->assertOk();
});

it('still answers 304 to a conditional request through the gate', function (): void {
    $etag = (string) $this->get(LIN_CODEX_GATE_PREFIX.'shared.png')->headers->get('ETag');

    expect($etag)->not->toBe('');

    $this->get(LIN_CODEX_GATE_PREFIX.'shared.png', ['If-None-Match' => $etag])->assertStatus(304);
});
