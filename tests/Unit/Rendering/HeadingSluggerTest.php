<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\HeadingSlugger;

it('slugs heading text with locale-aware transliteration', function (): void {
    expect((new HeadingSlugger('en'))->base('Reset a password'))->toBe('reset-a-password')
        ->and((new HeadingSlugger('en'))->base('Ärger über Straße'))->toBe('arger-uber-strasse')
        ->and((new HeadingSlugger('de'))->base('Ärger über Straße'))->toBe('aerger-ueber-strasse')
        ->and((new HeadingSlugger('hu_HU'))->base('Ügyfél kezelése'))->toBe('ugyfel-kezelese');
});

it('falls back to "section" for text that slugs to nothing', function (): void {
    expect((new HeadingSlugger('en'))->base('???'))->toBe('section');
});

it('truncates to the maximum length without a trailing dash', function (): void {
    $slug = (new HeadingSlugger('en'))->base(str_repeat('word ', 100), 20);

    expect(mb_strlen($slug))->toBeLessThanOrEqual(20)
        ->and($slug)->not->toEndWith('-');
});

it('suffixes duplicate ids starting at -2', function (): void {
    $slugger = new HeadingSlugger('en');

    expect($slugger->unique('Same'))->toBe('same')
        ->and($slugger->unique('Same'))->toBe('same-2')
        ->and($slugger->unique('Same'))->toBe('same-3');
});

it('honours reserved ids and forgets everything on reset', function (): void {
    $slugger = new HeadingSlugger('en');
    $slugger->reserve('intro');

    expect($slugger->unique('Intro'))->toBe('intro-2');

    $slugger->unique('Same');
    $slugger->reset();

    expect($slugger->unique('Same'))->toBe('same');
});

it('dedupes the fallback word too', function (): void {
    $slugger = new HeadingSlugger('en');

    expect($slugger->unique('???'))->toBe('section')
        ->and($slugger->unique('???'))->toBe('section-2');
});

it('reduces a locale to a two-letter language with an English fallback', function (): void {
    expect(HeadingSlugger::language('de_DE'))->toBe('de')
        ->and(HeadingSlugger::language('EN'))->toBe('en')
        ->and(HeadingSlugger::language(''))->toBe('en')
        ->and(HeadingSlugger::language('x'))->toBe('en');
});
