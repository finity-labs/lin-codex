<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\SearchField;
use FinityLabs\LinCodex\Search\Matcher;
use FinityLabs\LinCodex\Search\ParsedQuery;
use FinityLabs\LinCodex\Search\QueryParser;
use FinityLabs\LinCodex\Search\SearchText;

/**
 * @return array{title: string, keywords: string, excerpt: string, body: string}
 */
function linCodexMatchFields(string $title = '', string $keywords = '', string $excerpt = '', string $body = ''): array
{
    return [
        'title' => SearchText::fold($title),
        'keywords' => SearchText::fold($keywords),
        'excerpt' => SearchText::fold($excerpt),
        'body' => SearchText::fold($body),
    ];
}

function linCodexMatchQuery(string $query): ParsedQuery
{
    return (new QueryParser)->parse($query) ?? throw new RuntimeException('query too short: '.$query);
}

it('matches a token at a word start and names the field', function (): void {
    $match = (new Matcher)->matches(linCodexMatchQuery('pass'), linCodexMatchFields(title: 'Reset a password'));

    expect($match)->not->toBeNull()
        ->and($match?->hits)->toBe(['title' => 1, 'keywords' => 0, 'excerpt' => 0, 'body' => 0])
        ->and($match?->matchedField)->toBe(SearchField::Title);
});

it('never matches inside a word', function (): void {
    $matcher = new Matcher;

    expect($matcher->matches(linCodexMatchQuery('assword'), linCodexMatchFields(title: 'Reset a password')))->toBeNull()
        ->and($matcher->matches(linCodexMatchQuery('pass'), linCodexMatchFields(body: 'a compass')))->toBeNull();
});

it('requires every token to hit some field', function (): void {
    $matcher = new Matcher;
    $fields = linCodexMatchFields(title: 'Reset a password', body: 'paste the token');

    $match = $matcher->matches(linCodexMatchQuery('reset token'), $fields);

    expect($match?->hits)->toBe(['title' => 1, 'keywords' => 0, 'excerpt' => 0, 'body' => 1])
        ->and($match?->matchedField)->toBe(SearchField::Title)
        ->and($matcher->matches(linCodexMatchQuery('reset missing'), $fields))->toBeNull();
});

it('counts distinct token hits per field', function (): void {
    $matcher = new Matcher;

    $title = $matcher->matches(linCodexMatchQuery('reset password'), linCodexMatchFields(title: 'Reset a password'));
    $spread = $matcher->matches(linCodexMatchQuery('reset password'), linCodexMatchFields(excerpt: 'reset', body: 'password'));

    expect($title?->hits['title'])->toBe(2)
        ->and($title?->matchedField)->toBe(SearchField::Title)
        ->and($spread?->hits)->toBe(['title' => 0, 'keywords' => 0, 'excerpt' => 1, 'body' => 1])
        ->and($spread?->matchedField)->toBe(SearchField::Excerpt);
});

it('prefers the first field holding every token', function (): void {
    $matcher = new Matcher;

    $body = $matcher->matches(linCodexMatchQuery('token'), linCodexMatchFields(excerpt: 'no', body: 'the token'));
    $keywords = $matcher->matches(linCodexMatchQuery('zephyr'), linCodexMatchFields(keywords: 'zephyr', body: 'zephyr too'));

    expect($body?->matchedField)->toBe(SearchField::Body)
        ->and($keywords?->matchedField)->toBe(SearchField::Keywords);
});

it('detects the contiguous phrase inside one field', function (): void {
    $matcher = new Matcher;
    $query = linCodexMatchQuery('password reset');

    expect($matcher->matches($query, linCodexMatchFields(body: 'Password reset happens here'))?->phrase)->toBeTrue()
        ->and($matcher->matches($query, linCodexMatchFields(body: 'reset the password'))?->phrase)->toBeFalse()
        ->and($matcher->matches($query, linCodexMatchFields(title: 'password', body: 'reset'))?->phrase)->toBeFalse();
});

it('counts occurrences beyond the first hit', function (): void {
    $matcher = new Matcher;

    $single = $matcher->matches(linCodexMatchQuery('token'), linCodexMatchFields(body: 'token here, token there, token everywhere'));
    $double = $matcher->matches(linCodexMatchQuery('token form'), linCodexMatchFields(body: 'token form token form'));

    expect($single?->occurrences)->toBe(2)
        ->and($double?->occurrences)->toBe(2);
});

it('matches folded input accent-insensitively', function (): void {
    $matcher = new Matcher;
    $fields = linCodexMatchFields(title: 'Über Straße');

    expect($matcher->matches(linCodexMatchQuery('uber'), $fields))->not->toBeNull()
        ->and($matcher->matches(linCodexMatchQuery('strasse'), $fields))->not->toBeNull();
});
