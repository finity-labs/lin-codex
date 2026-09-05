<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Livewire\Livewire;

/**
 * The heading contract of the drawer: codex:open carries {slug, heading},
 * the deep link is ?codex=slug#heading, and the Alpine glue scrolls the
 * drawer body to the heading id the renderer wrote once Livewire has
 * rendered the article. The scroll itself runs in a browser; here the
 * markup is checked for the wiring and for the ids it targets.
 *
 * The docs-ui switch and the Dashboard mount are written out here rather
 * than through HelpDrawerTest.php's global helpers, so this file also runs
 * on its own.
 */
beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('wires the heading into the open event and the deep link', function (): void {
    Livewire::test(HelpDrawer::class)
        ->assertSeeHtml('x-on:codex:open.window="openFrom($event)"')
        ->assertSeeHtml('detail.heading')
        ->assertSeeHtml('window.location.hash')
        ->assertSeeHtml('scrollIntoView')
        ->assertSeeHtml('codex-article__body')
        ->assertSeeHtml('CSS.escape')
        ->assertDontSeeHtml('window.location.pathname + window.location.hash');
});

it('renders the heading ids the scroll targets', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-ui')]);

    app()->forgetInstance(FilesystemSource::class);
    app()->forgetInstance(DatabaseSource::class);
    app()->forgetInstance(CompositeSource::class);
    app()->forgetInstance(ContentSource::class);

    Livewire::test(HelpDrawer::class)
        ->call('open', 'guide')
        ->assertSeeHtml('id="second-step"')
        ->assertSeeHtml('href="#second-step"');
});

it('still wires the trigger and the shortcut after the change', function (): void {
    Livewire::test(HelpDrawer::class, ['pageClass' => 'App\\Filament\\Pages\\Dashboard'])
        ->assertSeeHtml('x-data="codexDrawer(')
        ->assertSeeHtml('x-on:keydown.window=');
});
