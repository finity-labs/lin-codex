<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Sync\ArticleExporter;
use FinityLabs\LinCodex\Sync\ExportOptions;
use FinityLabs\LinCodex\Sync\SyncReport;
use Illuminate\Support\Facades\File;

/**
 * The exporter writes database articles into a temp folder. Every case
 * starts from an empty temp directory configured as the only docs path
 * unless it says otherwise; the docs-roundtrip fixture is only ever read
 * (for images), never written to.
 */
function linCodexExporterRun(array $overrides = []): SyncReport
{
    $options = new ExportOptions(
        only: $overrides['only'] ?? [],
        locale: $overrides['locale'] ?? null,
        path: $overrides['path'] ?? null,
        dryRun: $overrides['dryRun'] ?? false,
    );

    return app(ArticleExporter::class)->export($options);
}

function linCodexExporterTempDir(): string
{
    $dir = sys_get_temp_dir().'/lin-codex-sync-'.uniqid();
    File::ensureDirectoryExists($dir);

    return $dir;
}

function linCodexExporterFile(string $root, string $relative): string
{
    $file = $root.'/'.$relative;

    expect(is_file($file))->toBeTrue($relative.' was not written');

    return (string) file_get_contents($file);
}

/**
 * @return list<string> every file under $root, docs-relative, sorted
 */
function linCodexExporterFiles(string $root): array
{
    $files = [];

    if (! is_dir($root)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }

    sort($files);

    return $files;
}

beforeEach(function (): void {
    $this->tmp = linCodexExporterTempDir();
    $this->extra = [];

    config()->set('lin-codex.sources.filesystem.paths', [$this->tmp]);
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);

    foreach ($this->extra as $dir) {
        File::deleteDirectory($dir);
    }
});

it('writes an article without a source path under locale/slug and a section under index', function (): void {
    $users = Article::factory()->public()
        ->withTranslation('en', ['title' => 'Users', 'excerpt' => null, 'body' => 'People.'])
        ->withTranslation('de', ['title' => 'Benutzer', 'excerpt' => null, 'body' => 'Personen.'])
        ->create(['slug' => 'users']);
    Article::factory()->childOf($users, 'roles')
        ->withTranslation('en', ['title' => 'Roles', 'excerpt' => null, 'body' => 'Roles.'])
        ->create();

    $report = linCodexExporterRun();

    $index = linCodexExporterFile($this->tmp, 'en/users/index.md');
    $indexDe = linCodexExporterFile($this->tmp, 'de/users/index.md');

    expect(linCodexExporterFiles($this->tmp))->toBe(['de/users/index.md', 'en/users/index.md', 'en/users/roles.md'])
        ->and($index)->toStartWith("---\ntitle: Users\n")
        ->and($index)->toContain("visibility: public\n")
        ->and($index)->toEndWith("\nPeople.\n")
        ->and($index)->not->toEndWith("\n\n")
        ->and($indexDe)->toBe("---\ntitle: Benutzer\n---\n\nPersonen.\n")
        ->and($report->count('en', 'created'))->toBe(2)
        ->and($report->count('de', 'created'))->toBe(1)
        ->and($report->hasFailures())->toBeFalse();
});

it('uses .html for html articles without a source path', function (): void {
    Article::factory()->html()
        ->withTranslation('en', ['title' => 'Guide', 'excerpt' => null, 'body' => '<p>Guide.</p>'])
        ->create(['slug' => 'guide']);

    linCodexExporterRun();

    expect(linCodexExporterFiles($this->tmp))->toBe(['en/guide.html'])
        ->and(linCodexExporterFile($this->tmp, 'en/guide.html'))->not->toContain('format:');
});

it('writes to the recorded relative source path and swaps the locale segment for other languages', function (): void {
    Article::factory()
        ->withTranslation('en', ['title' => 'Users', 'excerpt' => null, 'body' => 'People.'])
        ->withTranslation('de', ['title' => 'Benutzer', 'excerpt' => null, 'body' => 'Personen.'])
        ->create(['slug' => 'users', 'sort_order' => 2, 'source_path' => 'en/02-users/index.md']);

    linCodexExporterRun();

    $index = linCodexExporterFile($this->tmp, 'en/02-users/index.md');

    expect(linCodexExporterFiles($this->tmp))->toBe(['de/02-users/index.md', 'en/02-users/index.md'])
        ->and($index)->not->toContain('order:')
        ->and($index)->not->toContain('slug:')
        ->and(linCodexExporterFile($this->tmp, 'de/02-users/index.md'))->toStartWith("---\ntitle: Benutzer\n");
});

