<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Locale\TranslationChoice;
use FinityLabs\LinCodex\Search\Candidate;
use FinityLabs\LinCodex\Search\Matcher;
use FinityLabs\LinCodex\Search\ParsedQuery;
use FinityLabs\LinCodex\Search\QueryParser;
use FinityLabs\LinCodex\Search\Ranker;
use FinityLabs\LinCodex\Search\ScoredCandidate;
use FinityLabs\LinCodex\Search\SearchText;

function linCodexRankCandidate(string $slug, string $title, string $keywords = '', string $excerpt = '', string $body = ''): Candidate
{
    $translation = new TranslationData('en', $title, $excerpt === '' ? null : $excerpt, $body, $body);

    $article = new ArticleData(
        slug: $slug,
        parentSlug: null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: $keywords === '' ? [] : explode(' ', $keywords),
        translations: ['en' => $translation],
    );

    return new Candidate($article, new TranslationChoice($translation, false), [
        'title' => SearchText::fold($title),
        'keywords' => SearchText::fold($keywords),
        'excerpt' => SearchText::fold($excerpt),
        'body' => SearchText::fold($body),
    ]);
}

/**
 * @param  list<ScoredCandidate>  $scored
 *
 * @return list<string>
 */
function linCodexRankSlugs(array $scored): array
{
    return array_map(static fn (ScoredCandidate $entry): string => $entry->candidate->article->slug, $scored);
}

function linCodexRankQuery(string $query): ParsedQuery
{
    return (new QueryParser)->parse($query) ?? throw new RuntimeException('query too short: '.$query);
}

it('ranks title over keywords over excerpt over body', function (): void {
    $ranker = new Ranker(new Matcher);

    $scored = $ranker->rank([
        linCodexRankCandidate('tier-body', 'Plain', body: 'zephyr'),
        linCodexRankCandidate('tier-excerpt', 'Plain', excerpt: 'zephyr'),
        linCodexRankCandidate('tier-keywords', 'Plain', keywords: 'zephyr'),
        linCodexRankCandidate('tier-title', 'Zephyr'),
    ], linCodexRankQuery('zephyr'));

    expect(linCodexRankSlugs($scored))->toBe(['tier-title', 'tier-keywords', 'tier-excerpt', 'tier-body']);
});

it('rewards the contiguous phrase without crossing a tier', function (): void {
    $ranker = new Ranker(new Matcher);
    $query = linCodexRankQuery('password reset');

    $scored = $ranker->rank([
        linCodexRankCandidate('scattered', 'Plain', body: 'reset the password'),
        linCodexRankCandidate('phrase', 'Plain', body: 'Password reset happens'),
        linCodexRankCandidate('excerpt', 'Plain', excerpt: 'reset the password'),
    ], $query);

    expect(linCodexRankSlugs($scored))->toBe(['excerpt', 'phrase', 'scattered']);
});

it('rewards more occurrences within one tier', function (): void {
    $ranker = new Ranker(new Matcher);

    $scored = $ranker->rank([
        linCodexRankCandidate('once', 'Plain', body: 'token'),
        linCodexRankCandidate('thrice', 'Plain', body: 'token token token'),
    ], linCodexRankQuery('token'));

    expect(linCodexRankSlugs($scored))->toBe(['thrice', 'once']);
});

it('breaks ties by folded title then slug', function (): void {
    $ranker = new Ranker(new Matcher);

    $scored = $ranker->rank([
        linCodexRankCandidate('b', 'Beta', body: 'token'),
        linCodexRankCandidate('a2', 'alpha', body: 'token'),
        linCodexRankCandidate('a1', 'Alpha', body: 'token'),
    ], linCodexRankQuery('token'));

    expect(linCodexRankSlugs($scored))->toBe(['a1', 'a2', 'b']);
});

it('drops non-matching candidates and handles an empty list', function (): void {
    $ranker = new Ranker(new Matcher);
    $query = linCodexRankQuery('token');

    $scored = $ranker->rank([
        linCodexRankCandidate('miss', 'Nothing', body: 'nothing here'),
        linCodexRankCandidate('hit', 'Plain', body: 'token'),
    ], $query);

    expect(linCodexRankSlugs($scored))->toBe(['hit'])
        ->and($ranker->rank([], $query))->toBe([]);
});

it('scores each tier strictly above the next even with sixteen body tokens', function (): void {
    $ranker = new Ranker(new Matcher);
    $single = linCodexRankQuery('zephyr');

    $score = static fn (Candidate $candidate, ParsedQuery $query) => $ranker->rank([$candidate], $query)[0]->score;

    $title = $score(linCodexRankCandidate('t', 'Zephyr'), $single);
    $keywords = $score(linCodexRankCandidate('k', 'Plain', keywords: 'zephyr'), $single);
    $excerpt = $score(linCodexRankCandidate('e', 'Plain', excerpt: 'zephyr'), $single);
    $body = $score(linCodexRankCandidate('b', 'Plain', body: 'zephyr'), $single);

    $words = array_map(static fn (int $i): string => sprintf('word%02d', $i), range(1, 16));
    $sixteen = linCodexRankQuery(implode(' ', $words));
    $heavyBody = $score(
        linCodexRankCandidate('heavy', 'Plain', body: implode(' ', $words).' '.implode(' ', $words).' '.implode(' ', $words)),
        $sixteen,
    );
    $oneExcerpt = $score(linCodexRankCandidate('light', 'Plain', excerpt: 'word01', body: implode(' ', array_slice($words, 1))), $sixteen);

    expect($title)->toBeGreaterThan($keywords)
        ->and($keywords)->toBeGreaterThan($excerpt)
        ->and($excerpt)->toBeGreaterThan($body)
        ->and($heavyBody)->toBeLessThan($oneExcerpt);
});

it('produces readonly serialisable results', function (): void {
    $scored = (new Ranker(new Matcher))->rank([
        linCodexRankCandidate('hit', 'Plain', body: 'token'),
    ], linCodexRankQuery('token'));

    linCodexAssertNoModels($scored);

    expect(unserialize(serialize($scored)))->toEqual($scored);
});
