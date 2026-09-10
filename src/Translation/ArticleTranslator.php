<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

use FinityLabs\LinCodex\Ai\AiAvailabilityCheck;
use FinityLabs\LinCodex\Ai\AiCallFailed;
use FinityLabs\LinCodex\Ai\AiReason;
use FinityLabs\LinCodex\Ai\Contracts\AiClient;
use FinityLabs\LinCodex\Ai\OutputCanaries;
use FinityLabs\LinCodex\Ai\StructuredRequest;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Settings\CodexAiSettings;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use LogicException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Throwable;

/**
 * One article, one target language, one reviewed answer.
 *
 * Two entry points for the two callers. translate() is the queued job's: it
 * reads the article's source row - the default language unless another is
 * named - and hands its three fields on. translateText() is the panel tab
 * action's: the admin may have edited the form without saving, so the text
 * comes in as arguments and no model is involved. Everything below the first
 * step is shared.
 *
 * The call itself is the smaller half. The prompt has two parts, both built
 * by TranslationPrompt: the instructions carry the package's fixed contract
 * plus the admin's editable text, and the user turn carries the language line
 * and the three delimited source blocks. The stored provider, model, timeout
 * and key travel with the request; the seam injects the key for that one call.
 *
 * The larger half is that the answer is never trusted. In order:
 *
 * - a truncated answer, a missing or blank title where the source had one, or
 *   the same for the body, is invalid_output. A model that stops at the output
 *   ceiling produces a body that looks fine and ends mid-sentence;
 * - a field whose source block was blank is forced back to blank, whatever the
 *   model wrote there. The "never invent content" rule is a promise the package
 *   keeps in PHP, not an instruction it hopes the model followed;
 * - one outer code fence the model wrapped the body in is stripped, but only
 *   when the source body did not itself start with a fence;
 * - an output canary - a chat-template delimiter or an injection phrase - is
 *   output_rejected, unless the source text carries that marker too. An
 *   article about PWNED passwords must stay translatable, so a marker the
 *   admin's own text contains is not evidence of anything;
 * - the code fences and link targets of the body must survive translation
 *   (StructureCheck), else invalid_output. That check is on by default and
 *   switched off with lin-codex.ai.check_structure.
 *
 * A failure is a TranslationResult, not an exception, so a caller working
 * through several locales carries on. The two exceptions are the caller's own
 * errors - a target equal to the source, and an article with nothing to
 * translate - which are bugs at the call site, not outcomes.
 *
 * Nothing here writes: no model is saved, no revision recorded. The job owns
 * the write.
 */
final class ArticleTranslator
{
    public function __construct(
        private readonly AiClient $client,
        private readonly AiAvailabilityCheck $availability,
        private readonly TranslationPrompt $prompt,
        private readonly StructureCheck $structure,
        private readonly OutputCanaries $canaries,
        private readonly LocaleResolver $locales,
    ) {}

    /**
     * Translate the article's $source row - the default locale when null -
     * into $target. Reads the row and delegates; writes nothing.
     *
     * @throws LogicException when the article has no $source row, or that row's title and body are both blank
     * @throws InvalidArgumentException when $target equals $source
     */
    public function translate(Article $article, string $target, ?string $source = null): TranslationResult
    {
        $source ??= $this->locales->defaultLocale();

        if ($target === $source) {
            throw new InvalidArgumentException(sprintf('Cannot translate "%s" into itself.', $target));
        }

        $row = $article->translations()->where('locale', $source)->first();

        if ($row === null || (trim($row->title) === '' && trim($row->body) === '')) {
            throw new LogicException(sprintf('Article #%d has nothing to translate in "%s".', $article->id, $source));
        }

        return $this->translateText($row->title, $row->excerpt, $row->body, $target, $source);
    }

