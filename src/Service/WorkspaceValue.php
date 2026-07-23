<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use function is_array;
use function is_numeric;
use function is_scalar;

final class WorkspaceValue
{
    /**
     * HR: Normalizira proizvoljnu vrijednost na tekst bez PHP upozorenja.
     * EN: Normalizes an arbitrary value to text without PHP warnings.
     */
    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * HR: Normalizira numeričku vrijednost na cijeli broj.
     * EN: Normalizes a numeric value to an integer.
     */
    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * HR: Zadržava samo string ključeve jednog proizvoljnog polja.
     * EN: Retains only string keys from an arbitrary array.
     *
     * @return array<string, mixed>
     */
    public static function stringKeyArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * HR: Normalizira ORM rezultat u listu string-key redaka.
     * EN: Normalizes an ORM result into a list of string-key rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            $normalized = self::stringKeyArray($row);
            if ($normalized !== []) {
                $rows[] = $normalized;
            }
        }

        return $rows;
    }
}
