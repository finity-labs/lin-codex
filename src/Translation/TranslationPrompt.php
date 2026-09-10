<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

use FinityLabs\LinCodex\Locale\LocaleResolver;

/**
 * The two-part translation prompt.
 *
 * The instructions are the fixed CONTRACT the package owns, followed by the
 * admin's editable text (CodexAiSettings::$translation_instructions, seeded
 * from DefaultInstructions::TEXT). The contract comes first and is always
 * present, so an admin who empties or rewrites the editable half can change
 * how the article is translated but never what shape the answer has or which
 * Markdown tokens survive.
 *
 * The user prompt is the language line plus the three source fields, each
 * wrapped in an XML-style block (<source_title>, <source_excerpt>,
 * <source_body>) exactly as fin-sentinel's AiPromptBuilder wraps an error
 * context. Delimiters keep the source text apart from the instructions on
 * every provider, and the contract says the tags are not part of the content
 * so they are never echoed back into a field.
 *
 * Both languages are named "Display (code)" — "Deutsch (de)". The display
 * name is the native or admin-edited value from CodexSettings::$languages, so
 * it may read "Hungarian (formal)" or an untranslated code; the code beside
 * it leaves the model no ambiguity about which language is meant. When no
 * display name is configured, or it equals the code, the code stands alone.
 *
 * Caveat, deliberately not solved here: headings are prose and are
 * translated, so a translated article's heading anchor ids differ from the
 * source's and a field hint that names a source heading may not land in
 * another language. That is exactly what happens with a hand-written
 * translation today; the README says so.
 */
final class TranslationPrompt
{
    /**
     * Field name => description handed to the structured-output schema;
     * every field a required string.
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        'title' => 'The translated title as plain text.',
        'excerpt' => 'The translated excerpt as plain text; an empty string when the source excerpt block is empty.',
        'body' => 'The translated body as raw Markdown, never wrapped in a code fence.',
    ];

    /**
     * The fixed contract the admin cannot edit.
     */
    public const CONTRACT = <<<'CONTRACT'
        You translate help articles written in Markdown for a software product. Answer with a JSON object holding the fields title, excerpt and body, nothing else.
        Keep exactly, untranslated and unchanged: fenced code blocks and inline code; URLs and link targets, including relative paths and anchors; image paths, translating only the alt text of the ![alt](path) syntax; the callout keywords [!NOTE], [!TIP], [!IMPORTANT], [!WARNING] and [!CAUTION]; the :::steps, :::details and ::: fences; HTML tags and their attributes; heading levels, list markers, table layout and emphasis markers.
        Return the body as raw Markdown, never wrapped in a code fence. A source block that is empty yields an empty string for that field. The <source_title>, <source_excerpt> and <source_body> tags delimit the source text and are not part of the content.
        CONTRACT;

    public function __construct(private readonly LocaleResolver $locales) {}

    /**
     * The contract, a blank line, then the admin's text trimmed; the
     * contract alone when that text is blank.
     */
    public function instructions(string $adminInstructions): string
    {
        $admin = trim($adminInstructions);

        if ($admin === '') {
            return self::CONTRACT;
        }

        return self::CONTRACT."\n\n".$admin;
    }

    /**
     * The language line, a blank line, then the three delimited blocks in
     * the order title, excerpt, body. A null excerpt yields an empty block,
     * which the contract turns into an empty string for that field.
     */
    public function prompt(string $source, string $target, string $title, ?string $excerpt, string $body): string
    {
        return sprintf(
            "Translate from %s into %s.\n\n%s%s%s",
            $this->languageName($source),
            $this->languageName($target),
            $this->block('source_title', $title),
            $this->block('source_excerpt', (string) $excerpt),
            $this->block('source_body', $body),
        );
    }

    /**
     * "Deutsch (de)" from the settings display name; the bare code when no
     * display name is configured or it equals the code.
     */
    public function languageName(string $code): string
    {
        $display = $this->locales->displayName($code);

        if ($display === '' || $display === $code) {
            return $code;
        }

        return "{$display} ({$code})";
    }

    /**
     * @return array<string, string> self::FIELDS
     */
    public function fields(): array
    {
        return self::FIELDS;
    }

    private function block(string $tag, string $text): string
    {
        return "<{$tag}>\n{$text}\n</{$tag}>\n";
    }
}
