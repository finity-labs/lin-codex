{{-- One root element. The Alpine glue lives in partials/drawer-script (included at the end); everything Livewire morphs sits inside the panel, the lightbox is Alpine-owned (wire:ignore). --}}
<div class="codex-root codex-drawer"
     data-codex-drawer
     data-codex-page-count="{{ count($pageArticles) }}"
     data-codex-view="{{ $view }}"
     style="--codex-drawer-width: {{ $width }}px"
     x-data="codexDrawer(@js($options))"
     x-bind:data-open="$wire.isOpen ? 'true' : 'false'"
     x-on:codex:open.window="openFrom($event)"
     x-on:keydown.window="onKey($event)">
    <div class="codex-drawer__overlay" x-cloak x-show="$wire.isOpen" x-transition.opacity x-on:click="$wire.close()" aria-hidden="true"></div>
    <div class="codex-drawer__panel" x-cloak x-show="$wire.isOpen" x-transition role="dialog" aria-modal="true" aria-label="{{ __('lin-codex::lin-codex.ui.title') }}">
        <header class="codex-drawer__header">
            @if ($history !== [])
                <button type="button" class="codex-drawer__back" wire:click="back" aria-label="{{ __('lin-codex::lin-codex.ui.back') }}">
                    <svg class="codex-drawer__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                </button>
            @endif
            <h2 class="codex-drawer__title">{{ $title }}</h2>
            <button type="button" class="codex-drawer__close" wire:click="close" aria-label="{{ __('lin-codex::lin-codex.ui.close') }}">
                <svg class="codex-drawer__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </header>
        <div class="codex-drawer__search">
            <input type="search" class="codex-search__input" data-codex-focus wire:model.live.debounce.300ms="query" placeholder="{{ __('lin-codex::lin-codex.ui.search_placeholder') }}" aria-label="{{ __('lin-codex::lin-codex.ui.search') }}" autocomplete="off">
        </div>
        <nav class="codex-drawer__tabs" aria-label="{{ __('lin-codex::lin-codex.ui.title') }}">
            <a href="#" @class(['codex-tab', 'codex-tab--active' => in_array($view, ['page', 'article'], true)]) wire:click.prevent="goTo('page')">{{ __('lin-codex::lin-codex.ui.this_page') }}</a>
            <a href="#" @class(['codex-tab', 'codex-tab--active' => $view === 'tree']) wire:click.prevent="goTo('tree')">{{ __('lin-codex::lin-codex.ui.browse') }}</a>
        </nav>
        <div class="codex-drawer__body" x-on:click="onBodyClick($event)">
            @switch($view)
                @case('page')
                    @if ($pageArticles !== [])
                        <ul class="codex-page-articles">
                            @foreach ($pageArticles as $entry)
                                <li class="codex-page-articles__item" wire:key="page-{{ $entry['slug'] }}" @if ($entry['isFallback']) data-fallback @endif>
                                    <a class="codex-page-article" href="{{ \FinityLabs\LinCodex\Rendering\ArticlePath::href($entry['slug']) }}" data-codex-page-article="{{ $entry['slug'] }}" wire:click.prevent="show('{{ $entry['slug'] }}')">
                                        <span class="codex-page-article__title">{{ $entry['title'] }}</span>
                                        @if ($entry['excerpt'] !== null && $entry['excerpt'] !== '')
                                            <span class="codex-page-article__excerpt">{{ $entry['excerpt'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="codex-notice">{{ __('lin-codex::lin-codex.ui.no_help_for_page') }}</p>
                        @include('lin-codex::livewire.partials.tree-nodes', ['nodes' => $nodes, 'current' => null])
                    @endif
                    @break

                @case('search')
                    @if ($result !== null)
                        @include('lin-codex::livewire.partials.search-results', ['result' => $result])
                    @endif
                    @break

                @case('tree')
                    @include('lin-codex::livewire.partials.tree-nodes', ['nodes' => $nodes, 'current' => $slug])
                    @break

                @default
                    @if ($also !== [])
                        <section class="codex-also">
                            <h3 class="codex-also__title">{{ __('lin-codex::lin-codex.ui.also_on_this_page') }}</h3>
                            <ul class="codex-also__list">
                                @foreach ($also as $entry)
                                    @continue($entry['slug'] === $slug)
                                    <li class="codex-also__item" wire:key="also-{{ $entry['slug'] }}">
                                        <a class="codex-also__link" href="{{ \FinityLabs\LinCodex\Rendering\ArticlePath::href($entry['slug']) }}" data-codex-page-article="{{ $entry['slug'] }}" wire:click.prevent="show('{{ $entry['slug'] }}')">{{ $entry['title'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if ($read === null)
                        <p class="codex-empty">{{ __('lin-codex::lin-codex.ui.not_found') }}</p>
                    @else
                        @include('lin-codex::livewire.partials.article-body', ['read' => $read, 'fallbackNotice' => $fallbackNotice, 'showToc' => true])
                    @endif
            @endswitch
        </div>
        <footer class="codex-drawer__footer">
            @if ($helpCenterUrl !== null)
                <a class="codex-drawer__help-center" href="{{ $helpCenterUrl }}">{{ __('lin-codex::lin-codex.ui.open_help_center') }}</a>
            @endif
            @if ($options['shortcut'] !== null)
                <span class="codex-drawer__shortcut">{{ __('lin-codex::lin-codex.ui.shortcut_hint', ['shortcut' => $options['shortcut']]) }}</span>
            @endif
        </footer>
    </div>
    <div class="codex-lightbox" wire:ignore x-cloak x-show="lightbox !== null" x-on:click="closeLightbox()" x-on:keydown.escape.window="closeLightbox()" role="dialog" aria-label="{{ __('lin-codex::lin-codex.ui.lightbox_close') }}">
        <img class="codex-lightbox__image" x-bind:src="lightbox" x-bind:alt="lightboxAlt" alt="">
        <button type="button" class="codex-lightbox__close" aria-label="{{ __('lin-codex::lin-codex.ui.lightbox_close') }}">
            <svg class="codex-drawer__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>
    @script
    @include('lin-codex::livewire.partials.drawer-script')
    @endscript
</div>
