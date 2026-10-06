<?php

namespace App\Support;

use InvalidArgumentException;

/** Percentages as integer basis points (1% = 100 bp; 20% = 2000). */
final class Percent
{
    public static function parseBp(?string $input): ?int
    {
        $clean = trim(str_replace('%', '', (string) $input));
        if ($clean === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,4})(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Not a valid percentage: {$input}");
        }

        return (int) $m[1] * 100 + (isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0);
    }

    public static function format(int $bp, int $decimals = 1): string
    {
        return number_format($bp / 100, $decimals).'%';
    }

    /** For input boxes: 2000 → "20", 1825 → "18.25". */
    public static function toInput(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2, '.', ''), '0'), '.');
    }
}