it('resolves a relative source path against the first configured docs path that holds the file, else the first path', function (): void {
    $second = linCodexExporterTempDir();
    $this->extra[] = $second;
    File::ensureDirectoryExists($second.'/en');
    file_put_contents($second.'/en/x.md', "---\ntitle: Stale\n---\n\nStale.\n");

    config()->set('lin-codex.sources.filesystem.paths', [$this->tmp, $second]);
    $this->forgetSources();

    Article::factory()->withTranslation('en', ['title' => 'X', 'excerpt' => null, 'body' => 'X.'])->create(['slug' => 'x', 'source_path' => 'en/x.md']);
    Article::factory()->withTranslation('en', ['title' => 'Y', 'excerpt' => null, 'body' => 'Y.'])->create(['slug' => 'y', 'source_path' => 'en/y.md']);

    $report = linCodexExporterRun();

    expect(linCodexExporterFiles($second))->toBe(['en/x.md'])
        ->and(linCodexExporterFile($second, 'en/x.md'))->toStartWith("---\ntitle: X\n")
        ->and(linCodexExporterFiles($this->tmp))->toBe(['en/y.md'])
        ->and($report->count('en', 'updated'))->toBe(1)
        ->and($report->count('en', 'created'))->toBe(1);
});

it('honours --path as the root and reports updated for existing files', function (): void {
    $other = linCodexExporterTempDir();
    $this->extra[] = $other;

    Article::factory()->withTranslation('en', ['title' => 'A', 'excerpt' => null, 'body' => 'A.'])->create(['slug' => 'a']);
    Article::factory()->withTranslation('en', ['title' => 'B', 'excerpt' => null, 'body' => 'B.'])->create(['slug' => 'b', 'source_path' => 'en/01-b.md']);

    $first = linCodexExporterRun(['path' => $other.'/']);
    $second = linCodexExporterRun(['path' => $other]);

    expect(linCodexExporterFiles($other))->toBe(['en/01-b.md', 'en/a.md'])
        ->and(linCodexExporterFiles($this->tmp))->toBe([])
        ->and($first->count('en', 'created'))->toBe(2)
        ->and($first->count('en', 'updated'))->toBe(0)
        ->and($second->count('en', 'created'))->toBe(0)
        ->and($second->count('en', 'updated'))->toBe(2);
});

it('relativises media urls and copies the images from the docs path that holds them', function (): void {
    $fixture = $this->fixtureDocsPath('docs-roundtrip');
    config()->set('lin-codex.sources.filesystem.paths', [$fixture]);
    $this->forgetSources();

    Article::factory()
        ->withTranslation('en', ['title' => 'Introduction', 'excerpt' => null, 'body' => "Hello.\n\n![Reset](/codex/media/en/images/reset.png)"])
        ->create(['slug' => 'intro', 'source_path' => 'en/01-intro.md']);
    Article::factory()
        ->withTranslation('en', ['title' => 'Roles', 'excerpt' => null, 'body' => "![Reset](/codex/media/en/images/reset.png)\n\n![Missing](/codex/media/en/images/missing.png)"])
        ->create(['slug' => 'roles', 'source_path' => 'en/02-users/01-roles.md']);

    $report = linCodexExporterRun(['path' => $this->tmp]);

    expect(linCodexExporterFile($this->tmp, 'en/01-intro.md'))->toContain('![Reset](images/reset.png)')
        ->and(linCodexExporterFile($this->tmp, 'en/02-users/01-roles.md'))->toContain('![Reset](../images/reset.png)')
        ->and(linCodexExporterFile($this->tmp, 'en/02-users/01-roles.md'))->toContain('![Missing](../images/missing.png)')
        ->and(is_file($this->tmp.'/en/images/reset.png'))->toBeTrue()
        ->and(filesize($this->tmp.'/en/images/reset.png'))->toBe(67)
        ->and(file_get_contents($this->tmp.'/en/images/reset.png'))->toBe(file_get_contents($fixture.'/en/images/reset.png'))
        ->and(is_file($this->tmp.'/en/images/missing.png'))->toBeFalse()
        ->and(linCodexExporterFiles($this->tmp))->toBe(['en/01-intro.md', 'en/02-users/01-roles.md', 'en/images/reset.png'])
        ->and($report->warnings())->toHaveCount(1)
        ->and($report->warnings()[0])->toContain('en/images/missing.png')
        ->and($report->hasFailures())->toBeFalse();
});

