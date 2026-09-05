<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Sources\SlugPath;

it('derives the parent slug by dropping the last segment', function (): void {
    expect(SlugPath::parentOf('users/roles'))->toBe('users')
        ->and(SlugPath::parentOf('a/b/c'))->toBe('a/b')
        ->and(SlugPath::parentOf('users'))->toBeNull();
});

it('returns the last segment of a slug', function (): void {
    expect(SlugPath::lastSegment('users/roles'))->toBe('roles')
        ->and(SlugPath::lastSegment('intro'))->toBe('intro');
});

it('humanises a segment into a sentence-case label', function (): void {
    expect(SlugPath::humanise('reset-password'))->toBe('Reset password')
        ->and(SlugPath::humanise('no_title'))->toBe('No title')
        ->and(SlugPath::humanise('billing'))->toBe('Billing');
});

it('accepts only kebab-case segments', function (string $segment, bool $valid): void {
    expect(SlugPath::isValidSegment($segment))->toBe($valid);
})->with([
    'kebab' => ['reset-password', true],
    'digit first' => ['2fa', true],
    'upper case' => ['Reset', false],
    'leading dash' => ['-a', false],
    'double dash' => ['a--b', false],
    'empty' => ['', false],
    'slash' => ['a/b', false],
]);
