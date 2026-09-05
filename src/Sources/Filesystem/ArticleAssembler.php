<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Rendering\ArticlePath;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Sources\ArticleSet;
use FinityLabs\LinCodex\Sources\SlugPath;
use InvalidArgumentException;

/**
 * Turns the scanner's ArticleFile records of one docs path into an
 * ArticleSet: the locale files of one slug become one ArticleData whose
 * shared metadata (slug, order, icon, visibility, published, format,
 * contexts, related, keywords, meta) comes from the default-locale file
 * only; every other locale file contributes its title, excerpt and body
 * and gets a SharedKeyIgnored warning for anything else it carries. When
 * there is no default-locale file the first locale (sorted) supplies the
 * shared keys with a MissingDefaultLocale warning.
 *
 * Per file the body has its H1 consumed when the front matter has no title,
 * relative image references rewritten to the media route, and the search
 * text extracted through ArticleRenderer::plainText() under the render slug
 * (ArticlePath::renderSlug(), "{slug}/index" for sections), which warms the
 * render cache for Phase 4 at the same time.
 *
 * The chosen format (front matter "format" or the default-locale file's
 * extension) applies to every locale file of the article. Ancestors of a
 * slug that are not articles themselves become groups with a humanised
 * label.
 */
final class ArticleAssembler
{
    private const KNOWN_KEYS = [
        'title', 'excerpt', 'slug', 'icon', 'order', 'visibility',
        'published', 'contexts', 'related', 'keywords', 'format',
    ];

    private const TRANSLATION_KEYS = ['title' => true, 'excerpt' => true];

    public function __construct(private readonly ArticleRenderer $renderer) {}

    /**
     * @param  list<ArticleFile>  $files
     * @param  list<SourceWarning>  $warnings  scanner warnings carried into the set
     */
    public function assemble(array $files, string $defaultLocale, array $warnings = []): ArticleSet
    {
        $mediaPrefix = (string) config('lin-codex.routes.media', '/codex/media');

        /** @var array<string, array<string, ArticleFile>> $grouped */
        $grouped = [];

        foreach ($files as $file) {
            if (isset($grouped[$file->slug][$file->locale])) {
                $warnings[] = new SourceWarning(SourceWarningKind::DuplicateSlug, $file->path, $file->slug, $file->locale, $file->slug);

                continue;
            }

            $grouped[$file->slug][$file->locale] = $file;
        }

        ksort($grouped);

        /** @var array<string, true> $taken */
        $taken = array_fill_keys(array_keys($grouped), true);

        /** @var array<string, ArticleData> $articles */
        $articles = [];

        foreach ($grouped as $pathSlug => $byLocale) {
            ksort($byLocale);
            $pathSlug = (string) $pathSlug;
            $primary = $byLocale[$defaultLocale] ?? null;

            if ($primary === null) {
                $primary = $byLocale[array_key_first($byLocale)];
                $warnings[] = new SourceWarning(
                    SourceWarningKind::MissingDefaultLocale,
                    $primary->path,
                    $pathSlug,
                    $primary->locale,
                    sprintf('no %s file', $defaultLocale),
                );
            }

            $shared = $this->sharedFields($primary, $pathSlug, $taken, $warnings);
            $slug = $shared['slug'];
            $taken[$slug] = true;

            $translations = [];

            foreach ($byLocale as $locale => $file) {
                $translations[(string) $locale] = $this->translation(
                    $file,
                    $file === $primary,
                    $slug,
                    $primary->isSection,
                    $shared['format'],
                    $mediaPrefix,
                    $warnings,
                );
            }

            $articles[$slug] = new ArticleData(
                slug: $slug,
                parentSlug: SlugPath::parentOf($slug),
                order: $shared['order'],
                icon: $shared['icon'],
                format: $shared['format'],
                visibility: $shared['visibility'],
                published: $shared['published'],
                contexts: $shared['contexts'],
                related: $shared['related'],
                keywords: $shared['keywords'],
                translations: $translations,
                meta: $shared['meta'],
                isSection: $primary->isSection,
                sourcePath: $primary->path,
            );
        }

        return new ArticleSet($articles, $this->groupsFor($articles), $warnings);
    }

