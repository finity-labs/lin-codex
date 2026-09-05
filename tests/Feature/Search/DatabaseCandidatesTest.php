<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\SearchStrategy;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Search\CandidateSet;
use FinityLabs\LinCodex\Search\DatabaseCandidates;
use FinityLabs\LinCodex\Search\QueryParser;
use FinityLabs\LinCodex\Search\SearchText;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;

/**
 * Published public articles unless stated; parents before children.
 */
function linCodexDbSearchSeed(): void
{
    Article::factory()->public()->published()->state(['slug' => 'password-reset', 'sort_order' => 0])
        ->withKeywords(['credentials', 'login'])
        ->withTranslation('en', [
            'title' => 'Reset a password',
            'excerpt' => 'How to recover an account when the password was forgotten.',
            'body' => "# Reset a password\n\nOpen the login page and paste the security token into the form.",
        ])
        ->withTranslation('de', ['title' => 'Passwort zurücksetzen', 'excerpt' => null, 'body' => 'Öffnen Sie die Anmeldeseite.'])
        ->create();

    Article::factory()->public()->published()->state(['slug' => 'tier-title', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Zephyr overview', 'excerpt' => null, 'body' => 'Nothing else.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'tier-keywords', 'sort_order' => 0])
        ->withKeywords(['zephyr'])
        ->withTranslation('en', ['title' => 'Keyword tier', 'excerpt' => null, 'body' => 'Only a keyword.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'tier-excerpt', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Excerpt tier', 'excerpt' => 'About the zephyr setting.', 'body' => 'Body says nothing.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'tier-body', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Body tier', 'excerpt' => null, 'body' => 'The zephyr option lives here.'])->create();

    Article::factory()->authenticated()->published()->state(['slug' => 'internal', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Internal', 'excerpt' => null, 'body' => 'Escalation root.'])->create();
    Article::factory()->public()->published()->state(['slug' => 'internal/pub', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Public child', 'excerpt' => null, 'body' => 'Escalation child.'])->create();
    Article::factory()->authenticated()->published()->state(['slug' => 'notes', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Internal escalation notes', 'excerpt' => null, 'body' => 'Escalate to on-call.'])->create();
    Article::factory()->public()->unpublished()->state(['slug' => 'draft', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Draft feature flag', 'excerpt' => null, 'body' => 'flag'])->create();

    Article::factory()->public()->published()->state(['slug' => 'billing', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Invoices', 'excerpt' => null, 'body' => 'Download an invoice.'])
        ->withTranslation('de', ['title' => 'Rechnungen', 'excerpt' => null, 'body' => 'Eine Rechnung laden.'])
        ->create();
    Article::factory()->public()->published()->state(['slug' => 'only-english', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Only English glossary', 'excerpt' => null, 'body' => 'English only.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'short', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'ID and UI', 'excerpt' => null, 'body' => 'The id field and the ui toggle, und so weiter.'])->create();

    Article::factory()->public()->published()->state(['slug' => 'longword', 'sort_order' => 0])
        ->withTranslation('en', ['title' => 'Long word', 'excerpt' => null, 'body' => 'prefix '.str_repeat('q', 90).' suffix'])->create();
}

function linCodexDbSearchFind(string $query, Viewer $viewer, string $locale = 'en'): CandidateSet
{
    $parsed = app(QueryParser::class)->parse($query);

    expect($parsed)->not->toBeNull();

    $all = app(ContentSource::class)->all();
    $visible = app(ArticleGate::class)->filter($all, $viewer);

    return app(DatabaseCandidates::class)->find($parsed, $viewer, $locale, $visible);
}

/**
 * @return list<string>
 */
function linCodexDbSearchSlugs(CandidateSet $set): array
{
    $slugs = array_map(fn ($candidate): string => $candidate->article->slug, $set->candidates);
    sort($slugs);

    return $slugs;
}

/**
 * @param  list<string>  $codes
 */
function linCodexDbSearchUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = 'en';
    $settings->fallback = $fallback;
    $settings->save();
}

/**
 * The logged queries whose SQL mentions the search_text column.
 *
 * @return list<array{query: string, bindings: array<int, mixed>}>
 */
function linCodexDbSearchLoggedMatches(): array
{
    return array_values(array_filter(
        DB::getQueryLog(),
        fn (array $entry): bool => str_contains($entry['query'], 'search_text'),
    ));
}

beforeEach(function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();
    linCodexDbSearchSeed();

    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

it('finds a token in the title, the keywords, the excerpt or the body', function (): void {
    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('reset', $this->guest)))->toBe(['password-reset'])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('zephyr', $this->guest)))->toBe(['tier-body', 'tier-excerpt', 'tier-keywords', 'tier-title'])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('token', $this->guest)))->toBe(['password-reset'])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('credentials', $this->guest)))->toBe(['password-reset'])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('forgotten', $this->guest)))->toBe(['password-reset']);
});

