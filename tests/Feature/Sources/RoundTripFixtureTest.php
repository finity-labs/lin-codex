<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\ArticleSet;
use FinityLabs\LinCodex\Sources\Filesystem\FrontMatterWriter;
use FinityLabs\LinCodex\Sources\Filesystem\ImagePathRewriter;
use FinityLabs\LinCodex\Sources\FilesystemSource;

/**
 * The canonical fixture tree for the import/export round trip. It is
 * authored in the writer's own form (explicit titles, no H1s, the dumper's
 * quoting, LF only) so that reading it and writing it back is a no-op: the
 * file-level proof of the lossless half of CMD-02, before any database is
 * involved. tests/Fixtures/docs stays the warnings fixture.
 */
const LIN_CODEX_ROUND_TRIP_FIXTURE_SLUGS = [
    'billing/invoices',
    'intro',
    'questions',
    'users',
    'users/permissions',
    'users/roles',
];

const LIN_CODEX_ROUND_TRIP_FIXTURE_MEDIA_PREFIX = '/codex/media';

/** The fixture file's path relative to the fixture root: "en/02-users/01-roles.md". */
function linCodexRoundTripFixtureRelativePath(string $absolute): string
{
    $root = test()->fixtureDocsPath('docs-roundtrip').'/';

    expect($absolute)->toStartWith($root);

    return str_replace('\\', '/', substr($absolute, strlen($root)));
}

/** The fixture file as written, with the trailing newline normalised to exactly one. */
function linCodexRoundTripFixtureExpected(string $file): string
{
    return rtrim((string) file_get_contents($file), "\n")."\n";
}

/**
 * @return list<string> the '[panel:]type:key' strings in written order
 */
function linCodexRoundTripFixtureContexts(ArticleData $article): array
{
    return array_map(fn (ContextData $context): string => $context->toString(), $article->contexts);
}

function linCodexRoundTripFixtureSet(): ArticleSet
{
    return app(FilesystemSource::class)->set();
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-roundtrip')]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('loads without a single warning', function (): void {
    $set = linCodexRoundTripFixtureSet();

    expect($set->warnings)->toBe([])
        ->and(array_keys($set->articles))->toBe(LIN_CODEX_ROUND_TRIP_FIXTURE_SLUGS)
        ->and($set->groups)->toBe(['billing' => 'Billing']);
});

it('covers every writable key', function (): void {
    $set = linCodexRoundTripFixtureSet();

    $intro = $set->articles['intro'];
    $users = $set->articles['users'];
    $roles = $set->articles['users/roles'];
    $permissions = $set->articles['users/permissions'];
    $invoices = $set->articles['billing/invoices'];
    $questions = $set->articles['questions'];

    expect($intro->icon)->toBe('heroicon-o-book-open')
        ->and($intro->visibility)->toBe(Visibility::Public)
        ->and($intro->published)->toBeTrue()
        ->and($intro->order)->toBe(1)
        ->and($intro->format)->toBe(ArticleFormat::Markdown)
        ->and($intro->isSection)->toBeFalse()
        ->and(linCodexRoundTripFixtureContexts($intro))->toBe([
            'route:dashboard',
            'url:/',
            'class:App\Filament\Pages\Dashboard',
            'admin:class:App\Filament\Pages\Dashboard',
            'admin:url:/admin',
            'admin:route:filament.admin.pages.dashboard',
        ])
        ->and($intro->related)->toBe(['users', 'users/roles'])
        ->and($intro->keywords)->toBe(['getting started', 'overview'])
        ->and($intro->meta)->toBe(['owner' => 'docs-team', 'review' => ['cycle' => 'quarterly', 'team' => 'docs']])
        ->and($intro->locales())->toBe(['de', 'en'])
        ->and($intro->translation('en')->title)->toBe('Introduction')
        ->and($intro->translation('en')->excerpt)->toBe('What Codex does and where to start.')
        ->and($intro->translation('de')->title)->toBe('Einführung')
        ->and($intro->translation('de')->excerpt)->toBe('Was Codex ist und wo man anfängt.')
        ->and($intro->translation('en')->body)->toContain('](/codex/media/en/images/reset.png "The reset screen")');

    expect($users->isSection)->toBeTrue()
        ->and($users->order)->toBe(2)
        ->and($users->icon)->toBe('heroicon-o-users')
        ->and($users->visibility)->toBe(Visibility::Public)
        ->and(linCodexRoundTripFixtureContexts($users))->toBe(['route:users.index', 'url:/admin/users/*'])
        ->and($users->related)->toBe(['users/roles'])
        ->and($users->keywords)->toBe(['accounts', 'sign in'])
        ->and($users->translation('en')->title)->toBe('Users')
        ->and($users->translation('de')->title)->toBe('Benutzer')
        ->and($users->translation('en')->body)->toContain('](/codex/media/en/02-users/images/users.png "The users page")');

    expect($roles->published)->toBeFalse()
        ->and($roles->visibility)->toBe(Visibility::Authenticated)
        ->and($roles->order)->toBe(1)
        ->and($roles->keywords)->toBe(['rbac'])
        ->and($roles->contexts)->toBe([])
        ->and($roles->translation('en')->excerpt)->toBeNull()
        ->and($roles->translation('de')->title)->toBe('Rollen')
        ->and($roles->translation('de')->excerpt)->toBeNull()
        ->and($roles->translation('en')->body)->toContain('](/codex/media/en/images/reset.png)');

    expect($permissions->format)->toBe(ArticleFormat::Html)
        ->and($permissions->visibility)->toBe(Visibility::Authenticated)
        ->and($permissions->sourcePath)->toEndWith('02-permissions.md')
        ->and($permissions->locales())->toBe(['en'])
        ->and($permissions->translation('en')->body)->toContain('<img src="/codex/media/en/02-users/images/users.png"');

    expect($invoices->order)->toBe(2)
        ->and($invoices->format)->toBe(ArticleFormat::Html)
        ->and($invoices->visibility)->toBe(Visibility::Authenticated)
        ->and(linCodexRoundTripFixtureContexts($invoices))->toBe(['billing:url:/billing/invoices/**'])
        ->and($invoices->translation('en')->excerpt)->toBe('Download and pay invoices.')
        ->and($invoices->sourcePath)->toEndWith('03-billing/invoices.html');

    expect($questions->slug)->toBe('questions')
        ->and($questions->sourcePath)->toEndWith('en/faq.md')
        ->and($questions->translation('en')->title)->toBe('Common questions')
        ->and($questions->visibility)->toBe(Visibility::Public)
        ->and($questions->order)->toBe(0);
});