    /**
     * Normalise the primary file's front matter into the shared fields,
     * recording every substitution as a warning against that file.
     *
     * @param  array<string, true>  $taken  slugs already claimed by another article
     * @param  list<SourceWarning>  $warnings
     *
     * @return array{slug: string, order: int, icon: ?string, visibility: Visibility, format: ArticleFormat, published: bool, contexts: list<ContextData>, related: list<string>, keywords: list<string>, meta: array<string, mixed>}
     */
    private function sharedFields(ArticleFile $primary, string $pathSlug, array $taken, array &$warnings): array
    {
        $fm = $primary->frontMatter;
        $warn = function (SourceWarningKind $kind, string $detail) use ($primary, $pathSlug, &$warnings): void {
            $warnings[] = new SourceWarning($kind, $primary->path, $pathSlug, $primary->locale, $detail);
        };

        $slug = $pathSlug;

        if (array_key_exists('slug', $fm)) {
            $requested = $fm['slug'];

            if (is_string($requested) && SlugPath::isValidSegment($requested)) {
                $parent = SlugPath::parentOf($pathSlug);
                $renamed = ($parent !== null ? $parent.'/' : '').$requested;

                if ($renamed !== $pathSlug && isset($taken[$renamed])) {
                    $warn(SourceWarningKind::DuplicateSlug, $renamed);
                } else {
                    $slug = $renamed;
                }
            } else {
                $warn(SourceWarningKind::InvalidSlug, sprintf('slug "%s" is not kebab-case and was ignored', $this->describe($requested)));
            }
        }

        $order = $primary->order;

        if (array_key_exists('order', $fm)) {
            if (is_int($fm['order']) || is_numeric($fm['order'])) {
                $order = (int) $fm['order'];
            } else {
                $warn(SourceWarningKind::UnknownValue, sprintf('order "%s" is not a number', $this->describe($fm['order'])));
            }
        }

        $icon = isset($fm['icon']) && is_string($fm['icon']) ? $fm['icon'] : null;

        $visibility = Visibility::Authenticated;

        if (array_key_exists('visibility', $fm)) {
            $parsed = is_string($fm['visibility']) ? Visibility::tryFromKey($fm['visibility']) : null;

            if ($parsed === null) {
                $warn(SourceWarningKind::UnknownValue, sprintf('visibility "%s" is not public or authenticated', $this->describe($fm['visibility'])));
            } else {
                $visibility = $parsed;
            }
        }

        $format = $primary->format;

        if (array_key_exists('format', $fm)) {
            $parsed = is_string($fm['format']) ? ArticleFormat::tryFromKey($fm['format']) : null;

            if ($parsed === null) {
                $warn(SourceWarningKind::UnknownValue, sprintf('format "%s" is not markdown or html', $this->describe($fm['format'])));
            } else {
                $format = $parsed;
            }
        }

        $published = true;

        if (array_key_exists('published', $fm)) {
            if (is_bool($fm['published'])) {
                $published = $fm['published'];
            } else {
                $parsed = filter_var($this->describe($fm['published']), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($parsed === null) {
                    $warn(SourceWarningKind::UnknownValue, sprintf('published "%s" is not a boolean', $this->describe($fm['published'])));
                } else {
                    $published = $parsed;
                }
            }
        }

        $contexts = array_key_exists('contexts', $fm) ? $this->normaliseContexts($fm['contexts'], $warn) : [];

        if (array_key_exists('parent', $fm)) {
            $warn(SourceWarningKind::UnknownKey, 'parent');
        }

        $meta = [];

        foreach ($fm as $key => $value) {
            if (! in_array($key, self::KNOWN_KEYS, true)) {
                $meta[$key] = $value;
            }
        }

        return [
            'slug' => $slug,
            'order' => $order,
            'icon' => $icon,
            'visibility' => $visibility,
            'format' => $format,
            'published' => $published,
            'contexts' => $contexts,
            'related' => $this->stringList($fm['related'] ?? null),
            'keywords' => $this->stringList($fm['keywords'] ?? null),
            'meta' => $meta,
        ];
    }

    /**
     * Each context is "[panel:]class|route|url:key" or the one-key mapping
     * form ("- url: /users/*"), numbered by its position as written; an
     * item that does not parse is dropped with an InvalidContext warning
     * and the positions of the others are kept.
     *
     * @param  callable(SourceWarningKind, string): void  $warn
     *
     * @return list<ContextData>
     */
    private function normaliseContexts(mixed $raw, callable $warn): array
    {
        if (! is_array($raw)) {
            $warn(SourceWarningKind::UnknownValue, sprintf('contexts "%s" is not a list', $this->describe($raw)));

            return [];
        }

        $contexts = [];

        foreach (array_values($raw) as $index => $item) {
            if (is_array($item) && count($item) === 1) {
                $key = array_key_first($item);

                if (is_string($key) && is_scalar($item[$key])) {
                    $item = $key.':'.$this->describe($item[$key]);
                }
            }

            if (! is_string($item)) {
                $warn(SourceWarningKind::InvalidContext, $this->describe($item));

                continue;
            }

            try {
                $contexts[] = ContextData::fromString($item, $index);
            } catch (InvalidArgumentException) {
                $warn(SourceWarningKind::InvalidContext, $item);
            }
        }

        return $contexts;
    }

    /**
     * One locale's translation: title from the front matter, else the
     * consumed H1, else the humanised last slug segment; image paths
     * rewritten; search text through the renderer under the render slug.
     *
     * @param  list<SourceWarning>  $warnings
     */
    private function translation(
        ArticleFile $file,
        bool $isPrimary,
        string $slug,
        bool $isSection,
        ArticleFormat $format,
        string $mediaPrefix,
        array &$warnings,
    ): TranslationData {
        $fm = $file->frontMatter;
        $body = $file->body;
        $title = $this->trimmedString($fm['title'] ?? null);

        if ($title === null) {
            $extracted = TitleExtractor::extract($body, $format);
            $title = $extracted['title'];
            $body = $extracted['body'];
        }

        $title ??= SlugPath::humanise(SlugPath::lastSegment($slug));

        if (! $isPrimary) {
            $ignored = array_keys(array_diff_key($fm, self::TRANSLATION_KEYS));

            if ($ignored !== []) {
                sort($ignored);
                $warnings[] = new SourceWarning(SourceWarningKind::SharedKeyIgnored, $file->path, $slug, $file->locale, implode(', ', $ignored));
            }
        }

        $body = ImagePathRewriter::rewrite($body, $format, $file->relativeDir, $mediaPrefix);
        $searchText = $this->renderer->plainText($body, $format, $file->locale, ArticlePath::renderSlug($slug, $isSection));

        return new TranslationData(
            $file->locale,
            $title,
            $this->trimmedString($fm['excerpt'] ?? null),
            $body,
            $searchText,
            $file->path,
        );
    }

    /**
     * Every ancestor of an article slug that is not an article itself.
     *
     * @param  array<string, ArticleData>  $articles
     *
     * @return array<string, string>
     */
    private function groupsFor(array $articles): array
    {
        $groups = [];

        foreach (array_keys($articles) as $slug) {
            $ancestor = SlugPath::parentOf((string) $slug);

            while ($ancestor !== null) {
                if (! isset($articles[$ancestor])) {
                    $groups[$ancestor] = SlugPath::humanise(SlugPath::lastSegment($ancestor));
                }

                $ancestor = SlugPath::parentOf($ancestor);
            }
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    private function trimmedString(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * A value as it appears in a warning: strings verbatim, anything else
     * as JSON so a list or mapping is still readable.
     */
    private function describe(mixed $value): string
    {
        return is_string($value) ? $value : (string) json_encode($value);
    }
}
