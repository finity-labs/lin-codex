<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleContext;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use FinityLabs\LinCodex\Sync\ArticleImporter;
use FinityLabs\LinCodex\Sync\ImportOptions;
use FinityLabs\LinCodex\Sync\SyncReport;
use Illuminate\Support\Facades\DB;

/**
 * The importer reads the docs-roundtrip fixture through FilesystemSource
 * and writes it through the models. The source is set to composite on
 * purpose: a slug already in the database would hide its file article from
 * the composite source, and the importer must not read through it.
 */
function linCodexImporterRun(array $overrides = []): SyncReport
{
    $options = new ImportOptions(
        only: $overrides['only'] ?? [],
        locale: $overrides['locale'] ?? null,
        force: $overrides['force'] ?? false,
        dryRun: $overrides['dryRun'] ?? false,
        userId: $overrides['userId'] ?? null,
    );

    return app(ArticleImporter::class)->import($options);
}

function linCodexImporterEnableRevisions(): void
{
    $settings = app(CodexSettings::class);
    $settings->revisions_enabled = true;
    $settings->revisions_keep = 10;
    $settings->save();
}

function linCodexImporterUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Imogen',
        'email' => 'imogen-'.uniqid().'@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return list<string> the '[panel:]type:key' strings of an article's contexts by sort order
 */
function linCodexImporterContexts(Article $article): array
{
    return $article->contexts()
        ->orderBy('sort_order')
        ->get()
        ->map(fn (ArticleContext $context): string => ($context->panel_id !== null ? $context->panel_id.':' : '').$context->type->key().':'.$context->key)
        ->all();
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-roundtrip')]);
    config()->set('lin-codex.source', 'composite');
    $this->forgetSources();
});

it('imports every article, translation, context, keyword, related and meta from the files', function (): void {
    $report = linCodexImporterRun();

    expect(Article::query()->count())->toBe(6)
        ->and(ArticleTranslation::query()->count())->toBe(9)
        ->and($report->warnings())->toBe([])
        ->and($report->locales())->toBe(['de', 'en'])
        ->and($report->count('en', 'created'))->toBe(6)
        ->and($report->count('de', 'created'))->toBe(3)
        ->and($report->count('en', 'updated'))->toBe(0)
        ->and($report->count('en', 'skipped'))->toBe(0)
        ->and($report->count('en', 'failed'))->toBe(0)
        ->and($report->hasFailures())->toBeFalse();

    $intro = Article::query()->where('slug', 'intro')->firstOrFail();

    expect(linCodexImporterContexts($intro))->toBe([
        'route:dashboard',
        'url:/',
        'class:App\Filament\Pages\Dashboard',
        'admin:class:App\Filament\Pages\Dashboard',
        'admin:url:/admin',
        'admin:route:filament.admin.pages.dashboard',
    ])
        ->and($intro->contexts()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1, 2, 3, 4, 5])
        ->and($intro->keywords)->toBe(['getting started', 'overview'])
        ->and($intro->related)->toBe(['users', 'users/roles'])
        ->and($intro->meta)->toBe(['owner' => 'docs-team', 'review' => ['cycle' => 'quarterly', 'team' => 'docs']])
        ->and($intro->icon)->toBe('heroicon-o-book-open')
        ->and($intro->visibility)->toBe(Visibility::Public)
        ->and($intro->sort_order)->toBe(1)
        ->and($intro->source_path)->toBe('en/01-intro.md')
        ->and($intro->parent_id)->toBeNull()
        ->and($intro->created_by)->toBeNull();

    $users = Article::query()->where('slug', 'users')->firstOrFail();
    $roles = Article::query()->where('slug', 'users/roles')->firstOrFail();
    $permissions = Article::query()->where('slug', 'users/permissions')->firstOrFail();
    $invoices = Article::query()->where('slug', 'billing/invoices')->firstOrFail();
    $questions = Article::query()->where('slug', 'questions')->firstOrFail();

    expect($roles->is_published)->toBeFalse()
        ->and($roles->parent_id)->toBe($users->id)
        ->and($roles->sort_order)->toBe(1)
        ->and($permissions->format)->toBe(ArticleFormat::Html)
        ->and($permissions->parent_id)->toBe($users->id)
        ->and($invoices->sort_order)->toBe(2)
        ->and($invoices->parent_id)->toBeNull()
        ->and(linCodexImporterContexts($invoices))->toBe(['billing:url:/billing/invoices/**'])
        ->and($invoices->contexts()->first()?->panel_id)->toBe('billing')
        ->and($questions->source_path)->toBe('en/faq.md')
        ->and($users->source_path)->toBe('en/02-users/index.md');

    $introDe = $intro->translations()->where('locale', 'de')->firstOrFail();
    $introEn = $intro->translations()->where('locale', 'en')->firstOrFail();
    $usersEn = $users->translations()->where('locale', 'en')->firstOrFail();

    expect($intro->translations()->pluck('locale')->sort()->values()->all())->toBe(['de', 'en'])
        ->and($introDe->title)->toBe('Einführung')
        ->and($introDe->excerpt)->toBe('Was Codex ist und wo man anfängt.')
        ->and($introEn->title)->toBe('Introduction')
        ->and($usersEn->body)->toContain('/codex/media/en/02-users/images/users.png')
        ->and(ArticleTranslation::query()->whereNull('search_text')->count())->toBe(0)
        ->and($introDe->search_text)->toContain('einfuhrung')
        ->and($introEn->search_text)->toContain('introduction');
});

