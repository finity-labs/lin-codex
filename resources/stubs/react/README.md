# Codex help drawer (React)

Published by `php artisan vendor:publish --tag=lin-codex-react` into `resources/js/codex`. The files are yours now: the package never loads them, ships no npm package and will not overwrite them unless you publish again with `--force`.

| File | What it is |
|---|---|
| `types.ts` | The JSON API payloads as TypeScript types, key for key. |
| `codex.ts` | `createCodexClient()`, a fetch client over the four endpoints. |
| `HelpButton.tsx` | A button that opens the drawer. |
| `HelpDrawer.tsx` | The drawer: the current page's articles, search, the tree and the article view. |

`--tag=lin-codex-vue` publishes the Vue set into the same folder. The two tags are alternatives; `codex.ts` and `types.ts` are identical in both, so publishing both overwrites the shared files with the same content and leaves both component pairs side by side.

## The client

```ts
import { createCodexClient } from '@/codex/codex'

const client = createCodexClient({
  prefix: '/codex/api',   // lin-codex.routes.api; default '/codex/api'
  context: { route: 'users.index', path: '/users' },   // the page, for context()
  locale: 'de',           // optional; sent with every request
})

await client.context()          // Envelope<ContextArticle[], ContextMeta>
await client.article('users/roles')   // Envelope<Article, ArticleMeta>, or null on 404
await client.search('password')       // { rateLimited: false, data, meta } or { rateLimited: true, retryAfterSeconds }
await client.tree()             // Envelope<TreeNode[]>
```

Every request goes out with `credentials: 'same-origin'` and `Accept: application/json`, so the session identifies the viewer. Any other non-2xx answer throws a `CodexError` with the server's message and the status.

## The drawer

```tsx
import { useState } from 'react'
import { usePage } from '@inertiajs/react'
import { HelpButton } from '@/codex/HelpButton'
import { HelpDrawer, openCodex } from '@/codex/HelpDrawer'

export default function Layout({ children }) {
  const { codex } = usePage().props

  return (
    <>
      <HelpButton onClick={() => openCodex()} />
      <HelpDrawer prefix={codex.prefix} context={codex.context} />
      {children}
    </>
  )
}
```

Props:

| Prop | Type | What it does |
|---|---|---|
| `context` | `PageContext` | Required. The page the drawer is on; see below. |
| `prefix` | `string` | The API prefix. Default `/codex/api`. |
| `locale` | `string` | Request articles in this language instead of the app locale. |
| `fallbackNotice` | `string` | The text shown above an article served in another language (`isFallback`). |
| `labels` | `Partial<HelpDrawerLabels>` | `title`, `thisPage`, `search`, `browse`, `searchPlaceholder`, `noResults`, `rateLimited`, `close`, `back`. |

The drawer opens on the button, on `Ctrl+/`, on a `codex:open` window event and on a `?codex=slug` query parameter, read once on mount. Without a slug it opens on the first article for the current page; with one it opens that article. `openCodex(slug?)` dispatches the event for you:

```ts
openCodex()               // the current page's first article
openCodex('users/roles')  // one article
window.dispatchEvent(new CustomEvent('codex:open', { detail: { slug: 'users/roles' } }))   // the same, by hand
```

Inside an article, links to other articles carry `data-codex-article` and open in the drawer. Search waits 200 ms after the last keystroke and needs two characters; over the search rate limit it shows `labels.rateLimited` with the seconds to wait. The tree loads the first time its tab is opened.

## The page context from Inertia

The drawer needs to know which page it is on. Share it from `HandleInertiaRequests` so every page has it:

```php
use FinityLabs\LinCodex\Contexts\RequestContextDetector;

public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'codex' => [
            'prefix' => config('lin-codex.routes.api'),
            'context' => app(RequestContextDetector::class)->detect($request)->toArray(),
        ],
    ];
}
```

`detect()` takes an optional page class and panel id as its second and third arguments when the host knows them. The array holds `route`, `path`, `class` and `panel`, which is what the `context` endpoint reads.

## Styling

No CSS ships with these files. Every element carries a class prefixed `codex-` (`codex-drawer`, `codex-drawer-panel`, `codex-tab`, `codex-search-input`, `codex-tree`, `codex-article-body`, `codex-fallback-notice`, ...), the wrapper has `data-open="true"` while open and `hidden` while closed, and fallback entries carry `data-fallback`. The package stylesheet of a later release styles these classes when you include it; until then, or instead, style them yourself.
