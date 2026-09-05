<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests;

use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\LinCodexServiceProvider;
use FinityLabs\LinCodex\Rendering\ArticleRenderer;
use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;
use FinityLabs\LinCodex\Sources\CompositeSource;
use FinityLabs\LinCodex\Sources\DatabaseSource;
use FinityLabs\LinCodex\Sources\FilesystemSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Package test harness.
 *
 * The database follows DB_CONNECTION: in-memory SQLite by default, a real
 * MySQL, MariaDB or PostgreSQL server when the CI service rows or a developer
 * set the six DB_* variables. The package migrations run through
 * include()->up(), never the framework migration loader, so custom table names
 * and the driver branches are exercised exactly as a host app would.
 *
 * Tests are never wrapped in a transaction: InnoDB full-text indexes only see
 * committed rows, so a refresh or transaction trait would make every
 * MATCH ... AGAINST test silently fall through to the LIKE path. On a
 * persistent server the schema is instead dropped and created once per PHP
 * process (so a crashed run leaves nothing stale), every test starts by
 * deleting the rows of the previous one inside a single committed
 * transaction, and the connection is closed when the application is
 * destroyed. In-memory SQLite is a fresh database per test as before.
 */
class TestCase extends Orchestra
{
    /**
     * Package schema migrations in dependency order (articles first).
     * Reused by tests that need to run down() or re-run up().
     *
     * @var list<string>
     */
    public const PACKAGE_MIGRATIONS = [
        'create_codex_articles_table',
        'create_codex_article_translations_table',
        'create_codex_article_contexts_table',
        'create_codex_article_revisions_table',
        'create_codex_media_table',
    ];

