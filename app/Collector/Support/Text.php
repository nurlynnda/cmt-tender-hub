<?php

namespace App\Collector\Support;

use Carbon\CarbonImmutable;

/** Port of tms-v2 backend/src/parsing/text.ts. */
final class Text
{
    public static function clean(?string $s): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $s ?? ''));
    }

    public static function ddmmyyyy(?string $s): ?string
    {
        return self::date($s, '/^(\d{2})\/(\d{2})\/(\d{4})$/', 3, 2, 1);
    }

    public static function isoPrefix(?string $s): ?string
    {
        return self::date($s, '/^(\d{4})-(\d{2})-(\d{2})/', 1, 2, 3);
    }

    public static function dotted(?string $s): ?string
    {
        return self::date($s, '/^(\d{2})\.(\d{2})\.(\d{4})/', 3, 2, 1);
    }

    public static function dashed(?string $s): ?string
    {
        return self::date($s, '/^(\d{2})-(\d{2})-(\d{4})$/', 3, 2, 1);
    }

    public static function rmPriceSen(?string $s): ?int
    {
        if ($s === null || $s === '') {
            return null;
        }
        if (! preg_match('/RM\s*(\d+(?:\.\d+)?)/i', str_replace(',', '', $s), $m)) {
            return null;
        }

        return (int) round(((float) $m[1]) * 100);
    }

    /** @return list<string> */
    public static function fieldCodes(?string $s): array
    {
        if ($s === null || $s === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $s)), fn ($c) => $c !== ''));
    }

    public static function scrapedAtNow(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    private static function date(?string $s, string $pattern, int $y, int $m, int $d): ?string
    {
        if ($s === null || ! preg_match($pattern, trim($s), $x)) {
            return null;
        }
        if (! checkdate((int) $x[$m], (int) $x[$d], (int) $x[$y])) {
            return null;
        }

        return "{$x[$y]}-{$x[$m]}-{$x[$d]}";
    }
}
