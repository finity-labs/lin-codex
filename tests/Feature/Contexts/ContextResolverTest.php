<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\Viewer;
use FinityLabs\LinCodex\Contexts\ContextResolver;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\Visibility;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sources\ArticleSet;
use Illuminate\Auth\GenericUser;

/**
 * A source over an in-memory article set, so ordering cases are exact and
 * need no fixture.
 */
final class LinCodexResolverStubSource implements ContentSource
{
    public function __construct(private readonly ArticleSet $set) {}

    public function all(): array
    {
        return $this->set->all();
    }

    public function findBySlug(string $slug): ?ArticleData
    {
        return $this->set->findBySlug($slug);
    }

    public function tree(): array
    {
        return $this->set->tree();
    }

    public function findByContext(ContextType $type, string $key, ?string $panelId = null): array
    {
        return $this->set->findByContext($type, $key, $panelId);
    }

    public function allForSearch(): array
    {
        return $this->set->allForSearch();
    }

    public function warnings(): array
    {
        return $this->set->warnings();
    }
}

/**
 * @param  list<string|array{string, int}>  $contexts  context strings, or [string, sortOrder]
 * @param  list<string>  $locales
 */
function linCodexResolverArticle(string $slug, array $contexts, Visibility $visibility = Visibility::Public, bool $published = true, array $locales = ['en']): ArticleData
{
    $translations = [];

    foreach ($locales as $code) {
        $translations[$code] = new TranslationData($code, $slug.' '.$code, null, 'Body', null);
    }

    return new ArticleData(
        slug: $slug,
        parentSlug: str_contains($slug, '/') ? substr($slug, 0, (int) strrpos($slug, '/')) : null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: $visibility,
        published: $published,
        contexts: array_map(
            fn (string|array $context): ContextData => is_array($context)
                ? ContextData::fromString($context[0], $context[1])
                : ContextData::fromString($context),
            $contexts,
        ),
        related: [],
        keywords: [],
        translations: $translations,
    );
}

function linCodexResolver(ArticleData ...$articles): ContextResolver
{
    $map = [];

    foreach ($articles as $article) {
        $map[$article->slug] = $article;
    }

    return new ContextResolver(
        new LinCodexResolverStubSource(new ArticleSet($map)),
        app(ArticleGate::class),
        app(LocaleResolver::class),
    );
}

/**
 * @param  list<ArticleData>  $articles
 *
 * @return list<string>
 */
function linCodexResolverSlugs(array $articles): array
{
    return array_map(fn (ArticleData $article): string => $article->slug, $articles);
}

/**
 * @param  list<string>  $codes
 */
function linCodexResolverUseLanguages(array $codes, FallbackBehaviour $fallback = FallbackBehaviour::ShowDefault): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->fallback = $fallback;
    $settings->save();
}

function linCodexResolverPage(?string $route, string $path, ?string $class = null, ?string $panel = null): PageContext
{
    return new PageContext($route, $path, $class, $panel);
}

beforeEach(function (): void {
    $this->guest = Viewer::guest();
    $this->user = Viewer::authenticated(new GenericUser(['id' => 1]));
});

describe('passes', function (): void {
    it('lets the panel pass win and consults the panel-less pass only when it is empty', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('a', ['admin:route:users.index']),
            linCodexResolverArticle('b', ['route:users.index']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('users.index', '/users', null, 'admin'), $this->guest)))->toBe(['a'])
            ->and(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('users.index', '/users'), $this->guest)))->toBe(['b'])
            ->and(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('users.index', '/users', null, 'other'), $this->guest)))->toBe(['b']);
    });

    it('never appends panel-less matches to a non-empty panel pass', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('a', ['admin:route:users.index']),
            linCodexResolverArticle('b', ['route:users.index']),
            linCodexResolverArticle('c', ['route:users.*']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('users.index', '/users', null, 'admin'), $this->guest)))->toBe(['a']);
    });

    it('filters before the emptiness check so a guest reaches the public panel-less article', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('p', ['admin:route:users.index'], Visibility::Authenticated),
            linCodexResolverArticle('q', ['route:users.index']),
        );
        $page = linCodexResolverPage('users.index', '/users', null, 'admin');

        expect(linCodexResolverSlugs($resolver->resolve($page, $this->guest)))->toBe(['q'])
            ->and(linCodexResolverSlugs($resolver->resolve($page, $this->user)))->toBe(['p']);
    });
});

