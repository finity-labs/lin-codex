<?php

/**
 * Guards the dependency constraints locked in Phase 1.
 *
 * The strings are pinned exactly so that a `composer require` that rewrites
 * the spelling, or a loosening of a constraint, fails the suite.
 */
function linCodexComposerJson(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('pins the locked runtime constraint for :dataset', function (string $package, string $constraint) {
    expect(linCodexComposerJson()['require'][$package] ?? null)->toBe($constraint);
})->with([
    'php' => ['php', '^8.2'],
    'illuminate/contracts' => ['illuminate/contracts', '^11.0|^12.0|^13.0'],
    'league/commonmark' => ['league/commonmark', '^2.10'],
    'spatie/laravel-package-tools' => ['spatie/laravel-package-tools', '^1.92'],
    'spatie/laravel-settings' => ['spatie/laravel-settings', '^3.7|^4.0'],
    'symfony/html-sanitizer' => ['symfony/html-sanitizer', '^7.1|^8.0'],
    'symfony/yaml' => ['symfony/yaml', '^7.0|^8.0'],
]);

it('pins the locked dev constraint for :dataset', function (string $package, string $constraint) {
    expect(linCodexComposerJson()['require-dev'][$package] ?? null)->toBe($constraint);
})->with([
    'larastan/larastan' => ['larastan/larastan', '^3.0'],
    'orchestra/testbench' => ['orchestra/testbench', '^9.0|^10.0|^11.0'],
    'pestphp/pest' => ['pestphp/pest', '^3.0|^4.0'],
    'pestphp/pest-plugin-laravel' => ['pestphp/pest-plugin-laravel', '^3.0|^4.0'],
]);

it('keeps dev-main aliased to 0.2.x-dev so fin-codex can require ^0.2 through a path repository', function () {
    expect(linCodexComposerJson()['extra']['branch-alias']['dev-main'] ?? null)->toBe('0.2.x-dev');
});