    /**
     * Testbench does not run package discovery, so Livewire is listed here.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            LinCodexServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', self::databaseConnectionConfig());
    }

    /**
     * The testing connection for env('DB_CONNECTION', 'sqlite'): in-memory
     * SQLite by default, a MySQL, MariaDB or PostgreSQL server when the CI
     * service rows or a developer set DB_CONNECTION and the DB_* variables.
     * phpunit.xml's <env> defaults never overwrite a variable that is already
     * in the shell, so the exported variables win.
     *
     * @return array<string, mixed>
     */
    public static function databaseConnectionConfig(): array
    {
        $driver = (string) env('DB_CONNECTION', 'sqlite');

        return match ($driver) {
            'mysql', 'mariadb' => [
                'driver' => $driver,
                'host' => (string) env('DB_HOST', '127.0.0.1'),
                'port' => (string) env('DB_PORT', '3306'),
                'database' => (string) env('DB_DATABASE', 'lin_codex'),
                'username' => (string) env('DB_USERNAME', 'root'),
                'password' => (string) env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => (string) env('DB_HOST', '127.0.0.1'),
                'port' => (string) env('DB_PORT', '5432'),
                'database' => (string) env('DB_DATABASE', 'lin_codex'),
                'username' => (string) env('DB_USERNAME', 'codex'),
                'password' => (string) env('DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        };
    }

    /**
     * The driver name of the testing connection: 'sqlite', 'mysql', 'mariadb'
     * or 'pgsql'. Meant for ->skip() closures on engine-specific tests.
     */
    public function databaseDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * The schema currently live on a persistent server: its signature
     * (driver, table names, users table) and the tables it owns. Exactly one
     * signature is live at a time, so a custom-table-names test never sees
     * the default tables and vice versa. Never consulted for SQLite, whose
     * in-memory database is new for every test.
     *
     * @var array{signature: string, tables: list<string>}|null
     */
    private static ?array $liveSchema = null;

    /**
     * Set by markPackageSchemaDirty(): the next test drops and recreates the
     * live signature's schema without forgetting which tables it owns.
     */
    private static bool $rebuild = false;

    /**
     * Runs after the providers boot. SQLite gets the full schema every time.
     * A persistent server gets it once per schema signature (dropping whatever
     * an earlier run or another signature left behind first); after that each
     * test only clears the rows and re-seeds the settings.
     */
    protected function defineDatabaseMigrations(): void
    {
        if ($this->databaseDriver() === 'sqlite') {
            $this->createPackageSchema();

            return;
        }

        $signature = $this->schemaSignature();

        if (self::$liveSchema !== null && self::$liveSchema['signature'] !== $signature) {
            $this->dropTables(self::$liveSchema['tables']);
            self::$liveSchema = null;
        }

        if (self::$liveSchema === null || self::$rebuild || $this->packageSchemaIsIncomplete()) {
            $this->dropPackageSchema();
            $this->createPackageSchema();
            self::$liveSchema = ['signature' => $signature, 'tables' => $this->packageTables()];
            self::$rebuild = false;

            return;
        }

        $this->clearPackageTables();
        $this->seedSettings();
    }

    /**
     * Close the connection so a long run never exhausts the server's
     * connection limit; PHP only collects the cycle the connection sits in
     * on a later GC pass. Teardown must never throw.
     */
    protected function destroyDatabaseMigrations(): void
    {
        try {
            $this->app['db']->purge();
        } catch (\Throwable) {
            // A failed disconnect must not turn a passing test into a failure.
        }
    }

    /**
     * Ask for a drop and create before the next test on a persistent server.
     * For tests that change the schema in a way a table listing cannot see,
     * such as rebuilding an index with another configuration. Only the
     * rebuild flag is raised: the signature memo stays, so a later signature
     * switch still drops these tables instead of leaving them behind.
     */
    public function markPackageSchemaDirty(): void
    {
        self::$rebuild = true;
    }

    /**
     * The host tables the package depends on come first, then the package
     * schema in dependency order, then the settings seed.
     */
    private function createPackageSchema(): void
    {
        $this->createUsersTable($this->usersTable());
        $this->createSettingsTable();

        foreach (self::PACKAGE_MIGRATIONS as $file) {
            $this->migration($file)->up();
        }

        $this->seedSettings();
    }

    /**
     * Drop everything createPackageSchema() creates, dependents first, with
     * foreign key checks off. CustomTableNamesTestCase drops its kb_* names
     * because down() and this method read the overridden config.
     */
    private function dropPackageSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse(self::PACKAGE_MIGRATIONS) as $file) {
            $this->migration($file)->down();
        }

        Schema::dropIfExists('settings');
        Schema::dropIfExists($this->usersTable());

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Drop the tables of a signature that is no longer current, dependents
     * first, with foreign key checks off.
     *
     * @param  list<string>  $tables
     */
    private function dropTables(array $tables): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse($tables) as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Delete every row of the package tables, the settings and the users
     * table in one committed transaction. DELETE is DML, so on InnoDB it
     * costs one fsync for the lot; TRUNCATE is DROP and CREATE in disguise
     * and takes over a second on a table with a full-text index.
     */
    private function clearPackageTables(): void
    {
        DB::transaction(function (): void {
            Schema::disableForeignKeyConstraints();

            foreach (array_reverse($this->packageTables()) as $table) {
                DB::table($table)->delete();
            }

            Schema::enableForeignKeyConstraints();
        });
    }

    private function seedSettings(): void
    {
        (include dirname(__DIR__).'/database/settings/create_codex_settings.php')->up();
    }

    /**
     * One table listing tells whether a test dropped part of the schema; the
     * next test then rebuilds it instead of failing on a missing table. The
     * listing is scoped to the connection's own schema: without it MySQL
     * reports every database the account can see.
     */
    private function packageSchemaIsIncomplete(): bool
    {
        $existing = Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false);

        return array_diff($this->packageTables(), $existing) !== [];
    }

    /**
     * Every table the harness owns, dependencies first.
     *
     * @return list<string>
     */
    private function packageTables(): array
    {
        $names = (array) config('lin-codex.table_names', []);

        return [
            $this->usersTable(),
            'settings',
            (string) ($names['articles'] ?? 'codex_articles'),
            (string) ($names['article_translations'] ?? 'codex_article_translations'),
            (string) ($names['article_contexts'] ?? 'codex_article_contexts'),
            (string) ($names['article_revisions'] ?? 'codex_article_revisions'),
            (string) ($names['media'] ?? 'codex_media'),
        ];
    }

    private function schemaSignature(): string
    {
        return $this->databaseDriver().'|'.implode(',', $this->packageTables());
    }

    private function usersTable(): string
    {
        return (string) config('lin-codex.users_table', 'users');
    }

    /**
     * Load one of the package's schema migrations by file name (without .php).
     */
    public function migration(string $file): Migration
    {
        return include dirname(__DIR__).'/database/migrations/'.$file.'.php';
    }

    /**
     * A renderer whose pipelines have not memoized any config yet. Use after
     * config()->set(): the singletons capture app.url, the limits and the
     * help-center prefix when first resolved.
     */
    protected function freshRenderer(): ArticleRenderer
    {
        $this->app->forgetInstance(MarkdownPipeline::class);
        $this->app->forgetInstance(HtmlPipeline::class);
        $this->app->forgetInstance(HtmlSanitizerInterface::class);
        $this->app->forgetInstance(ArticleRenderer::class);

        return $this->app->make(ArticleRenderer::class);
    }

    /**
     * Drop the resolved source singletons so the next make() reads the
     * current config (lin-codex.source, the docs paths) again.
     */
    protected function forgetSources(): void
    {
        $this->app->forgetInstance(FilesystemSource::class);
        $this->app->forgetInstance(DatabaseSource::class);
        $this->app->forgetInstance(CompositeSource::class);
        $this->app->forgetInstance(ContentSource::class);
    }

    /**
     * The content source the provider binds for the current config.
     */
    protected function freshSource(): ContentSource
    {
        $this->forgetSources();

        return $this->app->make(ContentSource::class);
    }

    /** Absolute path of a docs fixture tree under tests/Fixtures ('docs' or 'docs-override'). */
    public function fixtureDocsPath(string $name = 'docs'): string
    {
        return __DIR__.'/Fixtures/'.$name;
    }

    protected function createUsersTable(string $table): void
    {
        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    protected function createSettingsTable(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['group', 'name']);
        });
    }
}
