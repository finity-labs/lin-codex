<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Search\IndexedDocument;
use FinityLabs\LinCodex\Search\InMemoryCandidates;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Search\QueryParser;
use FinityLabs\LinCodex\Search\Ranker;
use FinityLabs\LinCodex\Search\ScoredCandidate;
use FinityLabs\LinCodex\Search\SearchText;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * @param  list<string>  $codes
 */
function linCodexMemoryUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * Parse, gate, find and rank exactly as the searcher will, over the source
 * the current config binds.
 *
 * @return list<ScoredCandidate>
 */
function linCodexMemoryFind(string $query, Viewer $viewer, string $locale = 'en', bool $fileOnly = false): array
{
    $parsed = app(QueryParser::class)->parse($query);

    expect($parsed)->not->toBeNull();

    $all = app(ContentSource::class)->all();
    $visible = app(ArticleGate::class)->filter($all, $viewer);

    return app(Ranker::class)->rank(app(InMemoryCandidates::class)->find($locale, $all, $visible, $fileOnly), $parsed);
}

/**
 * @param  list<ScoredCandidate>  $scored
 *
 * @return list<string> slugs in rank order
 */
function linCodexMemorySlugs(array $scored): array
{
    return array_map(static fn (ScoredCandidate $scored): string => $scored->candidate->article->slug, $scored);
}

function linCodexMemoryDocument(string $slug, string $title): SearchDocument
{
    return new SearchDocument($slug, 'en', $title, null, '', Visibility::Public, true);
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-search')]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();

    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

describe('fields', function (): void {
    it('matches the title tier first and the body probes after it', function (): void {
        $slugs = linCodexMemorySlugs(linCodexMemoryFind('reset', $this->guest));

        expect($slugs[0])->toBe('password-reset')
            ->and($slugs)->toHaveCount(3)
            ->and(array_slice($slugs, 1))->toEqualCanonicalizing(['phrase-a', 'phrase-b']);
    });

    it('matches a keyword, an excerpt word and a body word on their own', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('credentials', $this->guest)))->toBe(['password-reset'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('forgotten', $this->guest)))->toBe(['password-reset'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('token', $this->guest)))->toBe(['password-reset']);
    });

    it('folds every field of the candidate from the real file', function (): void {
        $scored = linCodexMemoryFind('credentials', $this->guest);
        $plain = app(ContentSource::class)->all()['password-reset']->translation('en')->searchText;

        expect($scored)->toHaveCount(1)
            ->and($scored[0]->candidate->fields)->toBe([
                'title' => 'reset a password',
                'keywords' => 'credentials login',
                'excerpt' => 'how to recover an account when the password was forgotten',
                'body' => SearchText::fold((string) $plain),
            ]);
    });

    it('requires every token and matches word starts only', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('reset token', $this->guest)))->toBe(['password-reset'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('reset nothing', $this->guest)))->toBe([])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('pass', $this->guest)))->toContain('password-reset')
            ->and(linCodexMemorySlugs(linCodexMemoryFind('assword', $this->guest)))->toBe([]);
    });
});

describe('folding', function (): void {
    it('folds accented titles, excerpts and bodies read from the files', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('arvizturo', $this->guest)))->toBe(['accents'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('hoseg', $this->guest)))->toBe(['accents'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('Árvíztűrő', $this->guest)))->toBe(['accents']);
    });

    it('folds German umlauts and sharp s the same way on both sides', function (): void {
        linCodexMemoryUseLanguages(['en', 'de']);

        expect(linCodexMemorySlugs(linCodexMemoryFind('uber', $this->guest, 'de')))->toBe(['umlaute'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('strasse', $this->guest, 'de')))->toBe(['umlaute'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('grusse', $this->guest, 'de')))->toBe(['umlaute'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('uber', $this->guest, 'en')))->toBe([]);
    });
});

describe('ranking', function (): void {
    it('orders the tiers title, keywords, excerpt, body', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('zephyr', $this->guest)))
            ->toBe(['tier-title', 'tier-keywords', 'tier-excerpt', 'tier-body']);
    });

    it('ranks a contiguous phrase above the same words apart within a tier', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('password reset', $this->guest)))
            ->toBe(['password-reset', 'phrase-b', 'phrase-a']);
    });
});

describe('visibility', function (): void {
    it('applies the gate map: authenticated articles only for a user, unpublished for nobody', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('escalation', $this->guest)))->toBe([])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('escalation', $this->user)))->toBe(['internal-notes'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('flag', $this->guest)))->toBe([])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('flag', $this->user)))->toBe([])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('roles', $this->guest)))->toBe(['users/roles']);
    });
});

describe('locale rule', function (): void {
    beforeEach(function (): void {
        linCodexMemoryUseLanguages(['en', 'de']);
    });

    it('searches the translation the locale rule picks', function (): void {
        $scored = linCodexMemoryFind('rechnung', $this->guest, 'de');

        expect(linCodexMemorySlugs($scored))->toBe(['billing'])
            ->and($scored[0]->candidate->choice->translation->locale)->toBe('de')
            ->and($scored[0]->candidate->choice->isFallback)->toBeFalse()
            ->and(linCodexMemorySlugs(linCodexMemoryFind('rechnung', $this->guest, 'en')))->toBe([])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('invoice', $this->guest, 'de')))->toBe([]);
    });

    it('falls back to the default translation under show-default and hides it under hide', function (): void {
        $scored = linCodexMemoryFind('glossary', $this->guest, 'de');

        expect(linCodexMemorySlugs($scored))->toBe(['only-english'])
            ->and($scored[0]->candidate->choice->isFallback)->toBeTrue();

        linCodexMemoryUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

        expect(linCodexMemorySlugs(linCodexMemoryFind('glossary', $this->guest, 'de')))->toBe([]);
    });

    it('matches the exact translation when it exists', function (): void {
        $scored = linCodexMemoryFind('passwort', $this->guest, 'de');

        expect(linCodexMemorySlugs($scored))->toBe(['password-reset'])
            ->and($scored[0]->candidate->choice->isFallback)->toBeFalse();
    });
});

