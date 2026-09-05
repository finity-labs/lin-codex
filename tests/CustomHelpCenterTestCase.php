<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests;

/**
 * Boots the package with the help center moved to a non-default prefix, the
 * way a host app that already owns /help would.
 *
 * The route file reads lin-codex.routes.help_center when the provider boots,
 * so the key must be set in defineEnvironment(), before the providers
 * register, exactly as CustomApiPrefixTestCase does for the JSON API. The
 * prefix carries a trailing slash on purpose: the routes must rtrim() it,
 * and ArticlePath::href() must agree with them. The middleware group keeps
 * its default ("web").
 */
class CustomHelpCenterTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('lin-codex.routes.help_center', '/docs/');
    }
}
