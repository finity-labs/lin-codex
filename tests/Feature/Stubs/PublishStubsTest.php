<?php

declare(strict_types=1);

use FinityLabs\LinCodex\LinCodexServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Where vendor:publish drops the stubs: the Testbench app's resources/js/codex.
 */
function linCodexStubsTarget(): string
{
    return resource_path('js/codex');
}

/**
 * The package's own copy of one stub set, "react" or "vue".
 */
function linCodexStubsSource(string $set): string
{
    return dirname(__DIR__, 3).'/resources/stubs/'.$set;
}

it('publishes each stub set into resources/js/codex', function (string $tag, string $set, array $files): void {
    $paths = ServiceProvider::pathsToPublish(LinCodexServiceProvider::class, $tag);

    expect($paths)->toHaveCount(1);

    $from = (string) array_key_first($paths);

    expect(realpath($from))->toBe(realpath(linCodexStubsSource($set)))
        ->and($paths[$from])->toEndWith('resources/js/codex');

    $target = linCodexStubsTarget();
    $hadJs = File::isDirectory(resource_path('js'));
    File::deleteDirectory($target);

    try {
        Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);

        foreach ($files as $file) {
            expect(File::exists($target.'/'.$file))->toBeTrue($file.' was not published under '.$target);
        }

        expect(File::get($target.'/codex.ts'))
            ->toContain('/tree', '/articles/', '/search', '/context', "credentials: 'same-origin'", 'Retry-After');

        $drawer = array_values(array_filter($files, static fn (string $file): bool => str_starts_with($file, 'HelpDrawer')))[0];

        expect(File::get($target.'/'.$drawer))->toContain('codex:open', 'ctrlKey', 'data-codex-article')
            ->and(File::get($target.'/README.md'))->toContain('RequestContextDetector');
    } finally {
        File::deleteDirectory($target);

        if (! $hadJs) {
            File::deleteDirectory(resource_path('js'));
        }
    }
})->with([
    'react' => ['lin-codex-react', 'react', ['codex.ts', 'types.ts', 'HelpButton.tsx', 'HelpDrawer.tsx', 'README.md']],
    'vue' => ['lin-codex-vue', 'vue', ['codex.ts', 'types.ts', 'HelpButton.vue', 'HelpDrawer.vue', 'README.md']],
]);

it('keeps the shared client identical in both sets', function (): void {
    expect(File::get(linCodexStubsSource('react').'/codex.ts'))->toBe(File::get(linCodexStubsSource('vue').'/codex.ts'))
        ->and(File::get(linCodexStubsSource('react').'/types.ts'))->toBe(File::get(linCodexStubsSource('vue').'/types.ts'));
});

it('registers both tags as publishable groups', function (): void {
    expect(ServiceProvider::publishableGroups())->toContain('lin-codex-react', 'lin-codex-vue');
});

it('ships the stubs in the dist package', function (): void {
    $attributes = File::get(dirname(__DIR__, 3).'/.gitattributes');

    expect(preg_match('/^\/?resources\b.*export-ignore/m', $attributes))->toBe(0);
});

it('mirrors every frozen payload key in the types', function (): void {
    $types = File::get(linCodexStubsSource('react').'/types.ts');

    $keys = [
        'slug', 'title', 'excerpt', 'locale', 'isFallback', 'format', 'html', 'toc', 'breadcrumbs', 'related', 'icon', 'updatedAt',
        'sectionPath', 'snippet', 'matchedField', 'score',
        'isGroup', 'hasArticle', 'children',
        'defaultLocale', 'rateLimited', 'retryAfterSeconds', 'panel',
    ];

    foreach ($keys as $key) {
        expect($types)->toMatch('/\b'.preg_quote($key, '/').'\??:/', 'types.ts does not declare '.$key);
    }
});
