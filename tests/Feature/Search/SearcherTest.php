<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\SearchField;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Search\InMemoryIndex;
use FinityLabs\LinCodex\Search\Searcher;
use FinityLabs\LinCodex\Search\SearchHit;
use FinityLabs\LinCodex\Search\SearchLimiter;
use FinityLabs\LinCodex\Search\SearchResult;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The filesystem source over tests/Fixtures/docs-search.
 */
function linCodexSearcherUseFiles(): void
{
    config()->set('lin-codex.sources.filesystem.paths', [test()->fixtureDocsPath('docs-search')]);
    config()->set('lin-codex.source', 'filesystem');
    test()->forgetSources();
}

/**
 * Database twins of tests/Fixtures/docs-search: the same slugs, states,
 * keywords and words per field in en and de. Published public articles
 * unless stated; parents before children; excerpts pinned because the
 * factory would otherwise invent one.
 */
function linCodexSearcherSeed(): void
{
    Article::factory()->public()->published()->state(['slug' => 'password-reset', 'sort_order' => 0])
        ->withKeywords(['credentials', 'login'])
        ->withTranslation('en', [
            'title' => 'Reset a password',
            'excerpt' => 'How to recover an account when the password was forgotten.',
            'body' => "# Reset a password\n\nOpen the login page and choose *Forgot password*. We send a security token by email; paste it into the form to pick a new password.",
        ])
        ->withTranslation('de', ['title' => 'Passwort zurücksetzen', 'excerpt' => null, 'body' => 'Öffnen Sie die Anmeldeseite und fordern Sie ein neues Passwort an.'])
        ->create();

    Article::factory()->public()->published()->state(['slug' => 'tier-title', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Zephyr overview', 'excerpt' => 'A page used by the ranking tests.', 'body' => 'Nothing else to see on this page.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'tier-keywords', 'sort_order' => 0])
        ->withKeywords(['zephyr'])
        ->withTranslation('en', ['title' => 'Keyword tier', 'excerpt' => null, 'body' => 'This page carries the probe word only as a keyword.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'tier-excerpt', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Excerpt tier', 'excerpt' => 'About the zephyr setting.', 'body' => 'The body says nothing about it.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'tier-body', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Body tier', 'excerpt' => null, 'body' => 'The zephyr option lives in the body only.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'phrase-a', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Profile page', 'excerpt' => null, 'body' => 'You can reset the password from the profile page.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'phrase-b', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Login page', 'excerpt' => null, 'body' => 'Password reset happens from the login page.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'accents', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Árvíztűrő tükörfúrógép', 'excerpt' => 'Hőség és űrhajó', 'body' => 'Hungarian probe words with long accents: űrhajó, hőség.'])->create();

    Article::factory()->authenticated()->published()->state(['slug' => 'internal-notes', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Internal escalation notes', 'excerpt' => null, 'body' => 'How to escalate an incident to the on-call engineer.'])->create();
    Article::factory()->public()->unpublished()->state(['slug' => 'draft-feature', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Draft feature flag', 'excerpt' => null, 'body' => 'Unfinished flag notes.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'short-tokens', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'ID and UI', 'excerpt' => null, 'body' => 'The id field and the ui toggle.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'users', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Users', 'excerpt' => null, 'body' => 'Manage the people who sign in.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'users/roles', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Roles and permissions', 'excerpt' => null, 'body' => 'Assign a role to grant permissions.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'only-english', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Only English glossary', 'excerpt' => null, 'body' => 'Terms that exist in English only.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'billing', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Invoices', 'excerpt' => null, 'body' => 'Download an invoice from the billing page.'])
        ->withTranslation('de', ['title' => 'Rechnungen', 'excerpt' => null, 'body' => 'Eine Rechnung laden Sie auf der Abrechnungsseite herunter.'])
        ->create();

    Article::factory()->public()->published()->state(['slug' => 'umlaute', 'sort_order' => 0])
        ->withTranslation('de', ['title' => 'Über Straße und Grüße', 'excerpt' => null, 'body' => 'Öffentliche Straßen und Plätze.'])->create();
}

function linCodexSearcherUseDatabase(): void
{
    config()->set('lin-codex.source', 'database');
    linCodexSearcherSeed();
    test()->forgetSources();
}

/**
 * @param  list<string>  $codes
 */
function linCodexSearcherUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = 'en';
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * @return list<string> slugs in hit order
 */
function linCodexSearcherSlugs(SearchResult $result): array
{
    return array_map(static fn (SearchHit $hit): string => $hit->slug, $result->hits);
}

/**
 * @return list<array{0: string, 1: string}> slug and snippet per hit
 */
function linCodexSearcherSnippets(SearchResult $result): array
{
    return array_map(static fn (SearchHit $hit): array => [$hit->slug, $hit->snippet], $result->hits);
}

/**
 * The hit for a slug, failing when it is absent.
 */
function linCodexSearcherHit(SearchResult $result, string $slug): SearchHit
{
    foreach ($result->hits as $hit) {
        if ($hit->slug === $slug) {
            return $hit;
        }
    }

    throw new RuntimeException('No hit for '.$slug.' in '.implode(', ', linCodexSearcherSlugs($result)));
}

beforeEach(function (): void {
    linCodexSearcherUseFiles();

    $this->searcher = fn (): Searcher => app(Searcher::class);
    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

describe('shape', function (): void {
    it('returns a title hit with the unmarked excerpt as its snippet', function (): void {
        $result = ($this->searcher)()->search('reset', $this->guest);

        expect($result->query)->toBe('reset')
            ->and($result->rateLimited)->toBeFalse()
            ->and($result->retryAfterSeconds)->toBeNull()
            ->and($result->total)->toBe(count($result->hits))
            ->and($result->hits[0]->slug)->toBe('password-reset')
            ->and($result->hits[0]->title)->toBe('Reset a password')
            ->and($result->hits[0]->matchedField)->toBe(SearchField::Title)
            ->and($result->hits[0]->isFallback)->toBeFalse()
            ->and($result->hits[0]->sectionPath)->toBe([])
            ->and($result->hits[0]->score)->toBeGreaterThan(0)
            ->and($result->hits[0]->snippet)->toBe(e('How to recover an account when the password was forgotten.'))
            ->and($result->hits[0]->snippet)->not->toContain('<mark>');
    });

    it('marks the body, the excerpt and leaves the keyword hit unmarked', function (): void {
        $body = linCodexSearcherHit(($this->searcher)()->search('token', $this->guest), 'password-reset');
        $excerpt = linCodexSearcherHit(($this->searcher)()->search('forgotten', $this->guest), 'password-reset');
        $keywords = linCodexSearcherHit(($this->searcher)()->search('credentials', $this->guest), 'password-reset');

        expect($body->matchedField)->toBe(SearchField::Body)
            ->and($body->snippet)->toContain('<mark>token</mark>')
            ->and(strip_tags($body->snippet, '<mark>'))->toBe($body->snippet)
            ->and($excerpt->matchedField)->toBe(SearchField::Excerpt)
            ->and($excerpt->snippet)->toContain('<mark>forgotten</mark>')
            ->and($keywords->matchedField)->toBe(SearchField::Keywords)
            ->and($keywords->snippet)->toBe(e('How to recover an account when the password was forgotten.'));
    });

    it('carries the ancestor titles as the section path', function (): void {
        $result = ($this->searcher)()->search('roles', $this->guest);

        expect(linCodexSearcherHit($result, 'users/roles')->sectionPath)->toBe(['Users']);

        foreach ($result->hits as $hit) {
            if ($hit->slug === 'users') {
                expect($hit->sectionPath)->toBe([]);
            }
        }
    });

    it('orders the tiers and folds accents on both sides', function (): void {
        expect(linCodexSearcherSlugs(($this->searcher)()->search('zephyr', $this->guest)))
            ->toBe(['tier-title', 'tier-keywords', 'tier-excerpt', 'tier-body'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('arvizturo', $this->guest)))->toBe(['accents'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('Árvíztűrő', $this->guest)))->toBe(['accents']);
    });

    it('applies the locale rule', function (): void {
        linCodexSearcherUseLanguages(['en', 'de']);

        $billing = ($this->searcher)()->search('rechnung', $this->guest, 'de');
        $glossary = ($this->searcher)()->search('glossary', $this->guest, 'de');

        expect(linCodexSearcherSlugs(($this->searcher)()->search('uber', $this->guest, 'de')))->toBe(['umlaute'])
            ->and(linCodexSearcherSlugs($billing))->toBe(['billing'])
            ->and($billing->hits[0]->title)->toBe('Rechnungen')
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('rechnung', $this->guest, 'en')))->toBe([])
            ->and(linCodexSearcherSlugs($glossary))->toBe(['only-english'])
            ->and($glossary->hits[0]->isFallback)->toBeTrue()
            ->and($glossary->hits[0]->title)->toBe('Only English glossary');

        linCodexSearcherUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

        expect(linCodexSearcherSlugs(($this->searcher)()->search('glossary', $this->guest, 'de')))->toBe([]);

        app()->setLocale('de');

        expect(linCodexSearcherSlugs(($this->searcher)()->search('rechnung', $this->guest)))->toBe(['billing']);
    });

    it('scopes by the viewer and the published flag', function (): void {
        expect(linCodexSearcherSlugs(($this->searcher)()->search('escalation', $this->guest)))->toBe([])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('escalation', $this->user)))->toBe(['internal-notes'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('flag', $this->guest)))->toBe([])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('flag', $this->user)))->toBe([]);
    });

    it('answers a query under the minimum length with an empty result that costs nothing', function (): void {
        $key = app(SearchLimiter::class)->keyFor($this->guest);

        foreach (['a', 'ü'] as $query) {
            $result = ($this->searcher)()->search($query, $this->guest);

            expect($result->hits)->toBe([])
                ->and($result->total)->toBe(0)
                ->and($result->rateLimited)->toBeFalse()
                ->and($result->query)->toBe($query)
                ->and(app(RateLimiter::class)->attempts($key))->toBe(0);
        }
    });

    it('returns a throttled result over the limit and recovers after the window', function (): void {
        config()->set('lin-codex.search.rate_limit.guest', 2);

        expect(($this->searcher)()->search('reset', $this->guest)->rateLimited)->toBeFalse()
            ->and(($this->searcher)()->search('reset', $this->guest)->rateLimited)->toBeFalse();

        $throttled = ($this->searcher)()->search('reset', $this->guest);

        expect($throttled->rateLimited)->toBeTrue()
            ->and($throttled->hits)->toBe([])
            ->and($throttled->total)->toBe(0)
            ->and($throttled->query)->toBe('reset')
            ->and($throttled->retryAfterSeconds)->toBeGreaterThanOrEqual(1)
            ->and($throttled->retryAfterSeconds)->toBeLessThanOrEqual(60)
            ->and(($this->searcher)()->search('reset', $this->user)->rateLimited)->toBeFalse();

        $this->travel(61)->seconds();

        expect(($this->searcher)()->search('reset', $this->guest)->rateLimited)->toBeFalse();
    });

    it('caps the hits at the limit', function (): void {
        linCodexSearcherUseDatabase();

        Article::factory()->count(60)->public()->published()
            ->withTranslation('en', ['title' => 'Cap row', 'excerpt' => null, 'body' => 'cap words'])
            ->create();

        $default = ($this->searcher)()->search('cap', $this->guest);

        expect($default->hits)->toHaveCount(10)
            ->and($default->total)->toBe(10)
            ->and(($this->searcher)()->search('cap', $this->guest, null, 3)->hits)->toHaveCount(3)
            ->and(($this->searcher)()->search('cap', $this->guest, null, 99)->hits)->toHaveCount(50)
            ->and(($this->searcher)()->search('cap', $this->guest, null, 0)->hits)->toHaveCount(1);

        config()->set('lin-codex.search.limit', 7);

        expect(($this->searcher)()->search('cap', $this->guest)->hits)->toHaveCount(7);

        config()->set('lin-codex.search.max_limit', 20);

        expect(($this->searcher)()->search('cap', $this->guest, null, 99)->hits)->toHaveCount(20);
    });

    it('reports the effective limit it clamps to', function (): void {
        $searcher = ($this->searcher)();

        expect($searcher->effectiveLimit(null))->toBe(10)
            ->and($searcher->effectiveLimit(3))->toBe(3)
            ->and($searcher->effectiveLimit(99))->toBe(50)
            ->and($searcher->effectiveLimit(0))->toBe(1)
            ->and($searcher->effectiveLimit(-5))->toBe(1);

        config()->set('lin-codex.search.limit', 7);

        expect($searcher->effectiveLimit(null))->toBe(7);

        config()->set('lin-codex.search.max_limit', 20);

        expect($searcher->effectiveLimit(99))->toBe(20);
    });
});

