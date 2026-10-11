<?php

namespace App\Support;

use App\Exceptions\FieldCodeNotHeld;

/** The MOF field codes (kod bidang) 10 Creative Solutions is registered under — config('tenderhub.company_field_codes'). A tender needs all of its codes to be among them. */
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

    /** @param  list<string>  $codes  @return list<string> those the company does not hold (all of them when it holds none) */
    public static function missing(array $codes): array
    {
        return array_values(array_diff(array_map('strtoupper', $codes), array_column(self::all(), 'code')));
    }

    /** The company must hold EVERY code the tender asks for (and the tender must ask for at least one). */
    public static function assertHeld(array $codes): void
    {
        if ($codes === [] || self::missing($codes) !== []) {
            throw new FieldCodeNotHeld(array_values($codes));
        }
    }
}
