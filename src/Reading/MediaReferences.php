<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Reading;

use FinityLabs\LinCodex\Data\ArticleData;

/**
 * Which articles reference which docs image, scanned from the bodies.
 *
 * Neither ArticleData nor TranslationData records image references, so the
 * bodies are the only place to find them. The file source has already
 * rewritten relative images to the media URL ("{prefix}/{locale}/{path}")
 * and database authors write that same URL, so one regex pass per
 * translation body finds every reference from every source: Markdown, HTML,
 * or any other occurrence of the prefix followed by a path, including a
 * scheme-qualified URL that contains it. A ?query or #fragment suffix is
 * dropped so the key matches what the media route receives.
 *
 * Pure, no disk access, rebuilt on every call. The media route builds it per
 * image request, which is one all() call plus one scan at help-content
 * scale; memoising it against the source fingerprint is a later
 * optimisation, deliberately not part of this release.
 */
final readonly class MediaReferences
{
    /**
     * @param  array<string, list<string>>  $owners  docs-relative image path ("en/images/x.png") => owning slugs
     */
    private function __construct(private array $owners) {}

    /**
     * @param  array<string, ArticleData>  $articles  the source's all() map
     * @param  string  $mediaPrefix  config('lin-codex.routes.media'), with or without a trailing slash
     */
    public static function fromArticles(array $articles, string $mediaPrefix): self
    {
        $prefix = rtrim($mediaPrefix, '/');
        $pattern = '~'.preg_quote($prefix, '~').'/([^\s"\'<>()]+)~';
        $owners = [];

        foreach ($articles as $article) {
            foreach ($article->translations as $translation) {
                preg_match_all($pattern, $translation->body, $matches);

                foreach ($matches[1] as $reference) {
                    $path = substr($reference, 0, strcspn($reference, '?#'));

                    if ($path !== '' && ! in_array($article->slug, $owners[$path] ?? [], true)) {
                        $owners[$path][] = $article->slug;
                    }
                }
            }
        }

        return new self($owners);
    }

    /**
     * @return list<string> owning slugs in map order, [] when no article references the path
     */
    public function owners(string $localeAndPath): array
    {
        return $this->owners[$localeAndPath] ?? [];
    }

    public function isReferenced(string $localeAndPath): bool
    {
        return isset($this->owners[$localeAndPath]);
    }
}
