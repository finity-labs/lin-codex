<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Filters class attribute values on HTML-format articles down to the tokens
 * the renderer owns: everything prefixed "codex-", plus "language-*" on
 * <code> so pasted HTML keeps the <pre><code class="language-x"> shape the
 * Markdown pipeline produces. Anything else, including host framework
 * classes, is removed; an attribute with nothing left is dropped.
 */
final class CodexClassSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function getSupportedAttributes(): array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];

        foreach (preg_split('/\s+/', trim($value)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, 'codex-') || (strtolower($element) === 'code' && str_starts_with($token, 'language-'))) {
                $kept[] = $token;
            }
        }

        return $kept === [] ? null : implode(' ', $kept);
    }
}
