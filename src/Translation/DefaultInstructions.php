<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

/**
 * The seeded value of CodexAiSettings::$translation_instructions: the part
 * of the translation prompt an admin may edit. It says how to translate
 * (faithfully, in the formal register, with consistent terminology and
 * without inventing content) and nothing about the shape of the answer.
 *
 * The contract the admin cannot edit — the structured output fields, the
 * delimited source blocks and the keep-untranslated Markdown rules — lives
 * in Translation\TranslationPrompt and is added around this text on every
 * call.
 *
 * A constant rather than a lang key: the prompt is always written in
 * English whatever the panel's locale is. fin-codex offers it as the
 * "reset to default" value of the instructions field on its settings page,
 * so this text is the one place that wording is maintained.
 */
final class DefaultInstructions
{
    public const TEXT = <<<'TEXT'
        Translate faithfully: keep the meaning, tone and level of detail of the source, and translate everything that is prose, including headings, list items, table cells, image alt text and callout text.
        Use the formal register in languages that have one (German "Sie", Hungarian "Ön", French "vous").
        Keep terminology consistent within the article and prefer the terms a native speaker would expect in software documentation.
        Do not add, drop or reorder content, and do not add explanations or notes of your own.
        TEXT;
}
