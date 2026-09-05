<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\SearchDocument;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Cache;

const LIN_CODEX_FIXTURE_SLUGS = [
    'billing/invoice-history',
    'crlf',
    'duplicate',
    'escaping',
    'intro',
    'no-title',
    'nur-deutsch',
    'users',
    'users/permissions',
    'users/roles',
];

function linCodexFreshSource(): FilesystemSource
{
    app()->forgetInstance(FilesystemSource::class);

    return app(FilesystemSource::class);
}

/**
 * @param  list<ArticleData>  $articles
 *
 * @return list<string>
 */
function linCodexSlugList(array $articles): array
{
    return array_map(fn (ArticleData $article): string => $article->slug, $articles);
}

/**
 * @param  list<SourceWarning>  $warnings
 */
function linCodexWarningOf(array $warnings, SourceWarningKind $kind): SourceWarning
{
    foreach ($warnings as $warning) {
        if ($warning->kind === $kind) {
            return $warning;
        }
    }

    throw new RuntimeException('No warning of kind '.$kind->key());
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    $this->source = linCodexFreshSource();
});

describe('articles', function (): void {
    it('loads exactly the fixture slugs and the billing group', function (): void {
        expect(array_keys($this->source->all()))->toBe(LIN_CODEX_FIXTURE_SLUGS)
            ->and($this->source->set()->groups)->toBe(['billing' => 'Billing']);
    });

    it('parses the full front matter set of the intro article', function (): void {
        $intro = $this->source->findBySlug('intro');

        expect($intro)->toBeInstanceOf(ArticleData::class)
            ->and($intro->translation('en')->title)->toBe('Introduction')
            ->and($intro->translation('en')->excerpt)->toBe('What Codex does and where to start.')
            ->and($intro->icon)->toBe('heroicon-o-book-open')
            ->and($intro->visibility)->toBe(Visibility::Public)
            ->and($intro->published)->toBeTrue()
            ->and($intro->order)->toBe(1)
            ->and($intro->parentSlug)->toBeNull()
            ->and($intro->isSection)->toBeFalse()
            ->and($intro->format)->toBe(ArticleFormat::Markdown)
            ->and($intro->related)->toBe(['users', 'users/roles'])
            ->and($intro->keywords)->toBe(['getting started', 'overview'])
            ->and($intro->meta)->toBe(['owner' => 'docs-team'])
            ->and($intro->id)->toBeNull()
            ->and($intro->contexts)->toEqual([
                new ContextData(ContextType::Route, 'dashboard', null, 0),
                new ContextData(ContextType::Url, '/', null, 1),
                new ContextData(ContextType::PageClass, 'App\Filament\Pages\Dashboard', null, 2),
                new ContextData(ContextType::PageClass, 'App\Filament\Pages\Dashboard', 'admin', 3),
                new ContextData(ContextType::Url, '/welcome/*', null, 4),
            ])
            ->and($intro->locales())->toBe(['de', 'en'])
            ->and($intro->translation('en')->body)->toStartWith('# Introduction')
            ->and($intro->translation('de')->title)->toBe('Einführung')
            ->and($intro->translation('de')->excerpt)->toBe('Was Codex macht und wo man anfängt.')
            ->and($intro->translation('de')->body)->toContain('](/codex/media/en/images/reset.png)')
            ->and($intro->sourcePath)->toEndWith('docs/en/01-intro.md')
            ->and($intro->translation('de')->sourcePath)->toEndWith('docs/de/01-intro.md')
            ->and($intro->translation('en')->updatedAt)->toBeNull();
    });

    it('consumes the h1 of a section file and rewrites its images', function (): void {
        $users = $this->source->findBySlug('users');

        expect($users->isSection)->toBeTrue()
            ->and($users->order)->toBe(2)
            ->and($users->visibility)->toBe(Visibility::Public)
            ->and($users->translation('en')->title)->toBe('Users')
            ->and($users->translation('en')->body)->toStartWith('Manage the people')
            ->and($users->translation('en')->body)->toContain('](/codex/media/en/02-users/images/users.png "The users page")')
            ->and($users->translation('de')->title)->toBe('Benutzer')
            ->and($users->translation('de')->body)->toStartWith('Verwalten Sie');
    });

    it('reads published no as false and keeps parent in meta', function (): void {
        $roles = $this->source->findBySlug('users/roles');

        expect($roles->published)->toBeFalse()
            ->and($roles->keywords)->toBe(['rbac'])
            ->and($roles->meta)->toBe(['parent' => 'users'])
            ->and($roles->order)->toBe(1)
            ->and($roles->parentSlug)->toBe('users')
            ->and($roles->locales())->toBe(['de', 'en'])
            ->and($roles->translation('de')->title)->toBe('Rollen');
    });

    it('loads html files with the h1 consumed and images rewritten', function (): void {
        $permissions = $this->source->findBySlug('users/permissions');

        expect($permissions->format)->toBe(ArticleFormat::Html)
            ->and($permissions->translation('en')->title)->toBe('Permissions')
            ->and($permissions->translation('en')->body)->not->toContain('<h1>')
            ->and($permissions->translation('en')->body)->toContain('src="/codex/media/en/images/reset.png"')
            ->and($permissions->order)->toBe(2)
            ->and($permissions->visibility)->toBe(Visibility::Authenticated);
    });

    it('applies a front matter slug and order to the last path segment', function (): void {
        $invoices = $this->source->findBySlug('billing/invoice-history');

        expect($invoices->order)->toBe(5)
            ->and($invoices->parentSlug)->toBe('billing')
            ->and($invoices->translation('en')->title)->toBe('Invoices')
            ->and($invoices->translation('en')->body)->toStartWith('Monthly invoices');
    });

    it('reads a windows file without its byte order mark', function (): void {
        $crlf = $this->source->findBySlug('crlf');

        expect($crlf->translation('en')->title)->toBe('Windows file')
            ->and($crlf->order)->toBe(4)
            ->and($crlf->translation('en')->body)->not->toContain("\xEF\xBB\xBF")
            ->and($crlf->translation('en')->body)->toContain('Saved on Windows');
    });

    it('keeps the first duplicate, humanises a missing title and loads a de-only article', function (): void {
        $duplicate = $this->source->findBySlug('duplicate');
        $noTitle = $this->source->findBySlug('no-title');
        $german = $this->source->findBySlug('nur-deutsch');

        expect($duplicate->translation('en')->title)->toBe('First')
            ->and($duplicate->order)->toBe(5)
            ->and($noTitle->translation('en')->title)->toBe('No title')
            ->and($noTitle->order)->toBe(0)
            ->and($german->locales())->toBe(['de'])
            ->and($german->visibility)->toBe(Visibility::Public)
            ->and($german->translation('de')->title)->toBe('Nur Deutsch');
    });

    it('leaves escaping, absolute and remote images as written', function (): void {
        $escaping = $this->source->findBySlug('escaping');
        $body = $escaping->translation('en')->body;

        expect($escaping->visibility)->toBe(Visibility::Authenticated)
            ->and($body)->toContain('![Outside](../../secret.png)')
            ->and($body)->toContain('![Inside](/codex/media/en/images/reset.png)')
            ->and($body)->toContain('![Versioned](/codex/media/en/images/reset.png?v=2)')
            ->and($body)->toContain('![Absolute](/storage/codex/a.png)')
            ->and($body)->toContain('![Remote](https://example.com/a.png)');
    });
});

