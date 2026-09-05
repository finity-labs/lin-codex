<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleContext;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Models\Media;

/**
 * @return array<string, mixed>
 */
function linCodexConfigFile(): array
{
    /** @var array<string, mixed> $config */
    $config = require dirname(__DIR__, 2).'/config/lin-codex.php';

    return $config;
}

/**
 * Collect every non-scalar, non-null, non-array leaf with its dotted path.
 *
 * @param  array<array-key, mixed>  $values
 *
 * @return list<string>
 */
function linCodexNonScalarLeaves(array $values, string $prefix = ''): array
{
    $offenders = [];

    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $offenders = [...$offenders, ...linCodexNonScalarLeaves($value, $path)];

            continue;
        }

        if (! is_scalar($value) && $value !== null) {
            $offenders[] = $path.' => '.get_debug_type($value);
        }
    }

    return $offenders;
}

it('survives the var_export round trip that config:cache performs', function (): void {
    $config = linCodexConfigFile();

    $roundTrip = eval('return '.var_export($config, true).';');

    expect($roundTrip)->toBe($config);
});

it('contains only scalar or null leaves', function (): void {
    expect(linCodexNonScalarLeaves(linCodexConfigFile()))->toBe([]);
});

it('reads the table name from config at call time, not at class load', function (string $model, string $key): void {
    $runtimeName = 'runtime_'.$key;

    expect((new $model)->getTable())->not->toBe($runtimeName);

    config()->set('lin-codex.table_names.'.$key, $runtimeName);

    expect((new $model)->getTable())->toBe($runtimeName);
})->with([
    'articles' => [Article::class, 'articles'],
    'article_translations' => [ArticleTranslation::class, 'article_translations'],
    'article_contexts' => [ArticleContext::class, 'article_contexts'],
    'article_revisions' => [ArticleRevision::class, 'article_revisions'],
    'media' => [Media::class, 'media'],
]);

it('never calls config() in a class constant or static property default under src/', function (): void {
    $sourceDir = dirname(__DIR__, 2).'/src';
    $pattern = '/\b(const|static)\s+[^;=]*=\s*[^;]*\bconfig\(/';
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            continue;
        }

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($sourceDir) + 1).':'.($index + 1);
            }
        }
    }

    expect($offenders)->toBe([], 'config() captured at class load in: '.implode(', ', $offenders));
});

it('exposes every table name key and a string users table', function (): void {
    /** @var array<string, string> $tableNames */
    $tableNames = config('lin-codex.table_names');

    expect(array_keys($tableNames))->toBe([
        'articles',
        'article_translations',
        'article_contexts',
        'article_revisions',
        'media',
    ])
        ->and(array_values($tableNames))->each->toBeString()
        ->and(config('lin-codex.users_table'))->toBeString();
});
