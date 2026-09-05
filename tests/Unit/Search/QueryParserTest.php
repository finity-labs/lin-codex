<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Search\ParsedQuery;
use FinityLabs\LinCodex\Search\QueryParser;

function linCodexParse(string $query): ?ParsedQuery
{
    return (new QueryParser)->parse($query);
}

it('returns null below the minimum folded length', function (): void {
    expect(linCodexParse('a'))->toBeNull()
        ->and(linCodexParse('ü'))->toBeNull()
        ->and(linCodexParse('   '))->toBeNull()
        ->and(linCodexParse('日本語'))->toBeNull();

    $parsed = linCodexParse('ab');

    expect($parsed)->not->toBeNull()
        ->and($parsed?->tokens)->toBe(['ab'])
        ->and($parsed?->phrase)->toBe('ab')
        ->and($parsed?->raw)->toBe('ab');
});

it('strips every operator and keeps the raw query', function (): void {
    $parsed = linCodexParse('+foo -bar "x" (y)');

    expect($parsed?->tokens)->toBe(['foo', 'bar', 'x', 'y'])
        ->and($parsed?->phrase)->toBe('foo bar x y')
        ->and($parsed?->raw)->toBe('+foo -bar "x" (y)');
});

it('dedupes tokens in first-occurrence order and keeps the phrase intact', function (): void {
    $parsed = linCodexParse('Pass PASS pass word');

    expect($parsed?->tokens)->toBe(['pass', 'word'])
        ->and($parsed?->phrase)->toBe('pass pass pass word');
});

it('caps the tokens at sixteen', function (): void {
    $words = array_map(static fn (int $i): string => sprintf('t%02d', $i), range(1, 20));

    $parsed = linCodexParse(implode(' ', $words));

    expect(QueryParser::MAX_TOKENS)->toBe(16)
        ->and($parsed?->tokens)->toBe(array_slice($words, 0, 16));
});

it('reads the minimum length from config and measures it on the folded text', function (): void {
    config()->set('lin-codex.search.min_length', 4);

    expect(linCodexParse('abc'))->toBeNull()
        ->and(linCodexParse('abcd'))->not->toBeNull();

    config()->set('lin-codex.search.min_length', 2);

    expect(linCodexParse('Ü.'))->toBeNull()
        ->and(linCodexParse('Üb')?->tokens)->toBe(['ub']);
});

it('routes short and stopword tokens to the LIKE path', function (string $query, bool $expected): void {
    expect(linCodexParse($query)?->needsLike())->toBe($expected);
})->with([
    ['password reset', false],
    ['ui toggle', true],
    ['the password', true],
    ['und', true],
    ['passwort strasse', false],
    ['www example', true],
    ['company', false],
]);

it('is a readonly data object', function (): void {
    $parsed = linCodexParse('password reset');

    linCodexAssertNoModels($parsed);

    expect(unserialize(serialize($parsed)))->toEqual($parsed);
});
