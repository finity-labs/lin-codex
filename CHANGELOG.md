# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-09-05

### Added

- `guard` prop on `<x-lin-codex::help-drawer>`, `<x-lin-codex::help-button>` and the `lin-codex.help-drawer` component; it reaches `ViewerResolver` through `CapturesPageHelp::viewer()` and `PageHelpResolver`, whose memo is now keyed by guard too.
- `shortcut` and `width` props on the drawer, defaulting to `lin-codex.ui.shortcut` and `lin-codex.ui.drawer_width`; an empty string or `null` disables the shortcut for that drawer.
- A heading in `codex:open` (`detail.heading`) and in the deep link (`?codex=slug#heading`); the drawer scrolls to it once the article has rendered, and `ArticlePath::href()` accepts a bare heading id.
- `openCodex(slug, heading)` and the same heading contract in the React and Vue stubs.
- German and Hungarian translations, with a key-parity test against the English file.
- `RevisionReason::Restore`, recorded on the snapshot `restore()` takes (was `Manual`).
- `ArticleRevisionFactory::restore()`.

### Fixed

- `TableNameOverrideTest` no longer depends on test order on a persistent database: the harness kept dropping the wrong signature's tables after `markPackageSchemaDirty()`.

## [0.1.1] - 2026-09-05

### Changed

- README: added the Packagist download count badge and the release badges.

## [0.1.0] - 2026-09-05

### Added

