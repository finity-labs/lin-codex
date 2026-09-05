<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Sources\Filesystem\FrontMatter;

/**
 * Pins the docs fixture tree the file source plans read, so a later edit
 * cannot silently change what those tests exercise.
 */
dataset('fixture articles', [
    'de/01-intro.md',
    'de/02-users/01-roles.md',
    'de/02-users/index.md',
    'de/nur-deutsch.md',
    'en/01-intro.md',
    'en/02-users/01-roles.md',
    'en/02-users/02-permissions.html',
    'en/02-users/index.md',
    'en/03-billing/invoices.md',
    'en/04-crlf.md',
    'en/05-duplicate.md',
    'en/06-duplicate.md',
    'en/broken.md',
    'en/escaping.md',
    'en/no-title.md',
]);

it('contains exactly the expected article files', function (): void {
    $root = $this->fixtureDocsPath();
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (in_array(strtolower($file->getExtension()), ['md', 'html'], true)) {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($files);

    expect($files)->toHaveCount(15)->toBe([
        'de/01-intro.md',
        'de/02-users/01-roles.md',
        'de/02-users/index.md',
        'de/nur-deutsch.md',
        'en/01-intro.md',
        'en/02-users/01-roles.md',
        'en/02-users/02-permissions.html',
        'en/02-users/index.md',
        'en/03-billing/invoices.md',
        'en/04-crlf.md',
        'en/05-duplicate.md',
        'en/06-duplicate.md',
        'en/broken.md',
        'en/escaping.md',
        'en/no-title.md',
    ]);
});

it('parses the front matter of every fixture except the broken one', function (string $relative): void {
    $result = FrontMatter::read((string) file_get_contents($this->fixtureDocsPath().'/'.$relative));

    if ($relative === 'en/broken.md') {
        expect($result['error'])->toBeString()->toContain('colon');

        return;
    }

    expect($result['error'])->toBeNull();
})->with('fixture articles');

it('keeps the bom and crlf bytes of the windows fixture', function (): void {
    $contents = (string) file_get_contents($this->fixtureDocsPath().'/en/04-crlf.md');
    $result = FrontMatter::read($contents);

    expect(str_starts_with($contents, "\xEF\xBB\xBF"))->toBeTrue()
        ->and(str_contains($contents, "\r\n"))->toBeTrue()
        ->and($result['data'])->toBe(['title' => 'Windows file'])
        ->and(str_starts_with($result['body'], "\xEF\xBB\xBF"))->toBeFalse();
});

it('ships the three fixture images as 1x1 pngs', function (string $path): void {
    expect(is_file($path))->toBeTrue()
        ->and(filesize($path))->toBe(67)
        ->and(str_starts_with((string) file_get_contents($path), "\x89PNG"))->toBeTrue();
})->with([
    'reset' => fn (): string => $this->fixtureDocsPath().'/en/images/reset.png',
    'users' => fn (): string => $this->fixtureDocsPath().'/en/02-users/images/users.png',
    'logo' => fn (): string => $this->fixtureDocsPath('docs-override').'/en/images/logo.png',
]);

it('ships an override tree that replaces the intro article', function (): void {
    $result = FrontMatter::read((string) file_get_contents($this->fixtureDocsPath('docs-override').'/en/01-intro.md'));

    expect($result['error'])->toBeNull()
        ->and($result['data']['title'])->toBe('Introduction (override)');
});
