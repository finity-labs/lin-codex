<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * The only class in the package that talks to the auth system. It asks one
 * guard for its user and wraps the answer in a Viewer.
 *
 * Guard precedence: the argument (fin-codex passes the panel's guard), then
 * lin-codex.auth.guard when it is a non-empty string, then the application's
 * default guard. It never calls shouldUse(), so resolving a viewer leaves the
 * host's default guard untouched. An undefined guard name throws from the
 * auth manager: that is host misconfiguration, not a guest.
 */
final class ViewerResolver
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function resolve(?string $guard = null): Viewer
    {
        $configured = config('lin-codex.auth.guard');
        $name = $guard ?? (is_string($configured) && $configured !== '' ? $configured : (string) config('auth.defaults.guard'));
        $user = $this->auth->guard($name)->user();

        return new Viewer($user !== null, $user, $name);
    }
}