it('searches the folded text of the requested locale only', function (): void {
    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('uber', $this->guest)))->toBe([]);

    linCodexDbSearchUseLanguages(['en', 'de']);

    $set = linCodexDbSearchFind('passwort', $this->guest, 'de');

    expect(linCodexDbSearchSlugs($set))->toBe(['password-reset'])
        ->and($set->candidates[0]->choice->translation->locale)->toBe('de')
        ->and($set->candidates[0]->choice->isFallback)->toBeFalse();
});

it('builds every candidate from the search_text column and never from a model', function (): void {
    linCodexDbSearchUseLanguages(['en', 'de']);

    $translations = (new ArticleTranslation)->getTable();
    $articles = (new Article)->getTable();

    foreach ([linCodexDbSearchFind('zephyr', $this->guest), linCodexDbSearchFind('passwort', $this->guest, 'de')] as $set) {
        expect($set->candidates)->not->toBe([]);

        foreach ($set->candidates as $candidate) {
            $column = DB::table($translations)
                ->join($articles, $articles.'.id', '=', $translations.'.article_id')
                ->where($articles.'.slug', $candidate->article->slug)
                ->where($translations.'.locale', $candidate->choice->translation->locale)
                ->value($translations.'.search_text');

            expect($candidate->article->id)->not->toBeNull()
                ->and($candidate->fields)->toBe(SearchText::split(is_string($column) ? $column : null));
        }

        linCodexAssertNoModels($set);
    }
});

it('scopes by the gate-filtered map and the published flag', function (): void {
    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('escalation', $this->guest)))->toBe([])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('escalation', $this->user)))->toBe(['internal', 'internal/pub', 'notes'])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('flag', $this->guest)))->toBe([])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('flag', $this->user)))->toBe([]);
});

it('puts the published, visibility and locale clauses before the match clause', function (): void {
    DB::enableQueryLog();
    linCodexDbSearchFind('reset', $this->guest);

    $queries = linCodexDbSearchLoggedMatches();

    expect($queries)->not->toBe([]);

    $sql = $queries[0]['query'];

    expect(strpos($sql, 'is_published'))->toBeLessThan((int) strpos($sql, 'search_text'))
        ->and(strpos($sql, 'visibility'))->toBeLessThan((int) strpos($sql, 'search_text'))
        ->and(strpos($sql, 'locale'))->toBeLessThan((int) strpos($sql, 'search_text'));

    DB::flushQueryLog();
    linCodexDbSearchFind('reset', $this->user);

    $sql = linCodexDbSearchLoggedMatches()[0]['query'];

    expect($sql)->toContain('is_published')
        ->and($sql)->toContain('locale')
        ->and($sql)->not->toContain('visibility');
});

it('runs one joined query per find when the first branch answers', function (): void {
    DB::enableQueryLog();
    linCodexDbSearchFind('reset', $this->guest);

    $queries = linCodexDbSearchLoggedMatches();
    $articles = DB::connection()->getQueryGrammar()->wrapTable((new Article)->getTable());

    expect($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toContain('join')
        ->and($queries[0]['query'])->toContain($articles);
});

it('applies the locale rule: an existing translation is never replaced by the default one', function (): void {
    linCodexDbSearchUseLanguages(['en', 'de']);

    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('invoice', $this->guest, 'de')))->toBe([])
        ->and(linCodexDbSearchSlugs(linCodexDbSearchFind('rechnung', $this->guest, 'de')))->toBe(['billing']);

    $set = linCodexDbSearchFind('glossary', $this->guest, 'de');

    expect(linCodexDbSearchSlugs($set))->toBe(['only-english'])
        ->and($set->candidates[0]->choice->isFallback)->toBeTrue();

    linCodexDbSearchUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('glossary', $this->guest, 'de')))->toBe([]);

    linCodexDbSearchUseLanguages(['en']);

    $set = linCodexDbSearchFind('glossary', $this->guest, 'de');

    expect(linCodexDbSearchSlugs($set))->toBe(['only-english'])
        ->and($set->candidates[0]->choice->isFallback)->toBeTrue();
});