describe('warnings', function (): void {
    it('collects one warning of each kind over the fixture tree', function (): void {
        $warnings = $this->source->warnings();
        $kinds = array_map(fn (SourceWarning $warning): string => $warning->kind->key(), $warnings);
        sort($kinds);

        $expected = array_map(fn (SourceWarningKind $kind): string => $kind->key(), [
            SourceWarningKind::InvalidFrontMatter,
            SourceWarningKind::SharedKeyIgnored,
            SourceWarningKind::MissingDefaultLocale,
            SourceWarningKind::UnknownValue,
            SourceWarningKind::InvalidContext,
            SourceWarningKind::DuplicateSlug,
            SourceWarningKind::UnknownKey,
        ]);
        sort($expected);

        expect($warnings)->toHaveCount(7)
            ->and($kinds)->toBe($expected);

        $shared = linCodexWarningOf($warnings, SourceWarningKind::SharedKeyIgnored);
        $duplicate = linCodexWarningOf($warnings, SourceWarningKind::DuplicateSlug);
        $missing = linCodexWarningOf($warnings, SourceWarningKind::MissingDefaultLocale);
        $context = linCodexWarningOf($warnings, SourceWarningKind::InvalidContext);
        $unknownKey = linCodexWarningOf($warnings, SourceWarningKind::UnknownKey);
        $unknownValue = linCodexWarningOf($warnings, SourceWarningKind::UnknownValue);
        $frontMatter = linCodexWarningOf($warnings, SourceWarningKind::InvalidFrontMatter);

        expect($shared->detail)->toBe('contexts, order')
            ->and($shared->path)->toEndWith('de/02-users/index.md')
            ->and($shared->slug)->toBe('users')
            ->and($shared->locale)->toBe('de')
            ->and($duplicate->path)->toEndWith('06-duplicate.md')
            ->and($duplicate->detail)->toBe('duplicate')
            ->and($missing->slug)->toBe('nur-deutsch')
            ->and($context->detail)->toBe('bogus')
            ->and($context->slug)->toBe('intro')
            ->and($unknownKey->detail)->toBe('parent')
            ->and($unknownKey->slug)->toBe('users/roles')
            ->and($unknownValue->path)->toEndWith('escaping.md')
            ->and($unknownValue->detail)->toContain('secret')
            ->and($frontMatter->path)->toEndWith('broken.md');

        foreach ($warnings as $warning) {
            expect($warning->message())->toBeString()->not->toBe('')->not->toStartWith('lin-codex::');
        }
    });
});

