<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources;

use FinityLabs\LinCodex\Settings\CodexSettings;
use Illuminate\Database\QueryException;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * The locale articles are written in first: CodexSettings::$default_locale,
 * or app.locale when the settings group is unseeded (MissingSettings) or the
 * settings table does not exist yet (QueryException). A try/catch instead of
 * Schema::hasTable() because this runs on a read path and a schema check is
 * a query per call.
 */
final class DefaultLocale
{
    public function get(): string
    {
        try {
            $locale = app(CodexSettings::class)->default_locale;
        } catch (MissingSettings|QueryException) {
            return (string) config('app.locale', 'en');
        }

        return $locale !== '' ? $locale : (string) config('app.locale', 'en');
    }
}
