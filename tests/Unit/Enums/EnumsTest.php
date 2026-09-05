<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\ContextType;
use FinityLabs\LinCodex\Enums\FallbackBehaviour;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Enums\SourceWarningKind;
use FinityLabs\LinCodex\Enums\Visibility;

dataset('enum backing values', [
    'ArticleFormat' => [ArticleFormat::class, [
        'Markdown' => [1, 'markdown'],
        'Html' => [2, 'html'],
    ]],
    'Visibility' => [Visibility::class, [
        'Public' => [1, 'public'],
        'Authenticated' => [2, 'authenticated'],
    ]],
    'ContextType' => [ContextType::class, [
        'PageClass' => [1, 'class'],
        'Route' => [2, 'route'],
        'Url' => [3, 'url'],
    ]],
    'RevisionReason' => [RevisionReason::class, [
        'Manual' => [1, 'manual'],
        'Import' => [2, 'import'],
        'AiRewrite' => [3, 'ai_rewrite'],
    ]],
    'FallbackBehaviour' => [FallbackBehaviour::class, [
        'ShowDefault' => [1, 'show_default'],
        'Hide' => [2, 'hide'],
    ]],
    'SourceWarningKind' => [SourceWarningKind::class, [
        'InvalidFrontMatter' => [1, 'invalid_front_matter'],
        'SharedKeyIgnored' => [2, 'shared_key_ignored'],
        'MissingDefaultLocale' => [3, 'missing_default_locale'],
        'UnknownValue' => [4, 'unknown_value'],
        'InvalidContext' => [5, 'invalid_context'],
        'DuplicateSlug' => [6, 'duplicate_slug'],
        'UnknownKey' => [7, 'unknown_key'],
        'InvalidSlug' => [8, 'invalid_slug'],
    ]],
]);

it('exposes the locked int backing values and string keys, and no extra cases', function (string $enum, array $expected) {
    /** @var array<int, BackedEnum> $cases */
    $cases = $enum::cases();

    $actual = [];
    foreach ($cases as $case) {
        $actual[$case->name] = [$case->value, $case->key()];
    }

    expect($actual)->toBe($expected);
})->with('enum backing values');

it('round-trips every case through its key', function (string $enum) {
    /** @var array<int, BackedEnum> $cases */
    $cases = $enum::cases();

    foreach ($cases as $case) {
        expect($enum::fromKey($case->key()))->toBe($case)
            ->and($enum::tryFromKey($case->key()))->toBe($case);
    }

    expect($enum::tryFromKey('nope'))->toBeNull()
        ->and(fn () => $enum::fromKey('nope'))->toThrow(ValueError::class);
})->with([
    ArticleFormat::class,
    Visibility::class,
    ContextType::class,
    RevisionReason::class,
    FallbackBehaviour::class,
    SourceWarningKind::class,
]);

it('resolves the class context type from its front matter prefix', function () {
    expect(ContextType::fromKey('class'))->toBe(ContextType::PageClass)
        ->and(ContextType::keys())->toBe(['class', 'route', 'url']);
});

it('labels every case through the lin-codex translation namespace', function (string $enum) {
    /** @var array<int, BackedEnum> $cases */
    $cases = $enum::cases();

    foreach ($cases as $case) {
        $label = $case->label();

        expect($label)->toBeString()->not->toBeEmpty()
            ->and($label)->not->toStartWith('lin-codex::');
    }
})->with([
    ArticleFormat::class,
    Visibility::class,
    ContextType::class,
    RevisionReason::class,
    FallbackBehaviour::class,
    SourceWarningKind::class,
]);

it('spot-checks known English labels', function () {
    expect(Visibility::Public->label())->toBe('Public')
        ->and(ContextType::PageClass->label())->toBe('Page class')
        ->and(ArticleFormat::Html->label())->toBe('HTML')
        ->and(RevisionReason::AiRewrite->label())->toBe('AI rewrite')
        ->and(FallbackBehaviour::ShowDefault->label())->toBe('Show default language');
});

it('exposes users_table and media config with the locked defaults', function () {
    expect(config('lin-codex.users_table'))->toBe('users')
        ->and(config('lin-codex.media.disk'))->toBe('public')
        ->and(config('lin-codex.media.directory'))->toBe('codex')
        ->and(config('lin-codex.source'))->toBe('composite')
        ->and(config('lin-codex.sources.filesystem.paths'))->toBe([resource_path('codex')])
        ->and(config('lin-codex.routes.media'))->toBe('/codex/media')
        ->and(config('lin-codex.routes.api'))->toBe('/codex/api')
        ->and(config('lin-codex.routes.middleware'))->toBe(['web'])
        ->and(config('lin-codex.routes.help_center'))->toBe('/help');
});

it('keeps the five codex_ table names untouched', function () {
    expect(config('lin-codex.table_names'))->toBe([
        'articles' => 'codex_articles',
        'article_translations' => 'codex_article_translations',
        'article_contexts' => 'codex_article_contexts',
        'article_revisions' => 'codex_article_revisions',
        'media' => 'codex_media',
    ]);
});
