{{-- One root element. The Alpine glue lives in the @script block at the end; everything Livewire morphs sits inside the panel, the lightbox is Alpine-owned (wire:ignore). --}}
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
            <a class="codex-drawer__help-center" href="{{ $helpCenterUrl }}">{{ __('lin-codex::lin-codex.ui.open_help_center') }}</a>
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
    <script>
        Alpine.data('codexDrawer', (options) => ({
            lightbox: null,
            lightboxAlt: '',
            trigger: null,

            init() {
                // The deep link is read once: the parameter is removed from the
                // URL before the drawer opens, so a re-init never sees it again.
                const slug = new URLSearchParams(window.location.search).get('codex')

                if (slug) {
                    window.history.replaceState(null, '', window.location.pathname + window.location.hash)
                    this.$wire.open(slug)
                }

                this.$watch('$wire.isOpen', (open) => open ? this.lock() : this.unlock())

                if (this.$wire.isOpen) {
                    this.lock()
                }
            },

            openFrom(event) {
                this.trigger = document.activeElement
                this.$wire.open((event && event.detail && event.detail.slug) ? String(event.detail.slug) : null)
            },

            onKey(event) {
                if (event.key === 'Escape') {
                    if (this.lightbox !== null) {
                        this.closeLightbox()
                    } else if (this.$wire.isOpen) {
                        this.$wire.close()
                    }

                    return
                }

                if (! options.shortcut) {
                    return
                }

                const target = event.target

                if (target && typeof target.closest === 'function' && target.closest('input, textarea, select, [contenteditable]')) {
                    return
                }

                if (! this.matchesShortcut(event, options.shortcut)) {
                    return
                }

                event.preventDefault()

                if (this.$wire.isOpen) {
                    this.$wire.close()
                } else {
                    this.openFrom({ detail: {} })
                }
            },

            // 'ctrl+/' means ctrlKey (or metaKey on a Mac) plus the key; shift and
            // alt are required only when the shortcut names them.
            matchesShortcut(event, shortcut) {
                const parts = String(shortcut).toLowerCase().split('+')
                const key = parts.pop()

                if (! key || String(event.key || '').toLowerCase() !== key) {
                    return false
                }

                const ctrl = parts.includes('ctrl') ? (event.ctrlKey || event.metaKey) : true
                const shift = parts.includes('shift') ? event.shiftKey : true
                const alt = parts.includes('alt') ? event.altKey : true

                return ctrl && shift && alt
            },

            onBodyClick(event) {
                const image = event.target.closest('img[data-codex-lightbox]')

                if (image) {
                    this.lightbox = image.currentSrc || image.src
                    this.lightboxAlt = image.alt || ''

                    return
                }

                const article = event.target.closest('a[data-codex-article]')

                if (article) {
                    event.preventDefault()
                    this.$wire.show(article.dataset.codexArticle)

                    return
                }

                const link = event.target.closest('a[href]')

                if (! link || link.target || link.hasAttribute('wire:click') || link.hasAttribute('wire:click.prevent')) {
                    return
                }

                const href = link.getAttribute('href') || ''

                // A same-host link the browser will follow: close first so the
                // scroll lock is gone when the next page paints.
                if (! href.startsWith('#') && link.host === window.location.host) {
                    this.$wire.close()
                }
            },

            closeLightbox() {
                this.lightbox = null
                this.lightboxAlt = ''
            },

            lock() {
                document.body.style.overflow = 'hidden'

                this.$nextTick(() => {
                    const focus = this.$root.querySelector('[data-codex-focus]')

                    if (focus) {
                        focus.focus()
                    }
                })
            },

            unlock() {
                document.body.style.overflow = ''

                if (this.trigger && typeof this.trigger.focus === 'function') {
                    this.trigger.focus()
                }

                this.trigger = null
            },

            destroy() {
                this.unlock()
            },
        }))
    </script>
    @endscript
</div>
