<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Every "lin-codex::lin-codex.ui.*" key referenced by a package view, unique
 * and sorted. The views are read from disk so a key added to a Blade file
 * without a translation fails here before it echoes back to a reader.
 *
 * @return list<string>
 */
function linCodexLangKeysUsedInViews(): array
{
    $keys = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 3).'/resources/views')->name('*.blade.php') as $file) {
        preg_match_all('/lin-codex::lin-codex\.ui\.([a-z_]+)/', $file->getContents(), $matches);

        $keys = [...$keys, ...$matches[1]];
    }

    $keys = array_values(array_unique($keys));
    sort($keys);

    return $keys;
}

/**
 * The ui group as the views were written against it.
 *
 * @return list<string>
 */
function linCodexLangKeysExpectedUiKeys(): array
{
    return [
        'help', 'title', 'close', 'back', 'this_page', 'also_on_this_page', 'no_help_for_page', 'pick_a_topic',
        'search', 'search_placeholder', 'no_results', 'rate_limited', 'browse', 'on_this_page', 'related', 'not_found',
        'help_center', 'back_to_app', 'toggle_tree', 'open_help_center', 'lightbox_close', 'shortcut_hint',
    ];
}

it('defines every ui key the views use', function (): void {
    $keys = linCodexLangKeysUsedInViews();

    expect($keys)->not->toBe([])
        ->and($keys)->toContain('title', 'search_placeholder', 'rate_limited', 'not_found');

    $undefined = [];

    foreach ($keys as $key) {
        $translated = __('lin-codex::lin-codex.ui.'.$key);

        if (! is_string($translated) || $translated === 'lin-codex::lin-codex.ui.'.$key) {
            $undefined[] = $key;
        }
    }

    expect($undefined)->toBe([]);
});

it('keeps the ui group complete', function (): void {
    $group = trans('lin-codex::lin-codex.ui');

    expect($group)->toBeArray();

    $missing = array_values(array_filter(
        linCodexLangKeysExpectedUiKeys(),
        fn (string $key): bool => ! array_key_exists($key, $group),
    ));

    expect($missing)->toBe([]);
});

it('formats the placeholders', function (): void {
    expect(__('lin-codex::lin-codex.ui.rate_limited', ['seconds' => 7]))->toContain('7')
        ->and(__('lin-codex::lin-codex.ui.back_to_app', ['app' => 'Acme']))->toContain('Acme')
        ->and(__('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => 'ctrl+/']))->toContain('ctrl+/');
});