describe('index cache', function (): void {
    it('caches the folded documents under one key with a content hash and reuses them across instances', function (): void {
        linCodexMemoryFind('reset', $this->guest);

        expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeTrue();

        $first = Cache::get(InMemoryIndex::CACHE_KEY)['hash'];

        expect($first)->toMatch('/^[0-9a-f]{32}$/');

        linCodexMemoryFind('reset', $this->guest);

        expect(Cache::get(InMemoryIndex::CACHE_KEY)['hash'])->toBe($first);
    });

    it('serves the cached documents without a rebuild while the hash matches', function (): void {
        linCodexMemoryFind('reset', $this->guest);

        $entry = Cache::get(InMemoryIndex::CACHE_KEY);

        foreach ($entry['documents'] as $position => $document) {
            if ($document->slug === 'password-reset' && $document->locale === 'en') {
                $entry['documents'][$position] = new IndexedDocument('password-reset', 'en', null, 'plantedword', '', '', '');
            }
        }

        Cache::forever(InMemoryIndex::CACHE_KEY, $entry);

        expect(linCodexMemorySlugs(linCodexMemoryFind('plantedword', $this->guest)))->toBe(['password-reset'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('reset', $this->guest)))->not->toContain('password-reset');
    });

    it('ignores a corrupt cache entry and replaces it', function (): void {
        Cache::forever(InMemoryIndex::CACHE_KEY, 'garbage');

        expect(linCodexMemorySlugs(linCodexMemoryFind('reset', $this->guest))[0])->toBe('password-reset')
            ->and(Cache::get(InMemoryIndex::CACHE_KEY))->toBeArray();
    });

    it('hashes the documents with the folding version', function (): void {
        $a = [linCodexMemoryDocument('a', 'Alpha'), linCodexMemoryDocument('b', 'Beta')];
        $same = [linCodexMemoryDocument('a', 'Alpha'), linCodexMemoryDocument('b', 'Beta')];
        $changed = [linCodexMemoryDocument('a', 'Alpha'), linCodexMemoryDocument('b', 'Gamma')];

        expect(InMemoryIndex::hashFor([]))->toMatch('/^[0-9a-f]{32}$/')
            ->and(InMemoryIndex::hashFor($a))->toBe(InMemoryIndex::hashFor($same))
            ->and(InMemoryIndex::hashFor($a))->not->toBe(InMemoryIndex::hashFor($changed))
            ->and(InMemoryIndex::hashFor($a))->toBe(hash('xxh128', SearchText::VERSION.'|'.serialize($a)));
    });

    it('holds no models and survives serialization', function (): void {
        $scored = linCodexMemoryFind('reset', $this->guest);
        $entry = Cache::get(InMemoryIndex::CACHE_KEY);

        linCodexAssertNoModels($scored);
        linCodexAssertNoModels($entry['documents']);

        expect(unserialize(serialize($entry)))->toEqual($entry);
    });
});

describe('freshness', function (): void {
    beforeEach(function (): void {
        $this->tmp = sys_get_temp_dir().'/lin-codex-search-'.uniqid();
        File::copyDirectory($this->fixtureDocsPath('docs-search'), $this->tmp);
        clearstatcache(true);

        config()->set('lin-codex.sources.filesystem.paths', [$this->tmp]);
        $this->forgetSources();
    });

    afterEach(function (): void {
        File::deleteDirectory($this->tmp);
    });

    it('rebuilds the index when a file changes', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('probeword', $this->guest)))->toBe([]);

        $first = Cache::get(InMemoryIndex::CACHE_KEY)['hash'];
        $file = $this->tmp.'/en/tier-body.md';

        file_put_contents($file, "\n\nA brand new probeword.", FILE_APPEND);
        touch($file, (int) filemtime($file) + 5);
        clearstatcache(true);

        expect(linCodexMemorySlugs(linCodexMemoryFind('probeword', $this->guest)))->toBe(['tier-body'])
            ->and(Cache::get(InMemoryIndex::CACHE_KEY)['hash'])->not->toBe($first);
    });
});

describe('composite', function (): void {
    beforeEach(function (): void {
        config()->set('lin-codex.source', 'composite');

        Article::factory()->public()->published()
            ->state(['slug' => 'password-reset'])
            ->withTranslation('en', ['title' => 'Database reset', 'body' => 'db'])
            ->create();

        $this->forgetSources();
    });

    it('leaves a slug the database owns to the database path when file-only', function (): void {
        $slugs = linCodexMemorySlugs(linCodexMemoryFind('reset', $this->guest, 'en', true));

        expect($slugs)->toEqualCanonicalizing(['phrase-a', 'phrase-b'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('arvizturo', $this->guest, 'en', true)))->toBe(['accents'])
            ->and(linCodexMemorySlugs(linCodexMemoryFind('database', $this->guest, 'en', true)))->toBe([]);
    });

    it('indexes the database article from its translation data when not file-only', function (): void {
        expect(linCodexMemorySlugs(linCodexMemoryFind('database', $this->guest)))->toBe(['password-reset']);
    });
});
