<?php

namespace App\Support;

/** "Ahmad Faizal" → "AF": first letter of the first two words. */
final class Initials
{
    public static function of(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));
    }
}
