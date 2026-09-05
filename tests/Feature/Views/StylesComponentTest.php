<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Assets\StylesheetVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

/**
 * The published copy the styles component prefers when it exists.
 */
function linCodexStylesPublishedFile(): string
{
    return public_path('vendor/lin-codex/codex.css');
}

it('links the served stylesheet with a hash version', function (): void {
    $this->blade('<x-lin-codex::styles />')
        ->assertSeeHtml('<link rel="stylesheet" href="http://localhost/codex/assets/codex.css?v='.app(StylesheetVersion::class)->hash().'"');
});

it('prefers the published copy when it exists', function (): void {
    $hadVendor = File::isDirectory(public_path('vendor'));

    try {
        File::ensureDirectoryExists(dirname(linCodexStylesPublishedFile()));
        File::put(linCodexStylesPublishedFile(), '.codex-root{}');

        $view = $this->blade('<x-lin-codex::styles />');

        $view->assertSeeHtml('href="http://localhost/vendor/lin-codex/codex.css?v='.app(StylesheetVersion::class)->hash().'"')
            ->assertDontSeeHtml('/codex/assets/');
    } finally {
        File::deleteDirectory(public_path('vendor/lin-codex'));

        if (! $hadVendor) {
            File::deleteDirectory(public_path('vendor'));
        }
    }
});

// routes.assets is read at boot like the other prefixes; a custom prefix
// would need its own TestCase (see CustomApiPrefixTestCase), which one
// route does not justify.
it('builds the route href with the version', function (): void {
    expect(route('lin-codex.assets.css', ['v' => 'x']))->toBe('http://localhost/codex/assets/codex.css?v=x');
});

it('renders the package layout with the slot and title', function (): void {
    $appName = (string) config('app.name');

    $html = view('lin-codex::layouts.help-center', [
        'slot' => new HtmlString('<p id="slot-marker">body</p>'),
        'title' => 'Help center',
    ])->render();

    expect(strtolower($html))->toContain('<!doctype html>');
    expect($html)->toContain(
        'slot-marker',
        '<title>Help center</title>',
        'class="codex-help-center-body"',
        $appName,
        'href="http://localhost"',
        'Back to '.$appName,
        '/codex/assets/codex.css?v=',
    );
});
