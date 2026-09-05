<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contexts\ContextResolver;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Http\Json\ArticlePayload;
use FinityLabs\LinCodex\Http\Json\ContextPayload;
use FinityLabs\LinCodex\Http\Json\SearchPayload;
use FinityLabs\LinCodex\Http\Json\TreePayload;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Reading\ArticleReader;
use FinityLabs\LinCodex\Reading\ReadArticle;
use FinityLabs\LinCodex\Reading\TreeBuilder;
use FinityLabs\LinCodex\Search\Searcher;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;

/**
 * Every .php file under a directory, recursively, as absolute paths.
 *
 * @return list<string>
 */
function linCodexApiModelsPhpFiles(string $directory): array
{
    $paths = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

/**
 * The number of queries naming the articles table that $run issues. The log
 * is cleared before and after so measurements never bleed into each other.
 */
function linCodexApiModelsArticleQueries(callable $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $run();

    $table = DB::connection()->getQueryGrammar()->wrapTable('codex_articles');
    $count = count(array_filter(DB::getQueryLog(), fn (array $entry): bool => str_contains($entry['query'], $table)));

    DB::flushQueryLog();

    return $count;
}

beforeEach(function (): void {
    config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
    config()->set('lin-codex.source', 'filesystem');
    $this->forgetSources();
});

it('never references models or the query builder from the api sources', function (): void {
    $root = dirname(__DIR__, 3).'/src/Http';
    $directories = [$root.'/Controllers/Api', $root.'/Json'];
    $pattern = '/LinCodex\\\\Models\\\\|Illuminate\\\\Database\\\\|\bDB::/';
    $offenders = [];

    foreach ($directories as $directory) {
        expect(is_dir($directory))->toBeTrue($directory.' is missing');

        $files = linCodexApiModelsPhpFiles($directory);

        expect(count($files))->toBeGreaterThanOrEqual(4, $directory.' holds fewer than four php files');

        foreach ($files as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = substr($path, strlen($root) + 1).':'.($index + 1);
                }
            }
        }
    }

    expect($offenders)->toBe([], 'model or query builder references in the api sources: '.implode(', ', $offenders));
});

it('builds scalar-only payloads from readonly inputs', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $viewer = app(ViewerResolver::class)->resolve();
    $locales = app(LocaleResolver::class);
    $page = new PageContext(null, '/');

    $tree = app(TreeBuilder::class)->build($viewer);
    $read = app(ArticleReader::class)->read('intro', $viewer);
    $result = app(Searcher::class)->search('users', $viewer);
    $articles = app(ContextResolver::class)->resolve($page, $viewer);

    expect($read)->not->toBeNull()
        ->and($tree)->not->toBe([])
        ->and($result->hits)->not->toBe([])
        ->and($articles)->not->toBe([]);

    linCodexAssertNoModels($tree);
    linCodexAssertNoModels($read);
    linCodexAssertNoModels($result);
    linCodexAssertNoModels($articles);

    /** @var ReadArticle $read */
    $payloads = [
        'tree' => TreePayload::make($tree, 'en', 'en'),
        'article' => ArticlePayload::make($read, 'en', 'en'),
        'search' => SearchPayload::make($result, 10, 'en', 'en'),
        'context' => ContextPayload::make($articles, $page, 'en', 'en', $locales),
    ];

    foreach ($payloads as $name => $payload) {
        expect(array_keys($payload))->toBe(['data', 'meta'], $name)
            ->and(json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))->toBe($payload, $name.' payload does not round-trip through json');
    }
});

it('issues exactly the queries the read services issue', function (): void {
    config()->set('lin-codex.source', 'database');
    $this->forgetSources();

    foreach ([['alpha', 'Alpha title word'], ['beta', 'Beta title word'], ['gamma', 'Gamma title word']] as [$slug, $title]) {
        $factory = Article::factory()
            ->public()
            ->published()
            ->state(['slug' => $slug, 'sort_order' => 0])
            ->withTranslation('en', ['title' => $title, 'body' => '# '.$title."\n\nBody of ".$slug.'.']);

        if ($slug === 'alpha') {
            $factory = $factory->withContext(ContextType::Url, '/q');
        }

        $factory->create();
    }

    $guest = app(ViewerResolver::class)->resolve();

    // Warm the render cache so neither side pays the first render.
    $this->getJson('/codex/api/articles/alpha')->assertOk();

    $pairs = [
        'tree' => [
            fn () => app()->make(TreeBuilder::class)->build($guest),
            fn () => $this->getJson('/codex/api/tree')->assertOk(),
        ],
        'article' => [
            fn () => app()->make(ArticleReader::class)->read('alpha', $guest),
            fn () => $this->getJson('/codex/api/articles/alpha')->assertOk(),
        ],
        'context' => [
            fn () => app()->make(ContextResolver::class)->resolve(new PageContext(null, '/q'), $guest),
            fn () => $this->getJson('/codex/api/context?path=/q')->assertOk(),
        ],
        'search' => [
            fn () => app()->make(Searcher::class)->search('title word', $guest),
            fn () => $this->getJson('/codex/api/search?q=title+word')->assertOk(),
        ],
    ];

    foreach ($pairs as $name => [$service, $request]) {
        $serviceQueries = linCodexApiModelsArticleQueries($service);
        $requestQueries = linCodexApiModelsArticleQueries($request);

        expect($requestQueries)->toBeGreaterThanOrEqual(1, $name.' endpoint issued no article query')
            ->and($requestQueries)->toBe($serviceQueries, $name.' endpoint issued '.$requestQueries.' article queries against '.$serviceQueries.' from the service');
    }
});
