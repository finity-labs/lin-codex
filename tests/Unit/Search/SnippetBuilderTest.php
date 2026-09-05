<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Search\ParsedQuery;
use FinityLabs\LinCodex\Search\QueryParser;
use FinityLabs\LinCodex\Search\SnippetBuilder;

function linCodexSnippetQuery(string $query): ParsedQuery
{
    return (new QueryParser)->parse($query) ?? throw new RuntimeException('query too short: '.$query);
}

function linCodexSnippetPlain(string $snippet): string
{
    return html_entity_decode(strip_tags($snippet), ENT_QUOTES, 'UTF-8');
}

function linCodexSnippetWords(int $count): string
{
    return implode(' ', array_map(static fn (int $i): string => sprintf('w%02d', $i), range(1, $count)));
}

it('marks the matched word and leaves a short text uncut', function (): void {
    $snippet = (new SnippetBuilder)->build(
        linCodexSnippetQuery('token'),
        'Open the login page and paste the security token into the form.',
        160,
    );

    expect($snippet)->toBe('Open the login page and paste the security <mark>token</mark> into the form.');
});

it('marks only the typed prefix and keeps the original case', function (): void {
    expect((new SnippetBuilder)->build(linCodexSnippetQuery('pass'), 'Password reset', 160))
        ->toBe('<mark>Pass</mark>word reset');
});

it('marks every occurrence of every token', function (): void {
    $snippet = (new SnippetBuilder)->build(linCodexSnippetQuery('token form'), 'token here, token there, on the form', 160);

    expect(substr_count($snippet, '<mark>token</mark>'))->toBe(2)
        ->and(substr_count($snippet, '<mark>form</mark>'))->toBe(1);
});

it('lets the longest matching token win for one word', function (): void {
    expect((new SnippetBuilder)->build(linCodexSnippetQuery('pass password'), 'password', 160))
        ->toBe('<mark>password</mark>');
});

it('escapes everything except the mark tags', function (): void {
    $snippet = (new SnippetBuilder)->build(
        linCodexSnippetQuery('token'),
        'Use <script>alert(1)</script> & "quotes" before the token',
        160,
    );

    expect($snippet)->toContain('&lt;script&gt;')
        ->and($snippet)->toContain('&amp;')
        ->and($snippet)->toContain('&quot;quotes&quot;')
        ->and($snippet)->toContain('<mark>token</mark>')
        ->and(strip_tags($snippet, '<mark>'))->toBe($snippet);
});

it('cuts a window around the first match at word boundaries', function (): void {
    $snippet = (new SnippetBuilder)->build(linCodexSnippetQuery('w40'), linCodexSnippetWords(80), 160);
    $plain = linCodexSnippetPlain($snippet);

    expect($snippet)->toStartWith('…')
        ->and($snippet)->toEndWith('…')
        ->and($snippet)->toContain('<mark>w40</mark>')
        ->and(strlen($plain))->toBeLessThanOrEqual(160 + 6)
        ->and(strlen($plain))->toBeGreaterThanOrEqual(100)
        ->and(mb_check_encoding($snippet, 'UTF-8'))->toBeTrue();

    foreach (preg_split('/\s+/', trim($plain, '… ')) ?: [] as $word) {
        expect($word)->toMatch('/^w\d\d$/');
    }
});

it('omits the leading ellipsis near the start and the trailing one near the end', function (): void {
    $builder = new SnippetBuilder;
    $text = linCodexSnippetWords(80);

    $early = $builder->build(linCodexSnippetQuery('w02'), $text, 160);
    $late = $builder->build(linCodexSnippetQuery('w79'), $text, 160);

    expect($early)->not->toStartWith('…')
        ->and($early)->toEndWith('…')
        ->and($early)->toContain('<mark>w02</mark>')
        ->and($late)->toStartWith('…')
        ->and($late)->not->toEndWith('…')
        ->and($late)->toContain('<mark>w79</mark>');
});

it('maps the prefix onto multibyte characters without splitting them', function (): void {
    $builder = new SnippetBuilder;

    $sharp = $builder->build(linCodexSnippetQuery('stras'), 'Große Straße hier', 160);
    $grosse = $builder->build(linCodexSnippetQuery('grosse'), 'Große Straße', 160);
    $aero = $builder->build(linCodexSnippetQuery('aero'), 'Ærø island', 160);

    expect($sharp)->toBe('Große <mark>Straß</mark>e hier')
        ->and($grosse)->toBe('<mark>Große</mark> Straße')
        ->and($aero)->toBe('<mark>Ærø</mark> island')
        ->and(mb_check_encoding($sharp, 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($grosse, 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($aero, 'UTF-8'))->toBeTrue();
});

it('matches accent-insensitively on the original text', function (): void {
    expect((new SnippetBuilder)->build(linCodexSnippetQuery('uber'), 'Über uns', 160))
        ->toBe('<mark>Über</mark> uns');
});

it('returns the start of the text when nothing matches', function (): void {
    $builder = new SnippetBuilder;
    $long = linCodexSnippetWords(80);

    $short = $builder->build(linCodexSnippetQuery('zzz'), 'Short text', 160);
    $cut = $builder->build(linCodexSnippetQuery('zzz'), $long, 160);

    expect($short)->toBe('Short text')
        ->and($cut)->toStartWith('w01 w02')
        ->and($cut)->toEndWith('…')
        ->and($cut)->not->toContain('<mark>')
        ->and(strlen(linCodexSnippetPlain($cut)))->toBeLessThanOrEqual(163)
        ->and(strlen(linCodexSnippetPlain($cut)))->toBeGreaterThanOrEqual(140);
});

it('returns an empty string for empty text', function (): void {
    expect((new SnippetBuilder)->build(linCodexSnippetQuery('a b'), '', 160))->toBe('');
});

it('reads the default length from config', function (): void {
    config()->set('lin-codex.search.snippet_length', 20);

    $snippet = (new SnippetBuilder)->build(linCodexSnippetQuery('zzz'), linCodexSnippetWords(80));

    expect(strlen($snippet))->toBeLessThanOrEqual(26)
        ->and($snippet)->toEndWith('…');
});