describe('search text', function (): void {
    it('produces plain text at scan time through the renderer', function (): void {
        $intro = $this->source->findBySlug('intro');
        $users = $this->source->findBySlug('users');
        $roles = $this->source->findBySlug('users/roles');

        expect($intro->translation('en')->searchText)->toContain('Welcome to the help center')
            ->and($users->translation('en')->searchText)->toContain('Users list')
            ->and($users->translation('en')->searchText)->toContain('The users page')
            ->and($roles->translation('en')->searchText)->toContain('Roles group permissions');
    });

    it('emits one search document per translation with keywords folded in', function (): void {
        $documents = $this->source->allForSearch();
        $keys = array_map(fn (SearchDocument $document): string => $document->slug.'#'.$document->locale, $documents);
        $sorted = $keys;
        sort($sorted);

        $roles = array_values(array_filter(
            $documents,
            fn (SearchDocument $document): bool => $document->slug === 'users/roles' && $document->locale === 'en',
        ))[0];

        expect($documents)->toHaveCount(13)
            ->and($keys)->toBe($sorted)
            ->and($roles->text)->toEndWith('rbac')
            ->and($roles->published)->toBeFalse();
    });

    it('warms the render cache under the render slug', function (): void {
        $this->source->all();

        $renderer = app(ArticleRenderer::class);
        $users = $this->source->findBySlug('users');
        $roles = $this->source->findBySlug('users/roles');

        expect(Cache::has($renderer->cacheKey($users->translation('en')->body, ArticleFormat::Markdown, 'en', 'users/index')))->toBeTrue()
            ->and(Cache::has($renderer->cacheKey($roles->translation('en')->body, ArticleFormat::Markdown, 'en', 'users/roles')))->toBeTrue();
    });
});

describe('queries', function (): void {
    it('builds the tree with the billing group and ordered children', function (): void {
        $roots = $this->source->tree();
        $bySlug = [];

        foreach ($roots as $root) {
            $bySlug[$root->slug] = $root;
        }

        expect(array_map(fn (TreeNode $node): string => $node->slug, $roots))
            ->toBe(['billing', 'escaping', 'no-title', 'nur-deutsch', 'intro', 'users', 'crlf', 'duplicate'])
            ->and($bySlug['billing']->article)->toBeNull()
            ->and($bySlug['billing']->label)->toBe('Billing')
            ->and(array_map(fn (TreeNode $node): string => $node->slug, $bySlug['billing']->children))->toBe(['billing/invoice-history'])
            ->and(array_map(fn (TreeNode $node): string => $node->slug, $bySlug['users']->children))->toBe(['users/roles', 'users/permissions']);
    });

    it('finds articles by context and ignores contexts from non-default locale files', function (): void {
        expect(linCodexSlugList($this->source->findByContext(ContextType::Route, 'dashboard')))->toBe(['intro'])
            ->and(linCodexSlugList($this->source->findByContext(ContextType::PageClass, 'App\Filament\Pages\Dashboard', 'admin')))->toBe(['intro'])
            ->and(linCodexSlugList($this->source->findByContext(ContextType::PageClass, 'App\Filament\Pages\Dashboard')))->toBe(['intro'])
            ->and($this->source->findByContext(ContextType::Route, 'benutzer.index'))->toBe([]);
    });
});

