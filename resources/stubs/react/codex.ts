/**
 * A small fetch client for the Codex JSON API. Framework-free: the React
 * and Vue drawers both use it, and so can any other code in the app.
 *
 * Every call is a GET with credentials "same-origin" and an Accept header
 * of application/json, so the session identifies the viewer and the server
 * answers JSON even for errors. Nothing is cached here; the server applies
 * its own cache headers and the drawer keeps what it has loaded.
 */

import type {
  Article,
  ArticleMeta,
  CodexOptions,
  ContextArticle,
  ContextMeta,
  Envelope,
  PageContext,
  SearchHit,
  SearchMeta,
  SearchOutcome,
  TreeNode,
} from './types'

/** A non-2xx answer the client does not map itself: the server's message and the HTTP status. */
export class CodexError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message)
    this.name = 'CodexError'
  }
}

export interface CodexClient {
  /** GET {prefix}/tree: the tree the viewer sees. */
  tree(): Promise<Envelope<TreeNode[]>>
  /** GET {prefix}/articles/{slug}: one rendered article, or null when the server answers 404. */
  article(slug: string): Promise<Envelope<Article, ArticleMeta> | null>
  /** GET {prefix}/search?q=&limit=: the hits, or { rateLimited: true, retryAfterSeconds } for a 429. */
  search(q: string, limit?: number): Promise<SearchOutcome>
  /** GET {prefix}/context?route=&path=&class=&panel=: the articles for the page given in the options. */
  context(): Promise<Envelope<ContextArticle[], ContextMeta>>
}

type Params = Record<string, string | number | null | undefined>

interface Answer {
  status: number
  headers: Headers
  body: unknown
}

const RETRY_AFTER_FALLBACK = 60

/**
 * Creates a client for one API prefix (default "/codex/api"), one page
 * context and, optionally, one locale that is sent with every request.
 */
export function createCodexClient(options: CodexOptions = {}): CodexClient {
  const prefix = (options.prefix ?? '/codex/api').replace(/\/+$/, '')
  const locale = options.locale
  const context: PageContext = options.context ?? {}

  /**
   * Builds the URL (undefined and null parameters are left out), performs
   * the request and parses the JSON body. A non-2xx status the caller has
   * not listed in `mapped` becomes a CodexError.
   */
  async function request(path: string, params: Params = {}, mapped: number[] = []): Promise<Answer> {
    const query = new URLSearchParams()

    for (const [key, value] of Object.entries({ ...params, locale })) {
      if (value !== undefined && value !== null) {
        query.set(key, String(value))
      }
    }

    const search = query.toString()
    const url = `${prefix}${path}${search ? `?${search}` : ''}`

    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })

    const body = await parseJson(response)

    if (!response.ok && !mapped.includes(response.status)) {
      throw new CodexError(messageOf(body) ?? response.statusText, response.status)
    }

    return { status: response.status, headers: response.headers, body }
  }

  return {
    async tree() {
      const answer = await request('/tree')

      return answer.body as Envelope<TreeNode[]>
    },

    // The slug is appended as it is: slugs are kebab-case ASCII with "/"
    // separators, and encoding the whole slug would encode the slash too.
    async article(slug) {
      const answer = await request(`/articles/${slug}`, {}, [404])

      if (answer.status === 404) {
        return null
      }

      return answer.body as Envelope<Article, ArticleMeta>
    },

    async search(q, limit) {
      const answer = await request('/search', { q, limit }, [429])

      if (answer.status === 429) {
        return { rateLimited: true, retryAfterSeconds: retryAfterSeconds(answer.headers) }
      }

      const envelope = answer.body as Envelope<SearchHit[], SearchMeta>

      return { rateLimited: false, data: envelope.data, meta: envelope.meta }
    },

    async context() {
      const answer = await request('/context', pageParams(context))

      return answer.body as Envelope<ContextArticle[], ContextMeta>
    },
  }
}

/** The page context as query parameters: only the keys that hold a string. */
function pageParams(context: PageContext): Params {
  const params: Params = {}

  for (const key of ['route', 'path', 'class', 'panel'] as const) {
    const value = context[key]

    if (typeof value === 'string') {
      params[key] = value
    }
  }

  return params
}

async function parseJson(response: Response): Promise<unknown> {
  try {
    return await response.json()
  } catch {
    return null
  }
}

function messageOf(body: unknown): string | null {
  if (body !== null && typeof body === 'object' && 'message' in body && typeof body.message === 'string') {
    return body.message
  }

  return null
}

/** The Retry-After header in seconds, or a one-minute fallback when it is missing or not a number. */
function retryAfterSeconds(headers: Headers): number {
  const seconds = Number.parseInt(headers.get('Retry-After') ?? '', 10)

  return Number.isNaN(seconds) || seconds < 0 ? RETRY_AFTER_FALLBACK : seconds
}
