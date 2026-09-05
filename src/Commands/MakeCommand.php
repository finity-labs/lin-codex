<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Sources\DefaultLocale;
use FinityLabs\LinCodex\Sources\Filesystem\FilePath;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use FinityLabs\LinCodex\Sources\SlugPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Inline;

/**
 * Write the first file of a new article under the first configured docs
 * path: {locale}/{slug}.md, {slug}/index.md with --section, .html with
 * --format=html. The scaffold doubles as the syntax cheat sheet for
 * authors: every front matter key with a value, commented examples of
 * the list keys (contexts, related, keywords), and a body that shows a
 * heading, a paragraph, a :::steps block with a figure and a callout.
 *
 * The excerpt is written empty on purpose so the author fills it in;
 * the reader treats a blank excerpt as none. The file is a hand-built
 * string because YAML comments cannot come out of the dumper, and the
 * title goes through Inline::dump(), which is what keeps a title with a
 * colon parseable. Body text comes from the make.* lang group in the
 * file's locale, falling back to English.
 */
final class MakeCommand extends Command
{
    protected $signature = 'codex:make
        {slug : The article slug, folders included (users/roles)}
        {--locale= : Language folder; defaults to the settings default locale}
        {--title= : Article title; defaults to the humanised last segment}
        {--section : Create slug/index.md for a folder that will hold children}
        {--format=markdown : markdown or html}
        {--force : Overwrite an existing file}';

    protected $description = 'Scaffold a new article file with front matter and a starter body.';

    public function handle(FilesystemSource $files, DefaultLocale $defaultLocale): int
    {
        $slug = trim((string) $this->argument('slug'), '/');

        if (! $this->isValidSlug($slug)) {
            $this->error(sprintf('"%s" is not a valid slug (lowercase letters, digits and dashes, folders separated by /).', $slug));

            return self::FAILURE;
        }

        $format = ArticleFormat::tryFromKey((string) $this->option('format'));

        if ($format === null) {
            $this->error('--format must be markdown or html.');

            return self::FAILURE;
        }

        $root = $files->paths()[0] ?? null;

        if ($root === null) {
            $this->error('No docs path configured (lin-codex.sources.filesystem.paths).');

            return self::FAILURE;
        }

        $locale = (string) ($this->option('locale') ?: $defaultLocale->get());

        if (! FilePath::isLocaleFolder($locale)) {
            $this->error(sprintf('"%s" is not a locale folder name (en, de, pt-BR).', $locale));

            return self::FAILURE;
        }

        $title = (string) ($this->option('title') ?: SlugPath::humanise(SlugPath::lastSegment($slug)));
        $relative = $locale.'/'.$slug.($this->option('section') ? '/index' : '').($format === ArticleFormat::Html ? '.html' : '.md');
        $target = rtrim($root, '/').'/'.$relative;

        if (File::exists($target) && ! $this->option('force')) {
            $this->error(sprintf('%s already exists. Pass --force to overwrite it.', $relative));

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $this->frontMatter($title).$this->body($format, $locale));

        $this->info(sprintf('Created %s', $relative));
        $this->comment('  Add contexts to show it on a page; the file comments show the syntax.');

        return self::SUCCESS;
    }

    /**
     * Every segment of the slash path must be kebab-case; an empty path or
     * an empty segment (from a double slash) fails.
     */
    private function isValidSlug(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        foreach (explode('/', $slug) as $segment) {
            if (! SlugPath::isValidSegment($segment)) {
                return false;
            }
        }

        return true;
    }

    private function frontMatter(string $title): string
    {
        $lines = [
            '---',
            'title: '.Inline::dump($title),
            'excerpt: '.Inline::dump(''),
            'order: 0',
            'visibility: '.Visibility::Authenticated->key(),
            'published: true',
            'contexts: []',
            '# contexts:',
            '#   - route:users.index',
            '#   - url:/admin/users/*',
            '#   - class:App\Filament\Resources\UserResource',
            '#   - admin:class:App\Filament\Resources\UserResource',
            '# related:',
            '#   - users/roles',
            '# keywords:',
            '#   - accounts',
            '---',
            '',
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * The starter body in the locale's language. The steps block and the
     * callout are Markdown-only syntax, so the HTML form uses an ordered
     * list and a plain paragraph instead.
     */
    private function body(ArticleFormat $format, string $locale): string
    {
        $t = static fn (string $key): string => (string) __('lin-codex::lin-codex.make.'.$key, [], $locale);

        if ($format === ArticleFormat::Html) {
            $e = static fn (string $text): string => e($text);

            return implode("\n", [
                '<h2>'.$e($t('heading')).'</h2>',
                '<p>'.$e($t('intro')).'</p>',
                '<ol>',
                '  <li>'.$e($t('step_one')).'</li>',
                '  <li>'.$e($t('step_two')).'<figure><img src="images/example.png" alt="'.$e($t('figure_alt')).'"><figcaption>'.$e($t('figure_caption')).'</figcaption></figure></li>',
                '</ol>',
                '<p>'.$e($t('tip')).'</p>',
            ])."\n";
        }

        $caption = str_replace('"', '\\"', $t('figure_caption'));

        return implode("\n", [
            '## '.$t('heading'),
            '',
            $t('intro'),
            '',
            ':::steps',
            '1. '.$t('step_one'),
            '2. '.$t('step_two'),
            '',
            '   !['.$t('figure_alt').'](images/example.png "'.$caption.'")',
            ':::',
            '',
            '> [!TIP]',
            '> '.$t('tip'),
        ])."\n";
    }
}