describe('paths', function (): void {
    it('is empty without a warning when the configured folder is missing', function (): void {
        config()->set('lin-codex.sources.filesystem.paths', [resource_path('codex')]);
        $source = linCodexFreshSource();

        expect($source->all())->toBe([])
            ->and($source->warnings())->toBe([]);

        config()->set('lin-codex.sources.filesystem.paths', []);
        $source = linCodexFreshSource();

        expect($source->all())->toBe([])
            ->and($source->warnings())->toBe([]);

        config()->set('lin-codex.sources.filesystem.paths', 'not-a-list');
        $source = linCodexFreshSource();

        expect($source->paths())->toBe([])
            ->and($source->all())->toBe([])
            ->and($source->warnings())->toBe([]);
    });

    it('lets a later path replace an article whole', function (): void {
        config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath(), $this->fixtureDocsPath('docs-override')]);
        $source = linCodexFreshSource();
        $intro = $source->findBySlug('intro');

        expect($intro->translation('en')->title)->toBe('Introduction (override)')
            ->and($intro->contexts)->toEqual([new ContextData(ContextType::Route, 'home', null, 0)])
            ->and($intro->locales())->toBe(['en'])
            ->and($intro->meta)->toBe([])
            ->and(array_keys($source->all()))->toBe(LIN_CODEX_FIXTURE_SLUGS)
            ->and($source->findByContext(ContextType::Route, 'dashboard'))->toBe([])
            ->and(linCodexSlugList($source->findByContext(ContextType::Route, 'home')))->toBe(['intro'])
            ->and($source->warnings())->toHaveCount(7);
    });

    it('rewrites images under the configured media prefix', function (): void {
        config()->set('lin-codex.routes.media', '/help-media/');
        $source = linCodexFreshSource();

        expect($source->findBySlug('users')->translation('en')->body)->toContain('/help-media/en/02-users/images/users.png');
    });

    it('takes shared keys from the new default locale file after a settings change', function (): void {
        $settings = app(CodexSettings::class);
        $settings->default_locale = 'de';
        $settings->save();

        $source = linCodexFreshSource();
        $users = $source->findBySlug('users');
        $intro = $source->findBySlug('intro');
        $shared = array_values(array_filter(
            $source->warnings(),
            fn (SourceWarning $warning): bool => $warning->kind === SourceWarningKind::SharedKeyIgnored && $warning->slug === 'users',
        ));

        expect($users->order)->toBe(9)
            ->and($users->contexts)->toEqual([new ContextData(ContextType::Route, 'benutzer.index', null, 0)])
            ->and($shared)->toHaveCount(1)
            ->and($shared[0]->path)->toEndWith('en/02-users/index.md')
            ->and($shared[0]->detail)->toBe('visibility')
            ->and($intro->contexts)->toBe([])
            ->and($intro->meta)->toBe([]);
    });
});

describe('cache', function (): void {
    it('survives a serialize round trip', function (): void {
        expect(unserialize(serialize($this->source->set())))->toEqual($this->source->set());
    });

    it('derives one cache key per path from the scan version, locale, renderer and media prefix', function (): void {
        $docs = $this->fixtureDocsPath();
        $override = $this->fixtureDocsPath('docs-override');
        $before = $this->source->cacheKey($docs);

        expect($this->source->cacheKeys())->toHaveCount(1)
            ->and($this->source->cacheKeys()[0])->toStartWith('lin-codex:source:fs:')
            ->and($before)->not->toBe($this->source->cacheKey($override));

        config()->set('lin-codex.routes.media', '/other-media');

        expect(linCodexFreshSource()->cacheKey($docs))->not->toBe($before);
    });
});
