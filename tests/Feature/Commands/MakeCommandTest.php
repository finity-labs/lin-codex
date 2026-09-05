<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\ArticleSet;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * The article set read back from the temp docs path through the filesystem
 * source, with the memoized instance dropped so the new file is seen.
 */
function linCodexMakeLoad(): ArticleSet
{
    app()->forgetInstance(FilesystemSource::class);

    return app(FilesystemSource::class)->set();
}

/**
 * The contents of a file under the temp docs path.
 */
function linCodexMakeFile(string $tmp, string $relative): string
{
    return File::get($tmp.'/'.$relative);
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/lin-codex-make-'.uniqid();
    File::ensureDirectoryExists($this->tmp);

    config()->set('lin-codex.source', 'filesystem');
    config()->set('lin-codex.sources.filesystem.paths', [$this->tmp]);
    $this->forgetSources();
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

it('is discovered under src/Commands', function (): void {
    expect(Artisan::all())->toHaveKey('codex:make');
});

it('writes a parseable article with zero warnings', function (): void {
    $this->artisan('codex:make', ['slug' => 'users/roles', '--title' => 'Roles'])
        ->expectsOutputToContain('Created en/users/roles.md')
        ->assertExitCode(0);

    $set = linCodexMakeLoad();
    $article = $set->findBySlug('users/roles');

    expect($set->warnings())->toBe([])
        ->and($article)->not->toBeNull()
        ->and($article?->translation('en')?->title)->toBe('Roles')
        ->and($article?->translation('en')?->excerpt)->toBeNull()
        ->and($article?->visibility)->toBe(Visibility::Authenticated)
        ->and($article?->published)->toBeTrue()
        ->and($article?->order)->toBe(0)
        ->and($article?->format)->toBe(ArticleFormat::Markdown)
        ->and($article?->contexts)->toBe([])
        ->and($article?->related)->toBe([])
        ->and($article?->keywords)->toBe([])
        ->and($article?->meta)->toBe([]);

    $file = linCodexMakeFile($this->tmp, 'en/users/roles.md');

    expect($file)->toStartWith("---\ntitle: Roles\nexcerpt: ''\norder: 0\nvisibility: authenticated\npublished: true\ncontexts: []\n")
        ->and($file)->toContain("# contexts:\n#   - route:users.index\n")
        ->and($file)->toContain('#   - url:/admin/users/*')
        ->and($file)->toContain('#   - class:App\Filament\Resources\UserResource')
        ->and($file)->toContain('#   - admin:class:App\Filament\Resources\UserResource')
        ->and($file)->toContain("# related:\n#   - users/roles\n")
        ->and($file)->toContain("# keywords:\n#   - accounts\n")
        ->and($file)->toContain("---\n\n## Overview\n\n")
        ->and($file)->toContain(":::steps\n1. ")
        ->and($file)->toContain("\n2. ")
        ->and($file)->toContain('![Screenshot](images/example.png "')
        ->and($file)->toContain("\n:::\n\n> [!TIP]\n> ")
        ->and($file)->toEndWith("\n")
        ->and($file)->not->toContain("\r");
});

it('defaults the locale to the settings default locale and the title to the humanised segment', function (): void {
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], ['en', 'de']);
    $settings->default_locale = 'de';
    $settings->save();

    $this->artisan('codex:make', ['slug' => 'billing/reset-password'])
        ->expectsOutputToContain('Created de/billing/reset-password.md')
        ->assertExitCode(0);

    $file = linCodexMakeFile($this->tmp, 'de/billing/reset-password.md');
    $article = linCodexMakeLoad()->findBySlug('billing/reset-password');

    expect($file)->toContain("title: 'Reset password'\n")
        ->and($article?->translation('de')?->title)->toBe('Reset password');
});

it('--section writes index.md', function (): void {
    $this->artisan('codex:make', ['slug' => 'users', '--section' => true])
        ->expectsOutputToContain('Created en/users/index.md')
        ->assertExitCode(0);

    $set = linCodexMakeLoad();
    $article = $set->findBySlug('users');

    expect(File::exists($this->tmp.'/en/users/index.md'))->toBeTrue()
        ->and($set->warnings())->toBe([])
        ->and($article?->isSection)->toBeTrue()
        ->and($article?->translation('en')?->title)->toBe('Users');
});

it('--format=html writes an html file whose body uses html', function (): void {
    $this->artisan('codex:make', ['slug' => 'guide', '--format' => 'html'])
        ->expectsOutputToContain('Created en/guide.html')
        ->assertExitCode(0);

    $file = linCodexMakeFile($this->tmp, 'en/guide.html');
    $set = linCodexMakeLoad();
    $article = $set->findBySlug('guide');

    expect($file)->toContain('<h2>Overview</h2>')
        ->and($file)->toContain('<figure>')
        ->and($file)->toContain('<figcaption>')
        ->and($file)->not->toContain(':::steps')
        ->and($file)->not->toContain('[!TIP]')
        ->and($set->warnings())->toBe([])
        ->and($article?->format)->toBe(ArticleFormat::Html)
        ->and($article?->translation('en')?->title)->toBe('Guide');

    $this->artisan('codex:make', ['slug' => 'other', '--format' => 'docx'])
        ->expectsOutputToContain('--format must be markdown or html.')
        ->assertExitCode(1);

    expect(File::exists($this->tmp.'/en/other.md'))->toBeFalse()
        ->and(File::exists($this->tmp.'/en/other.docx'))->toBeFalse();
});

