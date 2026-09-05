<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Support\Facades\File;

/**
 * The lossless half of CMD-02 at the command level: codex:import over the
 * canonical docs-roundtrip fixture, then codex:export into an empty folder,
 * must reproduce every article file byte for byte (trailing newline
 * normalised) and both images, with nothing extra. The commands are driven
 * through artisan so the CLI leg is what is proven, not the services alone.
 */
function linCodexRoundTripFiles(string $root): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }

    sort($files);

    return $files;
}

function linCodexRoundTripNormalised(string $file): string
{
    return rtrim((string) file_get_contents($file), "\n")."\n";
}

/**
 * Compare every file under the fixture with its twin under $root.
 */
function linCodexRoundTripAssertIdentical(string $fixture, string $root): void
{
    $expected = linCodexRoundTripFiles($fixture);

    expect(linCodexRoundTripFiles($root))->toBe($expected);

    foreach ($expected as $relative) {
        expect(is_file($root.'/'.$relative))->toBeTrue($relative.' is missing');

        if (str_ends_with($relative, '.png')) {
            expect(file_get_contents($root.'/'.$relative))->toBe(file_get_contents($fixture.'/'.$relative), 'Image mismatch for '.$relative);

            continue;
        }

        expect(linCodexRoundTripNormalised($root.'/'.$relative))
            ->toBe(linCodexRoundTripNormalised($fixture.'/'.$relative), 'Round trip mismatch for '.$relative);
    }
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/lin-codex-sync-'.uniqid();
    File::ensureDirectoryExists($this->tmp);
    $this->fixture = $this->fixtureDocsPath('docs-roundtrip');

    config()->set('lin-codex.sources.filesystem.paths', [$this->fixture]);
    config()->set('lin-codex.source', 'composite');
    $this->forgetSources();
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

it('imports the fixture and exports it back identically', function (): void {
    $this->artisan('codex:import')->assertExitCode(0);
    $this->artisan('codex:export', ['--path' => $this->tmp])->assertExitCode(0);

    expect(linCodexRoundTripFiles($this->fixture))->toHaveCount(11)
        ->and(Article::query()->count())->toBe(6);

    linCodexRoundTripAssertIdentical($this->fixture, $this->tmp);
});

it('exports the same bytes with revisions enabled and after a forced re-import', function (): void {
    $settings = app(CodexSettings::class);
    $settings->revisions_enabled = true;
    $settings->save();

    $this->artisan('codex:import')->assertExitCode(0);
    $this->artisan('codex:export', ['--path' => $this->tmp])->assertExitCode(0);

    $this->artisan('codex:import', ['--force' => true])
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '0', '3', '0', '0'],
            ['en', '0', '6', '0', '0'],
        ])
        ->assertExitCode(0);
    $this->artisan('codex:export', ['--path' => $this->tmp])
        ->expectsTable(['Locale', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['de', '0', '3', '0', '0'],
            ['en', '0', '6', '0', '0'],
        ])
        ->assertExitCode(0);

    linCodexRoundTripAssertIdentical($this->fixture, $this->tmp);

    expect(ArticleRevision::query()->count())->toBe(0);
});
