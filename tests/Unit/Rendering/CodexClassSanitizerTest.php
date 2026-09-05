<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Html\CodexClassSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

function sanitizeClass(string $element, string $value): ?string
{
    return (new CodexClassSanitizer)->sanitizeAttribute($element, 'class', $value, new HtmlSanitizerConfig);
}

it('keeps only codex- prefixed tokens', function (): void {
    expect(sanitizeClass('div', 'codex-x other'))->toBe('codex-x');
});

it('drops the attribute when no token survives', function (): void {
    expect(sanitizeClass('span', 'foo'))->toBeNull();
});

it('keeps language- tokens on code only', function (): void {
    expect(sanitizeClass('code', 'language-php hljs'))->toBe('language-php');
    expect(sanitizeClass('p', 'language-php'))->toBeNull();
});

it('normalises whitespace between kept tokens', function (): void {
    expect(sanitizeClass('div', '  codex-a   codex-b '))->toBe('codex-a codex-b');
});

it('applies to the class attribute on every element', function (): void {
    $sanitizer = new CodexClassSanitizer;

    expect($sanitizer->getSupportedAttributes())->toBe(['class']);
    expect($sanitizer->getSupportedElements())->toBeNull();
});
