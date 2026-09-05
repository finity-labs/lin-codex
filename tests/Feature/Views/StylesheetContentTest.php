<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * The package's prebuilt stylesheet, read from disk.
 */
function linCodexStylesheetContentCss(): string
{
    return File::get(dirname(__DIR__, 3).'/resources/dist/codex.css');
}

/**
 * The stylesheet with every block comment removed.
 */
function linCodexStylesheetContentWithoutComments(string $css): string
{
    return (string) preg_replace('~/\*.*?\*/~s', '', $css);
}

/**
 * Every unique class name selected in the stylesheet, sorted; comments and quoted strings are ignored.
 *
 * @return list<string>
 */
function linCodexStylesheetContentClasses(string $css): array
{
    $stripped = (string) preg_replace('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/', '', linCodexStylesheetContentWithoutComments($css));

    preg_match_all('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $stripped, $matches);

    $classes = array_values(array_unique($matches[1]));
    sort($classes);

    return $classes;
}

/**
 * The light token block on .codex-root (optionally shared with .codex-help-button).
 */
function linCodexStylesheetContentLightBlock(string $css): string
{
    preg_match('/^\.codex-root(?:,\n\.codex-help-button)? \{\n(.*?)\n\}/ms', $css, $match);

    return $match[1] ?? '';
}

/**
 * The .dark ancestor token block.
 */
function linCodexStylesheetContentDarkBlock(string $css): string
{
    preg_match('/^\.dark \.codex-root(?:,\n\.dark \.codex-help-button)? \{\n(.*?)\n\}/ms', $css, $match);

    return $match[1] ?? '';
}

/**
 * The prefers-color-scheme: dark media block, including its inner rule.
 */
function linCodexStylesheetContentMediaBlock(string $css): string
{
    preg_match('/^@media \(prefers-color-scheme: dark\) \{\n(.*?)\n\}/ms', $css, $match);

    return $match[1] ?? '';
}

/**
 * The stylesheet with comments and the three token blocks removed: nothing left may carry a raw colour.
 */
function linCodexStylesheetContentWithoutTokenBlocks(string $css): string
{
    $css = linCodexStylesheetContentWithoutComments($css);

    foreach ([
        '/^\.codex-root(?:,\n\.codex-help-button)? \{\n.*?\n\}/ms',
        '/^\.dark \.codex-root(?:,\n\.dark \.codex-help-button)? \{\n.*?\n\}/ms',
        '/^@media \(prefers-color-scheme: dark\) \{\n.*?\n\}/ms',
    ] as $pattern) {
        $css = (string) preg_replace($pattern, '', $css, 1);
    }

    return $css;
}

it('prefixes every class selector with codex-', function (): void {
    $offenders = array_values(array_filter(
        linCodexStylesheetContentClasses(linCodexStylesheetContentCss()),
        static fn (string $class): bool => ! str_starts_with($class, 'codex-') && $class !== 'dark' && $class !== 'light',
    ));

    expect($offenders)->toBe([]);
});

it('defines every token on the root and redefines the dark set', function (): void {
    $css = linCodexStylesheetContentCss();
    $light = linCodexStylesheetContentLightBlock($css);

    expect($light)->not->toBe('');

    $tokens = [
        '--codex-bg', '--codex-fg', '--codex-muted', '--codex-border', '--codex-surface',
        '--codex-accent: var(--primary, #2563eb)', '--codex-accent-fg',
        '--codex-radius', '--codex-space', '--codex-drawer-width: 480px',
        '--codex-font', '--codex-mono', '--codex-shadow', '--codex-overlay',
    ];

    $missing = array_values(array_filter($tokens, static fn (string $token): bool => ! str_contains($light, $token.(str_contains($token, ':') ? '' : ':'))));

    expect($missing)->toBe([]);

    $dark = linCodexStylesheetContentDarkBlock($css);
    $media = linCodexStylesheetContentMediaBlock($css);

    expect($dark)->not->toBe('')
        ->and($media)->toContain('.codex-root:not(.light *)');

    $darkSet = ['--codex-bg:', '--codex-fg:', '--codex-muted:', '--codex-border:', '--codex-surface:', '--codex-shadow:', '--codex-overlay:'];

    $missingDark = array_values(array_filter($darkSet, static fn (string $token): bool => ! str_contains($dark, $token)));
    $missingMedia = array_values(array_filter($darkSet, static fn (string $token): bool => ! str_contains($media, $token)));

    expect($missingDark)->toBe([])
        ->and($missingMedia)->toBe([]);
});

it('uses tokens instead of raw colours outside the token blocks', function (): void {
    $rules = linCodexStylesheetContentWithoutTokenBlocks(linCodexStylesheetContentCss());

    expect($rules)->not->toBe('');

    preg_match_all('/#[0-9a-f]{3,8}\b|rgb\(/i', $rules, $matches);

    expect($matches[0])->toBe([]);
});

it('carries the drawer, button, help center and content selectors', function (): void {
    $css = linCodexStylesheetContentWithoutComments(linCodexStylesheetContentCss());

    $selectors = [
        '.codex-drawer__panel', '.codex-drawer__overlay', '.codex-drawer__body', '.codex-search__input',
        '.codex-search-hit__snippet mark', '.codex-tree__article--active', '.codex-toc', '.codex-lightbox',
        '.codex-help-button--floating', '.codex-help-button__badge',
        '.codex-help-center__layout', '.codex-help-center__toggle',
        '.codex-callout--note', '.codex-callout--tip', '.codex-callout--important', '.codex-callout--warning', '.codex-callout--caution',
        '.codex-steps', '.codex-step__number', '.codex-details', '.codex-figure', '.codex-anchor', '.codex-external', '.codex-table',
        '[data-codex-lightbox]', '[x-cloak]',
    ];

    $missing = array_values(array_filter($selectors, static fn (string $selector): bool => ! str_contains($css, $selector)));

    expect($missing)->toBe([]);
});

it('holds the locked breakpoints', function (): void {
    $css = linCodexStylesheetContentCss();

    expect($css)->toMatch('/@media \(max-width: ?639(\.98)?px\)/')
        ->toMatch('/@media \(max-width: ?767(\.98)?px\)/')
        ->toMatch('/@media \(max-width: ?1023(\.98)?px\)/');
});

it('loads nothing external', function (): void {
    $css = linCodexStylesheetContentCss();

    expect($css)->not->toContain('@import')
        ->not->toContain('url(http')
        ->not->toContain('url(//');
});

it('ships the file', function (): void {
    $attributes = File::get(dirname(__DIR__, 3).'/.gitattributes');

    expect(preg_match('/^\/?resources\b.*export-ignore/m', $attributes))->toBe(0);
});
