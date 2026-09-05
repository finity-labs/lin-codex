<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Every table the package creates is prefixed with "codex_" so it never
    | collides with tables in the host application. Change the names here
    | before running the migrations if you need something else.
    |
    */

    'table_names' => [
        'articles' => 'codex_articles',
        'article_translations' => 'codex_article_translations',
        'article_contexts' => 'codex_article_contexts',
        'article_revisions' => 'codex_article_revisions',
        'media' => 'codex_media',
    ],

    /*
    |--------------------------------------------------------------------------
    | Users Table
    |--------------------------------------------------------------------------
    |
    | The table the author foreign keys point at: "created_by" and
    | "updated_by" on articles, "user_id" on revisions and "uploaded_by" on
    | media. The migrations read this value, so set it before migrating. The
    | foreign keys assume an unsigned big-integer primary key; user tables
    | keyed by UUID are not supported.
    |
    */

    'users_table' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | The filesystem disk uploaded images are stored on and the directory
    | inside that disk. Images are referenced from article bodies by their
    | plain disk URL (for example "/storage/codex/abc.png"), so the disk
    | needs a public URL for images to render.
    |
    */

    'media' => [
        'disk' => 'public',
        'directory' => 'codex',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Source
    |--------------------------------------------------------------------------
    |
    | Where articles come from: "filesystem", "database", "composite", or the
    | name of a class implementing FinityLabs\LinCodex\Contracts\ContentSource.
    | "composite" (the default) reads both, and a slug that exists in the
    | database hides the file version for every locale. It assumes the
    | "codex_*" tables exist, so an install that only ships files and never
    | runs the migrations must set this to "filesystem"; the provider does
    | not check the schema on every request.
    |
    */

    'source' => 'composite',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Source
    |--------------------------------------------------------------------------
    |
    | "paths" is an ordered list of docs folders, each holding one folder per
    | locale ("resources/codex/en/intro.md"). A later path replaces an
    | earlier one per slug, whole article, so a package can register its
    | folder first and the application override single articles. A missing
    | folder is simply empty.
    |
    */

    'sources' => [
        'filesystem' => [
            'paths' => [
                resource_path('codex'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    |
    | "cache" controls where rendered articles are kept. A null store means
    | the default cache store; any name from config/cache.php works. The
    | cache key contains the content hash, the format, the locale, the slug
    | and a renderer fingerprint (this config, the app URL host and the
    | extension list), so an edit or a config change produces a new key on
    | its own and nothing needs clearing. A TTL is therefore only a memory
    | bound: null keeps entries forever, 0 disables caching, and an integer
    | is a lifetime in seconds.
    |
    | "limits" protect the Markdown parser against pathological input:
    | "max_nesting_level" caps nested blockquotes and lists,
    | "max_delimiters_per_line" caps emphasis markers on one line, and
    | "max_autocompleted_cells" caps the cells a ragged table may add. Input
    | beyond a limit is flattened to text rather than rejected.
    |
    | "sanitizer" applies to HTML-format articles. The library default of
    | 20,000 bytes would silently truncate long articles; -1 lifts the limit.
    |
    */

    'render' => [
        'cache' => [
            'store' => null,
            'ttl' => null,
        ],
        'limits' => [
            'max_nesting_level' => 50,
            'max_delimiters_per_line' => 500,
            'max_autocompleted_cells' => 10000,
        ],
        'sanitizer' => [
            'max_input_length' => -1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The URL prefix article-to-article links resolve under: "[Roles](roles.md)"
    | in the article "users/intro" becomes "/help/users/roles". The link also
    | carries a "data-codex-article" attribute so the help drawer can open it
    | in place; the href works without JavaScript. The help center is mounted
    | at the same prefix. A full URL is accepted as well.
    |
    | "media" is the prefix relative images in file articles are served
    | under ("{media}/{locale}/{path}"). Only image extensions are served
    | (png, jpg, jpeg, gif, webp, avif; svg is refused because inline SVG can
    | carry scripts), and only from the configured docs paths. An image is
    | served when no article references it or when a referencing article is
    | visible to the viewer; hidden owners answer 404.
    |
    | "help_center_layout" names the layout the help center page renders
    | in. It must be a component layout, one that receives "$slot" (such as
    | "components.layouts.app"); an @extends layout does not work. null uses
    | the package layout.
    |
    | "api" is the prefix of the JSON endpoints (tree, articles/{slug},
    | search, context).
    |
    | "assets" is the prefix the prebuilt stylesheet is served under
    | ("{assets}/codex.css"). That route runs without the middleware group:
    | a static file needs no session, cookies or CSRF.
    |
    | "middleware" is the middleware group the package routes run under.
    |
    */

    'routes' => [
        'help_center' => '/help',
        'help_center_layout' => null,
        'media' => '/codex/media',
        'api' => '/codex/api',
        'assets' => '/codex/assets',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Interface
    |--------------------------------------------------------------------------
    |
    | "shortcut" opens and closes the help drawer. Cmd+/ on a Mac counts as
    | Ctrl+/. null disables the shortcut.
    |
    | "drawer_width" is the drawer's width in pixels, written to the
    | --codex-drawer-width custom property on the drawer root.
    |
    */

    'ui' => [
        'shortcut' => 'ctrl+/',
        'drawer_width' => 480,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | "guard" is the auth guard that decides whether a viewer is signed in;
    | null means the application's default guard. Articles with the
    | "authenticated" visibility are shown only when this guard has a user.
    |
    | "gate" is an optional veto: the class name of an invokable receiving
    | (Viewer $viewer, ArticleData $article) and returning a bool. It runs
    | after the published and visibility checks and can only hide articles.
    | A closure set at runtime works too, but a closure in this file breaks
    | "config:cache".
    |
    */

    'auth' => [
        'guard' => null,
        'gate' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | "engine" is "like" (the default) or "fulltext". "like" runs the portable
    | LIKE pre-filter on every database. "fulltext" uses the full-text index
    | on MySQL, MariaDB and PostgreSQL, falling back to LIKE for queries with
    | a short or stopword token and when the index answers no rows; on SQLite
    | it is plain LIKE. The migration creates the index either way, so
    | switching is a config change (CODEX_SEARCH_ENGINE), never a migration.
    | Queries shorter than "min_length" folded characters return nothing
    | and do not count against the rate limit. "limit" is the default
    | number of hits, "max_limit" the most a caller may ask for.
    |
    | "candidates" caps the rows the database pre-filter hands to the PHP
    | matcher; a help manual never has hundreds of articles matching one
    | query, and the cap keeps a pathological query cheap.
    |
    | "snippet_length" is the rough size of a highlighted snippet in
    | characters. "pgsql_language" is the PostgreSQL text search
    | configuration used by both the index and the query; keep it "simple"
    | unless every article is in one language.
    |
    | "rate_limit" is per minute: guests by IP, signed-in users by user id.
    | null disables a tier. The limiter lives in the search service, so the
    | JSON API and the drawer share one counter.
    |
    */

    'search' => [
        'engine' => env('CODEX_SEARCH_ENGINE', 'like'),
        'min_length' => 2,
        'limit' => 10,
        'max_limit' => 50,
        'candidates' => 200,
        'snippet_length' => 160,
        'pgsql_language' => 'simple',
        'rate_limit' => [
            'guest' => 30,
            'user' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Coverage
    |--------------------------------------------------------------------------
    |
    | What "codex:coverage" considers: every named GET route that runs in
    | the "web" middleware group or whose expanded middleware stack starts
    | a session. The second rule is how Filament panel routes qualify; a
    | panel lists its middleware classes explicitly and never names the
    | "web" group.
    |
    | "ignore" are route-name globs to leave out, where "*" also crosses
    | dots ("filament.*.auth.*" covers "filament.admin.auth.login").
    |
    | "vendor_namespaces" are class-name prefixes of route actions to skip:
    | package routes with no page of their own. "Filament\" as a whole is
    | not listed because the default dashboard page is a vendor class and
    | one you want covered.
    |
    */

    'coverage' => [
        'ignore' => [
            'livewire.*',
            'ignition.*',
            'telescope.*',
            'horizon.*',
            'lin-codex.*',
            'sanctum.*',
            'debugbar.*',
            'filament.*.auth.*',
            'storage.*',
        ],
        'vendor_namespaces' => [
            'Livewire\\',
            'Laravel\\Telescope\\',
            'Laravel\\Horizon\\',
            'Spatie\\LaravelIgnition\\',
            'Spatie\\Ignition\\',
            'Laravel\\Sanctum\\',
            'Barryvdh\\Debugbar\\',
            'FinityLabs\\LinCodex\\',
            'Filament\\Http\\',
            'Filament\\Auth\\',
        ],
    ],

];
