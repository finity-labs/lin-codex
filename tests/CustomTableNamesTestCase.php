<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests;

/**
 * Boots the package with every table name and the users table overridden,
 * the way a host app with its own "articles" or "members" tables would.
 *
 * The overrides are set in defineEnvironment(), which runs before the
 * providers boot and before defineDatabaseMigrations(), so the base harness
 * creates the overridden users table and the migrations create the
 * overridden package tables with no further changes.
 */
class CustomTableNamesTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('lin-codex.table_names', [
            'articles' => 'kb_articles',
            'article_translations' => 'kb_translations',
            'article_contexts' => 'kb_contexts',
            'article_revisions' => 'kb_revisions',
            'media' => 'kb_files',
        ]);
        $app['config']->set('lin-codex.users_table', 'members');
    }
}
