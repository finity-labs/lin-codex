# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.1] - 2026-09-11

### Fixed

- Apps whose user model uses a string primary key (`HasUuids`, `HasUlids`) recorded no author at all. `codex_articles.created_by`, `codex_articles.updated_by`, `codex_article_revisions.user_id` and `codex_media.uploaded_by` were unsigned big integers with a foreign key to a literal users table, so the key could not be stored, and `RevisionManager` narrowed the authenticated viewer's id to `?int` on the way in, so it was already null by then. All four migrations now build the column with `foreignIdFor()` on the configured auth user model, so it comes out as a bigint, ULID or UUID to match; `lin-codex.users_table` still names the constrained table and falls back to that model's own table. Existing installs on a string-keyed user model need to alter the four columns by hand, see the README's Upgrading section
- `codex:import --user` refused anything but a whole number, so a UUID or ULID could not be given at all. Both it and `codex:revisions:restore --user` now take a digit string as an int, as before, or any other non-empty string as it is; an id that belongs to nobody still lands under `Failed` through the foreign key

### Changed

- The author id is typed `int|string|null` where it was `?int`: `Sync\ImportOptions::$userId`, `Revisions\RevisionManager::attributing()`, `snapshot()` and `restore()`, `Jobs\TranslateArticle::$userId` and `Events\ArticleTranslated::$userId`. Host code that passes `?int` keeps working; code that reads `ArticleTranslated::$userId` has to handle a string

## [0.4.0] - 2026-09-10

### Changed

- `lin-codex.routes.help_center` set to `null` switches the public help center off: neither `lin-codex.help-center` nor `lin-codex.help-center.article` is registered and `/help` and `/help/{slug}` answer 404 like any other unknown URL, while the drawer, `<x-lin-codex::help-button />` and the media, JSON API and stylesheet routes carry on. The `lin-codex.help-center` Livewire component and `routes.help_center_layout` stay registered too, so you can mount the page on a route of your own. The default is still `/help`.
- A prefix the route file cannot use — `""`, `"/"`, or a value that is not a string — is refused with an `InvalidArgumentException` naming the key and echoing the value, thrown when the route file loads and before any route is registered. It used to mount the help center at the site root. Only `null` switches the page off.
- `Rendering\ArticlePath::helpCenterHref(): ?string` gives the help center root as an absolute URL, or null when the page is off. The drawer footer and `<x-lin-codex::help-button />` build their link from it — from the prefix, read at call time — instead of from the `lin-codex.help-center` route name, so a prefix a host writes at runtime with `config()->set()` is followed and neither can throw a `RouteNotFoundException`. With the page off the footer renders without its "Open the help center" link and the button keeps its markup with `href="#"`, still opening the drawer on a click. `ArticlePath::href()` is unchanged and still writes `/{slug}` on a null prefix.
- `Livewire\HelpDrawer::viewData()`'s `helpCenterUrl` is `?string` where it was `string` — the one shape change in this release. A host layer that extends `HelpDrawer` and reads that array has to handle null.

### Fixed

- `Jobs\TranslateArticle::handle()` hands the throwable it catches to `report()` before it records the locale as failed with `unknown`, the way `Ai\LaravelAiClient` and `Translation\ArticleTranslator` have since 0.3.1. A `QueryException` or a translator guard on a queued run now leaves a stack in the host's log instead of a bare reason key.
- A null `lin-codex.routes.help_center` used to fall through to an empty prefix, which mounted the article route at the site root, where its catch-all pattern also swallowed the stylesheet route declared below it — `/codex/assets/codex.css` answered `text/html`. Switching the page off takes both routes out instead.

## [0.3.1] - 2026-09-10

### Fixed

- A translation, a tier lookup or a connection test that fails for a reason the seam cannot name (`AiReason::UNKNOWN`) now hands the original throwable to `report()` exactly once, in `Ai\LaravelAiClient::reason()` for an SDK or HTTP error and in `Translation\ArticleTranslator` for anything else, so the host's error tooling sees the real stack instead of a reason key. A failure with a named reason is still never reported.

### Changed

- `Translation\MissingTranslations::candidates()` reads the configured languages once per instance instead of on every call, so `for()` on an article whose translations are loaded costs no query; a new instance reads the settings again.

## [0.3.0] - 2026-09-10

### Added