it('never returns a row without a search_text blob', function (): void {
    $articleId = DB::table((new Article)->getTable())->insertGetId([
        'slug' => 'rawrow',
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table((new ArticleTranslation)->getTable())->insert([
        'article_id' => $articleId,
        'locale' => 'en',
        'title' => 'Rawtitle unique',
        'body' => 'Rawtitle body',
        'search_text' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('rawtitle', $this->user)))->toBe([]);
});

it('caps the candidate rows in slug order', function (): void {
    config()->set('lin-codex.search.candidates', 2);

    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('zephyr', $this->guest)))->toBe(['tier-body', 'tier-excerpt']);

    config()->set('lin-codex.search.candidates', 200);

    expect(linCodexDbSearchSlugs(linCodexDbSearchFind('zephyr', $this->guest)))->toHaveCount(4);
});

it('answers with the LIKE strategy and the same slugs on SQLite whichever engine is configured', function (): void {
    $cases = [
        ['reset token', $this->guest, ['password-reset']],
        ['zephyr', $this->guest, ['tier-body', 'tier-excerpt', 'tier-keywords', 'tier-title']],
        ['escalation', $this->user, ['internal', 'internal/pub', 'notes']],
    ];

    foreach ($cases as [$query, $viewer, $expected]) {
        config()->set('lin-codex.search.engine', 'fulltext');
        $fullText = linCodexDbSearchFind($query, $viewer);

        config()->set('lin-codex.search.engine', 'like');
        $like = linCodexDbSearchFind($query, $viewer);

        expect($fullText->strategy)->toBe(SearchStrategy::Like)
            ->and($like->strategy)->toBe(SearchStrategy::Like)
            ->and(linCodexDbSearchSlugs($fullText))->toBe($expected)
            ->and(linCodexDbSearchSlugs($like))->toBe($expected);
    }
})->skip(fn (): bool => $this->databaseDriver() !== 'sqlite', 'sqlite only');

it('routes the like engine, an unknown engine, short tokens and stopwords to LIKE on every driver', function (): void {
    $parser = app(QueryParser::class);
    $candidates = app(DatabaseCandidates::class);

    config()->set('lin-codex.search.engine', 'fulltext');

    expect($candidates->strategyFor($parser->parse('ui toggle')))->toBe(SearchStrategy::Like)
        ->and($candidates->strategyFor($parser->parse('und')))->toBe(SearchStrategy::Like)
        ->and($candidates->strategyFor($parser->parse('the password')))->toBe(SearchStrategy::Like);

    config()->set('lin-codex.search.engine', 'like');

    expect($candidates->strategyFor($parser->parse('reset')))->toBe(SearchStrategy::Like);

    config()->set('lin-codex.search.engine', 'elasticsearch');

    expect($candidates->strategyFor($parser->parse('reset')))->toBe(SearchStrategy::Like);

    config()->set('lin-codex.search.engine', null);

    expect($candidates->strategyFor($parser->parse('reset')))->toBe(SearchStrategy::Like);
});