describe('sources agree', function (): void {
    it('answers the same from the database source', function (): void {
        linCodexSearcherUseDatabase();

        $reset = ($this->searcher)()->search('reset', $this->guest);
        $token = linCodexSearcherHit(($this->searcher)()->search('token', $this->guest), 'password-reset');

        expect($reset->hits[0]->slug)->toBe('password-reset')
            ->and($reset->hits[0]->title)->toBe('Reset a password')
            ->and($reset->hits[0]->matchedField)->toBe(SearchField::Title)
            ->and($reset->hits[0]->snippet)->toBe(e('How to recover an account when the password was forgotten.'))
            ->and($token->matchedField)->toBe(SearchField::Body)
            ->and($token->snippet)->toContain('<mark>token</mark>')
            ->and(strip_tags($token->snippet, '<mark>'))->toBe($token->snippet)
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('zephyr', $this->guest)))
            ->toBe(['tier-title', 'tier-keywords', 'tier-excerpt', 'tier-body'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('arvizturo', $this->guest)))->toBe(['accents'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('escalation', $this->guest)))->toBe([])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('escalation', $this->user)))->toBe(['internal-notes'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('flag', $this->guest)))->toBe([])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('flag', $this->user)))->toBe([]);
    });

    it('applies the locale rule on the database source', function (): void {
        linCodexSearcherUseDatabase();
        linCodexSearcherUseLanguages(['en', 'de']);

        $billing = ($this->searcher)()->search('rechnung', $this->guest, 'de');
        $glossary = ($this->searcher)()->search('glossary', $this->guest, 'de');

        expect(linCodexSearcherSlugs(($this->searcher)()->search('uber', $this->guest, 'de')))->toBe(['umlaute'])
            ->and(linCodexSearcherSlugs($billing))->toBe(['billing'])
            ->and($billing->hits[0]->title)->toBe('Rechnungen')
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('rechnung', $this->guest, 'en')))->toBe([])
            ->and(linCodexSearcherSlugs($glossary))->toBe(['only-english'])
            ->and($glossary->hits[0]->isFallback)->toBeTrue()
            ->and($glossary->hits[0]->title)->toBe('Only English glossary');

        linCodexSearcherUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

        expect(linCodexSearcherSlugs(($this->searcher)()->search('glossary', $this->guest, 'de')))->toBe([]);

        app()->setLocale('de');

        expect(linCodexSearcherSlugs(($this->searcher)()->search('rechnung', $this->guest)))->toBe(['billing']);
    });

    it('loads the articles once and pre-filters once per search', function (): void {
        linCodexSearcherUseDatabase();

        DB::enableQueryLog();
        ($this->searcher)()->search('reset', $this->guest);

        $table = DB::connection()->getQueryGrammar()->wrapTable('codex_articles');
        $log = DB::getQueryLog();

        $loads = array_filter($log, fn (array $entry): bool => str_contains($entry['query'], $table) && ! str_contains($entry['query'], 'search_text'));
        $matches = array_filter($log, fn (array $entry): bool => str_contains($entry['query'], 'search_text'));

        expect($loads)->toHaveCount(1);

        if ($this->databaseDriver() === 'sqlite') {
            expect($matches)->toHaveCount(1);
        }
    });

    it('merges the database and the file-only articles on a composite install without duplicates', function (): void {
        config()->set('lin-codex.source', 'composite');

        Article::factory()->public()->published()->state(['slug' => 'password-reset', 'sort_order' => 0])
            ->withTranslation('en', ['title' => 'Database reset article', 'excerpt' => null, 'body' => 'A reset that lives in the database.'])
            ->create();

        $this->forgetSources();

        $reset = ($this->searcher)()->search('reset', $this->guest);

        expect(array_count_values(linCodexSearcherSlugs($reset))['password-reset'] ?? 0)->toBe(1)
            ->and(linCodexSearcherHit($reset, 'password-reset')->title)->toBe('Database reset article')
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('arvizturo', $this->guest)))->toBe(['accents'])
            ->and(linCodexSearcherSlugs(($this->searcher)()->search('reset arvizturo', $this->guest)))->toBe([]);
    });

    it('builds the in-memory index only when a file article can be searched', function (): void {
        linCodexSearcherUseDatabase();
        ($this->searcher)()->search('reset', $this->guest);

        expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeFalse();

        linCodexSearcherUseFiles();
        ($this->searcher)()->search('reset', $this->guest);

        expect(Cache::has(InMemoryIndex::CACHE_KEY))->toBeTrue();
    });

    it('returns the same hits and snippets on SQLite whichever engine is configured', function (): void {
        linCodexSearcherUseDatabase();

        config()->set('lin-codex.search.engine', 'fulltext');
        $fullText = ($this->searcher)()->search('reset token', $this->guest);
        $fullTextTiers = linCodexSearcherSlugs(($this->searcher)()->search('zephyr', $this->guest));

        config()->set('lin-codex.search.engine', 'like');
        $like = ($this->searcher)()->search('reset token', $this->guest);
        $likeTiers = linCodexSearcherSlugs(($this->searcher)()->search('zephyr', $this->guest));

        expect(linCodexSearcherSlugs($fullText))->toBe(['password-reset'])
            ->and(linCodexSearcherSnippets($like))->toBe(linCodexSearcherSnippets($fullText))
            ->and($fullTextTiers)->toBe(['tier-title', 'tier-keywords', 'tier-excerpt', 'tier-body'])
            ->and($likeTiers)->toBe($fullTextTiers);
    })->skip(fn (): bool => $this->databaseDriver() !== 'sqlite', 'sqlite only');

    it('returns the same hits and snippets from the full-text branch and the LIKE branch', function (): void {
        linCodexSearcherUseDatabase();

        config()->set('lin-codex.search.engine', 'fulltext');
        $fullText = ($this->searcher)()->search('reset token', $this->guest);
        $fullTextTiers = linCodexSearcherSlugs(($this->searcher)()->search('zephyr', $this->guest));

        config()->set('lin-codex.search.engine', 'like');
        $like = ($this->searcher)()->search('reset token', $this->guest);
        $likeTiers = linCodexSearcherSlugs(($this->searcher)()->search('zephyr', $this->guest));

        expect(linCodexSearcherSlugs($fullText))->toBe(['password-reset'])
            ->and(linCodexSearcherSnippets($like))->toBe(linCodexSearcherSnippets($fullText))
            ->and($fullTextTiers)->toBe(['tier-title', 'tier-keywords', 'tier-excerpt', 'tier-body'])
            ->and($likeTiers)->toBe($fullTextTiers);

        config()->set('lin-codex.search.engine', 'fulltext');

        expect(linCodexSearcherSlugs(($this->searcher)()->search('ui', $this->guest)))->toBe(['short-tokens']);
    })->skip(fn (): bool => $this->databaseDriver() === 'sqlite', 'needs a full-text engine');
});

describe('result objects', function (): void {
    it('carries no model and survives serialization', function (): void {
        $files = ($this->searcher)()->search('reset', $this->guest);

        linCodexSearcherUseDatabase();

        $database = ($this->searcher)()->search('reset', $this->guest);

        expect($files->hits)->not->toBe([])
            ->and($database->hits)->not->toBe([]);

        linCodexAssertNoModels($files);
        linCodexAssertNoModels($database);

        expect(unserialize(serialize($files)) == $files)->toBeTrue()
            ->and(unserialize(serialize($database)) == $database)->toBeTrue();
    });

    it('is resolved fresh on every make', function (): void {
        expect(app(Searcher::class))->not->toBe(app(Searcher::class));
    });
});
