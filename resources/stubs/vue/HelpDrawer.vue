<script lang="ts">
/**
 * The help drawer: the current page's articles first, then search and the
 * tree, and one article at a time. Vue 3, <script setup>, no state library,
 * no CSS framework; every class is prefixed "codex-" so the package
 * stylesheet applies when the app includes it.
 *
 * Opens on HelpButton, on Ctrl+/, on a "codex:open" window event (detail
 * { slug? }) and on a ?codex=slug query parameter read once on mount.
 */

import { defineComponent, h } from 'vue'
import type { PropType, VNode } from 'vue'
import type { TreeNode } from './types'

/** Opens the drawer from anywhere in the app, on the given article when a slug is passed. */
export function openCodex(slug?: string): void {
  window.dispatchEvent(new CustomEvent('codex:open', { detail: { slug } }))
}

/** The recursive tree list, as a render function because a template cannot nest itself. */
const CodexTree = defineComponent({
  name: 'CodexTree',
  props: {
    nodes: { type: Array as PropType<TreeNode[]>, required: true },
    onOpen: { type: Function as PropType<(slug: string) => void>, required: true },
  },
  setup(props) {
    const list = (nodes: TreeNode[]): VNode =>
      h(
        'ul',
        { class: 'codex-tree' },
        nodes.map((node) =>
          h('li', { key: node.slug, class: 'codex-tree-node', 'data-fallback': node.isFallback || undefined }, [
            node.hasArticle
              ? h('button', { type: 'button', class: 'codex-tree-article', onClick: () => props.onOpen(node.slug) }, node.title)
              : h('span', { class: 'codex-tree-group' }, node.title),
            node.children.length > 0 ? list(node.children) : null,
          ]),
        ),
      )

    return () => list(props.nodes)
  },
})
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { createCodexClient } from './codex'
import type { Article, ArticleMeta, ContextArticle, Envelope, PageContext, SearchOutcome, TreeNode } from './types'

interface HelpDrawerLabels {
  title: string
  thisPage: string
  search: string
  browse: string
  searchPlaceholder: string
  noResults: string
  rateLimited: string
  close: string
  back: string
}

type View = 'page' | 'search' | 'tree' | 'article'

const DEFAULT_LABELS: HelpDrawerLabels = {
  title: 'Help',
  thisPage: 'This page',
  search: 'Search',
  browse: 'Browse',
  searchPlaceholder: 'Search the help',
  noResults: 'Nothing found.',
  rateLimited: 'Too many searches. Try again in a moment.',
  close: 'Close',
  back: 'Back to this page',
}

const props = withDefaults(
  defineProps<{
    prefix?: string
    context: PageContext
    locale?: string
    fallbackNotice?: string
    labels?: Partial<HelpDrawerLabels>
  }>(),
  {
    prefix: undefined,
    locale: undefined,
    fallbackNotice: 'This article is not yet available in your language. Showing another language version.',
    labels: () => ({}),
  },
)

const Tree = CodexTree
const text = computed<HelpDrawerLabels>(() => ({ ...DEFAULT_LABELS, ...props.labels }))
const client = computed(() => createCodexClient({ prefix: props.prefix, context: props.context, locale: props.locale }))

const open = ref(false)
const view = ref<View>('page')
const pageArticles = ref<ContextArticle[]>([])
const article = ref<Envelope<Article, ArticleMeta> | null>(null)
const query = ref('')
const results = ref<SearchOutcome | null>(null)
const tree = ref<TreeNode[] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

let articleRequest = 0
let searchRequest = 0
let searchTimer: number | undefined

function messageOf(caught: unknown): string {
  return caught instanceof Error ? caught.message : String(caught)
}

async function openArticle(slug: string): Promise<void> {
  const current = ++articleRequest
  open.value = true
  view.value = 'article'
  loading.value = true
  error.value = null

  try {
    const loaded = await client.value.article(slug)
    if (current === articleRequest) article.value = loaded
  } catch (caught) {
    if (current === articleRequest) {
      article.value = null
      error.value = messageOf(caught)
    }
  } finally {
    if (current === articleRequest) loading.value = false
  }
}

// With no slug the drawer opens on the current page's first article.
function openDrawer(slug?: string): void {
  const target = slug ?? pageArticles.value[0]?.slug
  if (target) {
    void openArticle(target)
  } else {
    view.value = 'page'
    open.value = true
  }
}

function close(): void {
  open.value = false
}

async function loadContext(): Promise<void> {
  try {
    pageArticles.value = (await client.value.context()).data
  } catch (caught) {
    error.value = messageOf(caught)
  }
}

async function loadTree(): Promise<void> {
  loading.value = true
  try {
    tree.value = (await client.value.tree()).data
  } catch (caught) {
    error.value = messageOf(caught)
  } finally {
    loading.value = false
  }
}

async function runSearch(term: string): Promise<void> {
  const current = ++searchRequest
  loading.value = true
  try {
    const outcome = await client.value.search(term)
    if (current === searchRequest) results.value = outcome
  } catch (caught) {
    if (current === searchRequest) error.value = messageOf(caught)
  } finally {
    if (current === searchRequest) loading.value = false
  }
}

// Article links carry data-codex-article="slug": open them in the drawer.
function onBodyClick(event: MouseEvent): void {
  const link = (event.target as HTMLElement | null)?.closest('a[data-codex-article]')
  const slug = link?.getAttribute('data-codex-article')
  if (slug) {
    event.preventDefault()
    void openArticle(slug)
  }
}

function onKeyDown(event: KeyboardEvent): void {
  if (event.ctrlKey && event.key === '/') {
    event.preventDefault()
    if (open.value) close()
    else openDrawer()
  } else if (event.key === 'Escape' && open.value) {
    close()
  }
}