- Package foundation: `codex_` prefixed tables for articles, translations, contexts, revisions and media, the `Article`, `ArticleTranslation`, `ArticleContext`, `ArticleRevision` and `Media` models with factories, int-backed enums with string keys, and the `codex` settings group (languages, default locale, fallback behaviour, revision retention).
- Markdown rendering through one locked-down league/commonmark environment: raw HTML stripped, unsafe links disabled, nesting and delimiter limits.
- GFM tables, task lists, strikethrough and autolinks.
- Heading ids derived from the heading text (`## Reset a password` gives `reset-a-password`, duplicates get `-2`, `-3`), a `#` permalink on every heading, and table-of-contents data for h2 and h3.
- GitHub-style callouts (`> [!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, `[!CAUTION]`) with optional custom titles and translated default titles.
- `:::steps` containers around a numbered list, and `:::details Title` containers.
- Images as figures with lazy loading, a lightbox hook and an optional caption.
- Relative `.md` article links resolved under `routes.help_center` and marked with `data-codex-article`.
- External links open in a new tab with `rel="noopener noreferrer"`.
- HTML-format articles sanitized with an allowlist and given the same heading ids, anchors and link handling as Markdown.
- Plain-text extraction for search from either format.
- `ArticleRenderer` façade with a render cache keyed by content hash, format, locale, slug and a renderer fingerprint, so edits and config changes invalidate without a manual cache clear.
- Config keys `render.cache.store`, `render.cache.ttl`, `render.limits.*`, `render.sanitizer.max_input_length` and `routes.help_center`.
- Lang keys `callouts.*`, `anchor_label` and `details_default`.
- `ContentSource` contract (`all`, `findBySlug`, `tree`, `findByContext`, `allForSearch`, `warnings`) returning readonly `ArticleData`, `TranslationData`, `ContextData`, `TreeNode`, `SearchDocument` and `SourceWarning` objects, never Eloquent models.
- Filesystem source: Markdown and HTML articles under `{path}/{locale}/`, numeric prefixes for ordering, `index.md` sections, groups for folders without one, YAML front matter (`title`, `excerpt`, `slug`, `icon`, `order`, `visibility`, `published`, `contexts`, `related`, `keywords`, `format`, unknown keys kept in `meta`), title from the first heading, default-language precedence for shared keys, relative image rewriting, search text at scan time, a fingerprint-checked cache that needs no manual clear, and collected warnings.
- Database source over the `codex_*` tables, and a composite source where a database slug hides the file version for every language.
- Config keys `source`, `sources.filesystem.paths`, `routes.media` and `routes.middleware`.
- Media route `/codex/media/{locale}/{path}` streaming images from the docs folders with cache headers, an image-only allowlist and traversal protection.
- Article links written with numeric file prefixes (`01-roles.md`) or pointing at `index.md` resolve to the right slug, and links inside section files resolve against their folder.
- Lang keys `source_warnings.*` and `enums.source_warning_kind.*`.
- `keywords`, `related` and `meta` JSON columns on articles, cast on the model, filled by the factory and mapped by the database source, so database articles carry the same metadata as file articles.
- `Viewer` and `ViewerResolver` (guard from `auth.guard` or the app default), and `ArticleGate`, the one visibility rule: published, public or signed in, an optional `auth.gate` veto, and every parent article on the slug path visible too.
- `LocaleResolver` with exact language matching against the settings list and the `ShowDefault`/`Hide` fallback flagged by `isFallback`.
- `ArticleReader`, `TreeBuilder` and `ContextResolver`: read one article rendered with related links and breadcrumbs, build the visible tree with translated labels, and resolve the articles for a page (panel first, then panel-less; exact before wildcard; class, route, url; author order; slug).
- `PageContext` and `RequestContextDetector` capturing route name, path, page class and panel id once, with an array form for component state; `url:` patterns with `*` for one segment and `**` for any depth, `route:` with a trailing `*`.
- Media route gated by the referencing articles: an image is served when unreferenced or when a referencing article is visible; hidden owners answer 404.
- `TreeNode::$isFallback` and `isGroup()`.
- Config keys `auth.guard` and `auth.gate`; lang keys `fallback_notice` and `groups.*`.
- A shared visibility dataset (`tests/Datasets/Visibility.php`) proving no read path leaks.
- Search: `Searcher::search()` returning readonly `SearchResult`/`SearchHit` objects with highlighted snippets, section paths, the matched field, a score and the language fallback flag; results are scoped by visibility, published state and locale before any full-text clause.
- Accent-folded `search_text` on translations, kept current by the model hooks (title, keywords, excerpt and body plain text) and refreshed when an article's keywords or format change.
- Driver-aware matching behind `search.engine` (`CODEX_SEARCH_ENGINE`, default `like`): `like` runs the portable `LIKE` pre-filter on every database; `fulltext` uses MySQL/MariaDB boolean full-text and PostgreSQL `to_tsquery` with the configured language, with `LIKE` for short or stopword tokens, a `LIKE` retry when full-text finds nothing, and plain `LIKE` on SQLite; the migration creates the index either way; ranking and snippets in PHP so every engine returns the same results.
- A cached in-memory search index for filesystem installs and for the file-only articles of a composite install.
- In-service rate limiting for searches (guests by IP, users by id) returning `rateLimited` and `retryAfterSeconds` instead of throwing.
- Config block `search.*` (`engine`, `min_length`, `limit`, `max_limit`, `candidates`, `snippet_length`, `pgsql_language`, `rate_limit.guest`, `rate_limit.user`); lang keys `enums.search_field.*` and `enums.search_strategy.*`; `SearchField` and `SearchStrategy` enums.
- The test suite runs on MySQL 8.4 and PostgreSQL 16 in CI next to SQLite (those two rows with `CODEX_SEARCH_ENGINE=fulltext`, every other row on the default), and the PostgreSQL full-text index language follows `search.pgsql_language`.
- The shared visibility dataset now drives search too.
- JSON API under `routes.api` (default `/codex/api`) on the `routes.middleware` group: `GET tree`, `GET articles/{slug}`, `GET search` and `GET context`, answering `{data, meta}` built from the read services' data objects, with 404 for missing or hidden articles, 422 for malformed search input and 429 with `Retry-After` when the search limiter refuses.
- `ReadArticle::$related` now lists `{slug, title}` pairs instead of bare slugs, resolved inside `ArticleReader` from the same content map.
- `TranslationData::$updatedAt`: the ISO 8601 time of the last change for database translations (`null` for file articles).
- `Searcher::effectiveLimit()` reporting the clamp a search runs under.
- Publishable React (`lin-codex-react`) and Vue (`lin-codex-vue`) help drawer stubs under `resources/js/codex`: a typed `codex.ts` client over the four endpoints, `HelpButton`, `HelpDrawer` (`Ctrl+/`, `codex:open` event, `?codex=slug` deep link) and a README.
- Config key `routes.api`; lang keys `api.not_found`, `api.rate_limited`, `api.missing_query`, `api.invalid_limit`.
- The shared visibility dataset now drives the JSON API too.
- Livewire help drawer (`lin-codex.help-drawer`) with the current page's articles first, search, tree navigation, back stack and breadcrumbs, a collapsible table of contents, the fallback notice, in-place article links, an image lightbox, the `ctrl+/` shortcut, the `codex:open` window event and the `?codex=slug` deep link; page context, locale and page articles are captured once at mount as locked state.
- Blade components `<x-lin-codex::help-button>` (icon, label, floating and badge variants), `<x-lin-codex::help-drawer>` and `<x-lin-codex::styles>`, working on guest layouts.
- Help center page (`lin-codex.help-center`) at `routes.help_center` with tree, breadcrumbs, table of contents, article body and search, in the package layout or a host component layout (`routes.help_center_layout`).
- Prebuilt, `codex-` prefixed stylesheet with `--codex-*` tokens and dark mode, served at `routes.assets` with immutable cache headers and a hash version, publishable under `lin-codex-assets`.
- Config keys `routes.assets`, `routes.help_center_layout`, `ui.shortcut`, `ui.drawer_width`; lang keys `ui.*`.
- CI runs the suite on Livewire 3 and Livewire 4.
- The shared visibility dataset now drives the Livewire components too.
- Revision history for database articles: with `revisions_enabled` on, every change to a translation's title or body (and every article format change) stores the previous content with its format, reason (`manual`, `import`, `ai_rewrite`), author and timestamp, and prunes each article and language to `revisions_keep` in the same save; `RevisionManager` with `snapshot()`, `restore()` (snapshot first, then swap), `prune()`, `attributing()` and `withoutRevisions()` for host code.
- Commands `codex:revisions:prune [--keep]` and `codex:revisions:restore {id} [--user]`.
- `codex:install` (config, settings table, package migrations only, settings seed, `--assets`, reindex, next steps) and `codex:uninstall` (tables, settings rows, migration records, caches; `--files` for the published files; docs folders never touched).
- `codex:import` (files to database through the models, skip or `--force` with import revisions, `--only`, `--locale`, `--dry-run`, `--user`, docs-relative `source_path`) and `codex:export` (database to files at the recorded or derived path, relativised image paths, copied images, `--path`, `--only`, `--locale`, `--dry-run`), with `FrontMatterWriter` emitting canonical front matter that round-trips losslessly.
- `codex:coverage` listing the named routes without a help article (route, url and class contexts in any panel; Filament panel routes included; `--json`, `--no-fail`), with config keys `coverage.ignore` and `coverage.vendor_namespaces`.
- `codex:cache-clear` dropping rendered HTML through a render cache generation, the file source caches and the in-memory search index, and `codex:reindex` rebuilding `search_text` and the in-memory index.
- `codex:make` scaffolding an article file with front matter hints and a starter body; lang keys `make.*`.
- Console commands are discovered from `src/Commands`.
