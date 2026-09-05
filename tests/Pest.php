<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Tests\CustomApiPrefixTestCase;
use FinityLabs\LinCodex\Tests\CustomHelpCenterTestCase;
use FinityLabs\LinCodex\Tests\CustomTableNamesTestCase;
use FinityLabs\LinCodex\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestView;

uses(TestCase::class)->in('Unit', 'Feature/Migrations', 'Feature/Models', 'Feature/Rendering', 'Feature/Http', 'Feature/Settings', 'Feature/Sources', 'Feature/Auth', 'Feature/Contexts', 'Feature/Locale', 'Feature/Reading', 'Feature/Search', 'Feature/Api', 'Feature/Stubs', 'Feature/Livewire', 'Feature/Views', 'Feature/Revisions', 'Feature/Commands', 'Feature/Coverage', 'Feature/Sync');
uses(CustomTableNamesTestCase::class)->in('Feature/CustomTableNames');
uses(CustomApiPrefixTestCase::class)->in('Feature/CustomApiPrefix');
uses(CustomHelpCenterTestCase::class)->in('Feature/CustomHelpCenter');

/**
 * Walk a value recursively and fail on any Eloquent model, any closure or
 * any object that is not a readonly class; enums, scalars and null pass.
 * Shared by every source test so a model can never leak through the
 * ContentSource contract.
 */
function linCodexAssertNoModels(mixed $value): void
{
    if (is_array($value)) {
        foreach ($value as $item) {
            linCodexAssertNoModels($item);
        }

        return;
    }

    if (! is_object($value) || $value instanceof UnitEnum) {
        return;
    }

    expect($value)->not->toBeInstanceOf(Model::class)
        ->and($value)->not->toBeInstanceOf(Closure::class)
        ->and((new ReflectionClass($value))->isReadOnly())->toBeTrue($value::class.' is not readonly');

    foreach ((new ReflectionObject($value))->getProperties() as $property) {
        linCodexAssertNoModels($property->getValue($value));
    }
}

/*
 * Laravel 11's TestView lacks the *SeeHtml assertions that Laravel 12 added.
 * Register them as macros there so the view tests read the same on every
 * supported version; on Laravel 12+ the real methods win and these are inert.
 */
foreach (['assertSeeHtml' => 'assertSee', 'assertDontSeeHtml' => 'assertDontSee', 'assertSeeHtmlInOrder' => 'assertSeeInOrder'] as $htmlMethod => $escapedMethod) {
    if (! method_exists(TestView::class, $htmlMethod)) {
        TestView::macro($htmlMethod, function (mixed $value) use ($escapedMethod): TestView {
            /** @var TestView $this */
            return $this->{$escapedMethod}($value, false);
        });
    }
}