function onOpenEvent(event: Event): void {
  openDrawer((event as CustomEvent<{ slug?: string }>).detail?.slug)
}

watch(client, () => void loadContext(), { immediate: true })

// Search waits 200 ms after the last keystroke and needs two characters.
watch([query, view], ([term, current]) => {
  window.clearTimeout(searchTimer)
  if (current !== 'search') return
  const trimmed = term.trim()
  if (trimmed.length < 2) {
    results.value = null
    return
  }
  searchTimer = window.setTimeout(() => void runSearch(trimmed), 200)
})

watch(view, (current) => {
  if (current === 'tree' && tree.value === null) void loadTree()
})

onMounted(() => {
  window.addEventListener('keydown', onKeyDown)
  window.addEventListener('codex:open', onOpenEvent)

  const slug = new URLSearchParams(window.location.search).get('codex')
  if (slug) void openArticle(slug)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeyDown)
  window.removeEventListener('codex:open', onOpenEvent)
  window.clearTimeout(searchTimer)
})
</script>

<template>
  <div class="codex-drawer" :data-open="open" role="dialog" aria-modal="true" :aria-label="text.title" :hidden="!open">
    <template v-if="open">
      <button type="button" class="codex-drawer-backdrop" :aria-label="text.close" @click="close" />
      <div class="codex-drawer-panel">
        <header class="codex-drawer-header">
          <h2 class="codex-drawer-title">{{ text.title }}</h2>
          <nav class="codex-drawer-tabs">
            <button type="button" class="codex-tab" :aria-pressed="view === 'page' || view === 'article'" @click="view = 'page'">
              {{ text.thisPage }}
            </button>
            <button type="button" class="codex-tab" :aria-pressed="view === 'search'" @click="view = 'search'">
              {{ text.search }}
            </button>
            <button type="button" class="codex-tab" :aria-pressed="view === 'tree'" @click="view = 'tree'">
              {{ text.browse }}
            </button>
          </nav>
          <button type="button" class="codex-drawer-close" :aria-label="text.close" @click="close">&times;</button>
        </header>

        <div class="codex-drawer-body" :aria-busy="loading">
          <p v-if="error" class="codex-error">{{ error }}</p>

          <ul v-if="view === 'page'" class="codex-page-articles">
            <li v-if="pageArticles.length === 0" class="codex-empty">{{ text.noResults }}</li>
            <li v-for="entry in pageArticles" :key="entry.slug" :data-fallback="entry.isFallback || undefined">
              <button type="button" class="codex-page-article" @click="openArticle(entry.slug)">
                <span class="codex-page-article-title">{{ entry.title }}</span>
                <span v-if="entry.excerpt" class="codex-page-article-excerpt">{{ entry.excerpt }}</span>
              </button>
            </li>
          </ul>

          <div v-else-if="view === 'search'" class="codex-search">
            <input v-model="query" type="search" class="codex-search-input" :placeholder="text.searchPlaceholder" autofocus />
            <p v-if="results?.rateLimited" class="codex-rate-limited">{{ text.rateLimited }} ({{ results.retryAfterSeconds }}s)</p>
            <ul v-else-if="results" class="codex-search-results">
              <li v-if="results.data.length === 0" class="codex-empty">{{ text.noResults }}</li>
              <li v-for="hit in results.data" :key="hit.slug" :data-fallback="hit.isFallback || undefined">
                <button type="button" class="codex-search-hit" @click="openArticle(hit.slug)">
                  <span class="codex-search-hit-title">{{ hit.title }}</span>
                  <span v-if="hit.sectionPath.length > 0" class="codex-search-hit-path">{{ hit.sectionPath.join(' › ') }}</span>
                  <!-- The snippet is escaped by the server; only <mark> survives. -->
                  <span class="codex-search-hit-snippet" v-html="hit.snippet" />
                </button>
              </li>
            </ul>
          </div>

          <Tree v-else-if="view === 'tree' && tree" :nodes="tree" :on-open="openArticle" />

          <article v-else-if="view === 'article'" class="codex-article">
            <button type="button" class="codex-back" @click="view = 'page'">{{ text.back }}</button>
            <p v-if="!loading && article === null" class="codex-empty">{{ text.noResults }}</p>
            <template v-if="article">
              <nav v-if="article.data.breadcrumbs.length > 0" class="codex-breadcrumbs">
                <button v-for="crumb in article.data.breadcrumbs" :key="crumb.slug" type="button" class="codex-breadcrumb" @click="openArticle(crumb.slug)">
                  {{ crumb.title }}
                </button>
              </nav>
              <h2 class="codex-article-title">{{ article.data.title }}</h2>
              <p v-if="article.data.isFallback" class="codex-fallback-notice">{{ fallbackNotice }}</p>
              <ul v-if="article.data.toc.length > 0" class="codex-toc">
                <li v-for="entry in article.data.toc" :key="entry.id" :data-level="entry.level">
                  <a :href="`#${entry.id}`">{{ entry.text }}</a>
                </li>
              </ul>
              <!-- The html is sanitized by the server; article links inside it open in place. -->
              <div class="codex-article-body" :lang="article.data.locale" v-html="article.data.html" @click="onBodyClick" />
              <ul v-if="article.data.related.length > 0" class="codex-related">
                <li v-for="entry in article.data.related" :key="entry.slug">
                  <button type="button" class="codex-related-link" @click="openArticle(entry.slug)">{{ entry.title }}</button>
                </li>
              </ul>
            </template>
          </article>
        </div>
      </div>
    </template>
  </div>
</template>
