<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Reading;

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Rendering\RenderedArticle;

/**
 * What a viewer gets back for a slug: the article, the translation the
 * locale rule picked, the rendered body and the related slugs and ancestor
 * titles the same viewer may see in the same locale.
 *
 * `locale` is the translation's own locale, which differs from the requested
 * one under fallback (isFallback true, the UI shows the fallback notice).
 * The body is rendered eagerly so the object is a plain readonly value: a
 * closure would fail serialize() and the no-model walk.
 */
final readonly class ReadArticle
{
    /**
     * @param  list<array{slug: string, title: string}>  $related  related articles the same viewer may read in the same locale, in the article's related order, each with the title the locale rule picked
     * @param  list<array{slug: string, title: string}>  $breadcrumbs  visible ancestor articles root-first with their picked titles
     */
    public function __construct(
        public ArticleData $article,
        public TranslationData $translation,
        public string $locale,
        public bool $isFallback,
        public RenderedArticle $rendered,
        public array $related,
        public array $breadcrumbs = [],
    ) {}
}
