<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Livewire\Concerns;

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Reading\ReadArticle;
use FinityLabs\LinCodex\View\PageHelpResolver;
use Livewire\Attributes\Locked;

/**
 * The page state a help component captures once, at mount, and never
 * re-detects: the canonical page context, the locale and the articles for
 * the page, all locked so the client cannot change them.
 *
 * mount() is the only place that may touch the request. Every later
 * Livewire request hits Livewire's own update route, so "this page" reads
 * $pageArticles and nothing here asks the router again. The viewer is
 * resolved per request and never stored: it may hold an Eloquent user.
 */
trait CapturesPageHelp
{
    /** @var array{route: ?string, path: string, class: ?string, panel: ?string} */
    #[Locked]
    public array $page = ['route' => null, 'path' => '/', 'class' => null, 'panel' => null];

    #[Locked]
    public string $locale = 'en';

    /** @var list<array{slug: string, title: string, excerpt: ?string, isFallback: bool}> */
    #[Locked]
    public array $pageArticles = [];

    protected function capturePageHelp(PageHelpResolver $resolver, ?string $pageClass, ?string $panelId, ?string $locale): void
    {
        $help = $resolver->for($pageClass, $panelId, $locale);

        $this->page = $help->context->toArray();
        $this->locale = $help->locale;
        $this->pageArticles = $help->articles;
    }

    protected function pageContext(): PageContext
    {
        return PageContext::fromArray($this->page);
    }

    /**
     * Who is reading, resolved for the current request.
     */
    protected function viewer(): Viewer
    {
        return app(ViewerResolver::class)->resolve();
    }

    /**
     * The "not available in your language" line for a fallback read, null
     * for an exact translation.
     */
    protected function fallbackNoticeFor(ReadArticle $read): ?string
    {
        return $read->isFallback ? app(LocaleResolver::class)->fallbackNotice($read->locale) : null;
    }
}