it('reproduces every fixture file from the parsed data', function (): void {
    $writer = new FrontMatterWriter;
    $set = linCodexRoundTripFixtureSet();
    $compared = [];
    $images = [];

    foreach ($set->articles as $article) {
        foreach ($article->translations as $locale => $translation) {
            $file = $translation->sourcePath;

            expect($file)->toBeString();

            $relative = linCodexRoundTripFixtureRelativePath($file);
            $dir = dirname($relative);
            $relativised = ImagePathRewriter::relativise(
                $translation->body,
                $article->format,
                $dir === '.' ? $locale : $dir,
                LIN_CODEX_ROUND_TRIP_FIXTURE_MEDIA_PREFIX,
            );

            $written = $writer->write($article, $translation, $relativised['body'], $locale === 'en', $relative);

            expect($written)->toBe(linCodexRoundTripFixtureExpected($file), 'Round trip mismatch for '.$relative);

            $compared[] = $relative;
            $images = [...$images, ...$relativised['images']];
        }
    }

    sort($compared);
    $images = array_values(array_unique($images));
    sort($images);

    expect($compared)->toHaveCount(9)->toBe([
        'de/01-intro.md',
        'de/02-users/01-roles.md',
        'de/02-users/index.md',
        'en/01-intro.md',
        'en/02-users/01-roles.md',
        'en/02-users/02-permissions.md',
        'en/02-users/index.md',
        'en/03-billing/invoices.html',
        'en/faq.md',
    ])
        ->and($images)->toBe(['en/02-users/images/users.png', 'en/images/reset.png']);
});

it('contains exactly the expected files', function (): void {
    $root = $this->fixtureDocsPath('docs-roundtrip');
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }

    sort($files);

    expect($files)->toBe([
        'de/01-intro.md',
        'de/02-users/01-roles.md',
        'de/02-users/index.md',
        'en/01-intro.md',
        'en/02-users/01-roles.md',
        'en/02-users/02-permissions.md',
        'en/02-users/images/users.png',
        'en/02-users/index.md',
        'en/03-billing/invoices.html',
        'en/faq.md',
        'en/images/reset.png',
    ]);

    foreach ($files as $relative) {
        if (str_ends_with($relative, '.png')) {
            continue;
        }

        $contents = (string) file_get_contents($root.'/'.$relative);

        expect($contents)->not->toContain("\r", $relative.' has a CR')
            ->and($contents)->not->toStartWith("\xEF\xBB\xBF", $relative.' has a BOM')
            ->and($contents)->toStartWith("---\ntitle: ", $relative.' must open with an explicit title')
            ->and(preg_match('/^# /m', $contents))->toBe(0, $relative.' must not carry an H1');
    }
});