it('skips existing slugs without --force and lists them', function (): void {
    Article::factory()->withTranslation('en', ['title' => 'Kept'])->create(['slug' => 'intro']);

    $report = linCodexImporterRun();

    expect($report->count('en', 'skipped'))->toBe(1)
        ->and($report->count('de', 'skipped'))->toBe(1)
        ->and($report->count('en', 'created'))->toBe(5)
        ->and($report->count('de', 'created'))->toBe(2)
        ->and($report->skippedSlugs())->toBe(['intro'])
        ->and(Article::query()->count())->toBe(6)
        ->and(Article::query()->where('slug', 'intro')->firstOrFail()->translations()->where('locale', 'en')->value('title'))->toBe('Kept');
});

it('overwrites with --force and records an import revision only for changed existing translations', function (): void {
    linCodexImporterEnableRevisions();
    Article::factory()->markdown()->withTranslation('en', ['title' => 'Old', 'body' => 'Old body'])->create(['slug' => 'intro']);

    $report = linCodexImporterRun(['force' => true]);

    $intro = Article::query()->where('slug', 'intro')->firstOrFail();
    $revision = ArticleRevision::query()->first();

    expect($intro->translations()->where('locale', 'en')->value('title'))->toBe('Introduction')
        ->and($intro->translations()->where('locale', 'de')->value('title'))->toBe('Einführung')
        ->and(ArticleRevision::query()->count())->toBe(1)
        ->and($revision?->reason)->toBe(RevisionReason::Import)
        ->and($revision?->locale)->toBe('en')
        ->and($revision?->title)->toBe('Old')
        ->and($revision?->body)->toBe('Old body')
        ->and($revision?->user_id)->toBeNull()
        ->and($report->count('en', 'updated'))->toBe(1)
        ->and($report->count('de', 'updated'))->toBe(1)
        ->and($report->count('en', 'created'))->toBe(5)
        ->and($report->count('de', 'created'))->toBe(2)
        ->and($report->skippedSlugs())->toBe([]);

    linCodexImporterRun(['force' => true]);

    expect(ArticleRevision::query()->count())->toBe(1);
});

it('records the user on created_by, updated_by and revisions', function (): void {
    linCodexImporterEnableRevisions();
    $userId = linCodexImporterUser();
    Article::factory()->markdown()->withTranslation('en', ['title' => 'Old', 'body' => 'Old body'])->create(['slug' => 'users']);

    linCodexImporterRun(['force' => true, 'userId' => $userId]);

    $intro = Article::query()->where('slug', 'intro')->firstOrFail();
    $users = Article::query()->where('slug', 'users')->firstOrFail();

    expect($intro->created_by)->toBe($userId)
        ->and($intro->updated_by)->toBe($userId)
        ->and($users->created_by)->toBeNull()
        ->and($users->updated_by)->toBe($userId)
        ->and(ArticleRevision::query()->count())->toBe(1)
        ->and(ArticleRevision::query()->first()?->user_id)->toBe($userId);
});

