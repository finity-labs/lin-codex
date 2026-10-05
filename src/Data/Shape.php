<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Data;

use BackedEnum;
use InvalidArgumentException;

/**
 * Typed reads out of an array that came back from a cache store, for the
 * fromArray() constructors of the value objects lin-codex caches: the
 * rendered article, the file source's article set and everything in it,
 * and the search index's documents.
 *
 * The cache holds arrays rather than the objects themselves. Laravel 13's
 * skeleton sets cache.serializable_classes to false, and every store that
 * serializes then reads with unserialize(..., ['allowed_classes' => false]),
 * which hands back a __PHP_Incomplete_Class for any object. Arrays and
 * scalars are always allowed, so no app config is involved, and the stored
 * bytes carry no class names.
 *
 * Nothing here coerces: a field that is missing or of another type throws,
 * and the caller treats the whole entry as not its own and rebuilds it.
 * Enums travel as their backing value.
 */
final class Shape
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : self::fail($key, 'a string');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null || is_string($value) ? $value : self::fail($key, 'a string or null');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : self::fail($key, 'an integer');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null || is_int($value) ? $value : self::fail($key, 'an integer or null');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : self::fail($key, 'a boolean');
    }

    /**
     * Any array, its keys cast to string. The values are not inspected;
     * use this for free-form data such as front matter.
     *
     * @param  array<array-key, mixed>  $data
     *
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            self::fail($key, 'an array');
        }

        $map = [];

        foreach ($value as $itemKey => $item) {
            $map[(string) $itemKey] = $item;
        }

        return $map;
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @return list<string>
     */
    public static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            self::fail($key, 'a list of strings');
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                self::fail($key, 'a list of strings');
            }
        }

        return array_values($value);
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @return array<string, string>
     */
    public static function stringMap(array $data, string $key): array
    {
        $map = [];

        foreach (self::map($data, $key) as $itemKey => $item) {
            $map[$itemKey] = is_string($item) ? $item : self::fail($key, 'a map of strings');
        }

        return $map;
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @return list<array<array-key, mixed>>
     */
    public static function arrayList(array $data, string $key): array
    {
        $list = [];

        foreach (self::map($data, $key) as $item) {
            $list[] = is_array($item) ? $item : self::fail($key, 'a list of arrays');
        }

        return $list;
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @return array<string, array<array-key, mixed>>
     */
    public static function arrayMap(array $data, string $key): array
    {
        $map = [];

        foreach (self::map($data, $key) as $itemKey => $item) {
            $map[$itemKey] = is_array($item) ? $item : self::fail($key, 'a map of arrays');
        }

        return $map;
    }

    /**
     * An int-backed enum case from its backing value.
     *
     * @template T of BackedEnum
     *
     * @param  array<array-key, mixed>  $data
     * @param  class-string<T>  $enum
     *
     * @return T
     */
    public static function enum(array $data, string $key, string $enum): BackedEnum
    {
        $value = $data[$key] ?? null;
        $case = is_int($value) ? $enum::tryFrom($value) : null;

        return $case ?? self::fail($key, 'a '.$enum.' value');
    }

    private static function fail(string $key, string $expected): never
    {
        throw new InvalidArgumentException(sprintf('Cached entry: "%s" is not %s.', $key, $expected));
    }
}
