<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Livewire;

use FinityLabs\LinCodex\Data\TreeNode;
use FinityLabs\LinCodex\Livewire\Concerns\CapturesPageHelp;
use FinityLabs\LinCodex\Livewire\Concerns\SearchesArticles;
use FinityLabs\LinCodex\Reading\ArticleReader;
use FinityLabs\LinCodex\Reading\ReadArticle;
use FinityLabs\LinCodex\Reading\TreeBuilder;
use FinityLabs\LinCodex\View\PageHelpResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The in-app help drawer, registered as "lin-codex.help-drawer" and
 * rendered by <livewire:lin-codex.help-drawer /> or the
 * <x-lin-codex::help-drawer> wrapper.
 *
 * One component, one panel, four views: "page" (the articles captured for
 * the current page, or the no-help line with the tree), "search" (the hits
 * for the bound query), "tree" (every article the viewer may read) and
 * "article" (one slug). open() shows the first page article, show() opens
 * a slug, goTo() switches to page, search or tree, back() walks a bounded
 * history one view at a time and close() hides the panel without touching
 * any of it, so the next open resumes where the reader was.
 *
 * Nothing after mount() reads the request: every later Livewire request
 * hits Livewire's own update route, so "this page" always means the locked
 * $pageArticles the resolver captured on the page itself. The article and
 * the tree are computed per render from the read services for the current
 * viewer, never stored: a rendered article or a tree node is not a scalar,
 * the viewer may change between requests, and the read cache already makes
 * the second read cheap. A hidden or missing slug reads as null and renders
 * the not-found line, never a 403.
 */
final class HelpDrawer extends Component
{
    use CapturesPageHelp;
    use SearchesArticles;

    /** The deepest back stack a reader can build before the oldest entry drops. */
    private const HISTORY_LIMIT = 20;

    public bool $isOpen = false;

    /** One of page, search, tree or article. */
    public string $view = 'page';

    /** @var list<array{view: string, slug: ?string}> */
    public array $history = [];

    public ?string $slug = null;

    public function mount(PageHelpResolver $resolver, ?string $slug = null, ?string $pageClass = null, ?string $panelId = null, ?string $locale = null): void
    {
        $this->capturePageHelp($resolver, $pageClass, $panelId, $locale);

        if ($slug !== null) {
            $this->open($slug);
        }
    }

    /**
     * Open the panel: on a slug that article, on a fresh drawer the first
     * page article, otherwise wherever the reader left off.
     */
    public function open(?string $slug = null): void
    {
        $this->isOpen = true;

        if ($slug !== null) {
            $this->show($slug);

            return;
        }

        if ($this->view === 'page' && $this->slug === null && $this->pageArticles !== []) {
            $this->show($this->pageArticles[0]['slug']);
        }
    }

    public function show(string $slug): void
    {
        $this->isOpen = true;

        if ($this->view === 'article' && $this->slug === $slug) {
            return;
        }

        $this->push();
        $this->slug = $slug;
        $this->view = 'article';
    }

    /**
     * Switch to page, search or tree. "page" returns to the first article
     * captured at mount (or the page list when there is none); tree and
     * search keep the slug so back() lands on the article again. Unknown
     * names are ignored.
     */
    public function goTo(string $view): void
    {
        if (! in_array($view, ['page', 'search', 'tree'], true)) {
            return;
        }

        $this->push();

        if ($view === 'page') {
            $first = $this->pageArticles[0]['slug'] ?? null;
            $this->slug = $first;
            $this->view = $first === null ? 'page' : 'article';

            return;
        }

        $this->view = $view;
    }

    /**
     * Restore the previous view. A search view whose query was cleared in
     * the meantime is skipped; an empty history lands on the page view.
     */
    public function back(): void
    {
        while (($last = array_pop($this->history)) !== null) {
            if ($last['view'] === 'search' && trim($this->query) === '') {
                continue;
            }

            $this->view = $last['view'];
            $this->slug = $last['slug'];

            return;
        }

        $this->view = 'page';
        $this->slug = null;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    /**
     * wire:model.live on the search box: a query switches to the results,
     * clearing it returns to the previous view.
     */
    public function updatedQuery(): void
    {
        $query = trim($this->query);

        if ($query !== '' && $this->view !== 'search') {
            $this->push();
            $this->view = 'search';

            return;
        }

        if ($query === '' && $this->view === 'search') {
            $this->back();
        }
    }

    /**
     * The current article for the current viewer in the locked locale, null
     * when there is no slug or the viewer may not read it.
     */
    public function article(): ?ReadArticle
    {
        if ($this->slug === null) {
            return null;
        }

        return app(ArticleReader::class)->read($this->slug, $this->viewer(), $this->locale);
    }

    /**
     * @return list<TreeNode>
     */
    public function tree(): array
    {
        return app(TreeBuilder::class)->build($this->viewer(), $this->locale);
    }

    public function render(): View
    {
        $read = null;
        $fallbackNotice = null;
        $also = [];
        $result = null;
        $nodes = [];

        if ($this->view === 'article') {
            $read = $this->article();
            $fallbackNotice = $read === null ? null : $this->fallbackNoticeFor($read);

            if ($read !== null && count($this->pageArticles) > 1 && in_array($this->slug, array_column($this->pageArticles, 'slug'), true)) {
                $also = $this->pageArticles;
            }
        } elseif ($this->view === 'search') {
            $result = $this->hasSearchQuery() ? $this->searchResult() : null;
        } elseif ($this->view === 'tree' || $this->pageArticles === []) {
            $nodes = $this->tree();
        }

        $title = match ($this->view) {
            'search' => __('lin-codex::lin-codex.ui.search'),
            'tree' => __('lin-codex::lin-codex.ui.browse'),
            'article' => $read?->translation->title ?? __('lin-codex::lin-codex.ui.title'),
            default => __('lin-codex::lin-codex.ui.this_page'),
        };

        $width = (int) config('lin-codex.ui.drawer_width', 480);

        return view('lin-codex::livewire.help-drawer', [
            'read' => $read,
            'fallbackNotice' => $fallbackNotice,
            'also' => $also,
            'result' => $result,
            'nodes' => $nodes,
            'title' => $title,
            'options' => ['shortcut' => $this->shortcut(), 'width' => $width],
            'width' => $width,
            'helpCenterUrl' => route('lin-codex.help-center'),
        ]);
    }

    private function push(): void
    {
        $this->history[] = ['view' => $this->view, 'slug' => $this->slug];

        if (count($this->history) > self::HISTORY_LIMIT) {
            array_shift($this->history);
        }
    }

    /**
     * The configured shortcut as a string, or null when it is unset or not a
     * string (which disables it in the Alpine glue).
     */
    private function shortcut(): ?string
    {
        $shortcut = config('lin-codex.ui.shortcut');

        return is_string($shortcut) && trim($shortcut) !== '' ? $shortcut : null;
    }
}
