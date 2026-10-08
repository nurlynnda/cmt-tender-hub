<?php

namespace App\Quotations;

/** "Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only" — the line under the quotation total. */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private const SCALES = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];

    public static function ringgit(int $sen): string
    {
        $words = 'Ringgit Malaysia '.self::number(intdiv($sen, 100));
        if ($sen % 100 > 0) {
            $words .= ' and '.self::number($sen % 100).' Sen';
        }

        return $words.' Only';
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        $parts = [];
        for ($scale = 0; $n > 0; $scale++, $n = intdiv($n, 1000)) {
            if ($n % 1000) {
                array_unshift($parts, trim(self::chunk($n % 1000).' '.self::SCALES[$scale]));
            }
        }

        return implode(' ', $parts);
    }

    private static function chunk(int $n): string
    {
        $w = [];
        if ($n >= 100) {
            $w[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $w[] = self::TENS[intdiv($n, 10)];
            $n %= 10;
        }
        if ($n > 0) {
            $w[] = self::ONES[$n];
        }

        return implode(' ', $w);
    }
}
