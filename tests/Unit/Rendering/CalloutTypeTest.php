<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Markdown\Callout\CalloutType;

it('exposes the five github alert types as keys', function (): void {
    expect(CalloutType::keys())->toBe(['note', 'tip', 'important', 'warning', 'caution']);
});

it('resolves a type from its key and keeps an int backing value', function (): void {
    expect(CalloutType::fromKey('warning'))->toBe(CalloutType::Warning)
        ->and(CalloutType::Warning->value)->toBe(4)
        ->and(CalloutType::tryFromKey('danger'))->toBeNull();
});