- AI translation settings in a group of their own: `Settings\CodexAiSettings` (`enabled`, `provider`, `model`, an encrypted `api_key`, a `timeout` shared by both translation paths, and the editable `translation_instructions`) with its own seed under `database/settings`, so a group that was never seeded means AI is off rather than an error. `codex:install` publishes and runs the seed and `codex:uninstall` deletes its rows and its published file; an existing install opts in with `php artisan vendor:publish --tag=lin-codex-migrations` followed by `php artisan migrate`.
- A seam over the optional `laravel/ai` SDK, `Ai\Contracts\AiClient` with `Ai\LaravelAiClient` behind it: the nine offered providers under the SDK's own labels (Anthropic, OpenAI, Gemini, Mistral, Groq, DeepSeek, xAI, OpenRouter and Ollama), each provider's default, cheapest and smartest model, one structured call per translation with the stored key injected for that call alone and the SDK's env key as the fallback, a `Reply with the single word OK.` connection test on a ten-second timeout, output canaries, and a map from the SDK's exceptions to reason keys. Every SDK class is named as a string, so PHPStan and a host without the SDK are both fine.
- `Ai\AiAvailabilityCheck`, the one rule every entry point asks, answering `sdk_missing`, `not_migrated`, `disabled` or `no_key` in that order. A key stored in the settings wins, a provider's env key in the host's `config/ai.php` counts as well, and Ollama is checked through its URL because it needs no key.
- `Translation\ArticleTranslator` with `translate($article, $target)` for a stored article and `translateText($title, $excerpt, $body, $target)` for unsaved form fields. Both return a reviewed `Translation\TranslationResult` and write nothing. The prompt is a fixed contract the package owns followed by the admin's instructions, it names both languages by display name and code, and it keeps fenced and inline code, URLs and link targets, image paths, the callout keywords, the `:::steps` and `:::details` fences and HTML tags untranslated. Every answer is reviewed before it comes back: a truncated, empty or structurally broken payload fails as `invalid_output`, a canary the source itself does not carry fails as `output_rejected`, one outer code fence is stripped, and a blank source excerpt stays blank whatever the model wrote.
- `Translation\MissingTranslations`, naming the configured locales an article lacks; a row whose title or body is blank counts as missing and the default locale never does.
- `Jobs\TranslateArticle`, one queued job per article carrying locale codes and the id of the admin who queued it, on the app's default queue unless `lin-codex.ai.queue` names another. It re-checks availability and each locale at run time, skips a locale that was filled in the meantime, writes each success through the normal save path under `RevisionManager::attributing(RevisionReason::Manual, $userId)` so the revision and the search text ride along, leaves a failed locale missing with its reason and carries on, and always ends with an `Events\ArticleTranslated` event carrying a per-locale `Translation\TranslationReport`.
- Config block `lin-codex.ai` (`queue`, `max_tokens`, `check_structure`, `output_canaries`), and the lang keys `ai.reasons.*` and `ai.unavailable.*` in English, German and Hungarian.
- Two `+sdk` CI rows, PHP 8.3 on Laravel 12 and PHP 8.4 on Laravel 13, that install `laravel/ai` before the suite and run the AI tests; those tests skip on every other row, as they do on a host without the SDK.

### Changed

- `laravel/ai` (`^0.11`, which needs PHP 8.3 and Laravel 12 or newer) replaces the postponed `finity-labs/lin-ai` under `suggest`. lin-codex itself still runs on PHP 8.2 and Laravel 11; the SDK's floor applies only to a host that installs it.

## [0.2.2] - 2026-09-09

### Added

- Links to files download instead of navigating: a link whose path ends in one of `render.download_extensions` (PDF, the office formats, text, CSV and RTF by default) is stamped with a `download` attribute carrying the file name, in Markdown and HTML articles alike, and the sanitizer allows the attribute on `a`. The drawer leaves such a link to the browser rather than closing. Cached renders refresh through the markup version.

### Changed

- `media.directory` defaults to `codex/{Y}/{m}` and accepts the placeholders `{Y}`, `{m}` and `{d}`, which the uploader expands to the upload's year, month and day (fin-codex does from its next release), so a busy site's images spread over dated folders instead of one flat directory. A stored image keeps the path it was written under; a host that published the config keeps its own value.

## [0.2.1] - 2026-09-09

### Changed

- `Livewire\HelpDrawer` is no longer final and renders the view named by a new `viewName()` method, so a host layer can extend it and supply its own shell while keeping every property, action and the Alpine glue; `viewData()` assembles what the view needs, once per render, for the core view and any replacement. The glue now lives in `lin-codex::livewire.partials.drawer-script`, included inside the `@script` block of the core view and of any replacement view.


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