describe('ordering', function (): void {
    it('keeps the index ordering: exact before wildcard, class, route, url', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('w', ['route:users.*']),
            linCodexResolverArticle('e', [['route:users.index', 5]]),
            linCodexResolverArticle('c', ['class:App\Pages\Users']),
            linCodexResolverArticle('u', ['url:/users']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('users.index', '/users', 'App\Pages\Users'), $this->guest)))->toBe(['c', 'e', 'u', 'w']);
    });

    it('orders many articles on one context by sort order then slug', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('b', [['url:/x', 1]]),
            linCodexResolverArticle('a', [['url:/x', 2]]),
            linCodexResolverArticle('c', [['url:/x', 1]]),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage(null, '/x'), $this->guest)))->toBe(['b', 'c', 'a']);
    });

    it('returns an article once however many of its contexts match', function (): void {
        $resolver = linCodexResolver(linCodexResolverArticle('a', ['route:x', 'url:/x']));

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('x', '/x'), $this->guest)))->toBe(['a']);
    });

    it('matches one segment with * and any depth with **', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('one', ['url:/users/*']),
            linCodexResolverArticle('deep', ['url:/users/**']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage(null, '/users/1'), $this->guest)))->toBe(['deep', 'one'])
            ->and(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage(null, '/users/1/edit'), $this->guest)))->toBe(['deep']);
    });

    it('puts catch-alls last', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('all', ['url:/**']),
            linCodexResolverArticle('any', ['route:*']),
            linCodexResolverArticle('exact', ['route:home']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('home', '/home'), $this->guest)))->toBe(['exact', 'any', 'all']);
    });

    it('matches nothing against null page fields', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('any', ['route:*']),
            linCodexResolverArticle('root', ['url:/']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage(null, '/'), $this->guest)))->toBe(['root']);
    });
});

describe('visibility', function (): void {
    it('never resolves an unpublished article', function (): void {
        $resolver = linCodexResolver(linCodexResolverArticle('d', ['route:x'], Visibility::Public, false));
        $page = linCodexResolverPage('x', '/x');

        expect($resolver->resolve($page, $this->guest))->toBe([])
            ->and($resolver->resolve($page, $this->user))->toBe([]);
    });

    it('hides the public child of an authenticated section from guests', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('internal', [], Visibility::Authenticated),
            linCodexResolverArticle('internal/child', ['route:x']),
        );
        $page = linCodexResolverPage('x', '/x');

        expect($resolver->resolve($page, $this->guest))->toBe([])
            ->and(linCodexResolverSlugs($resolver->resolve($page, $this->user)))->toBe(['internal/child']);
    });

    it('honours a gate hook veto', function (): void {
        config()->set('lin-codex.auth.gate', fn (Viewer $viewer, ArticleData $article): bool => $article->slug !== 'blocked');

        $resolver = linCodexResolver(
            linCodexResolverArticle('blocked', ['route:x']),
            linCodexResolverArticle('open', ['route:x']),
        );

        expect(linCodexResolverSlugs($resolver->resolve(linCodexResolverPage('x', '/x'), $this->guest)))->toBe(['open']);
    });
});

describe('locale', function (): void {
    it('applies the locale rule to every match', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('x', ['route:x']),
            linCodexResolverArticle('y', ['route:x'], locales: ['en', 'de']),
        );
        $page = linCodexResolverPage('x', '/x');

        linCodexResolverUseLanguages(['en', 'de'], FallbackBehaviour::Hide);

        expect(linCodexResolverSlugs($resolver->resolve($page, $this->user, 'de')))->toBe(['y'])
            ->and(linCodexResolverSlugs($resolver->resolve($page, $this->user, 'en')))->toBe(['x', 'y']);

        linCodexResolverUseLanguages(['en', 'de'], FallbackBehaviour::ShowDefault);

        expect(linCodexResolverSlugs($resolver->resolve($page, $this->user, 'de')))->toBe(['x', 'y'])
            ->and(linCodexResolverSlugs($resolver->resolve($page, $this->user, 'en')))->toBe(['x', 'y']);
    });
});

describe('result', function (): void {
    it('returns a readonly list that survives serialization', function (): void {
        $resolver = linCodexResolver(
            linCodexResolverArticle('a', ['route:x']),
            linCodexResolverArticle('b', ['url:/x']),
        );

        $result = $resolver->resolve(linCodexResolverPage('x', '/x'), $this->user);

        linCodexAssertNoModels($result);

        expect(array_is_list($result))->toBeTrue()
            ->and($result)->each->toBeInstanceOf(ArticleData::class)
            ->and(unserialize(serialize($result)))->toEqual($result);
    });
});

describe('fixture', function (): void {
    beforeEach(function (): void {
        config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath()]);
        config()->set('lin-codex.source', 'filesystem');
        $this->forgetSources();
        $this->resolver = app(ContextResolver::class);
    });

    it('resolves the docs fixture contexts for a guest', function (): void {
        expect(linCodexResolverSlugs($this->resolver->resolve(new PageContext('dashboard', '/', 'App\Filament\Pages\Dashboard', 'admin'), $this->guest)))->toBe(['intro'])
            ->and(linCodexResolverSlugs($this->resolver->resolve(new PageContext(null, '/welcome/hello'), $this->guest)))->toBe(['intro'])
            ->and($this->resolver->resolve(new PageContext(null, '/welcome/a/b'), $this->guest))->toBe([])
            ->and($this->resolver->resolve(new PageContext('benutzer.index', '/benutzer'), $this->guest))->toBe([]);
    });
});
