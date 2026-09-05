<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

/**
 * The optional "locale" query parameter every JSON endpoint accepts.
 *
 * A non-string value ("?locale[]=x") or an empty one counts as absent, never
 * as an error: only "q" and "limit" have malformed forms (locked). The code
 * is not validated against the language list either, because
 * LocaleResolver::pick() treats an unknown code exactly like a missing
 * translation (locked), so the read services answer correctly on their own.
 */
trait ReadsLocaleParameter
{
    private function localeParameter(Request $request): ?string
    {
        $locale = $request->query('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
