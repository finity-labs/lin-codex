<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\AiReason;
use FinityLabs\LinCodex\Events\ArticleTranslated;
use FinityLabs\LinCodex\Translation\TranslationReport;
use Illuminate\Support\Facades\Event;

function linCodexTranslationReport(): TranslationReport
{
    $report = new TranslationReport;
    $report->translated('de', 120, 80);
    $report->skipped('hu');
    $report->failed('fr', AiReason::RATE_LIMITED);

    return $report;
}

it('records per-locale outcomes and sums the tokens', function (): void {
    $report = linCodexTranslationReport();

    expect($report->translatedLocales())->toBe(['de'])
        ->and($report->skippedLocales())->toBe(['hu'])
        ->and($report->failedLocales())->toBe(['fr' => 'rate_limited'])
        ->and($report->hasFailures())->toBeTrue()
        ->and($report->isEmpty())->toBeFalse()
        ->and($report->promptTokens())->toBe(120)
        ->and($report->completionTokens())->toBe(80)
        ->and((new TranslationReport)->isEmpty())->toBeTrue()
        ->and((new TranslationReport)->hasFailures())->toBeFalse();
});

it('serializes in recording order and round-trips', function (): void {
    $report = linCodexTranslationReport();

    $expected = [
        'de' => ['status' => 'translated', 'reason' => null, 'prompt_tokens' => 120, 'completion_tokens' => 80],
        'hu' => ['status' => 'skipped', 'reason' => null, 'prompt_tokens' => 0, 'completion_tokens' => 0],
        'fr' => ['status' => 'failed', 'reason' => 'rate_limited', 'prompt_tokens' => 0, 'completion_tokens' => 0],
    ];

    expect($report->toArray())->toBe($expected)
        ->and(TranslationReport::fromArray($report->toArray())->toArray())->toBe($expected)
        ->and(TranslationReport::fromArray([])->isEmpty())->toBeTrue()
        ->and(TranslationReport::fromArray(['de' => ['status' => 'translated']])->toArray())->toBe([
            'de' => ['status' => 'translated', 'reason' => null, 'prompt_tokens' => 0, 'completion_tokens' => 0],
        ]);
});

it('overwrites an earlier outcome for the same locale', function (): void {
    $report = new TranslationReport;
    $report->failed('de', AiReason::TIMEOUT);
    $report->translated('de', 10, 5);

    expect($report->failedLocales())->toBe([])
        ->and($report->translatedLocales())->toBe(['de'])
        ->and($report->hasFailures())->toBeFalse()
        ->and($report->promptTokens())->toBe(10)
        ->and($report->toArray()['de'])->toBe([
            'status' => 'translated',
            'reason' => null,
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
        ]);
});

it('dispatches ArticleTranslated with the id, the user and the report', function (): void {
    Event::fake([ArticleTranslated::class]);

    ArticleTranslated::dispatch(7, 3, linCodexTranslationReport());

    Event::assertDispatched(
        ArticleTranslated::class,
        fn (ArticleTranslated $event): bool => $event->articleId === 7
            && $event->userId === 3
            && $event->report->toArray()['de']['status'] === 'translated',
    );
});

it('survives serialization for a queued listener', function (): void {
    $event = new ArticleTranslated(7, null, linCodexTranslationReport());

    $revived = unserialize(serialize($event));

    expect($revived)->toBeInstanceOf(ArticleTranslated::class)
        ->and($revived->articleId)->toBe(7)
        ->and($revived->userId)->toBeNull()
        ->and($revived->report->toArray())->toBe($event->report->toArray());
});
