<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Sources\SlugPath;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes an article back to the file form FrontMatter::read() and
 * ArticleAssembler accept: the inverse of the reader for everything the
 * assembler keeps. The keys come out in one fixed order and only when they
 * carry a value, so a file written from parsed data reads back to the same
 * data and a re-export of an unchanged article is a no-op diff.
 *
 * Two deliberate asymmetries with the reader: `title` is always written,
 * because a title the reader consumed from the body's first H1 cannot be put
 * back where it came from; and empty arrays are never written, because the
 * dumper's `{  }` re-parses as a mapping and can never round-trip a list.
 * The YAML goes through the same symfony/yaml the reader parses with, so the
 * dumper decides the quoting and the two agree on every value. Stateless.
 */
final class FrontMatterWriter
{
    /**
     * The Phase 3 key set in the order files are written; meta keys follow
     * in their stored order.
     */
    private const KEY_ORDER = ['title', 'excerpt', 'slug', 'icon', 'order', 'visibility', 'published', 'contexts', 'related', 'keywords', 'format'];

    /**
     * The front matter mapping for one file, in emission order. $primary is
     * true for the default-locale file, which carries every shared key; other
     * locales get title and excerpt only, because the assembler ignores
     * anything else there. $targetRelativePath (docs-relative, e.g.
     * en/02-users/01-roles.md) decides whether slug, order and format are
     * implied by the path and therefore omitted.
     *
     * @return array<string, mixed>
     */
    public function data(ArticleData $article, TranslationData $translation, bool $primary, string $targetRelativePath): array
    {
        $data = ['title' => $translation->title];

        if ($translation->excerpt !== null && $translation->excerpt !== '') {
            $data['excerpt'] = $translation->excerpt;
        }

        if (! $primary) {
            return $data;
        }

        $derived = FilePath::derive($targetRelativePath);
        $lastSegment = SlugPath::lastSegment($article->slug);

        if ($derived === null || SlugPath::lastSegment($derived['slug']) !== $lastSegment) {
            $data['slug'] = $lastSegment;
        }

        if ($article->icon !== null) {
            $data['icon'] = $article->icon;
        }

        if ($article->order !== 0 && $article->order !== ($derived['order'] ?? 0)) {
            $data['order'] = $article->order;
        }

        $data['visibility'] = $article->visibility->key();

        if (! $article->published) {
            $data['published'] = false;
        }

        if ($article->contexts !== []) {
            $contexts = $article->contexts;
            usort($contexts, static fn (ContextData $a, ContextData $b): int => $a->sortOrder <=> $b->sortOrder);
            $data['contexts'] = array_map(static fn (ContextData $context): string => $context->toString(), $contexts);
        }

        if ($article->related !== []) {
            $data['related'] = $article->related;
        }

        if ($article->keywords !== []) {
            $data['keywords'] = $article->keywords;
        }

        if ($article->format !== FilePath::format($targetRelativePath)) {
            $data['format'] = $article->format->key();
        }

        foreach ($article->meta as $key => $value) {
            if (! in_array($key, self::KEY_ORDER, true)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * The fenced block followed by one blank line and the body with exactly
     * one trailing newline.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(array $data, string $body): string
    {
        return "---\n"
            .Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
            ."---\n\n"
            .rtrim($body, "\r\n")."\n";
    }

    /**
     * data() then render(). $body is passed separately because an export
     * relativises the image paths first.
     */
    public function write(ArticleData $article, TranslationData $translation, string $body, bool $primary, string $targetRelativePath): string
    {
        return $this->render($this->data($article, $translation, $primary, $targetRelativePath), $body);
    }
}
