<?php

namespace App\Collector;

use Illuminate\Support\Facades\{Cache, DB};

/**
 * Field codes for the Find Tenders filter: the official MOF list (copied from the old tms-v2 system,
 * resources/data/field-codes.json — 3 levels, e.g. 05 › 0502 › 050201) plus the CIDB construction codes
 * (B04, CE21…) that appear in collected tenders, which have no names in that list.
 */
final class FieldCodes
{
    private static ?array $mof = null;

    /** @return list<array{code:string, name:string, level:int}> */
    public static function mof(): array
    {
        return self::$mof ??= json_decode(file_get_contents(resource_path('data/field-codes.json')), true);
    }

    /** @return list<string> CIDB-style codes (letters + two digits) found in collected tenders */
    public static function cidb(): array
    {
        return Cache::remember('collector.cidb-codes', 3600, fn () => DB::table('collected_tender_field_codes')
            ->whereRaw("code NOT REGEXP '^[0-9]+$'")->distinct()->orderBy('code')->pluck('code')
            ->filter(fn ($c) => preg_match('/^[A-Z]{1,3}\d{2}$/', $c) === 1)->values()->all());
    }

    /** "010101 — Bahan Bacaan …" for a MOF code, the bare code otherwise. */
    public static function label(string $code): string
    {
        $name = collect(self::mof())->firstWhere('code', $code)['name'] ?? null;

        return $name === null ? $code : "{$code} — {$name}";
    }
}
