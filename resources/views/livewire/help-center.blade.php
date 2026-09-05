{{-- The help center page: tree and search on the left, the article or the search results in the middle, "On this page" on the right. One root; the tree toggle below 768px is inline Alpine. --}}
<div class="codex-root codex-help-center" data-codex-help-center x-data="{ treeOpen: false }">
    <button type="button" class="codex-help-center__toggle" x-on:click="treeOpen = !treeOpen" x-bind:aria-expanded="treeOpen">{{ __('lin-codex::lin-codex.ui.toggle_tree') }}</button>
    <div class="codex-help-center__layout">
        <aside class="codex-help-center__tree" x-bind:class="{ 'codex-help-center__tree--open': treeOpen }">
            <div class="codex-help-center__search codex-search">
                <input type="search" class="codex-search__input" wire:model.live.debounce.300ms="query" placeholder="{{ __('lin-codex::lin-codex.ui.search_placeholder') }}" aria-label="{{ __('lin-codex::lin-codex.ui.search') }}" autocomplete="off">
            </div>
            @include('lin-codex::livewire.partials.tree-nodes', ['nodes' => $nodes, 'current' => $slug])
        </aside>
        <main class="codex-help-center__main">
            @if ($result !== null)
                @include('lin-codex::livewire.partials.search-results', ['result' => $result])
            @elseif ($slug === null || $slug === '')
                <h1 class="codex-article__title">{{ __('lin-codex::lin-codex.ui.help_center') }}</h1>
                <p class="codex-notice">{{ __('lin-codex::lin-codex.ui.pick_a_topic') }}</p>
            @elseif ($read === null)
                <p class="codex-empty">{{ __('lin-codex::lin-codex.ui.not_found') }}</p>
            @else
                @include('lin-codex::livewire.partials.article-body', ['read' => $read, 'fallbackNotice' => $fallbackNotice, 'showToc' => false])
            @endif
        </main>
        @if ($result === null && $read !== null && $toc !== [])
            <aside class="codex-help-center__toc">
                <h3 class="codex-help-center__toc-title">{{ __('lin-codex::lin-codex.ui.on_this_page') }}</h3>
                <ul class="codex-toc__list">
                    @foreach ($toc as $entry)
                        <li class="codex-toc__item" wire:key="toc-{{ $entry['id'] }}" data-level="{{ $entry['level'] }}"><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </div>
</div>