it('limits to --only slugs', function (): void {
    $report = linCodexImporterRun(['only' => ['users/roles']]);

    expect(Article::query()->pluck('slug')->all())->toBe(['users/roles'])
        ->and($report->count('en', 'created'))->toBe(1)
        ->and($report->count('de', 'created'))->toBe(1)
        ->and($report->locales())->toBe(['de', 'en']);
});

it('limits to one --locale and leaves articles without that language alone', function (): void {
    $report = linCodexImporterRun(['locale' => 'de']);

    expect(Article::query()->pluck('slug')->sort()->values()->all())->toBe(['intro', 'users', 'users/roles'])
        ->and(ArticleTranslation::query()->count())->toBe(3)
        ->and(ArticleTranslation::query()->pluck('locale')->unique()->values()->all())->toBe(['de'])
        ->and($report->locales())->toBe(['de'])
        ->and($report->count('de', 'created'))->toBe(3);

    $intro = Article::query()->where('slug', 'intro')->firstOrFail();

    expect($intro->contexts()->count())->toBe(6)
        ->and($intro->keywords)->toBe(['getting started', 'overview'])
        ->and($intro->source_path)->toBe('en/01-intro.md');
});

it('computes the report without writing on a dry run', function (): void {
    Article::factory()->withTranslation('en', ['title' => 'Kept'])->create(['slug' => 'intro']);

    $report = linCodexImporterRun(['dryRun' => true]);

    expect($report->count('en', 'created'))->toBe(5)
        ->and($report->count('en', 'skipped'))->toBe(1)
        ->and($report->count('de', 'created'))->toBe(2)
        ->and($report->count('de', 'skipped'))->toBe(1)
        ->and($report->skippedSlugs())->toBe(['intro'])
        ->and(Article::query()->count())->toBe(1)
        ->and(ArticleTranslation::query()->count())->toBe(1);

    $report = linCodexImporterRun(['dryRun' => true, 'force' => true]);

    expect($report->count('en', 'updated'))->toBe(1)
        ->and($report->count('en', 'created'))->toBe(5)
        ->and(Article::query()->count())->toBe(1);
});

it('collects a failure per article instead of aborting', function (): void {
    $report = linCodexImporterRun(['userId' => 999999]);

    expect(Article::query()->count())->toBe(0)
        ->and(ArticleTranslation::query()->count())->toBe(0)
        ->and($report->hasFailures())->toBeTrue()
        ->and($report->count('en', 'failed'))->toBe(6)
        ->and($report->count('de', 'failed'))->toBe(3)
        ->and($report->count('en', 'created'))->toBe(0)
        ->and($report->failures())->toHaveCount(9)
        ->and($report->failures())->toHaveKey('en:intro')
        ->and($report->failures())->toHaveKey('de:intro')
        ->and($report->failures()['en:intro'])->not->toBe('');
});

it('passes the source warnings through', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs')]);
    $this->forgetSources();

    $expected = array_map(fn ($warning): string => $warning->message(), app(FilesystemSource::class)->warnings());

    $report = linCodexImporterRun();

    expect($expected)->not->toBe([])
        ->and($report->warnings())->toBe($expected);
});

it('exposes the whole report as an array', function (): void {
    $report = new SyncReport;
    $report->created('en', 'intro');
    $report->updated('de', 'intro');
    $report->skipped('en', 'users');
    $report->skipped('de', 'users');
    $report->failed('en', 'billing/invoices', 'disk full');
    $report->warning('something odd');

    expect($report->toArray())->toBe([
        'locales' => [
            'de' => ['created' => [], 'updated' => ['intro'], 'skipped' => ['users'], 'failed' => []],
            'en' => ['created' => ['intro'], 'updated' => [], 'skipped' => ['users'], 'failed' => ['billing/invoices' => 'disk full']],
        ],
        'warnings' => ['something odd'],
    ])
        ->and($report->locales())->toBe(['de', 'en'])
        ->and($report->skippedSlugs())->toBe(['users'])
        ->and($report->failures())->toBe(['en:billing/invoices' => 'disk full'])
        ->and($report->count('fr', 'created'))->toBe(0)
        ->and($report->hasFailures())->toBeTrue();
});
