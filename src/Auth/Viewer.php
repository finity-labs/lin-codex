<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is reading. Built once per request by ViewerResolver and passed
 * explicitly into every read service, so nothing deep inside the package
 * asks the auth system itself.
 *
 * `user` may be an Eloquent model in production, which is why the read
 * services never serialise or cache a Viewer; they only consult it.
 */
final readonly class Viewer
{
    public function __construct(
        public bool $isAuthenticated,
        public ?Authenticatable $user,
        public string $guard,
    ) {}

    public static function guest(string $guard = 'web'): self
    {
        return new self(false, null, $guard);
    }

    public static function authenticated(Authenticatable $user, string $guard = 'web'): self
    {
        return new self(true, $user, $guard);
    }
}
