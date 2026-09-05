<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;

final class LinCodexGateDenyBlocked
{
    public function __invoke(Viewer $viewer, ArticleData $article): bool
    {
        return $article->slug !== 'blocked';
    }
}

function linCodexGateArticle(string $slug, Visibility $visibility = Visibility::Public, bool $published = true): ArticleData
{
    return new ArticleData(
        slug: $slug,
        parentSlug: str_contains($slug, '/') ? substr($slug, 0, (int) strrpos($slug, '/')) : null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: $visibility,
        published: $published,
        contexts: [],
        related: [],
        keywords: [],
        translations: ['en' => new TranslationData('en', 'Title '.$slug, null, 'Body', null)],
    );
}

/**
 * @return array<string, ArticleData>
 */
function linCodexGateMap(ArticleData ...$articles): array
{
    $map = [];

    foreach ($articles as $article) {
        $map[$article->slug] = $article;
    }

    return $map;
}

beforeEach(function (): void {
    $this->gate = app(ArticleGate::class);
    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

it('shows a public published article to everyone', function (): void {
    $article = linCodexGateArticle('open');
    $map = linCodexGateMap($article);

    expect($this->gate->allows($article, $this->guest, $map))->toBeTrue()
        ->and($this->gate->allows($article, $this->user, $map))->toBeTrue();
});

it('shows an authenticated article only to a signed-in viewer', function (): void {
    $article = linCodexGateArticle('members', Visibility::Authenticated);
    $map = linCodexGateMap($article);

    expect($this->gate->allows($article, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($article, $this->user, $map))->toBeTrue();
});

it('hides unpublished articles from everyone whatever their visibility', function (): void {
    $public = linCodexGateArticle('draft-public', Visibility::Public, false);
    $auth = linCodexGateArticle('draft-auth', Visibility::Authenticated, false);
    $map = linCodexGateMap($public, $auth);

    expect($this->gate->allows($public, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($public, $this->user, $map))->toBeFalse()
        ->and($this->gate->allows($auth, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($auth, $this->user, $map))->toBeFalse();
});

it('hides a public child under an authenticated section from guests', function (): void {
    $internal = linCodexGateArticle('internal', Visibility::Authenticated);
    $child = linCodexGateArticle('internal/child');
    $map = linCodexGateMap($internal, $child);

    expect($this->gate->allows($child, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($child, $this->user, $map))->toBeTrue();
});

it('hides a public child under an unpublished section from everyone', function (): void {
    $draft = linCodexGateArticle('draft', Visibility::Public, false);
    $child = linCodexGateArticle('draft/child');
    $map = linCodexGateMap($draft, $child);

    expect($this->gate->allows($child, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($child, $this->user, $map))->toBeFalse();
});

it('walks every ancestor level', function (): void {
    $a = linCodexGateArticle('a', Visibility::Authenticated);
    $b = linCodexGateArticle('a/b');
    $c = linCodexGateArticle('a/b/c');
    $map = linCodexGateMap($a, $b, $c);

    expect($this->gate->allows($c, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($c, $this->user, $map))->toBeTrue();
});

it('lets folder groups hide nothing', function (): void {
    $child = linCodexGateArticle('group/child');
    $map = linCodexGateMap($child);

    expect($this->gate->allows($child, $this->guest, $map))->toBeTrue();
});

it('filters a map keeping keys and order', function (): void {
    $map = linCodexGateMap(
        linCodexGateArticle('public-published'),
        linCodexGateArticle('auth-published', Visibility::Authenticated),
        linCodexGateArticle('public-unpublished', Visibility::Public, false),
        linCodexGateArticle('internal', Visibility::Authenticated),
        linCodexGateArticle('internal/child'),
        linCodexGateArticle('group/child'),
    );

    $forGuest = $this->gate->filter($map, $this->guest);
    $forUser = $this->gate->filter($map, $this->user);

    expect(array_keys($forGuest))->toBe(['public-published', 'group/child'])
        ->and($forGuest['group/child'])->toBe($map['group/child'])
        ->and(array_keys($forUser))->toBe(['public-published', 'auth-published', 'internal', 'internal/child', 'group/child']);
});

it('lets an invokable gate class veto an article', function (): void {
    config()->set('lin-codex.auth.gate', LinCodexGateDenyBlocked::class);

    $blocked = linCodexGateArticle('blocked');
    $open = linCodexGateArticle('open');
    $map = linCodexGateMap($blocked, $open);

    expect($this->gate->allows($blocked, $this->user, $map))->toBeFalse()
        ->and($this->gate->allows($blocked, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($open, $this->user, $map))->toBeTrue()
        ->and($this->gate->allows($open, $this->guest, $map))->toBeTrue();
});

it('lets a runtime closure gate hide everything', function (): void {
    config()->set('lin-codex.auth.gate', fn (Viewer $viewer, ArticleData $article): bool => false);

    $map = linCodexGateMap(linCodexGateArticle('open'), linCodexGateArticle('members', Visibility::Authenticated));

    expect($this->gate->filter($map, $this->user))->toBe([]);
});

it('runs the hook only for articles that passed the published and visibility checks', function (): void {
    $seen = [];
    config()->set('lin-codex.auth.gate', function (Viewer $viewer, ArticleData $article) use (&$seen): bool {
        $seen[] = $article->slug;

        return true;
    });

    $draft = linCodexGateArticle('draft', Visibility::Public, false);
    $members = linCodexGateArticle('members', Visibility::Authenticated);
    $open = linCodexGateArticle('open');
    $map = linCodexGateMap($draft, $members, $open);

    expect($this->gate->allows($draft, $this->user, $map))->toBeFalse()
        ->and($this->gate->allows($members, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($open, $this->guest, $map))->toBeTrue()
        ->and($seen)->toBe(['open']);
});

it('passes the viewer to the hook', function (): void {
    config()->set('lin-codex.auth.gate', fn (Viewer $viewer, ArticleData $article): bool => $viewer->isAuthenticated);

    $open = linCodexGateArticle('open');
    $map = linCodexGateMap($open);

    expect($this->gate->allows($open, $this->guest, $map))->toBeFalse()
        ->and($this->gate->allows($open, $this->user, $map))->toBeTrue();
});

it('rejects a gate that resolves to something not callable', function (): void {
    config()->set('lin-codex.auth.gate', stdClass::class);

    $open = linCodexGateArticle('open');

    expect(fn () => $this->gate->allows($open, $this->user, linCodexGateMap($open)))
        ->toThrow(InvalidArgumentException::class);
});

it('never touches the database', function (): void {
    $internal = linCodexGateArticle('internal', Visibility::Authenticated);
    $child = linCodexGateArticle('internal/child');
    $map = linCodexGateMap($internal, $child);

    DB::enableQueryLog();

    $this->gate->allows($child, $this->guest, $map);
    $this->gate->filter($map, $this->user);

    expect(DB::getQueryLog())->toBe([]);
});
