<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
});

const LIN_CODEX_MEDIA_URL = '/codex/media/en/images/reset.png';

it('streams a docs folder image with cache headers', function (): void {
    $response = $this->get(LIN_CODEX_MEDIA_URL);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('max-age=86400')->toContain('public');

    expect((string) $response->headers->get('ETag'))->not->toBe('');
    expect((string) $response->headers->get('Last-Modified'))->not->toBe('');

    $base = $response->baseResponse;
    expect($base)->toBeInstanceOf(BinaryFileResponse::class);
    /** @var BinaryFileResponse $base */
    expect($base->getFile()->getRealPath())->toBe(realpath($this->fixtureDocsPath().'/en/images/reset.png'));
    expect($base->getFile()->getSize())->toBe(67);
});

it('serves an image from a nested folder', function (): void {
    $response = $this->get('/codex/media/en/02-users/images/users.png');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
});

it('answers 304 to a conditional request with a matching etag', function (): void {
    $etag = (string) $this->get(LIN_CODEX_MEDIA_URL)->headers->get('ETag');

    expect($etag)->not->toBe('');

    $this->get(LIN_CODEX_MEDIA_URL, ['If-None-Match' => $etag])->assertStatus(304);
});

it('answers 404 for a missing file', function (): void {
    $this->get('/codex/media/en/images/missing.png')->assertNotFound();
});

it('answers 404 for a locale folder that does not exist', function (): void {
    $this->get('/codex/media/xx/images/reset.png')->assertNotFound();
});

it('answers 404 for a non-image extension', function (): void {
    $this->get('/codex/media/en/01-intro.md')->assertNotFound();
});

it('refuses svg even when the path would otherwise match', function (): void {
    $this->get('/codex/media/en/images/reset.svg')->assertNotFound();
});

// The override tree exists on disk next to the docs tree, so a successful
// traversal would serve a real file; both spellings must answer 404. If a
// future Laravel version rejects the encoded form in the router before the
// controller runs, the assertion still holds.
it('refuses plain traversal out of the docs path', function (): void {
    $this->get('/codex/media/en/../../docs-override/en/images/logo.png')->assertNotFound();
});

it('refuses url-encoded traversal out of the docs path', function (): void {
    $this->get('/codex/media/en/..%2F..%2Fdocs-override%2Fen%2Fimages%2Flogo.png')->assertNotFound();
});

it('answers 404 without a locale segment', function (): void {
    $this->get('/codex/media/images/reset.png')->assertNotFound();
});

it('answers 404 when no docs path is configured', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', []);

    $this->get(LIN_CODEX_MEDIA_URL)->assertNotFound();
});

it('answers 404 when the configured docs path does not exist', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', ['/nonexistent/docs']);

    $this->get(LIN_CODEX_MEDIA_URL)->assertNotFound();
});

// The override intro replaces the public intro whole, German file included,
// so with both paths configured reset.png is referenced only by
// authenticated articles and the media gate hides it from guests. Sign in so
// this test sees path precedence, not visibility.
it('serves the later configured path first and falls back to earlier ones', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [
        $this->fixtureDocsPath(),
        $this->fixtureDocsPath('docs-override'),
    ]);

    $this->actingAs(new GenericUser(['id' => 1]));

    $logo = $this->get('/codex/media/en/images/logo.png');
    $logo->assertOk();
    /** @var BinaryFileResponse $logoBase */
    $logoBase = $logo->baseResponse;
    expect($logoBase->getFile()->getRealPath())
        ->toBe(realpath($this->fixtureDocsPath('docs-override').'/en/images/logo.png'));

    $reset = $this->get(LIN_CODEX_MEDIA_URL);
    $reset->assertOk();
    /** @var BinaryFileResponse $resetBase */
    $resetBase = $reset->baseResponse;
    expect($resetBase->getFile()->getRealPath())
        ->toBe(realpath($this->fixtureDocsPath().'/en/images/reset.png'));
});

it('is named lin-codex.media', function (): void {
    expect(route('lin-codex.media', ['locale' => 'en', 'path' => 'images/reset.png']))
        ->toBe('http://localhost/codex/media/en/images/reset.png');
});

it('runs under the configured middleware', function (): void {
    $route = Route::getRoutes()->getByName('lin-codex.media');

    expect($route)->not->toBeNull();
    expect($route?->middleware())->toBe(['web']);
});

// Containment is per docs path, not per locale: the rewriter maps
// "../en/images/reset.png" from a de article to the en URL, and a request
// that traverses between locale folders inside the same docs path is fine.
it('serves a traversal that stays inside the docs path', function (): void {
    $this->get('/codex/media/de/../en/images/reset.png')->assertOk();
});