it('does not copy images when exporting into the docs path that holds them', function (): void {
    File::copyDirectory($this->fixtureDocsPath('docs-roundtrip'), $this->tmp);
    $image = $this->tmp.'/en/images/reset.png';
    touch($image, 1_600_000_000);
    clearstatcache(true);

    Article::factory()
        ->withTranslation('en', ['title' => 'Introduction', 'excerpt' => null, 'body' => '![Reset](/codex/media/en/images/reset.png)'])
        ->create(['slug' => 'intro', 'source_path' => 'en/01-intro.md']);

    $report = linCodexExporterRun();
    clearstatcache(true);

    expect(filemtime($image))->toBe(1_600_000_000)
        ->and(linCodexExporterFile($this->tmp, 'en/01-intro.md'))->toContain('![Reset](images/reset.png)')
        ->and($report->count('en', 'updated'))->toBe(1)
        ->and($report->warnings())->toBe([]);
});

it('keeps shared keys in the default-locale file only', function (): void {
    Article::factory()
        ->withContext(ContextType::Route, 'dashboard')
        ->withMeta(['owner' => 'docs-team'])
        ->withKeywords(['alpha'])
        ->withTranslation('en', ['title' => 'Intro', 'excerpt' => 'Start here.', 'body' => 'Hello.'])
        ->withTranslation('de', ['title' => 'Einführung', 'excerpt' => 'Hier beginnen.', 'body' => 'Hallo.'])
        ->create(['slug' => 'intro']);

    linCodexExporterRun();

    $en = linCodexExporterFile($this->tmp, 'en/intro.md');
    $de = linCodexExporterFile($this->tmp, 'de/intro.md');

    expect($en)->toContain("contexts:\n  - 'route:dashboard'\n")
        ->and($en)->toContain("owner: docs-team\n")
        ->and($en)->toContain("keywords:\n  - alpha\n")
        ->and($de)->toBe("---\ntitle: Einführung\nexcerpt: 'Hier beginnen.'\n---\n\nHallo.\n");
});

it('limits to --only and --locale and writes nothing on a dry run', function (): void {
    Article::factory()
        ->withTranslation('en', ['title' => 'A', 'excerpt' => null, 'body' => 'A.'])
        ->withTranslation('de', ['title' => 'A de', 'excerpt' => null, 'body' => 'A de.'])
        ->create(['slug' => 'a']);
    Article::factory()->withTranslation('en', ['title' => 'B', 'excerpt' => null, 'body' => 'B.'])->create(['slug' => 'b']);

    $dry = linCodexExporterRun(['dryRun' => true]);

    expect(linCodexExporterFiles($this->tmp))->toBe([])
        ->and($dry->count('en', 'created'))->toBe(2)
        ->and($dry->count('de', 'created'))->toBe(1);

    $only = linCodexExporterRun(['only' => ['b']]);

    expect(linCodexExporterFiles($this->tmp))->toBe(['en/b.md'])
        ->and($only->locales())->toBe(['en'])
        ->and($only->count('en', 'created'))->toBe(1);

    $locale = linCodexExporterRun(['locale' => 'de']);

    expect(linCodexExporterFiles($this->tmp))->toBe(['de/a.md', 'en/b.md'])
        ->and($locale->locales())->toBe(['de'])
        ->and($locale->count('de', 'created'))->toBe(1);
});

it('reports a failure when the root is not writable', function (): void {
    Article::factory()
        ->withTranslation('en', ['title' => 'A', 'excerpt' => null, 'body' => 'A.'])
        ->withTranslation('de', ['title' => 'A de', 'excerpt' => null, 'body' => 'A de.'])
        ->create(['slug' => 'a']);

    $report = linCodexExporterRun(['path' => '/proc/lin-codex-nope']);

    expect($report->hasFailures())->toBeTrue()
        ->and($report->count('en', 'failed'))->toBe(1)
        ->and($report->count('de', 'failed'))->toBe(1)
        ->and($report->count('en', 'created'))->toBe(0)
        ->and($report->failures())->toHaveKey('en:a')
        ->and($report->failures()['en:a'])->not->toBe('');
});

it('fails up front when no docs path is configured and no --path was given', function (): void {
    config()->set('lin-codex.sources.filesystem.paths', []);
    $this->forgetSources();

    Article::factory()->withTranslation('en', ['title' => 'A', 'excerpt' => null, 'body' => 'A.'])->create(['slug' => 'a']);

    expect(fn () => linCodexExporterRun())
        ->toThrow(InvalidArgumentException::class, 'lin-codex.sources.filesystem.paths');
});
