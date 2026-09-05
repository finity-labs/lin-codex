<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Data\ContextData;
use FinityLabs\LinCodex\Data\SourceWarning;
use FinityLabs\LinCodex\Data\TranslationData;
use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use FinityLabs\LinCodex\Enums\Visibility;

dataset('valid context strings', [
    'route' => ['route:users.index', 0, ContextType::Route, 'users.index', null],
    'url with sort order' => ['url:/users/*', 3, ContextType::Url, '/users/*', null],
    'class keeps backslashes' => ['class:App\Filament\Resources\UserResource', 0, ContextType::PageClass, 'App\Filament\Resources\UserResource', null],
    'panel class' => ['admin:class:App\Filament\Resources\UserResource', 0, ContextType::PageClass, 'App\Filament\Resources\UserResource', 'admin'],
    'panel url' => ['admin:url:/users/*', 0, ContextType::Url, '/users/*', 'admin'],
    'key keeps further colons' => ['route:a:b', 0, ContextType::Route, 'a:b', null],
]);

dataset('invalid context strings', [
    'no type' => ['bogus'],
    'empty key' => ['route:'],
    'panel with empty key' => ['admin:route:'],
    'unknown type' => ['nope:users.index'],
    'panel with unknown type' => ['admin:nope:x'],
    'empty string' => [''],
    'panel only' => ['admin:'],
]);

it('parses [panel:]type:key strings', function (string $context, int $sortOrder, ContextType $type, string $key, ?string $panelId): void {
    $data = ContextData::fromString($context, $sortOrder);

    expect($data->type)->toBe($type)
        ->and($data->key)->toBe($key)
        ->and($data->panelId)->toBe($panelId)
        ->and($data->sortOrder)->toBe($sortOrder);
})->with('valid context strings');

it('round-trips every valid context string through toString()', function (string $context): void {
    expect(ContextData::fromString($context)->toString())->toBe($context);
})->with('valid context strings');

it('rejects strings that are not [panel:]class|route|url:key', function (string $context): void {
    expect(fn () => ContextData::fromString($context))->toThrow(InvalidArgumentException::class);
})->with('invalid context strings');

it('renders a source warning message through the lin-codex translation namespace', function (): void {
    $warning = new SourceWarning(SourceWarningKind::InvalidFrontMatter, '/x/broken.md', null, 'en', 'A colon cannot be used');

    $message = $warning->message();

    expect($message)->toBeString()->not->toBeEmpty()
        ->and($message)->toContain('/x/broken.md')
        ->and($message)->toContain('A colon cannot be used')
        ->and($message)->not->toStartWith('lin-codex::');
});

it('looks up translations by locale and lists locales in insertion order', function (): void {
    $en = new TranslationData('en', 'Users', null, 'Body', 'body');
    $de = new TranslationData('de', 'Benutzer', null, 'Inhalt', 'inhalt');

    $article = new ArticleData(
        slug: 'users',
        parentSlug: null,
        order: 0,
        icon: null,
        format: ArticleFormat::Markdown,
        visibility: Visibility::Public,
        published: true,
        contexts: [],
        related: [],
        keywords: [],
        translations: ['en' => $en, 'de' => $de],
    );

    expect($article->translation('de'))->toBe($de)
        ->and($article->translation('fr'))->toBeNull()
        ->and($article->locales())->toBe(['en', 'de'])
        ->and($article->meta)->toBe([])
        ->and($article->isSection)->toBeFalse()
        ->and($article->sourcePath)->toBeNull()
        ->and($article->id)->toBeNull();
});
