<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\SearchField;
use FinityLabs\LinCodex\Search\SearchHit;
use FinityLabs\LinCodex\Search\SearchResult;

function linCodexResultHit(): SearchHit
{
    return new SearchHit('users/roles', 'Roles', ['Users'], 'snippet', SearchField::Body, 10, false);
}

it('builds an empty result', function (): void {
    $result = SearchResult::empty('q');

    expect($result->hits)->toBe([])
        ->and($result->query)->toBe('q')
        ->and($result->total)->toBe(0)
        ->and($result->rateLimited)->toBeFalse()
        ->and($result->retryAfterSeconds)->toBeNull();
});

it('builds a throttled result', function (): void {
    $result = SearchResult::throttled('q', 42);

    expect($result->hits)->toBe([])
        ->and($result->query)->toBe('q')
        ->and($result->total)->toBe(0)
        ->and($result->rateLimited)->toBeTrue()
        ->and($result->retryAfterSeconds)->toBe(42);
});

it('carries hits as readonly serialisable data', function (): void {
    $hit = linCodexResultHit();
    $result = new SearchResult([$hit], 'roles', 1, false, null);

    linCodexAssertNoModels($result);

    expect(unserialize(serialize($result)))->toEqual($result)
        ->and($result->hits[0]->slug)->toBe('users/roles')
        ->and($result->hits[0]->title)->toBe('Roles')
        ->and($result->hits[0]->sectionPath)->toBe(['Users'])
        ->and($result->hits[0]->snippet)->toBe('snippet')
        ->and($hit->matchedField->key())->toBe('body')
        ->and($result->hits[0]->score)->toBe(10)
        ->and($result->hits[0]->isFallback)->toBeFalse();
});

it('is readonly', function (): void {
    expect((new ReflectionClass(SearchResult::class))->isReadOnly())->toBeTrue()
        ->and((new ReflectionClass(SearchHit::class))->isReadOnly())->toBeTrue();
});
