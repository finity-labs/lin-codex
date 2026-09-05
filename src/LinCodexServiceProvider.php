<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex;

use FinityLabs\LinCodex\Assets\StylesheetVersion;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Livewire\HelpCenter;
use FinityLabs\LinCodex\Livewire\HelpDrawer;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Html\SanitizerFactory;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use FinityLabs\LinCodex\View\PageHelpResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class LinCodexServiceProvider extends PackageServiceProvider
{
    public static string $name = 'lin-codex';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasAssets()
            ->hasRoute('web')
            ->hasMigrations([
                'create_codex_articles_table',
                'create_codex_article_translations_table',
                'create_codex_article_contexts_table',
                'create_codex_article_revisions_table',
                'create_codex_media_table',
                '../settings/create_codex_settings',
            ])
            ->hasConsoleCommands($this->commandClasses());
    }

    /**
     * Every class under src/Commands whose file name ends in Command.php, the
     * way the console kernel's load() discovers an app's commands. A command is
     * registered the moment its file exists, so adding one never edits this
     * provider. Console only: the directory read is skipped on web requests and
     * package-tools registers console commands only in console anyway.
     *
     * @return list<string>
     */
    private function commandClasses(): array
    {
        if (! $this->app->runningInConsole()) {
            return [];
        }

        $files = glob(__DIR__.'/Commands/*Command.php') ?: [];
        sort($files);

        return array_map(static fn (string $file): string => __NAMESPACE__.'\\Commands\\'.basename($file, '.php'), $files);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MarkdownPipeline::class);
        $this->app->singleton(HtmlSanitizerInterface::class, static fn (): HtmlSanitizer => SanitizerFactory::make());
        $this->app->singleton(HtmlPipeline::class);
        $this->app->singleton(ArticleRenderer::class);

        $this->app->singleton(FilesystemSource::class);
        $this->app->singleton(DatabaseSource::class);
        $this->app->singleton(CompositeSource::class);

        /*
         * The active source is chosen when the contract is first resolved,
         * not at boot, so a config()->set() followed by forgetInstance()
         * takes effect in tests. Nothing here touches the schema: a
         * files-only install that never ran the migrations sets
         * lin-codex.source to "filesystem" (see the config comment).
         */
        $this->app->singleton(ContentSource::class, static function (Container $app): ContentSource {
            $source = (string) config('lin-codex.source', 'composite');

            $class = match ($source) {
                'filesystem' => FilesystemSource::class,
                'database' => DatabaseSource::class,
                'composite' => CompositeSource::class,
                default => $source,
            };

            if (! is_a($class, ContentSource::class, true)) {
                throw new InvalidArgumentException(sprintf('lin-codex.source "%s" is not a ContentSource implementation.', $source));
            }

            /** @var ContentSource $instance */
            $instance = $app->make($class);

            return $instance;
        });

        $this->app->scoped(PageHelpResolver::class);
        $this->app->singleton(StylesheetVersion::class);
        $this->app->singleton(RevisionManager::class);
    }

    /**
     * The Livewire components and the Blade component namespace, then the
     * console-only stub publishes.
     *
     * The Livewire names are dotted: Livewire 4 reserves the double colon
     * for its own component namespaces, and the plain alias form is what
     * Livewire 3 consults first. One name per class, because a test by
     * class resolves the first registered name.
     *
     * <x-lin-codex::name> resolves a class under View\Components when one
     * exists and otherwise the anonymous view resources/views/components/name.
     *
     * The React and the Vue help drawer stubs are published into
     * resources/js/codex under the lin-codex-react and lin-codex-vue tags.
     * The two sets are alternatives and share codex.ts and types.ts, so
     * publishing both leaves both component pairs next to one client.
     * Registered only in console because publishes() is a console concern;
     * package-tools has no directory-publish helper, so this is the same
     * primitive it uses for its own views and migrations.
     */
    public function packageBooted(): void
    {
        Livewire::component('lin-codex.help-drawer', HelpDrawer::class);
        Livewire::component('lin-codex.help-center', HelpCenter::class);
        Blade::componentNamespace('FinityLabs\\LinCodex\\View\\Components', 'lin-codex');

        if ($this->app->runningInConsole()) {
            $stubs = $this->package->basePath('/../resources/stubs');

            $this->publishes([$stubs.'/react' => resource_path('js/codex')], 'lin-codex-react');
            $this->publishes([$stubs.'/vue' => resource_path('js/codex')], 'lin-codex-vue');
        }
    }
}
