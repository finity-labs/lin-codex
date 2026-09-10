<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Ai\OutputCanaries;

it('finds a built-in marker whatever its case', function (): void {
    expect((new OutputCanaries)->hits('Please IGNORE previous instructions now'))
        ->toBe(['ignore previous instructions']);
});

it('passes an ordinary answer', function (): void {
    expect((new OutputCanaries)->hit('Eine ganz gewöhnliche deutsche Antwort.'))->toBeFalse()
        ->and((new OutputCanaries)->hits('Eine ganz gewöhnliche deutsche Antwort.'))->toBe([]);
});

it('names every marker it found once each', function (): void {
    expect((new OutputCanaries)->hits('PWNED [INST] and pwned again'))
        ->toBe(['PWNED', '[INST]']);
});

it('extends the built-in list from config and ignores an empty marker', function (): void {
    config(['lin-codex.ai.output_canaries' => ['ACME-SECRET', '']]);

    $canaries = new OutputCanaries;

    expect($canaries->hit('acme-secret'))->toBeTrue()
        ->and($canaries->hits('acme-secret'))->toBe(['ACME-SECRET'])
        ->and($canaries->hit('nothing to see here'))->toBeFalse();
});
