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
 * The full-page help center, registered as "lin-codex.help-center" and
 * mounted by the "{help_center}" and "{help_center}/{slug}" routes
 * (lin-codex.routes.help_center).
 *
 * Three columns: the tree on the left with the search box above it, the
 * article with its breadcrumbs in the middle, and "On this page" built from
 * the article's table of contents on the right. While a query is typed the
 * results replace the article column; a hit or a tree link calls show() and
 * loads the article in place. The search asks for 50 hits, the widest useful
 * list on a full page; Searcher::effectiveLimit() clamps that to
 * lin-codex.search.max_limit.
 *
 * The page renders inside lin-codex.routes.help_center_layout when set,
 * else the package layout. Either must be a component layout, one that
 * receives $slot; an @extends layout does not work here. The layout is
 * always passed explicitly because the Livewire default differs between
 * versions.
 *
 * The page context is captured at mount for parity with the drawer, but the
 * help center never lists page articles: the tree is its entry point. The
 * article, the tree and the search result are computed per render and never
 * stored, so the component state stays scalars and arrays.
 */
final class HelpCenter extends Component
{
    use CapturesPageHelp;
    use SearchesArticles;

    public ?string $slug = null;

    public function mount(PageHelpResolver $resolver, ?string $slug = null, ?string $locale = null): void
    {
        $this->capturePageHelp($resolver, null, null, $locale);
        $this->slug = $slug;
    }

    /**
     * Load an article in place: from the tree, a search hit, a breadcrumb,
     * a related link or an in-article link. Clears the query so the article
     * column comes back.
     */
    public function show(string $slug): void
    {
        $this->slug = $slug;
        $this->query = '';
    }

    public function render(): View
    {
        $read = $this->article();
        $title = $read?->translation->title ?? __('lin-codex::lin-codex.ui.help_center');
        $appName = (string) config('app.name');

        $layout = config('lin-codex.routes.help_center_layout');
        $layout = is_string($layout) && $layout !== '' ? $layout : 'lin-codex::layouts.help-center';

        return view('lin-codex::livewire.help-center', [
            'read' => $read,
            'fallbackNotice' => $read !== null ? $this->fallbackNoticeFor($read) : null,
            'nodes' => $this->tree(),
            'result' => $this->hasSearchQuery() ? $this->searchResult(50) : null,
            'toc' => $read?->rendered->toc ?? [],
        ])->layout($layout, ['title' => $title.' · '.$appName]);
    }

    /**
     * The article for the current slug as the current viewer may read it in
     * the captured locale, null without a slug or when the viewer may not
     * see it (hidden, unpublished, restricted and missing look alike).
     */
    protected function article(): ?ReadArticle
    {
        if ($this->slug === null || $this->slug === '') {
            return null;
        }

        return app(ArticleReader::class)->read($this->slug, $this->viewer(), $this->locale);
    }

    /**
     * @return list<TreeNode>
     */
    protected function tree(): array
    {
        return app(TreeBuilder::class)->build($this->viewer(), $this->locale);
    }
}
