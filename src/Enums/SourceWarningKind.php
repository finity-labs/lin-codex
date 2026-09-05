<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Enums;

use FinityLabs\LinCodex\Enums\Concerns\HasKey;

/**
 * What a file source records when it has to work around its input:
 *
 * - InvalidFrontMatter: the YAML could not be parsed; the file was skipped.
 * - SharedKeyIgnored: a shared key in a non-default locale file; ignored.
 * - MissingDefaultLocale: no default-locale file exists; this file supplies everything.
 * - UnknownValue: a bad visibility, format, published or order value; the default was used.
 * - InvalidContext: a context string that could not be parsed; dropped.
 * - DuplicateSlug: the slug is already taken in the same docs path; the file was skipped.
 * - UnknownKey: a `parent` key, which has no effect; the folder decides.
 * - InvalidSlug: a root index file, or a file name that had to be normalised.
 */
enum SourceWarningKind: int
{
    use HasKey;

    case InvalidFrontMatter = 1;
    case SharedKeyIgnored = 2;
    case MissingDefaultLocale = 3;
    case UnknownValue = 4;
    case InvalidContext = 5;
    case DuplicateSlug = 6;
    case UnknownKey = 7;
    case InvalidSlug = 8;
}
