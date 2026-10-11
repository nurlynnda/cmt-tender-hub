<?php

namespace App\Support;

use App\Exceptions\FieldCodeNotHeld;

/** The MOF field codes (kod bidang) 10 Creative Solutions is registered under — config('tenderhub.company_field_codes'). */
final class CompanyFieldCodes
{
    /** @return list<array{code:string, name:string}> */
    public static function all(): array
    {
        return config('tenderhub.company_field_codes');
    }

    /** "210101, 210102; 210103" → ['210101', '210102', '210103'] (upper-cased, no blanks or repeats). */
    public static function parse(?string $text): array
    {
        $codes = preg_split('/[\s,;]+/', strtoupper((string) $text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($codes));
    }

    /** @param  list<string>  $codes  @return list<string> those the company holds */
    public static function matching(array $codes): array
    {
        $held = array_column(self::all(), 'code');

        return array_values(array_intersect(array_map('strtoupper', $codes), $held));
    }

    /** @param  list<string>  $codes */
    public static function assertHeld(array $codes): void
    {
        if (self::matching($codes) === []) {
            throw new FieldCodeNotHeld(array_values($codes));
        }
    }
}
