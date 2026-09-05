<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Assets\StylesheetVersion;
use FinityLabs\LinCodex\LinCodexServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

const LIN_CODEX_STYLESHEET_URL = '/codex/assets/codex.css';

/**
 * The package's prebuilt stylesheet on disk.
 */
function linCodexStylesheetFile(): string
{
    return dirname(__DIR__, 3).'/resources/dist/codex.css';
}

it('serves the stylesheet with long cache headers and a hash version', function (): void {
    $response = $this->get(LIN_CODEX_STYLESHEET_URL.'?v=abc');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->headers->get('Content-Type'))->toStartWith('text/css')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('max-age=31536000', 'public', 'immutable')
        ->and((string) $response->headers->get('ETag'))->not->toBe('');

    $base = $response->baseResponse;

    expect($base)->toBeInstanceOf(BinaryFileResponse::class);

    /** @var BinaryFileResponse $base */
    expect($base->getFile()->getRealPath())->toBe(realpath(linCodexStylesheetFile()))
        ->and(File::get($base->getFile()->getPathname()))->toContain('--codex-drawer-width');
});

it('answers 304 on a matching etag', function (): void {
    $etag = (string) $this->get(LIN_CODEX_STYLESHEET_URL)->headers->get('ETag');

    expect($etag)->not->toBe('')
        ->and($this->get(LIN_CODEX_STYLESHEET_URL, ['If-None-Match' => $etag])->getStatusCode())->toBe(304);
});

it('registers the asset route without the middleware group', function (): void {
    $route = Route::getRoutes()->getByName('lin-codex.assets.css');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('codex/assets/codex.css')
        ->and($route?->middleware())->toBe([]);
});

it('reports a stable hash for the file', function (): void {
    $version = app(StylesheetVersion::class);

    expect($version->hash())->toBe(hash_file('xxh128', linCodexStylesheetFile()))
        ->and($version->hash())->toBe($version->hash())
        ->and(realpath($version->path()))->toBe(realpath(linCodexStylesheetFile()));
});

it('registers the help center routes', function (): void {
    $index = Route::getRoutes()->getByName('lin-codex.help-center');
    $article = Route::getRoutes()->getByName('lin-codex.help-center.article');

    expect($index?->uri())->toBe('help')
        ->and($index?->middleware())->toBe(['web'])
        ->and($article?->uri())->toBe('help/{slug}')
        ->and($article?->wheres['slug'] ?? null)->toBe('.+')
        ->and($article?->middleware())->toBe(['web'])
        ->and(route('lin-codex.help-center'))->toBe('http://localhost/help')
        ->and(route('lin-codex.help-center.article', ['slug' => 'users/roles']))->toBe('http://localhost/help/users/roles');
});

it('publishes resources/dist under the lin-codex-assets tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(LinCodexServiceProvider::class, 'lin-codex-assets');

    expect($paths)->toHaveCount(1);

    $from = (string) array_key_first($paths);

    expect(realpath($from))->toBe(realpath(dirname(__DIR__, 3).'/resources/dist'))
        ->and($paths[$from])->toEndWith('vendor/lin-codex')
        ->and(ServiceProvider::publishableGroups())->toContain('lin-codex-assets');

    $hadVendor = File::isDirectory(public_path('vendor'));
    File::deleteDirectory(public_path('vendor/lin-codex'));

    try {
        Artisan::call('vendor:publish', ['--tag' => 'lin-codex-assets', '--force' => true]);

        expect(File::exists(public_path('vendor/lin-codex/codex.css')))->toBeTrue()
            ->and(File::get(public_path('vendor/lin-codex/codex.css')))->toBe(File::get(linCodexStylesheetFile()));
    } finally {
        File::deleteDirectory(public_path('vendor/lin-codex'));

        if (! $hadVendor) {
            File::deleteDirectory(public_path('vendor'));
        }
    }
});
