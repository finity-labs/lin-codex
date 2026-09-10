<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Livewire\HelpDrawer;
use Livewire\Livewire;

/**
 * The drawer footer and <x-lin-codex::help-button /> with the public help
 * center switched off. Neither may name the route: the footer drops its
 * anchor and keeps everything else, the button keeps its whole markup and
 * only its href goes dead.
 *
 * Livewire::test() mounts through a request that matches no context, so the
 * row that wants the docs intro page passes the Dashboard page class the
 * fixture's intro declares.
 */
const LIN_CODEX_NULL_DRAWER_DASHBOARD = 'App\Filament\Pages\Dashboard';

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('renders the drawer without the help center link and with the shortcut hint', function (): void {
    Livewire::test(HelpDrawer::class, ['pageClass' => LIN_CODEX_NULL_DRAWER_DASHBOARD])
        ->assertSeeHtml('codex-drawer__footer')
        ->assertDontSeeHtml('codex-drawer__help-center')
        ->assertDontSee(__('lin-codex::lin-codex.ui.open_help_center'))
        ->assertSee(__('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => 'ctrl+/']));
});

it('renders an empty footer when the shortcut is off too', function (): void {
    config()->set('lin-codex.ui.shortcut', null);

    Livewire::test(HelpDrawer::class)
        ->assertSeeHtml('codex-drawer__footer')
        ->assertDontSeeHtml('codex-drawer__help-center')
        ->assertDontSeeHtml('codex-drawer__shortcut');
});

it('renders the help button with a dead anchor that still opens the drawer', function (): void {
    $this->blade('<x-lin-codex::help-button />')
        ->assertSeeHtml('href="#"')
        ->assertSeeHtml('class="codex-help-button"')
        ->assertSeeHtml('data-codex-help-button')
        ->assertSeeHtml("CustomEvent('codex:open')")
        ->assertSeeHtml('x-on:click.prevent')
        ->assertDontSeeHtml('<button');
});