it('refuses to overwrite without --force', function (): void {
    $this->artisan('codex:make', ['slug' => 'intro', '--title' => 'First'])->assertExitCode(0);

    $before = linCodexMakeFile($this->tmp, 'en/intro.md');

    $this->artisan('codex:make', ['slug' => 'intro', '--title' => 'Second'])
        ->expectsOutputToContain('en/intro.md already exists. Pass --force to overwrite it.')
        ->assertExitCode(1);

    expect(linCodexMakeFile($this->tmp, 'en/intro.md'))->toBe($before);

    $this->artisan('codex:make', ['slug' => 'intro', '--title' => 'Second', '--force' => true])
        ->expectsOutputToContain('Created en/intro.md')
        ->assertExitCode(0);

    expect(linCodexMakeFile($this->tmp, 'en/intro.md'))->toContain("title: Second\n")
        ->and(linCodexMakeFile($this->tmp, 'en/intro.md'))->not->toBe($before);
});

it('rejects an invalid slug segment', function (string $slug): void {
    $this->artisan('codex:make', ['slug' => $slug])
        ->expectsOutputToContain('is not a valid slug')
        ->assertExitCode(1);

    expect(File::allFiles($this->tmp))->toBe([]);
})->with([
    'space and capitals' => 'Bad Slug',
    'double slash' => 'users//roles',
    'trailing dash' => 'users-',
    'dot segment' => '../escape',
    'empty' => '/',
]);

it('rejects a locale that is not a locale folder name', function (): void {
    $this->artisan('codex:make', ['slug' => 'intro', '--locale' => '../outside'])
        ->expectsOutputToContain('is not a locale')
        ->assertExitCode(1);

    expect(File::allFiles($this->tmp))->toBe([])
        ->and(File::exists(dirname($this->tmp).'/outside'))->toBeFalse();
});

it('quotes a title with a colon so the file parses', function (): void {
    $this->artisan('codex:make', ['slug' => 'users', '--title' => 'Users: overview'])->assertExitCode(0);

    $set = linCodexMakeLoad();

    expect(linCodexMakeFile($this->tmp, 'en/users.md'))->toContain("title: 'Users: overview'\n")
        ->and($set->warnings())->toBe([])
        ->and($set->findBySlug('users')?->translation('en')?->title)->toBe('Users: overview');
});

it('localises the body when the lang file has the locale', function (): void {
    app('translator')->addLines(['lin-codex.make.heading' => 'Überblick', 'lin-codex.make.tip' => 'Kurz halten.'], 'de', 'lin-codex');

    $this->artisan('codex:make', ['slug' => 'intro', '--locale' => 'de'])
        ->expectsOutputToContain('Created de/intro.md')
        ->assertExitCode(0);
    $this->artisan('codex:make', ['slug' => 'intro', '--locale' => 'fr'])
        ->expectsOutputToContain('Created fr/intro.md')
        ->assertExitCode(0);

    $german = linCodexMakeFile($this->tmp, 'de/intro.md');
    $french = linCodexMakeFile($this->tmp, 'fr/intro.md');

    expect($german)->toContain("## Überblick\n")
        ->and($german)->toContain("> [!TIP]\n> Kurz halten.\n")
        ->and($german)->toContain('Open the page from the menu.')
        ->and($french)->toContain("## Overview\n")
        ->and($french)->not->toContain('Überblick');
});

it('fails when no docs path is configured', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', []);
    $this->forgetSources();

    $this->artisan('codex:make', ['slug' => 'intro'])
        ->expectsOutputToContain('No docs path configured (lin-codex.sources.filesystem.paths).')
        ->assertExitCode(1);
});

it('writes the shipped German and Hungarian starter text', function (): void {
    $this->artisan('codex:make', ['slug' => 'intro', '--locale' => 'de'])
        ->expectsOutputToContain('Created de/intro.md')
        ->assertExitCode(0);
    $this->artisan('codex:make', ['slug' => 'intro', '--locale' => 'hu'])
        ->expectsOutputToContain('Created hu/intro.md')
        ->assertExitCode(0);

    $german = linCodexMakeFile($this->tmp, 'de/intro.md');
    $hungarian = linCodexMakeFile($this->tmp, 'hu/intro.md');
    $hungarianHeading = __('lin-codex::lin-codex.make.heading', [], 'hu');
    $hungarianStep = __('lin-codex::lin-codex.make.step_one', [], 'hu');

    expect($german)->toContain("## Überblick\n")
        ->and($german)->toContain('Öffnen Sie die Seite über das Menü.')
        ->and($german)->not->toContain('Overview')
        ->and($hungarianHeading)->not->toBe('Overview')
        ->and($hungarian)->toContain('## '.$hungarianHeading."\n")
        ->and($hungarian)->toContain($hungarianStep)
        ->and($hungarian)->not->toContain('Overview')
        ->and($hungarian)->not->toContain('Open the page from the menu.');
});
