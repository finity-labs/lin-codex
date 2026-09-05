<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use FinityLabs\LinCodex\Sources\Filesystem\ArticleFile;
use FinityLabs\LinCodex\Sources\Filesystem\DocsScanner;
use Illuminate\Support\Facades\File;

/**
 * @param  list<ArticleFile>  $files
 */
function linCodexFileFor(array $files, string $suffix): ArticleFile
{
    foreach ($files as $file) {
        if (str_ends_with(str_replace('\\', '/', $file->path), $suffix)) {
            return $file;
        }
    }

    throw new RuntimeException('No article file ending with '.$suffix);
}

/**
 * @param  list<ArticleFile>  $files
 *
 * @return list<string>
 */
function linCodexRelativePaths(array $files, string $root): array
{
    return array_map(
        fn (ArticleFile $file): string => str_replace('\\', '/', substr($file->path, strlen($root) + 1)),
        $files,
    );
}

describe('fixture tree', function (): void {
    beforeEach(function (): void {
        $this->scan = (new DocsScanner)->scan($this->fixtureDocsPath());
    });

    it('loads fourteen files and skips the broken one with a warning', function (): void {
        expect($this->scan['files'])->toHaveCount(14)
            ->and($this->scan['warnings'])->toHaveCount(1);

        $warning = $this->scan['warnings'][0];

        expect($warning)->toBeInstanceOf(SourceWarning::class)
            ->and($warning->kind)->toBe(SourceWarningKind::InvalidFrontMatter)
            ->and($warning->path)->toEndWith('en/broken.md')
            ->and($warning->locale)->toBe('en')
            ->and($warning->slug)->toBe('broken')
            ->and($warning->detail)->toContain('colon');
    });

    it('walks locale folders in name order and files in natural order', function (): void {
        $locales = array_values(array_unique(array_map(fn (ArticleFile $file): string => $file->locale, $this->scan['files'])));
        $paths = linCodexRelativePaths($this->scan['files'], $this->fixtureDocsPath());

        expect($locales)->toBe(['de', 'en'])
            ->and(array_search('en/02-users/01-roles.md', $paths, true))
            ->toBeLessThan(array_search('en/02-users/02-permissions.html', $paths, true))
            ->and(array_search('en/05-duplicate.md', $paths, true))
            ->toBeLessThan(array_search('en/06-duplicate.md', $paths, true))
            ->and(array_search('de/nur-deutsch.md', $paths, true))
            ->toBeLessThan(array_search('en/01-intro.md', $paths, true));
    });

    it('derives the slug, order, folder and raw front matter of a leaf file', function (): void {
        $file = linCodexFileFor($this->scan['files'], 'en/02-users/01-roles.md');

        expect($file->slug)->toBe('users/roles')
            ->and($file->order)->toBe(1)
            ->and($file->isSection)->toBeFalse()
            ->and($file->locale)->toBe('en')
            ->and($file->relativeDir)->toBe('en/02-users')
            ->and($file->format)->toBe(ArticleFormat::Markdown)
            ->and($file->frontMatter)->toBe(['title' => 'Roles', 'published' => 'no', 'parent' => 'users', 'keywords' => ['rbac']])
            ->and($file->body)->toStartWith('Roles group permissions');
    });

    it('marks an index file as its folder section with the folder order', function (): void {
        $file = linCodexFileFor($this->scan['files'], 'en/02-users/index.md');

        expect($file->slug)->toBe('users')
            ->and($file->order)->toBe(2)
            ->and($file->isSection)->toBeTrue()
            ->and($file->relativeDir)->toBe('en/02-users')
            ->and($file->frontMatter)->toBe(['visibility' => 'public'])
            ->and($file->body)->toStartWith('# Users');
    });

    it('reads html files with the html format and the body untouched', function (): void {
        $file = linCodexFileFor($this->scan['files'], 'en/02-users/02-permissions.html');

        expect($file->format)->toBe(ArticleFormat::Html)
            ->and($file->body)->toStartWith('<h1>Permissions</h1>');
    });

    it('strips the byte order mark from a windows file', function (): void {
        $file = linCodexFileFor($this->scan['files'], 'en/04-crlf.md');

        expect($file->frontMatter)->toBe(['title' => 'Windows file'])
            ->and(str_starts_with($file->body, "\xEF\xBB\xBF"))->toBeFalse();
    });

    it('gives a file without front matter an empty mapping and order zero', function (): void {
        $file = linCodexFileFor($this->scan['files'], 'en/no-title.md');

        expect($file->frontMatter)->toBe([])
            ->and($file->order)->toBe(0);
    });

    it('records an absolute path and the locale as the folder of a root file', function (): void {
        $file = linCodexFileFor($this->scan['files'], 'en/01-intro.md');

        expect($file->relativeDir)->toBe('en')
            ->and($file->path)->toStartWith('/')
            ->and(is_file($file->path))->toBeTrue();
    });
});

it('treats a missing folder as empty without a warning', function (): void {
    expect((new DocsScanner)->scan(sys_get_temp_dir().'/lin-codex-does-not-exist-'.uniqid()))
        ->toBe(['files' => [], 'warnings' => []]);
});

it('skips a root index file, ignores non-locale folders and normalises file names', function (): void {
    $tmp = sys_get_temp_dir().'/lin-codex-scan-'.uniqid();
    mkdir($tmp.'/en', 0777, true);
    mkdir($tmp.'/images', 0777, true);
    file_put_contents($tmp.'/en/index.md', "Root index\n");
    file_put_contents($tmp.'/images/x.md', "Not a locale\n");
    file_put_contents($tmp.'/en/Reset Password.md', "# Reset\n");

    try {
        $scan = (new DocsScanner)->scan($tmp);
    } finally {
        File::deleteDirectory($tmp);
    }

    expect($scan['files'])->toHaveCount(1)
        ->and($scan['files'][0]->slug)->toBe('reset-password')
        ->and($scan['files'][0]->locale)->toBe('en')
        ->and($scan['warnings'])->toHaveCount(2);

    $kinds = array_map(fn (SourceWarning $warning): SourceWarningKind => $warning->kind, $scan['warnings']);
    $paths = array_map(fn (SourceWarning $warning): string => (string) $warning->path, $scan['warnings']);

    expect($kinds)->toBe([SourceWarningKind::InvalidSlug, SourceWarningKind::InvalidSlug])
        ->and($paths[0])->toEndWith('en/Reset Password.md')
        ->and($scan['warnings'][0]->detail)->toContain('reset-password')
        ->and($scan['warnings'][0]->slug)->toBe('reset-password')
        ->and($paths[1])->toEndWith('en/index.md')
        ->and($scan['warnings'][1]->slug)->toBeNull()
        ->and(implode(' ', $paths))->not->toContain('images/x.md');
});
