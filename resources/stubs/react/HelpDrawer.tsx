/**
 * The help drawer: the current page's articles first, then search and the
 * tree, and one article at a time. Function component, hooks only, no
 * state library, no CSS framework; every class is prefixed "codex-" so the
 * package stylesheet applies when the app includes it.
 *
 * Opens on HelpButton, on Ctrl+/, on a "codex:open" window event (detail
 * { slug? }) and on a ?codex=slug query parameter read once on mount.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { MouseEvent } from 'react'
import { createCodexClient } from './codex'
import type { Article, ArticleMeta, ContextArticle, Envelope, PageContext, SearchOutcome, TreeNode } from './types'

export interface HelpDrawerLabels {
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

export interface HelpDrawerProps {
  prefix?: string
  context: PageContext
  locale?: string
  fallbackNotice?: string
  labels?: Partial<HelpDrawerLabels>
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

const DEFAULT_FALLBACK_NOTICE = 'This article is not yet available in your language. Showing another language version.'

/** Opens the drawer from anywhere in the app, on the given article when a slug is passed. */
export function openCodex(slug?: string): void {
  window.dispatchEvent(new CustomEvent('codex:open', { detail: { slug } }))
}

export function HelpDrawer({ prefix, context, locale, fallbackNotice = DEFAULT_FALLBACK_NOTICE, labels }: HelpDrawerProps) {
  const text = { ...DEFAULT_LABELS, ...labels }
  const client = useMemo(() => createCodexClient({ prefix, context, locale }), [prefix, context, locale])

  const [open, setOpen] = useState(false)
  const [view, setView] = useState<View>('page')
  const [pageArticles, setPageArticles] = useState<ContextArticle[]>([])
  const [article, setArticle] = useState<Envelope<Article, ArticleMeta> | null>(null)
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<SearchOutcome | null>(null)
  const [tree, setTree] = useState<TreeNode[] | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const articleRequest = useRef(0)
  const deepLinked = useRef(false)

  const openArticle = useCallback(
    async (slug: string) => {
      const current = ++articleRequest.current
      setOpen(true)
      setView('article')
      setLoading(true)
      setError(null)

      try {
        const loaded = await client.article(slug)
        if (current === articleRequest.current) setArticle(loaded)
      } catch (caught) {
        if (current === articleRequest.current) {
          setArticle(null)
          setError(messageOf(caught))
        }
      } finally {
        if (current === articleRequest.current) setLoading(false)
      }
    },
    [client],
  )

  // With no slug the drawer opens on the current page's first article.
  const openDrawer = useCallback(
    (slug?: string) => {
      const target = slug ?? pageArticles[0]?.slug
      if (target) {
        void openArticle(target)
      } else {
        setView('page')
        setOpen(true)
      }
    },
    [openArticle, pageArticles],
  )

  useEffect(() => {
    let cancelled = false
    client
      .context()
      .then((envelope) => {
        if (!cancelled) setPageArticles(envelope.data)
      })
      .catch((caught) => {
        if (!cancelled) setError(messageOf(caught))
      })
    return () => {
      cancelled = true
    }
  }, [client])

  useEffect(() => {
    if (deepLinked.current) return
    deepLinked.current = true
    const slug = new URLSearchParams(window.location.search).get('codex')
    if (slug) void openArticle(slug)
  }, [openArticle])

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.ctrlKey && event.key === '/') {
        event.preventDefault()
        if (open) setOpen(false)
        else openDrawer()
      } else if (event.key === 'Escape' && open) {
        setOpen(false)
      }
    }
    const onOpen = (event: Event) => openDrawer((event as CustomEvent<{ slug?: string }>).detail?.slug)

    window.addEventListener('keydown', onKeyDown)
    window.addEventListener('codex:open', onOpen)
    return () => {
      window.removeEventListener('keydown', onKeyDown)
      window.removeEventListener('codex:open', onOpen)
    }
  }, [open, openDrawer])

  useEffect(() => {
    if (view !== 'search') return
    const term = query.trim()
    if (term.length < 2) {
      setResults(null)
      return
    }

    let cancelled = false
    const timer = window.setTimeout(() => {
      setLoading(true)
      client
        .search(term)
        .then((outcome) => {
          if (!cancelled) setResults(outcome)
        })
        .catch((caught) => {
          if (!cancelled) setError(messageOf(caught))
        })
        .finally(() => {
          if (!cancelled) setLoading(false)
        })
    }, 200)
    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [client, query, view])

  useEffect(() => {
    if (view !== 'tree' || tree !== null) return
    let cancelled = false
    setLoading(true)
    client
      .tree()
      .then((envelope) => {
        if (!cancelled) setTree(envelope.data)
      })
      .catch((caught) => {
        if (!cancelled) setError(messageOf(caught))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [client, view, tree])

  // Article links carry data-codex-article="slug": open them in the drawer.
  const onBodyClick = (event: MouseEvent<HTMLDivElement>) => {
    const link = (event.target as HTMLElement | null)?.closest('a[data-codex-article]')
    const slug = link?.getAttribute('data-codex-article')
    if (slug) {
      event.preventDefault()
      void openArticle(slug)
    }
  }

  const close = () => setOpen(false)
  const tab = (target: View, label: string, active: boolean) => (
    <button type="button" className="codex-tab" aria-pressed={active} onClick={() => setView(target)}>
      {label}
    </button>
  )

  return (
    <div className="codex-drawer" data-open={open} role="dialog" aria-modal="true" aria-label={text.title} hidden={!open}>
      {open && (
        <>
          <button type="button" className="codex-drawer-backdrop" aria-label={text.close} onClick={close} />
          <div className="codex-drawer-panel">
            <header className="codex-drawer-header">
              <h2 className="codex-drawer-title">{text.title}</h2>
              <nav className="codex-drawer-tabs">
                {tab('page', text.thisPage, view === 'page' || view === 'article')}
                {tab('search', text.search, view === 'search')}
                {tab('tree', text.browse, view === 'tree')}
              </nav>
              <button type="button" className="codex-drawer-close" aria-label={text.close} onClick={close}>
                &times;
              </button>
            </header>

            <div className="codex-drawer-body" aria-busy={loading}>
              {error && <p className="codex-error">{error}</p>}

              {view === 'page' && (
                <ul className="codex-page-articles">
                  {pageArticles.length === 0 && <li className="codex-empty">{text.noResults}</li>}
                  {pageArticles.map((entry) => (
                    <li key={entry.slug} data-fallback={entry.isFallback || undefined}>
                      <button type="button" className="codex-page-article" onClick={() => void openArticle(entry.slug)}>
                        <span className="codex-page-article-title">{entry.title}</span>
                        {entry.excerpt && <span className="codex-page-article-excerpt">{entry.excerpt}</span>}
                      </button>
                    </li>
                  ))}
                </ul>
              )}

              {view === 'search' && (
                <div className="codex-search">
                  <input
                    type="search"
                    className="codex-search-input"
                    placeholder={text.searchPlaceholder}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    autoFocus
                  />
                  {results?.rateLimited && (
                    <p className="codex-rate-limited">
                      {text.rateLimited} ({results.retryAfterSeconds}s)
                    </p>
                  )}
                  {results && !results.rateLimited && (
                    <ul className="codex-search-results">
                      {results.data.length === 0 && <li className="codex-empty">{text.noResults}</li>}
                      {results.data.map((hit) => (
                        <li key={hit.slug} data-fallback={hit.isFallback || undefined}>
                          <button type="button" className="codex-search-hit" onClick={() => void openArticle(hit.slug)}>
                            <span className="codex-search-hit-title">{hit.title}</span>
                            {hit.sectionPath.length > 0 && (
                              <span className="codex-search-hit-path">{hit.sectionPath.join(' › ')}</span>
                            )}
                            <span className="codex-search-hit-snippet" dangerouslySetInnerHTML={{ __html: hit.snippet }} />
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}

              {view === 'tree' && tree && <TreeList nodes={tree} onOpen={openArticle} />}

              {view === 'article' && (
                <article className="codex-article">
                  <button type="button" className="codex-back" onClick={() => setView('page')}>
                    {text.back}
                  </button>
                  {!loading && article === null && <p className="codex-empty">{text.noResults}</p>}
                  {article && (
                    <>
                      {article.data.breadcrumbs.length > 0 && (
                        <nav className="codex-breadcrumbs">
                          {article.data.breadcrumbs.map((crumb) => (
                            <button key={crumb.slug} type="button" className="codex-breadcrumb" onClick={() => void openArticle(crumb.slug)}>
                              {crumb.title}
                            </button>
                          ))}
                        </nav>
                      )}
                      <h2 className="codex-article-title">{article.data.title}</h2>
                      {article.data.isFallback && <p className="codex-fallback-notice">{fallbackNotice}</p>}
                      {article.data.toc.length > 0 && (
                        <ul className="codex-toc">
                          {article.data.toc.map((entry) => (
                            <li key={entry.id} data-level={entry.level}>
                              <a href={`#${entry.id}`}>{entry.text}</a>
                            </li>
                          ))}
                        </ul>
                      )}
                      <div
                        className="codex-article-body"
                        lang={article.data.locale}
                        dangerouslySetInnerHTML={{ __html: article.data.html }}
                        onClick={onBodyClick}
                      />
                      {article.data.related.length > 0 && (
                        <ul className="codex-related">
                          {article.data.related.map((entry) => (
                            <li key={entry.slug}>
                              <button type="button" className="codex-related-link" onClick={() => void openArticle(entry.slug)}>
                                {entry.title}
                              </button>
                            </li>
                          ))}
                        </ul>
                      )}
                    </>
                  )}
                </article>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  )
}

function TreeList({ nodes, onOpen }: { nodes: TreeNode[]; onOpen: (slug: string) => void }) {
  return (
    <ul className="codex-tree">
      {nodes.map((node) => (
        <li key={node.slug} className="codex-tree-node" data-fallback={node.isFallback || undefined}>
          {node.hasArticle ? (
            <button type="button" className="codex-tree-article" onClick={() => onOpen(node.slug)}>
              {node.title}
            </button>
          ) : (
            <span className="codex-tree-group">{node.title}</span>
          )}
          {node.children.length > 0 && <TreeList nodes={node.children} onOpen={onOpen} />}
        </li>
      ))}
    </ul>
  )
}

function messageOf(caught: unknown): string {
  return caught instanceof Error ? caught.message : String(caught)
}

export default HelpDrawer