    /**
     * Translate raw text: the tab action's unsaved form fields.
     *
     * Never throws for an AI failure - the reason comes back on the result.
     *
     * @throws InvalidArgumentException when $target equals $source
     */
    public function translateText(string $title, ?string $excerpt, string $body, string $target, ?string $source = null): TranslationResult
    {
        $source ??= $this->locales->defaultLocale();

        if ($target === $source) {
            throw new InvalidArgumentException(sprintf('Cannot translate "%s" into itself.', $target));
        }

        if (! $this->availability->check()->available) {
            return TranslationResult::failed(AiReason::UNAVAILABLE);
        }

        $settings = $this->settings();

        if ($settings === null) {
            /*
             * The availability check just proved the group is seeded and the
             * provider set, so a MissingSettings here is a race: the migration
             * was rolled back between the two reads.
             */
            return TranslationResult::failed(AiReason::UNAVAILABLE);
        }

        $instructions = $this->prompt->instructions($settings['instructions']);
        $userPrompt = $this->prompt->prompt($source, $target, $title, $excerpt, $body);

        try {
            $completion = $this->client->structured(new StructuredRequest(
                instructions: $instructions,
                prompt: $userPrompt,
                fields: $this->prompt->fields(),
                provider: $settings['provider'],
                model: $settings['model'],
                timeout: $settings['timeout'],
                apiKey: $settings['apiKey'],
            ));
        } catch (AiCallFailed $e) {
            return TranslationResult::failed($e->reason);
        } catch (Throwable) {
            return TranslationResult::failed(AiReason::UNKNOWN);
        }

        $outTitle = $completion->string('title');
        $outExcerpt = $completion->string('excerpt');
        $outBody = $completion->string('body');

        if ($completion->truncated) {
            return TranslationResult::failed(AiReason::INVALID_OUTPUT);
        }

        if (trim($title) !== '' && ($outTitle === null || trim($outTitle) === '')) {
            return TranslationResult::failed(AiReason::INVALID_OUTPUT);
        }

        if (trim($body) !== '' && ($outBody === null || trim($outBody) === '')) {
            return TranslationResult::failed(AiReason::INVALID_OUTPUT);
        }

        $outTitle ??= '';
        $outBody ??= '';
        $outExcerpt = trim((string) $excerpt) === '' ? null : ($outExcerpt ?? '');
        $outBody = $this->unwrap($body, $outBody);

        $answer = $outTitle."\n".(string) $outExcerpt."\n".$outBody;
        $sourceText = $title."\n".(string) $excerpt."\n".$body;

        foreach ($this->canaries->hits($answer) as $marker) {
            if (stripos($sourceText, $marker) === false) {
                return TranslationResult::failed(AiReason::OUTPUT_REJECTED);
            }
        }

        if ((bool) config('lin-codex.ai.check_structure', true) && ! $this->structure->passes($body, $outBody)) {
            return TranslationResult::failed(AiReason::INVALID_OUTPUT);
        }

        return TranslationResult::success($outTitle, $outExcerpt, $outBody, $completion->promptTokens, $completion->completionTokens);
    }

    /**
     * Strip one outer code fence the model wrapped the whole body in.
     *
     * Only when the source body did not start with a fence itself: an article
     * whose first line opens a code block must keep it.
     */
    private function unwrap(string $sourceBody, string $outputBody): string
    {
        if (preg_match('/^\s*(`{3,}|~{3,})/', $sourceBody) === 1) {
            return $outputBody;
        }

        if (preg_match('/^\s*(`{3,}|~{3,})[^\n]*\n(.*)\n\1\s*$/s', $outputBody, $matches) === 1) {
            return $matches[2];
        }

        return $outputBody;
    }

    /**
     * What one call needs from the AI settings, or null when the group is
     * unseeded or the host has no settings table - the guard every settings
     * reader in the package uses.
     *
     * The values are read here rather than handed back on the settings object
     * because the first property read is what loads the group: every read has
     * to happen inside the guard.
     *
     * @return array{provider: string, model: string|null, timeout: int, apiKey: string|null, instructions: string}|null
     */
    private function settings(): ?array
    {
        try {
            $settings = app(CodexAiSettings::class);

            return [
                'provider' => (string) $settings->provider,
                'model' => $settings->model,
                'timeout' => $settings->timeout,
                'apiKey' => $settings->api_key,
                'instructions' => $settings->translation_instructions,
            ];
        } catch (MissingSettings|QueryException) {
            return null;
        }
    }
}
