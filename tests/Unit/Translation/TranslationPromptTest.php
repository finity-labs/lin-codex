<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Translation\TranslationPrompt;

/**
 * @param  list<string>  $codes
 */
function linCodexPromptUseLanguages(array $codes, string $default = 'en'): void
{
    $settings = app(CodexSettings::class);
    $settings->languages = array_map([CodexSettings::class, 'languageEntry'], $codes);
    $settings->default_locale = $default;
    $settings->save();
}

beforeEach(function (): void {
    linCodexPromptUseLanguages(['en', 'de', 'hu']);
});

it('names both languages with their display names and codes', function (): void {
    $prompt = app(TranslationPrompt::class);

    if (extension_loaded('intl')) {
        expect($prompt->languageName('en'))->toBe('English (en)')
            ->and($prompt->languageName('de'))->toBe('Deutsch (de)')
            ->and($prompt->languageName('hu'))->toBe('Magyar (hu)')
            ->and($prompt->prompt('en', 'de', 'T', null, 'B'))->toStartWith('Translate from English (en) into Deutsch (de).');
    } else {
        expect($prompt->languageName('en'))->toBe('en')
            ->and($prompt->languageName('de'))->toBe('de');
    }

    expect($prompt->languageName('fr'))->toBe('fr')
        ->and($prompt->prompt('en', 'de', 'T', null, 'B'))->toStartWith(
            sprintf('Translate from %s into %s.', $prompt->languageName('en'), $prompt->languageName('de')),
        );
});

it('wraps the three source fields in delimited blocks', function (): void {
    $prompt = app(TranslationPrompt::class);

    $text = $prompt->prompt('en', 'de', 'T', null, 'B');

    expect($text)->toContain("<source_title>\nT\n</source_title>")
        ->and($text)->toContain("<source_excerpt>\n\n</source_excerpt>")
        ->and($text)->toContain("<source_body>\nB\n</source_body>")
        ->and($prompt->prompt('en', 'de', 'T', 'E', 'B'))->toContain("<source_excerpt>\nE\n</source_excerpt>")
        ->and($text)->toContain(".\n\n<source_title>");

    expect(strpos($text, '<source_title>'))->toBeLessThan(strpos($text, '<source_excerpt>'))
        ->and(strpos($text, '<source_excerpt>'))->toBeLessThan(strpos($text, '<source_body>'));
});

it('puts the fixed contract before the admin text and keeps it when the text is blank', function (): void {
    $prompt = app(TranslationPrompt::class);

    expect($prompt->instructions('Use Sie.'))->toStartWith(TranslationPrompt::CONTRACT)
        ->toEndWith('Use Sie.')
        ->and($prompt->instructions('   '))->toBe(TranslationPrompt::CONTRACT)
        ->and($prompt->instructions(''))->toBe(TranslationPrompt::CONTRACT);

    foreach (['[!NOTE]', ':::steps', 'code', 'link', 'alt', 'HTML', 'code fence', '<source_'] as $needle) {
        expect(TranslationPrompt::CONTRACT)->toContain($needle);
    }
});

it('describes the three fields', function (): void {
    $fields = app(TranslationPrompt::class)->fields();

    expect($fields)->toBe(TranslationPrompt::FIELDS)
        ->and(array_keys($fields))->toBe(['title', 'excerpt', 'body']);

    foreach ($fields as $description) {
        expect($description)->toBeString()->not->toBe('');
    }
});
