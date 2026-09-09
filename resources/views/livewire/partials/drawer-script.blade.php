{{-- The drawer's Alpine glue: open/close, the shortcut, the deep link, the lightbox and the scroll lock. Included INSIDE an @script block by the core drawer view and by any host view that replaces it (fin-codex), so the behaviour is defined once. The @script directive itself must sit in the component's own view: Livewire captures it there, and one that sits in an included partial is silently dropped. --}}
<script>
    Alpine.data('codexDrawer', (options) => ({
        lightbox: null,
        lightboxAlt: '',
        trigger: null,

        init() {
            // The deep link is read once: parameter and hash are removed from
            // the URL before the drawer opens, so a re-init never sees them again.
            const slug = new URLSearchParams(window.location.search).get('codex')

            if (slug) {
                const heading = window.location.hash.replace(/^#/, '')

                window.history.replaceState(null, '', window.location.pathname)
                this.openAt(slug, heading)
            }

            this.$watch('$wire.isOpen', (open) => open ? this.lock() : this.unlock())

            if (this.$wire.isOpen) {
                this.lock()
            }
        },

        openFrom(event) {
            this.trigger = document.activeElement

            const detail = (event && event.detail) || {}
            const slug = detail.slug ? String(detail.slug) : null

            // A heading without a slug is ignored: there is nothing to land on.
            this.openAt(slug, slug && detail.heading ? String(detail.heading) : '')
        },

        // Livewire resolves the action promise after the morph, so the heading
        // exists by the time the scroll runs; a heading that is not in the
        // article leaves it at the top, silently.
        async openAt(slug, heading) {
            await this.$wire.open(slug)

            if (heading) {
                this.$nextTick(() => this.scrollToHeading(heading))
            }
        },

        scrollToHeading(id) {
            const body = this.$root.querySelector('.codex-drawer__body')
            const target = body && body.querySelector('.codex-article__body #' + CSS.escape(id))

            if (target) {
                target.scrollIntoView({ block: 'start' })
            }
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

            // A download link stays where it is: the browser saves the file
            // and the reader keeps the article.
            if (! link || link.target || link.hasAttribute('download') || link.hasAttribute('wire:click') || link.hasAttribute('wire:click.prevent')) {
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
