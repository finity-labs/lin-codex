<?php

declare(strict_types=1);

/**
 * The package supports Livewire 3 and 4 with one code base, so the views
 * and the PHP side may only use the API both versions share. Features that
 * Livewire 4 added or renamed are listed here and rejected by a plain scan,
 * the way ConfigCacheSafetyTest guards config() at class load.
 */

/**
 * Every file with the extension under a directory, recursively.
 *
 * @return list<string>
 */
function linCodexSharedApiFiles(string $directory, string $extension): array
{
    $paths = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), $extension)) {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

/**
 * The "path:line" of every line in the files matching one of the patterns.
 *
 * @param  list<string>  $files
 * @param  list<string>  $patterns  regular expressions
 *
 * @return list<string>
 */
function linCodexSharedApiOffenders(array $files, array $patterns, string $root): array
{
    $offenders = [];

    foreach ($files as $path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            continue;
        }

        foreach ($lines as $index => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = substr($path, strlen($root) + 1).':'.($index + 1);

                    break;
                }
            }
        }
    }

    return $offenders;
}

it('uses no view feature outside the Livewire 3 and 4 shared API', function (): void {
    $root = dirname(__DIR__, 3);
    $files = linCodexSharedApiFiles($root.'/resources/views', '.blade.php');

    $offenders = linCodexSharedApiOffenders($files, [
        '/wire:transition/',
        '/wire:navigate/',
        '/wire:model\.blur/',
        '/wire:model\.lazy/',
        '/wire:model\.change/',
        '/\$js\(/',
        '/@island/',
        '/wire:intersect/',
        '/wire:sort/',
        '/wire:ref/',
        '/x-navigate/',
    ], $root);

    expect($offenders)->toBe([], 'Livewire 4-only or renamed view features in: '.implode(', ', $offenders));
});

it('uses no PHP feature outside the Livewire 3 and 4 shared API', function (): void {
    $root = dirname(__DIR__, 3);
    $files = linCodexSharedApiFiles($root.'/src', '.php');

    $offenders = linCodexSharedApiOffenders($files, [
        '/addNamespace\(/',
        '/Route::livewire/',
        '/#\[Layout/',
        '/Livewire::component\(\s*[\'"][^\'"]*::/',
    ], $root);

    expect($offenders)->toBe([], 'Livewire 4-only features or a namespaced component name in: '.implode(', ', $offenders));
});
