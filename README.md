# Codex for Laravel

In-app help for Laravel applications. Codex serves help articles from Markdown files, the database, or both, and shows the right article for the page the user is on. It ships a Livewire help drawer, a JSON API for Inertia frontends, per-language content, and search.

[![Laravel 11 to 13](https://img.shields.io/badge/LARAVEL-11%20%7C%2012%20%7C%2013-FF2D20?style=flat-square)](https://laravel.com)
[![Livewire 3 and 4](https://img.shields.io/badge/LIVEWIRE-3%20%7C%204-FB70A9?style=flat-square)](https://livewire.laravel.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/finity-labs/lin-codex.svg?style=flat-square)](https://packagist.org/packages/finity-labs/lin-codex)
[![Tests](https://github.com/finity-labs/lin-codex/actions/workflows/tests.yml/badge.svg)](https://github.com/finity-labs/lin-codex/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/finity-labs/lin-codex.svg?style=flat-square)](https://packagist.org/packages/finity-labs/lin-codex)

## What it does

- **Contextual help.** Map articles to route names, page classes, or URL patterns. The Livewire drawer opens on the article that matches the current page.
- **Files, database, or both.** Ship default docs in your repo and let admins override or extend them in the database. `codex:import` and `codex:export` move content either way without losing front matter.
- **Multilingual.** One article, one translation row per language, with a configurable fallback when a translation is missing.
- **Search.** Accent-insensitive search on every database, with optional full-text search on MySQL, MariaDB and PostgreSQL.
- **Markdown with extras.** Callouts, step-by-step blocks with screenshots, tables of contents, and image lightboxes.
- **Works for guests.** `<x-lin-codex::help-button>` and `<x-lin-codex::help-drawer>` show public articles on login, registration and password reset pages.
- **Frontend stubs.** Publishable React and Vue drawer components for Inertia apps, talking to the JSON API.
- **No build step.** The drawer, the help center page and the stylesheet ship ready to use.

Filament panels get all of this plus an article editor and panel-aware contexts through [fin-codex](https://github.com/finity-labs/fin-codex).

## Requirements

- PHP 8.2 or newer
- Laravel 11, 12 or 13
- Livewire 3 or 4
- MySQL, MariaDB, PostgreSQL or SQLite

## Installation

```bash
composer require finity-labs/lin-codex
php artisan codex:install
```

`codex:install` publishes the config file, runs the migrations, seeds the settings and builds the search index. Add `--assets` to publish the stylesheet to `public/vendor/lin-codex` instead of serving it from the package route.

Then add the components to your layout:

```blade
<head>
    <x-lin-codex::styles />
</head>
<body>
    <x-lin-codex::help-button />
    <x-lin-codex::help-drawer />
</body>
```

Create your first article and open the drawer with `Ctrl+/`:

```bash
php artisan codex:make getting-started --title="Getting started"
```

Articles live in `resources/codex/{locale}/`. The help center page is at `/help`.

## Writing articles

Articles are Markdown. Everything below degrades to plain CommonMark on GitHub or in any other viewer, so the files stay readable outside the app.

### Front matter

A YAML block at the top of the file holds the article's metadata. The renderer strips it from the body; the file source reads it. The full key list is under [Content sources](#content-sources).

```markdown
---
title: Roles
visibility: public
---

Body starts here.
```

### Callouts

GitHub-style alerts. The marker is case-insensitive. Text after the marker becomes the title; without it the title is the translated type name (`Note`, `Tip`, `Important`, `Warning`, `Caution`). Elsewhere they render as blockquotes.

```markdown
> [!WARNING] Before you delete a user
> Their articles stay published.
```

### Steps

Wrap a numbered list in a `:::steps` fence. The first paragraph of each item is the step title. Anything indented under it, such as a screenshot, a callout or a code block, belongs to that step. A screenshot line becomes a figure.

```markdown
:::steps
1. Open the users page

   ![Users page](/storage/codex/users.png "The users list")

2. Click **Add** and fill in the form
:::
```

### Details

A collapsible block with a summary line. Without a title the summary reads `Details`.

```markdown
:::details Advanced options
Only needed when the connection uses a proxy.
:::
```

### Images

An image on its own line becomes a figure. The title, if given, is the caption. Images load lazily and carry a lightbox hook.

```markdown
![Alt](/storage/codex/a.png "Caption")
```

### Links

Link to another article with its relative file path, optionally with a section. The path is resolved against the current article's slug, `.md` is dropped, and the href is built under `lin-codex.routes.help_center` (`/help` by default). Links to other hosts open in a new tab.

```markdown
See [Roles](roles.md) and [Invoices](../billing/invoices.md#totals).
```

### Headings

Every heading from `##` down gets an id from its text and a `#` permalink: `## Reset a password` gives `#reset-a-password`. Duplicate headings get `-2`, `-3` suffixes. Second- and third-level headings make up the table of contents.

### HTML articles

An article can also be stored as HTML. It is sanitized on every render with an allowlist: scripts, embeds, forms, event handlers and `style` attributes are dropped, and classes survive only when they start with `codex-`. Headings get the same ids and permalinks, and article links work the same way.

### Rendering from code

```php
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;

$article = app(ArticleRenderer::class)->render($body, ArticleFormat::Markdown, 'en', 'users/roles');
$article->html;       // safe HTML with codex-* classes
$article->toc;        // [['level' => 2, 'text' => 'Reset a password', 'id' => 'reset-a-password'], ...]
$article->plainText;  // search text
```

Results are cached under a key built from the content hash, the format, the locale, the slug and a fingerprint of the renderer config, so an edit or a config change invalidates on its own; the store and TTL live under `lin-codex.render.cache`. Every class the renderer emits is prefixed `codex-`. The CSS for those classes is the prebuilt stylesheet (see [Help drawer](#help-drawer)).

## Content sources

Articles come from Markdown and HTML files, from the database, or from both. `lin-codex.source` picks which: `filesystem`, `database` or `composite` (the default). In composite mode a slug that exists in the database hides the file version for every language, so an admin can take over any article you ship. Composite assumes the `codex_*` tables exist; an install that only ships files sets `filesystem`.

### Folder layout

Files live under `resources/codex/{locale}/` by default. The locale folder is required even when you only write one language. `lin-codex.sources.filesystem.paths` is an ordered list of such folders; a later path replaces an earlier one per slug, whole article, so a package can ship its docs and the app can override single files.

```
resources/codex/
├── en/
│   ├── 01-intro.md            # slug "intro", order 1
│   ├── 02-users/
│   │   ├── index.md           # slug "users": the section's own article
│   │   ├── 01-roles.md        # slug "users/roles"
│   │   └── images/users.png
│   └── billing/
│       └── invoices.md        # slug "billing/invoices"; "billing" is a group with no article
└── de/
    ├── 01-intro.md
    └── 02-users/index.md
```

The slug is the path without the locale folder, the numeric prefixes and the extension. `01-intro.md` is `intro` with order 1. A prefix needs a separator, so `2fa.md` stays `2fa`. A folder with an `index.md` is a section: the index file is the section's own article and the other files are its children. A folder without one is a group, shown in the tree by its name. Both `.md` and `.html` files are articles; the extension sets the format.

### Front matter

Front matter is optional. Everything except `title` and `excerpt` is read from the default-language file only. Other languages contribute their title and excerpt; any other key in them is ignored with a warning.

```markdown
---
title: Users
excerpt: Who can sign in and what they may do.
icon: heroicon-o-users
order: 2
visibility: public
published: true
contexts:
  - route:users.index
  - url:/admin/users/*
  - class:App\Filament\Resources\UserResource
  - admin:class:App\Filament\Resources\UserResource
related:
  - users/roles
  - billing/invoices
keywords:
  - accounts
  - sign in
---
```

| Key | What it does |
|---|---|
| `title` | Falls back to the first level-one heading, which is then removed from the body, and then to the file name (`reset-password` becomes `Reset password`). |
| `excerpt` | A short summary for lists and search results. |
| `slug` | Replaces the last segment of the slug. The folder still decides the parent. |
| `icon` | An icon name for the tree and the drawer. |
| `order` | Sort position among siblings. Defaults to the numeric prefix, else 0. |
| `visibility` | `public` or `authenticated` (default). |
| `published` | Defaults to `true`. |
| `contexts` | Pages this article belongs to: `route:name`, `url:/pattern/*` or `class:Fully\Qualified\Page`, each with an optional `panel:` prefix. |
| `related` | Slugs of related articles. |
| `keywords` | Extra words folded into the search text. |
| `format` | `markdown` or `html`. Defaults to the extension. |

There's no `parent` key; the folder is the parent. Unknown keys are kept in the article's `meta` bag and survive export.

`codex:export` writes front matter in a fixed key order (title, excerpt, slug, icon, order, visibility, published, contexts, related, keywords, format, then your own keys), always writes `title`, quotes values the way symfony/yaml does, and omits keys the path already implies, so an exported file may differ in form from the one you wrote while meaning the same thing. Keys without a value are left out too: `published` only appears as `false`, and an empty list is never written.

A few YAML rules worth knowing: quote a title with a colon in it (`title: "Users: overview"`), `published: no` reads as false, quote a date you want kept as text, and keys are case-sensitive. A file with invalid front matter is skipped and reported; the rest of the folder still loads. Everything reported ends up in `ContentSource::warnings()`.

### Languages

One file per language at the same relative path: `en/02-users/index.md` and `de/02-users/index.md` are the same article. An article that only exists in a non-default language still loads, with a warning, and takes its metadata from that file.

### Images

Reference images relative to the article file, the way any Markdown editor does: `images/users.png` or `../images/logo.png`. The file source rewrites them to `/codex/media/{locale}/{path}`, and a route serves them from the docs folders with cache headers. Images must sit inside a locale folder; a path that escapes it is left as written. Only png, jpg, jpeg, gif, webp and avif are served, since an SVG can carry scripts. An image is served only when no article references it or when one of the articles that do is visible to the current viewer, so screenshots inside internal articles stay internal. The prefix lives in `lin-codex.routes.media` and the middleware in `lin-codex.routes.middleware`.

### Freshness

Every read checks a cheap fingerprint per docs folder: the file count and the newest modification time. When it changes, the folder is rescanned and re-cached, so an edit shows on the next request without clearing anything. An edit that keeps the modification time isn't detected; `codex:cache-clear` is the manual override.

### Reading from code

```php
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Enums\ContextType;

$source = app(ContentSource::class);
$source->findBySlug('users/roles');                          // ArticleData or null
$source->findByContext(ContextType::Route, 'users.index');   // ArticleData[] for that page
$source->tree();                                             // TreeNode[] roots
$source->allForSearch();                                     // SearchDocument[], one per language; the searcher builds its index from these
$source->warnings();                                         // SourceWarning[]
```

Every method returns plain readonly objects, never an Eloquent model. The source applies no locale, visibility or published filtering. That's what the read services under [Reading articles](#reading-articles) do.

## Reading articles

Three services answer the three questions the help UI asks: which articles belong to this page, show me this article, and show me the whole tree. Each takes a `Viewer` and an optional locale, and all three apply the same visibility and language rules, so the drawer, the API and a Tinker session always see the same thing.

### From Tinker

```php
use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contexts\ContextResolver;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Reading\ArticleReader;
use FinityLabs\LinCodex\Reading\TreeBuilder;

$viewer = app(ViewerResolver::class)->resolve();          // guest in Tinker unless you Auth::login() first

app(ContextResolver::class)->resolve(new PageContext('users.index', '/users'), $viewer);   // ArticleData[] for that page, best first
app(ArticleReader::class)->read('users/roles', $viewer, 'de');                            // ReadArticle or null
app(TreeBuilder::class)->build($viewer);                                                   // TreeNode[] with translated labels
```

`ReadArticle` carries the `article`, the chosen `translation` and its `locale`, `isFallback`, the `rendered` result (`html` and `toc`), `related` entries (`slug` and `title`) the same viewer may read in the same language, and `breadcrumbs` for the visible ancestors. A translation from the database carries `updatedAt`, the ISO 8601 time of its last change; file articles report `null`. In a request, `RequestContextDetector::detect($request, $pageClass, $panelId)` builds the `PageContext` from the route name and path; hosts that know more, like a Filament panel with its resource class and panel id, pass them in. `PageContext::toArray()` and `fromArray()` let a Livewire component keep it in state, captured once at mount, which is exactly what the help drawer does.

### Who sees what

An article is visible when it's published, when it's `public` or the viewer is signed in, and when every parent article on its slug path passes the same test. A section marked `authenticated` therefore hides everything below it, public children included; move a child out of the section if it should stay public. Folders without an index file are groups, not articles, and hide nothing. Guests only ever get public articles.

Signed in means the guard in `lin-codex.auth.guard` has a user. `null` (the default) is the app's default guard, and it's one guard, not a list. `lin-codex.auth.gate` can name an invokable class `(Viewer $viewer, ArticleData $article): bool` that runs after the other checks and can only hide articles, never reveal them.

Hidden, restricted and missing articles are all the same answer: `null` from the reader, absent from the tree, an empty list from the context resolver, 404 from the media route, never 403. Nothing confirms that an article exists.

### Language fallback

The `codex` settings decide the languages: `languages` is the list a translation may be requested in, `default_locale` the one every article must have, and `fallback` what happens when a translation is missing. Matching is exact against that list, so `de_DE` doesn't fall back to `de`, and a locale that isn't listed counts as a missing translation. With `ShowDefault` you get the default-language translation with `isFallback` set, and the UI shows `__('lin-codex::lin-codex.fallback_notice', ['language' => ...])`, or simply `LocaleResolver::fallbackNotice($read->locale)`. With `Hide` the article is missing in that language, and children of a section hidden this way move up to the nearest visible ancestor.

The tree and the context lookup apply the same rule, so a page never offers an article the reader would then refuse. Folder group labels come from `lin-codex::lin-codex.groups.<full slug>` (`groups.billing/archive`) and default to the humanised folder name.

### Page contexts

A context key says which pages an article belongs to. There are three kinds:

| Key | Matches |
|---|---|
| `class:App\Filament\Resources\UserResource` | the exact class name the host reports; no parent classes or interfaces |
| `route:users.*` | the exact route name, or every name under a trailing `*` |
| `url:/users/*/edit` | the request path without the query string; `*` is one segment, `**` any depth |

The catch-alls `route:*` and `url:/**` are allowed and sort last. Prefix a key with a panel id (`admin:route:users.index`) to scope it to that panel.

Resolution takes the articles for the current panel first and falls back to panel-less ones only when the panel gave nothing visible. Within that, exact keys come before wildcards, then class before route before url, then the order the author gave, then the slug. One article may list many contexts, and many articles may share one; the drawer opens the first.

## Revisions

Database articles can keep a history of their previous content. File articles don't need one; git already versions them. Revisions are off by default. Turn them on in the settings and pick how many to keep:

```php
use FinityLabs\LinCodex\Settings\CodexSettings;

$settings = app(CodexSettings::class);
$settings->revisions_enabled = true;
$settings->revisions_keep = 10;      // the default
$settings->save();
```

With the switch on, every save of an existing translation whose title or body changed first stores the previous title, body and article format, with a reason, an author and a timestamp. Changing an article's format (Markdown to HTML or back) stores one revision per translation, since the old content was written in the old format. The keep count applies per article and language, in the same save, so ten English edits never evict a German revision.

A revision doesn't hold the excerpt, keywords, contexts or any other metadata. Nothing is stored when a translation is created, when only the excerpt changes, when revisions are off, or when the settings group hasn't been seeded yet; an unseeded group counts as off, so a save never fails on the switch.

### Reasons and authors

The reason is `manual` (the default), `import` (`codex:import --force` overwriting an existing article) or `ai_rewrite` (reserved for fin-codex). The author is the authenticated user of the configured guard when there is one, and `null` otherwise, which is what a console command records unless it's given `--user`. Host code that saves translations on someone's behalf says so explicitly:

```php
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Revisions\RevisionManager;

app(RevisionManager::class)->attributing(RevisionReason::AiRewrite, $userId, fn () => $translation->save());
app(RevisionManager::class)->withoutRevisions(fn () => $translation->save());   // bulk fixes, migrations
```

The scopes nest and the innermost wins: an `attributing()` inside `withoutRevisions()` records. `snapshot($translation, $reason, $userId)` stores the current content on request, whatever the switch says.

### Restoring

```php
app(RevisionManager::class)->restore($revision, $userId);
```

`restore()` snapshots the current content first, with reason `manual` and the given author, then puts the revision's title, body and format back. A translation deleted since the revision was taken is recreated from it. Because of that snapshot a restore is itself undoable: restore the newest revision to go back.

### Commands

`codex:revisions:prune [--keep=N]` prunes every article to the keep count, or to `--keep` for that run; it runs whether revisions are enabled or not. `codex:revisions:restore {id} [--user=ID]` restores a revision by id. Both are listed under [Commands](#commands).

```
$ php artisan codex:revisions:prune --keep=5
Removed 42 revisions from 7 articles (keeping 5 per language).

$ php artisan codex:revisions:restore 118 --user=1
Restored revision 118 (de) of users/roles.
```

## Searching

Users find articles by typing words in their own language. Results follow the same visibility and language rules as everything else, so a search never lists an article the reader would then refuse. The same query returns the same hits in the same order on MySQL, MariaDB, PostgreSQL, SQLite and on a file-only install, because the database only pre-filters rows; PHP decides what matches and how it ranks.

### From code

```php
use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Search\Searcher;

$viewer = app(ViewerResolver::class)->resolve();
$result = app(Searcher::class)->search('passwort zurück', $viewer, 'de', 10);   // SearchResult

$result->hits;                // SearchHit[]: slug, title, sectionPath, snippet, matchedField, score, isFallback
$result->total;               // number of hits returned (no pagination)
$result->rateLimited;         // true when the viewer is over the limit; hits is empty then
$result->retryAfterSeconds;   // seconds until the next allowed search, or null
```

The locale defaults to the app locale. `limit` defaults to `search.limit` (10) and is capped at `search.max_limit` (50). `snippet` is HTML: the matched word prefixes are wrapped in `<mark>` and everything else is escaped, so it's safe to print with `{!! !!}`. `sectionPath` holds the ancestor article titles, root first, for a "Users › Roles" line. `isFallback` and the fallback notice work as they do for `ReadArticle`. `matchedField->key()` is `title`, `keywords`, `excerpt` or `body`.

### What gets indexed

Every translation row has a `search_text` column holding the title, the article's keywords, the excerpt and the plain text of the body, lowercased and accent-folded. That's why `uber` finds `über`, `strasse` finds `Straße` and `hoseg` finds `hőség`. The column is filled when a translation is saved and refreshed when the article's keywords or format change. Rows written with the query builder skip the model hooks and stay unindexed. File articles get the same text when their folder is scanned.

Folding transliterates to ASCII, so scripts without a Latin transliteration (Chinese, Japanese, Korean) fold to nothing and aren't searchable in this release.

### How matching works

A query needs at least two characters (`search.min_length`); anything shorter returns an empty result without touching the rate limit. The query is folded the same way as the text and split into words. Every word must match (AND), and a word matches the start of a word in the text, so `pass` finds `password` but `word` doesn't.

Ranking happens in PHP. A hit in the title beats one in the keywords, which beats the excerpt, which beats the body. Within a tier the exact phrase and repeated words earn a bonus, and ties break by title, then slug.

### Engines

`search.engine` picks the database pre-filter. The default, `like`, runs a word-start `LIKE` per token and works the same on every database. `fulltext` uses the index: MySQL and MariaDB match in boolean mode, PostgreSQL uses `to_tsquery` with the text search configuration in `search.pgsql_language` (the index is built with the same configuration, so keep `simple` for a manual written in several languages), and SQLite stays on `LIKE`. The migration creates the index whichever engine is set, so switching is a config change, `CODEX_SEARCH_ENGINE=fulltext` in `.env`, not a migration.

Words shorter than three characters and MySQL stopwords (`the`, `und`, ...) would be dropped by the full-text engines, so with `fulltext` a query containing one takes the `LIKE` path, and a full-text query that finds nothing is retried once with `LIKE`. Either way the hits, their order and the snippets are the same, because the matcher re-checks every candidate in PHP. `search.candidates` (200) caps the rows the pre-filter hands to PHP.

### File-only installs

The filesystem source is searched through an in-memory index: the folded documents are cached under one key and rebuilt when the content changes, so an edit shows on the next search. A composite install searches the database and the file-only articles and merges them; a slug that exists in the database wins. `codex:cache-clear` drops the index and `codex:reindex` rebuilds it.

### Rate limits

`search.rate_limit.guest` (30 per minute, by IP) and `search.rate_limit.user` (120 per minute, by user id) throttle searches; `null` disables a tier. The limit lives in the search service, so the JSON API and the drawer share one counter. Over the limit the result is empty with `rateLimited` set and `retryAfterSeconds` saying how long to wait; nothing is thrown. Queries under the minimum length don't count. Behind a proxy, configure trusted proxies so the IP is the client's and not the proxy's.

## JSON API

Four GET endpoints under `routes.api` (`/codex/api` by default) answer the same questions the services do, as JSON. They run on the `routes.middleware` group (`web` by default), so the session identifies the viewer and an Inertia page needs no token. Every read applies the visibility and language rules described above: a guest gets public articles only, and a hidden article is a 404. The contract is frozen; later releases add keys, they don't rename or remove them.

| Endpoint | Answers |
|---|---|
| `GET /codex/api/tree?locale=` | the tree the viewer sees |
| `GET /codex/api/articles/{slug}?locale=` | one rendered article; the slug may contain slashes (`articles/users/roles`) |
| `GET /codex/api/search?q=&limit=&locale=` | the hits with snippets |
| `GET /codex/api/context?route=&path=&class=&panel=&locale=` | the ordered articles for a page, built from the query string, never from the request the API itself received |

### Envelope

Every success is `{ "data": ..., "meta": ... }`. `meta.locale` is the requested locale, or the app locale, and `meta.defaultLocale` the default from the settings; both are on every answer. The article adds `isFallback`; search adds `query`, `total`, `limit`, `rateLimited` and `retryAfterSeconds`; context echoes `route`, `path`, `class` and `panel` as the server understood them.

```json
{
  "data": {
    "slug": "intro",
    "title": "Introduction",
    "excerpt": "What Codex does and where to start.",
    "locale": "en",
    "isFallback": false,
    "format": "markdown",
    "html": "<p>Welcome to the help center. ...</p>",
    "toc": [{ "level": 2, "text": "Where to start", "id": "where-to-start" }],
    "breadcrumbs": [],
    "related": [{ "slug": "users", "title": "Users" }, { "slug": "users/roles", "title": "Roles" }],
    "icon": "heroicon-o-book-open",
    "updatedAt": null
  },
  "meta": { "locale": "en", "defaultLocale": "en", "isFallback": false }
}
```

Tree nodes carry `slug`, `title`, `icon`, `isGroup`, `isFallback`, `hasArticle` and `children`. Search hits carry `slug`, `title`, `sectionPath`, `snippet`, `matchedField`, `score` and `isFallback`. Context entries carry `slug`, `title`, `excerpt` and `isFallback`. `format` and `matchedField` are the enum keys: `markdown` or `html`, and `title`, `keywords`, `excerpt` or `body`.

### Errors

Errors are `{ "message": "..." }`: 404 for a missing, hidden or unpublished article (never 403), 422 for a missing `q` or a `limit` that isn't a whole number, and 429 with a `Retry-After` header when the search limiter refuses. A query under `search.min_length` is a 200 with empty data. A `locale` outside the settings list isn't an error either: it counts as a missing translation, so the article comes back with `isFallback` set or as a 404, by the `fallback` setting.

### Config

`routes.api` sets the prefix and `routes.middleware` the group. Swapping the group, for example to `api` with Sanctum, is the whole story for token auth; the endpoints don't care how the viewer was identified.

## React and Vue stubs

Inertia apps get a help drawer as published components:

```bash
php artisan vendor:publish --tag=lin-codex-react
php artisan vendor:publish --tag=lin-codex-vue
```

The tags are alternatives and both land in `resources/js/codex`: `types.ts` (the payloads as TypeScript types), `codex.ts` (a fetch client over the four endpoints), `HelpButton` and `HelpDrawer` (`.tsx` or `.vue`), and a README. The drawer opens on the button, on `Ctrl+/`, on a `codex:open` window event with an optional slug and on a `?codex=slug` query parameter; it shows the current page's articles first, then search and the tree, and renders an article with the fallback notice when it came from another language.

The drawer learns which page it is on from Inertia shared props. Share the prefix and the page context from `HandleInertiaRequests`:

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

Then `<HelpDrawer prefix={codex.prefix} context={codex.context} />` in the React layout, or `<HelpDrawer :prefix="page.props.codex.prefix" :context="page.props.codex.context" />` in the Vue one. After publishing the files are the app's: nothing is loaded automatically, there's no npm package, and every class is prefixed `codex-` so the package stylesheet applies if you include it. The published README has the props, the client and the event.

## Help drawer

The drawer is a Livewire slide-over: the current page's articles first, then search and the whole tree. There is no build step; Alpine comes with Livewire and the styles are one prebuilt stylesheet the package serves itself. It reads through the same services as the JSON API, so a guest sees in the drawer exactly what the API would give a guest. Three tags in a layout:

```blade
<head>
    <x-lin-codex::styles />
</head>
<body>
    <x-lin-codex::help-button />          {{-- anywhere: a navbar, a footer --}}
    …
    <x-lin-codex::help-drawer />          {{-- once, before </body> --}}
</body>
```

Both components work on guest layouts (login, registration, password reset) and show public articles only there. Nothing else needs configuring.

### The button

`<x-lin-codex::help-button />` renders an anchor with a question-mark icon that opens the drawer. Props:

- `label` adds text next to the icon.
- `floating` renders it as a fixed pill in the bottom-right corner.
- `badge` shows how many articles the current page has; `:badge="false"` hides it. Zero hides it too.
- `count` overrides the number.
- `page-class` and `panel-id` are what fin-codex passes for a Filament resource and its panel; give the drawer the same values.

Host classes and attributes merge onto the anchor. The count is resolved once per request and shared with the drawer, so the badge and the drawer's page list always agree. Without JavaScript the button is a plain link to the help center.

### The drawer

`<x-lin-codex::help-drawer />` mounts the `lin-codex.help-drawer` component. On open it shows the page's first article, with "Also on this page" listing the others when the page has several; a page without articles shows "No help for this page yet", the search box and the tree. Four views: this page, search, browse (the tree) and article. The back button walks the history one view at a time and the breadcrumbs go up the slug path. An article with three or more headings gets its "On this page" block expanded; below that it starts collapsed. An article served from another language carries the fallback notice.

Links inside an article: `data-codex-article` links (the relative `.md` links the renderer resolves) open in place, other same-host links close the drawer and navigate, external links open in a new tab. Images with the lightbox hook open in a lightbox. Search runs as you type with a 300 ms debounce, and a refused search shows the rate-limit line with the seconds to wait.

Page context, locale and page articles are captured once at mount as locked state: searching and browsing never re-read the request, and the client cannot change them. Props: `slug` opens the drawer on that article as soon as the page loads, `page-class` and `panel-id` as for the button, `locale` overrides the resolved language.

### Opening it from anywhere

- The keyboard shortcut in `lin-codex.ui.shortcut`, default `ctrl+/`. Cmd counts as Ctrl on a Mac. `null` disables it. It is ignored while typing in an input, textarea, select or editable element, and it toggles: pressing it again closes the drawer.
- `window.dispatchEvent(new CustomEvent('codex:open', { detail: { slug: 'users/roles' } }))` from any script; leave `detail` out to open on the page's articles.
- `?codex=users/roles` on any URL that renders the drawer. The parameter is read once on load and removed with `history.replaceState`.

Escape and a click on the overlay close it. Focus moves to the search box on open and returns to the element that opened the drawer on close.

### Help center

`/help` lists the topics and `/help/{slug}` shows an article. The routes are named `lin-codex.help-center` and `lin-codex.help-center.article` and run on the `routes.middleware` group. Three columns: the tree with the search box on the left, the article with its breadcrumbs in the middle, "On this page" on the right; a query replaces the article column with up to 50 hits. Set `lin-codex.routes.help_center` to move it; the article links the renderer writes follow.

`lin-codex.routes.help_center_layout` renders the page into a host layout instead of the package one. It must be a component layout that echoes `$slot` (Livewire wraps it as an anonymous component; an `@extends` layout does not work). The layout receives `$title` and adds `<x-lin-codex::styles />` itself.

### Styling

One prebuilt stylesheet, served at `/codex/assets/codex.css` (`lin-codex.routes.assets`) with a year-long immutable cache header and the file's hash as `?v=`, so an upgrade never serves a stale copy. To serve it from the web server instead:

```bash
php artisan vendor:publish --tag=lin-codex-assets
```

That copies it to `public/vendor/lin-codex/`, and the styles component links that copy from then on. Re-publish with `--force` after upgrading the package.

Every class is prefixed `codex-`, and the look comes from `--codex-*` custom properties on `.codex-root` (and on `.codex-help-button`, which renders outside the root): `--codex-bg`, `--codex-fg`, `--codex-muted`, `--codex-border`, `--codex-surface`, `--codex-accent`, `--codex-accent-fg`, `--codex-radius`, `--codex-space`, `--codex-font` and `--codex-drawer-width`. Override them after the package stylesheet:

```css
.codex-root, .codex-help-button {
    --codex-accent: #0f766e;
    --codex-radius: 4px;
}
```

`--codex-accent` defaults to `var(--primary, #2563eb)`, so a Filament panel's primary colour comes through without configuration. Dark mode applies under a `.dark` ancestor, or when the system prefers dark and no `.light` ancestor is present. The drawer width also comes from `lin-codex.ui.drawer_width` (pixels, default 480).

### Livewire 3 and 4

The components use only the API the two majors share, and CI runs the suite on both (`livewire/livewire ^3.0|^4.0`). If you mount them with `<livewire:…>` directly instead of through the Blade wrappers, the names are `lin-codex.help-drawer` and `lin-codex.help-center`.

## Commands

Every command is `php artisan codex:…`, and `--help` prints its options. The package registers them itself; there's nothing to add to the host.

### codex:install

```
codex:install [--force] [--assets]
```

| Option | What it does |
|---|---|
| `--force` | Overwrite an existing `config/lin-codex.php`. |
| `--assets` | Publish the stylesheet to `public/vendor/lin-codex/codex.css`. |

Takes an app from `composer require` to a working package in one run, in this order: publish the config unless it's already there; create the `settings` table of spatie/laravel-settings when the app has none; publish the package migrations (the five `codex_*` tables and the settings seed under `database/settings`) and run exactly those files; seed the `lin-codex` settings group if the seed didn't; publish the stylesheet with `--assets`; run `codex:reindex`; print the next steps.

```
$ php artisan codex:install
Installing lin-codex...

Publishing the config...
  Config published to config/lin-codex.php
Publishing the migrations...
  Migrations published
Running the package migrations...
  2026_09_05_000001_create_codex_articles_table ........ DONE
  ...
  Migrations complete
  Settings seeded (lin-codex group, 10 revisions kept per language)
Re-indexing translations...
  0 translations indexed
  In-memory index rebuilt with 0 documents

lin-codex installed.

+-------------------------------+------------------------------------------------------------------------------------------+
| Next steps                    | Details                                                                                  |
+-------------------------------+------------------------------------------------------------------------------------------+
| Add the styles                | <x-lin-codex::styles /> in <head>                                                        |
| Add the button and the drawer | <x-lin-codex::help-button /> anywhere, <x-lin-codex::help-drawer /> once before </body>  |
| Write the first article       | php artisan codex:make intro --title="Introduction" (resources/codex/en/ is created for you) |
| Find pages without help       | php artisan codex:coverage                                                               |
+-------------------------------+------------------------------------------------------------------------------------------+
```

Every step is safe to repeat: a second run reports `Config already published (pass --force to overwrite)` and `Nothing to migrate`. The migrations run by path, so the app's own pending migrations stay out of an install that only asked for the package's; they're recorded in the migrations table like any other, and the next plain `migrate` skips them.

### codex:uninstall

```
codex:uninstall [--force] [--files]
```

| Option | What it does |
|---|---|
| `--force` | Skip the confirmation. |
| `--files` | Also delete the published config, stylesheet, React and Vue stubs and migration files. |

Reverses `codex:install`; run it before `composer remove finity-labs/lin-codex`. It lists what will go and asks `Remove everything listed?` (declining exits 1 and removes nothing), then drops the five tables under the names in `lin-codex.table_names`, deletes the `lin-codex` settings rows, deletes the `create_codex_*` records from the migrations table so a reinstall migrates again, and clears the settings cache and the package caches. `--files` adds `config/lin-codex.php`, `public/vendor/lin-codex`, `resources/js/codex` and the published `create_codex_*` migration and settings files. Docs folders are content you wrote and are never touched.

```
$ php artisan codex:uninstall --force
This will remove:
  - table codex_media
  - table codex_article_revisions
  - table codex_article_contexts
  - table codex_article_translations
  - table codex_articles
  - the lin-codex settings rows
  - the create_codex_* rows in the migrations table
  - the package caches
Docs folders are never touched.
  Dropped codex_media
  Dropped codex_article_revisions
  Dropped codex_article_contexts
  Dropped codex_article_translations
  Dropped codex_articles
  Settings rows deleted
  6 migration records deleted
  Caches cleared

lin-codex uninstalled. Remove the package with: composer remove finity-labs/lin-codex
```

### codex:import

```
codex:import [--only=SLUG]... [--locale=XX] [--force] [--dry-run] [--user=ID]
```

| Option | What it does |
|---|---|
| `--only=slug` | Import only these slugs; repeat the option for several. |
| `--locale=xx` | Import one language only. |
| `--force` | Overwrite articles that already exist in the database. |
| `--dry-run` | Print the summary without writing. |
| `--user=id` | Record this user id as the author of the articles and of the revisions. |

Reads every folder in `lin-codex.sources.filesystem.paths` through the file source, whatever `lin-codex.source` is set to, and writes the articles into the database through the models, so parent links, `search_text` and revisions come from the same hooks an editor's save goes through. An article whose slug already exists in the database is skipped and listed: a re-import never silently destroys an admin's edits. `--force` overwrites it and, when revisions are on, records an `import` revision for each translation whose title or body changed; an unchanged translation records nothing. Each article is written in its own transaction, so one failure (an unknown `--user`, a constraint) lands under `Failed` for its languages and the run carries on with the next slug. The article's `source_path` is stored relative to its docs folder (`en/02-users/index.md`), which is how `codex:export` finds the file again.

```
$ php artisan codex:import
Importing articles from /srv/app/resources/codex...
+--------+---------+---------+---------+--------+
| Locale | Created | Updated | Skipped | Failed |
+--------+---------+---------+---------+--------+
| de     | 2       | 0       | 1       | 0      |
| en     | 5       | 0       | 1       | 0      |
+--------+---------+---------+---------+--------+
Skipped: intro
  Pass --force to overwrite articles that already exist in the database.
```

Failures print under `Failed:` with their reason and the warnings the file source raised (invalid front matter, duplicate slugs) under `Warnings:`. The exit code is 1 only when an article failed; skipped articles and warnings are normal. With `--locale`, an article that has no file in that language is neither written nor listed.

### codex:export

```
codex:export [--only=SLUG]... [--locale=XX] [--path=DIR] [--dry-run]
```

| Option | What it does |
|---|---|
| `--only=slug` | Export only these slugs; repeat the option for several. |
| `--locale=xx` | Export one language only. |
| `--path=dir` | Write under this folder instead of the configured docs path. |
| `--dry-run` | Print the summary without writing. |

Writes every database article back to files, published or not, in every language it has. An article with a recorded `source_path` goes there, under the first configured docs path that already holds that file (else the first configured path). An article without one goes to `{locale}/{slug}.md`, or `{locale}/{slug}/index.md` when it has children, and `.html` for an HTML article. Other languages reuse the default language's path with the locale segment swapped, which is where the file source looks for them. `--path` writes everything under that folder instead, the safe way to look at an export before it lands in your repo. A file counts as created when it didn't exist and updated when it did.

Bodies carry media-route URLs in the database; they're turned back into paths relative to the file being written, and the images they name are copied from the docs path that holds them when the target is a different folder. Nothing is fetched from the media disk: only file articles can have images beside them, and an image no docs path holds is a warning. The front matter comes out in the canonical form described under [Front matter](#front-matter-1), so the round trip through the database keeps every key while the file may look a little different from the one you wrote.

```
$ php artisan codex:export --path=/tmp/codex-export
+--------+---------+---------+---------+--------+
| Locale | Created | Updated | Skipped | Failed |
+--------+---------+---------+---------+--------+
| de     | 3       | 0       | 0       | 0      |
| en     | 6       | 0       | 0       | 0      |
+--------+---------+---------+---------+--------+
```

Without a configured docs path and without `--path` the command exits 1 and says so. A file that can't be written lands under `Failed` and the run carries on.

### codex:coverage

```
codex:coverage [--json] [--no-fail]
```

| Option | What it does |
|---|---|
| `--json` | Print JSON instead of a table. |
| `--no-fail` | Exit 0 even when routes lack coverage. |

Lists the pages of the app and which help article each one maps to. A route is a page when it answers GET, has a name, and runs in the `web` middleware group or carries the session middleware somewhere in its expanded stack, which is how Filament panel routes qualify (they list their middleware classes and never name the group). Two config lists leave the noise out: `lin-codex.coverage.ignore` holds route-name globs (by default `livewire.*`, `ignition.*`, `telescope.*`, `horizon.*`, `lin-codex.*`, `sanctum.*`, `debugbar.*`, `filament.*.auth.*` and `storage.*`) and `lin-codex.coverage.vendor_namespaces` holds class prefixes of actions to skip (Livewire, Telescope, Horizon, Ignition, Sanctum, Debugbar, this package and Filament's own pages). Add your own entries to either.

A route is covered when a context matches it in any panel: its name against `route:` keys (exact or with a trailing `*`), its URI template against `url:` patterns (a placeholder such as `{user}` is one segment), and its page class against `class:` keys, where the page class is the route's controller or, for a Livewire route, the component behind it. Contexts are read from the content source without the viewer gate and without the published filter: an unpublished or members-only article still counts as coverage, because the question is whether a mapping exists, not whether a guest would see it.

```
$ php artisan codex:coverage
+-------------+---------------+-----------------+---------+
| Route       | URI           | Matched by      | Article |
+-------------+---------------+-----------------+---------+
| dashboard   | /             | route:dashboard | intro   |
| users.edit  | /users/{user} | url:/users/*    | users   |
| users.index | /users        | none            |         |
+-------------+---------------+-----------------+---------+
1 of 3 routes have no help article.
```

The exit code is 1 when any route lacks an article, so the command can gate a deploy; `--no-fail` keeps the output and exits 0. `--json` prints `{"routes": [...], "uncovered": N, "warnings": [...]}` where every route carries `name`, `uri`, `pageClass`, `matchedBy`, `slug` and `covered`. Source warnings print under `Warnings:` before the summary line.

### codex:cache-clear

```
codex:cache-clear
```

Drops everything the package caches and prints one line per cache:

```
$ php artisan codex:cache-clear
  Rendered HTML ...................... generation 2 (old entries expire with their ttl)
  File sources ....................... 2 entries forgotten
  Search index ....................... forgotten
  Context index ...................... not cached (rebuilt per request)
  Stylesheet hash .................... not cached (in memory only)
```

- **Rendered HTML.** The render store can't be enumerated, so the command bumps a generation number that is part of every render cache key. Every rendered article is orphaned at once and expires with its TTL; the line names the new generation. The counter lives on the render store (`lin-codex.render.cache.store`), so a queue worker picks it up on its next render.
- **File sources.** The fingerprint and parsed set of every docs folder, forgotten and counted. This is the override for an edit that kept the file's modification time.
- **Search index.** The in-memory index of a file-only or composite install; the next search rebuilds it, or `codex:reindex` does.
- **Context index** and **stylesheet hash** are listed so you know there's nothing to clear: one is rebuilt per request, the other lives in memory only.

### codex:reindex

```
codex:reindex
```

Recomputes `search_text` for every translation row, in chunks, saving with timestamps off so `updated_at` (what editors and the export see) doesn't move. That's the fix for rows written with the query builder (seeders, migrations), which skip the model hooks, and for rows indexed before a `SearchText::VERSION` bump in a package upgrade. Then it rebuilds the in-memory index for the configured source mode: every article in `filesystem` mode, the file-only articles in `composite` mode, skipped in `database` mode where a search never reads it. `codex:install` ends with it.

```
$ php artisan codex:reindex
Re-indexing translations...
  14 translations indexed
  In-memory index rebuilt with 6 documents
```

### codex:make

```
codex:make {slug} [--locale=XX] [--title=TITLE] [--section] [--format=markdown|html] [--force]
```

| Option | What it does |
|---|---|
| `--locale=xx` | Language folder; defaults to the settings' default locale. |
| `--title=…` | Article title; defaults to the humanised last segment of the slug. |
| `--section` | Create `slug/index.md` for a folder that will hold children. |
| `--format=` | `markdown` (the default) or `html`. |
| `--force` | Overwrite an existing file. |

Writes the first file of a new article under the first configured docs path: `{locale}/{slug}.md`, `{locale}/{slug}/index.md` with `--section`, `.html` with `--format=html`. The slug may contain folders (`users/roles`) and every segment must be lowercase letters, digits and dashes. The locale must be a locale folder name (`en`, `de`, `pt-BR`), so the option can't write outside the docs root. An existing file is refused unless `--force`.

The scaffold doubles as the syntax cheat sheet: front matter with `title`, an empty `excerpt` to fill in, `order`, `visibility`, `published`, an empty `contexts` list and commented examples of `contexts`, `related` and `keywords`; then a level-two heading, a paragraph, a `:::steps` block with two steps and a figure placeholder, and a `> [!TIP]` callout. The HTML form uses an ordered list and a plain paragraph in place of the two Markdown-only blocks.

```
$ php artisan codex:make users/roles --title="Roles"
Created en/users/roles.md
  Add contexts to show it on a page; the file comments show the syntax.
```

The body text comes from the `make.*` lang group in the file's language. Only English ships: to scaffold in another language, publish the translations (`php artisan vendor:publish --tag=lin-codex-translations`) and translate the `make` group in `lang/vendor/lin-codex/{locale}/lin-codex.php`. A language without one gets the English text in the right folder.

### codex:revisions:prune

```
codex:revisions:prune [--keep=N]
```

Removes every article's revisions beyond the keep count, language by language, newest kept; `--keep` overrides `revisions_keep` for this run and leaves the settings untouched. Runs whether revisions are enabled or not. See [Revisions](#revisions).

### codex:revisions:restore

```
codex:revisions:restore {revision} [--user=ID]
```

Restores a revision by id after snapshotting the current content, so the restore can itself be undone; `--user` records the author of that snapshot, which a console run otherwise leaves empty. See [Revisions](#revisions).

## License

MIT. See [LICENSE](LICENSE).
