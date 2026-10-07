<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function parse(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        $clean = trim(preg_replace('/^\s*RM\s*/i', '', $input));
        if ($clean === '') {
            return null;
        }

        // At most 12 ringgit digits (RM 999,999,999,999.99) so the sen value always fits in an integer.
        if (! preg_match('/^(\d{1,3}(?:,\d{3}){1,3}|\d{1,12})(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Not a valid ringgit amount: {$input}");
        }

        $ringgit = (int) str_replace(',', '', $m[1]);
        $sen = isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0;

        return $ringgit * 100 + $sen;
    }

    public static function format(?int $sen): string
    {
        if ($sen === null) {
            return '—';
        }

        $sign = $sen < 0 ? '-' : '';
        $abs = abs($sen);

        return $sign.'RM '.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Headline boxes: "RM 23.9M" from a million up (like the prototype), the full amount below that. */
    public static function short(?int $sen): string
    {
        if ($sen === null || abs($sen) < 100_000_000) {
            return self::format($sen);
        }

        return ($sen < 0 ? '-' : '').'RM '.number_format(abs($sen) / 100_000_000, 1).'M';
    }

    public static function toInput(?int $sen): string
    {
        if ($sen === null) {
            return '';
        }

        return intdiv($sen, 100).'.'.str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function variant(?int $numerator, ?int $denominator): ?string
    {
        if ($numerator === null || $denominator === null || $denominator === 0) {
            return null;
        }

        return number_format($numerator * 100 / $denominator, 1).'%';
    }
}
