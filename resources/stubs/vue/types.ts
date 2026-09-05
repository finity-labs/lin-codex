/**
 * Types for the Codex JSON API.
 *
 * This file mirrors the PHP payload mappers under src/Http/Json in the
 * finity-labs/lin-codex package, key for key. The API is frozen: a later
 * release may add a key but never renames or removes one, so a published
 * copy of this file keeps working.
 */

/** The page the drawer is opened on, as GET {prefix}/context reads it from the query string. */
export interface PageContext {
  route?: string | null
  path?: string
  class?: string | null
  panel?: string | null
}

/** The meta block every endpoint answers with. */
export interface Meta {
  locale: string
  defaultLocale: string
}

/** meta of GET {prefix}/articles/{slug}. */
export interface ArticleMeta extends Meta {
  isFallback: boolean
}

/** meta of GET {prefix}/search. */
export interface SearchMeta extends Meta {
  query: string
  total: number
  limit: number
  rateLimited: boolean
  retryAfterSeconds: number | null
}

/** meta of GET {prefix}/context: the canonical page context the server matched. */
export interface ContextMeta extends Meta {
  route: string | null
  path: string
  class: string | null
  panel: string | null
}

/** Every successful answer: { data, meta }. */
export interface Envelope<D, M extends Meta = Meta> {
  data: D
  meta: M
}

/** One table-of-contents entry of an article (h2 and h3). */
export interface TocEntry {
  level: number
  text: string
  id: string
}

/** A breadcrumb or a related entry of an article. */
export interface ArticleLink {
  slug: string
  title: string
}

/** Article.format: the enum key, never the backing int. */
export type ArticleFormat = 'markdown' | 'html'

/** SearchHit.matchedField: the enum key, never the backing int. */
export type MatchedField = 'title' | 'keywords' | 'excerpt' | 'body'

/** data of GET {prefix}/articles/{slug}. */
export interface Article {
  slug: string
  title: string
  excerpt: string | null
  locale: string
  isFallback: boolean
  format: ArticleFormat
  html: string
  toc: TocEntry[]
  breadcrumbs: ArticleLink[]
  related: ArticleLink[]
  icon: string | null
  updatedAt: string | null
}

/** One node of GET {prefix}/tree; groups are folders without an article. */
export interface TreeNode {
  slug: string
  title: string
  icon: string | null
  isGroup: boolean
  isFallback: boolean
  hasArticle: boolean
  children: TreeNode[]
}

/** One entry of GET {prefix}/search; the snippet is escaped HTML with <mark> around the matches. */
export interface SearchHit {
  slug: string
  title: string
  sectionPath: string[]
  snippet: string
  matchedField: MatchedField
  score: number
  isFallback: boolean
}

/** One entry of GET {prefix}/context, best match first. */
export interface ContextArticle {
  slug: string
  title: string
  excerpt: string | null
  isFallback: boolean
}

/** What search() resolves to: the envelope, or the rate-limit answer mapped from a 429. */
export type SearchOutcome =
  | ({ rateLimited: false } & Envelope<SearchHit[], SearchMeta>)
  | { rateLimited: true; retryAfterSeconds: number }

/** Options of createCodexClient(). */
export interface CodexOptions {
  prefix?: string
  context?: PageContext
  locale?: string
}