it('keeps the LIKE clause with the like engine on a full-text driver', function (): void {
    config()->set('lin-codex.search.engine', 'like');
    DB::enableQueryLog();

    $set = linCodexDbSearchFind('password', $this->guest);
    $queries = linCodexDbSearchLoggedMatches();

    expect($set->strategy)->toBe(SearchStrategy::Like)
        ->and(linCodexDbSearchSlugs($set))->toBe(['password-reset'])
        ->and($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toContain('like')
        ->and($queries[0]['query'])->not->toContain('in boolean mode')
        ->and($queries[0]['query'])->not->toContain('to_tsquery');
})->skip(fn (): bool => $this->databaseDriver() === 'sqlite', 'needs a full-text engine');

it('validates the PostgreSQL language', function (): void {
    expect(DatabaseCandidates::pgsqlLanguage())->toBe('simple');

    config()->set('lin-codex.search.pgsql_language', 'german');

    expect(DatabaseCandidates::pgsqlLanguage())->toBe('german');

    config()->set('lin-codex.search.pgsql_language', 'no such; drop');

    expect(DatabaseCandidates::pgsqlLanguage())->toBe('simple');
});

/**
 * The full-text index must answer here. A Like strategy on this test means
 * the index saw no rows, which is exactly what a transaction wrapper in the
 * harness would cause: InnoDB full-text only sees committed rows.
 */
it('answers from the full-text index on a full-text engine', function (): void {
    config()->set('lin-codex.search.engine', 'fulltext');

    $set = linCodexDbSearchFind('password', $this->guest);

    expect($set->strategy)->toBe(SearchStrategy::FullText)
        ->and(linCodexDbSearchSlugs($set))->toBe(['password-reset']);
})->skip(fn (): bool => $this->databaseDriver() === 'sqlite', 'needs a full-text engine');

it('returns the same slugs from the full-text branch and the LIKE branch', function (): void {
    $cases = [
        ['reset token', $this->guest, ['password-reset']],
        ['zephyr', $this->guest, ['tier-body', 'tier-excerpt', 'tier-keywords', 'tier-title']],
        ['escalation', $this->user, ['internal', 'internal/pub', 'notes']],
    ];

    foreach ($cases as [$query, $viewer, $expected]) {
        config()->set('lin-codex.search.engine', 'fulltext');
        $fullText = linCodexDbSearchFind($query, $viewer);

        config()->set('lin-codex.search.engine', 'like');
        $like = linCodexDbSearchFind($query, $viewer);

        expect($fullText->strategy)->toBe(SearchStrategy::FullText)
            ->and($like->strategy)->toBe(SearchStrategy::Like)
            ->and(linCodexDbSearchSlugs($fullText))->toBe($expected)
            ->and(linCodexDbSearchSlugs($like))->toBe($expected);
    }
})->skip(fn (): bool => $this->databaseDriver() === 'sqlite', 'needs a full-text engine');

it('takes the LIKE path for short tokens and stopwords on a full-text engine too', function (): void {
    config()->set('lin-codex.search.engine', 'fulltext');

    $short = linCodexDbSearchFind('ui', $this->guest);
    $stopword = linCodexDbSearchFind('und', $this->guest);

    expect($short->strategy)->toBe(SearchStrategy::Like)
        ->and(linCodexDbSearchSlugs($short))->toBe(['short'])
        ->and($stopword->strategy)->toBe(SearchStrategy::Like)
        ->and(linCodexDbSearchSlugs($stopword))->toBe(['short']);
})->skip(fn (): bool => $this->databaseDriver() === 'sqlite', 'needs a full-text engine');

it('emits the engine grammar for the full-text strategy', function (): void {
    config()->set('lin-codex.search.engine', 'fulltext');
    DB::enableQueryLog();
    linCodexDbSearchFind('reset token', $this->guest);

    $queries = linCodexDbSearchLoggedMatches();

    expect($queries)->toHaveCount(1);

    [$sql, $bindings] = [$queries[0]['query'], $queries[0]['bindings']];

    if ($this->databaseDriver() === 'pgsql') {
        expect($sql)->toContain("to_tsquery('simple', ?)")
            ->and($bindings)->toContain('reset:* & token:*');

        return;
    }

    expect($sql)->toContain('in boolean mode')
        ->and($bindings)->toContain('+reset* +token*');
})->skip(fn (): bool => $this->databaseDriver() === 'sqlite', 'needs a full-text engine');

it('retries with LIKE when the full-text query returns no rows', function (): void {
    config()->set('lin-codex.search.engine', 'fulltext');
    DB::enableQueryLog();

    $set = linCodexDbSearchFind(str_repeat('q', 10), $this->guest);

    expect($set->strategy)->toBe(SearchStrategy::Like)
        ->and(linCodexDbSearchSlugs($set))->toBe(['longword'])
        ->and(linCodexDbSearchLoggedMatches())->toHaveCount(2);
})->skip(fn (): bool => ! in_array($this->databaseDriver(), ['mysql', 'mariadb'], true), 'needs innodb');
