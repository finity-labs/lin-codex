<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sources\Filesystem;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Splits a "---" fenced YAML block from the top of an article file and
 * parses it. Both .md and .html files go through this: the HTML sanitizer
 * would otherwise print the fences as text. Pure: strings in, values out.
 *
 * The YAML is parsed with the default flags on purpose: no PHP objects are
 * instantiated from tags, and dates stay strings rather than becoming
 * DateTime objects that the Phase 8 export would have to dump again.
 */
final class FrontMatter
{
    private const BOM = "\xEF\xBB\xBF";

    private const FENCE = '/\A(?:\xEF\xBB\xBF)?---\R(?:(.*?)\R)?---(?:\R|\z)/s';

    private const MAPPING_ERROR = 'Front matter must be a YAML mapping.';

    /**
     * Split the fenced block from the body. Handles LF, CRLF and a leading
     * byte order mark; yaml is null when there is no fenced block.
     *
     * @return array{yaml: ?string, body: string}
     */
    public static function split(string $contents): array
    {
        if (preg_match(self::FENCE, $contents, $matches) !== 1) {
            return ['yaml' => null, 'body' => self::stripBom($contents)];
        }

        return [
            'yaml' => $matches[1] ?? '',
            'body' => substr($contents, strlen($matches[0])),
        ];
    }

    /**
     * Parse the front matter into a string-keyed array. A YAML error or a
     * block that is not a mapping gives an empty data array and an error
     * message instead of an exception, so one broken file cannot stop a scan.
     *
     * @return array{data: array<string, mixed>, body: string, error: ?string}
     */
    public static function read(string $contents): array
    {
        $split = self::split($contents);
        $yaml = $split['yaml'];

        if ($yaml === null || trim($yaml) === '') {
            return ['data' => [], 'body' => $split['body'], 'error' => null];
        }

        try {
            $parsed = Yaml::parse(self::stripBom($yaml));
        } catch (ParseException $exception) {
            return ['data' => [], 'body' => $split['body'], 'error' => $exception->getMessage()];
        }

        if (! is_array($parsed)) {
            return ['data' => [], 'body' => $split['body'], 'error' => self::MAPPING_ERROR];
        }

        foreach (array_keys($parsed) as $key) {
            if (! is_string($key)) {
                return ['data' => [], 'body' => $split['body'], 'error' => self::MAPPING_ERROR];
            }
        }

        /** @var array<string, mixed> $parsed */
        return ['data' => $parsed, 'body' => $split['body'], 'error' => null];
    }

    private static function stripBom(string $text): string
    {
        return str_starts_with($text, self::BOM) ? substr($text, strlen(self::BOM)) : $text;
    }
}
