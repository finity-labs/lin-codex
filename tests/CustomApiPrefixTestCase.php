<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests;

/**
 * Boots the package with the JSON API moved to a non-default prefix and
 * middleware group, the way a host app that already owns /codex or serves
 * the help API to a token-authenticated SPA would.
 *
 * The route file reads lin-codex.routes.api and lin-codex.routes.middleware
 * when the provider boots, so both keys must be set in defineEnvironment(),
 * before the providers register, exactly as CustomTableNamesTestCase does
 * for the table names. The prefix carries a trailing slash on purpose: the
 * route group must rtrim() it.
 */
class CustomApiPrefixTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('lin-codex.routes.api', '/help-api/');
        $app['config']->set('lin-codex.routes.middleware', ['api']);
    }
}
