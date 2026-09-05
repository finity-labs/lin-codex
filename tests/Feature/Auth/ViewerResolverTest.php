<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Auth\ViewerResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

function linCodexViewerUser(int $id = 1): GenericUser
{
    return new GenericUser(['id' => $id, 'name' => 'Ann']);
}

it('resolves a guest on the default guard when nobody is signed in', function (): void {
    $viewer = app(ViewerResolver::class)->resolve();

    expect($viewer)->toBeInstanceOf(Viewer::class)
        ->and($viewer->isAuthenticated)->toBeFalse()
        ->and($viewer->user)->toBeNull()
        ->and($viewer->guard)->toBe('web');
});

it('resolves the signed-in user on the default guard', function (): void {
    $this->actingAs(linCodexViewerUser());

    $viewer = app(ViewerResolver::class)->resolve();

    expect($viewer->isAuthenticated)->toBeTrue()
        ->and($viewer->user?->getAuthIdentifier())->toBe(1)
        ->and($viewer->guard)->toBe('web');
});

it('reads the configured guard instead of the default one', function (): void {
    config()->set('lin-codex.auth.guard', 'admin');
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    app(AuthFactory::class)->guard('web')->setUser(linCodexViewerUser());

    $resolver = app(ViewerResolver::class);
    $viewer = $resolver->resolve();

    expect($viewer->isAuthenticated)->toBeFalse()
        ->and($viewer->user)->toBeNull()
        ->and($viewer->guard)->toBe('admin');

    app(AuthFactory::class)->guard('admin')->setUser(linCodexViewerUser(2));

    $viewer = $resolver->resolve();

    expect($viewer->isAuthenticated)->toBeTrue()
        ->and($viewer->user?->getAuthIdentifier())->toBe(2)
        ->and($viewer->guard)->toBe('admin');
});

it('lets the guard argument win over the configured guard', function (): void {
    config()->set('lin-codex.auth.guard', 'admin');
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    app(AuthFactory::class)->guard('web')->setUser(linCodexViewerUser());

    $viewer = app(ViewerResolver::class)->resolve('web');

    expect($viewer->isAuthenticated)->toBeTrue()
        ->and($viewer->guard)->toBe('web');
});

it('treats an empty configured guard like null', function (): void {
    config()->set('lin-codex.auth.guard', '');
    $this->actingAs(linCodexViewerUser());

    $viewer = app(ViewerResolver::class)->resolve();

    expect($viewer->isAuthenticated)->toBeTrue()
        ->and($viewer->guard)->toBe('web');
});

it('throws for an undefined guard name', function (): void {
    expect(fn () => app(ViewerResolver::class)->resolve('nope'))
        ->toThrow(InvalidArgumentException::class, 'nope');
});

it('builds guests and authenticated viewers through the named constructors', function (): void {
    $guest = Viewer::guest();
    $admin = Viewer::guest('admin');
    $user = Viewer::authenticated(linCodexViewerUser());

    expect($guest->isAuthenticated)->toBeFalse()
        ->and($guest->user)->toBeNull()
        ->and($guest->guard)->toBe('web')
        ->and($admin->guard)->toBe('admin')
        ->and($user->isAuthenticated)->toBeTrue()
        ->and($user->user?->getAuthIdentifier())->toBe(1)
        ->and($user->guard)->toBe('web');
});

it('ships null for both auth config keys', function (): void {
    expect(config('lin-codex.auth.guard'))->toBeNull()
        ->and(config('lin-codex.auth.gate'))->toBeNull();
});
