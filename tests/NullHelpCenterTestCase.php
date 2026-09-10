<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests;

/**
 * Boots the package with the public help center switched off, the way a
 * fin-codex host will once the help center lives inside the panel.
 *
 * The route file reads lin-codex.routes.help_center when the provider boots,
 * so the key must be set in defineEnvironment(), before the providers
 * register; setting it in a test body is too late for the routes. That later
 * case is real too — a host may set a prefix at runtime, after the routes ran
 * — and the ArticlePath rows cover it separately.
 */
class NullHelpCenterTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('lin-codex.routes.help_center', null);
    }
}
