<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\AiAvailability;
use FinityLabs\LinCodex\Ai\AiCallFailed;
use FinityLabs\LinCodex\Ai\AiReason;

/**
 * The raw lang array of one locale, read from disk rather than through __(),
 * so a namespace that never loaded cannot make an assertion pass vacuously.
 *
 * @return array<string, mixed>
 */
function linCodexAiLang(string $locale): array
{
    return require dirname(__DIR__, 3).'/resources/lang/'.$locale.'/lin-codex.php';
}

it('lists the eight reason keys in the documented order', function (): void {
    expect(AiReason::ALL)->toBe([
        'timeout',
        'authentication_failed',
        'rate_limited',
        'quota_exceeded',
        'output_rejected',
        'invalid_output',
        'unavailable',
        'unknown',
    ]);
});

it('ships a label for every reason key and no key the constants do not name', function (): void {
    $english = linCodexAiLang('en');

    expect(array_keys($english['ai']['reasons']))->toBe(AiReason::ALL)
        ->and(array_keys($english['ai']['unavailable']))->toBe(AiAvailability::REASONS);
});

it('translates the reason label for :dataset into German and Hungarian', function (string $reason): void {
    $english = AiReason::label($reason);

    expect($english)->not->toBe('')->and($english)->not->toBe($reason);

    foreach (['de', 'hu'] as $locale) {
        app()->setLocale($locale);

        $translated = AiReason::label($reason);

        expect($translated)->not->toBe('')
            ->and($translated)->not->toBe($reason)
            ->and($translated)->not->toBe($english);
    }
})->with(AiReason::ALL);

it('translates the why-not label for :dataset into German and Hungarian', function (string $why): void {
    $english = AiAvailability::unavailable($why)->label();

    expect($english)->not->toBe('')->and($english)->not->toBe($why);

    foreach (['de', 'hu'] as $locale) {
        app()->setLocale($locale);

        $translated = AiAvailability::unavailable($why)->label();

        expect($translated)->not->toBe('')
            ->and($translated)->not->toBe($why)
            ->and($translated)->not->toBe($english);
    }
})->with(AiAvailability::REASONS);

it('says nothing when AI is available', function (): void {
    $availability = AiAvailability::available();

    expect($availability->available)->toBeTrue()
        ->and($availability->reason)->toBeNull()
        ->and($availability->label())->toBe('');
});

it('keeps an unknown why-not reason as given', function (): void {
    $availability = AiAvailability::unavailable('x');

    expect($availability->available)->toBeFalse()
        ->and($availability->reason)->toBe('x');
});

it('carries the reason key on the failure exception', function (): void {
    $previous = new RuntimeException('the provider hung up');
    $failure = new AiCallFailed(AiReason::TIMEOUT, $previous);

    expect($failure->reason)->toBe('timeout')
        ->and($failure->getMessage())->toBe('AI call failed: timeout')
        ->and($failure->getPrevious())->toBe($previous)
        ->and((new AiCallFailed(AiReason::TIMEOUT))->getPrevious())->toBeNull();
});
