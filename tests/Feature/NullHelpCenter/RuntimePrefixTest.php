<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * The contract a host layer stands on: the route table is whatever booted,
 * the links are whatever the config says at call time. A prefix written
 * after the providers registered moves every link — the drawer footer, the
 * help button and the hrefs inside a rendered article — without registering
 * a route, and the rendered HTML is cached once per prefix rather than
 * shared between them.
 *
 * fin-codex sets the prefix per panel this way at panel boot, so these rows
 * are what Phase 16 builds on.
 */
const LIN_CODEX_RUNTIME_PREFIX_DASHBOARD = 'App\Filament\Pages\Dashboard';

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('follows a prefix set after the routes were registered', function (): void {
    expect(ArticlePath::href('users'))->toBe('/users')
        ->and(ArticlePath::helpCenterHref())->toBeNull();

    config()->set('lin-codex.routes.help_center', '/admin/help');

    expect(ArticlePath::href('users'))->toBe('/admin/help/users')
        ->and(ArticlePath::href('users/roles', 'x'))->toBe('/admin/help/users/roles#x')
        ->and(ArticlePath::helpCenterHref())->toBe('http://localhost/admin/help')
        ->and(Route::has('lin-codex.help-center'))->toBeFalse()
        ->and($this->get('/admin/help')->getStatusCode())->toBe(404);
});

it('gives the drawer footer the runtime prefix', function (): void {
    config()->set('lin-codex.routes.help_center', '/admin/help');

    Livewire::test(HelpDrawer::class, ['pageClass' => LIN_CODEX_RUNTIME_PREFIX_DASHBOARD])
        ->assertSeeHtml('codex-drawer__help-center')
        ->assertSeeHtml('href="http://localhost/admin/help"');

    $this->blade('<x-lin-codex::help-button />')
        ->assertSeeHtml('href="http://localhost/admin/help"');
});

it('keeps one rendered copy per prefix', function (): void {
    $markdown = "[Users](users.md)\n";

    $off = $this->freshRenderer();
    $offKey = $off->cacheKey($markdown, ArticleFormat::Markdown, 'en', 'intro');
    $offHtml = $off->render($markdown, ArticleFormat::Markdown, 'en', 'intro')->html;

    config()->set('lin-codex.routes.help_center', '/admin/help');

    $on = $this->freshRenderer();
    $onKey = $on->cacheKey($markdown, ArticleFormat::Markdown, 'en', 'intro');
    $onHtml = $on->render($markdown, ArticleFormat::Markdown, 'en', 'intro')->html;

    expect($onKey)->not->toBe($offKey)
        ->and($offHtml)->toContain('href="/users"')
        ->and($onHtml)->toContain('href="/admin/help/users"')
        ->and(Cache::has($offKey))->toBeTrue()
        ->and(Cache::has($onKey))->toBeTrue();
});
