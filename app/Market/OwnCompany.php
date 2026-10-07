<?php

namespace App\Market;

use App\Collector\ContractorName;
use App\Models\FinanceSetting;

/** The names our company wins under in government results (Finance Settings, one per line). */
final class OwnCompany
{
    /** @return list<string> */
    private static function lines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) FinanceSetting::current()->own_company_names))));
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_values(array_unique(array_filter(array_map([ContractorName::class, 'key'], self::lines()))));
    }

    /** The first name for headings: an all-capitals name is title-cased, a name typed in mixed case is kept as typed. */
    public static function label(): string
    {
        $first = self::lines()[0] ?? null;
        if ($first === null) {
            return 'Our company';
        }

        return $first === mb_strtoupper($first) ? mb_convert_case(mb_strtolower($first), MB_CASE_TITLE) : $first;
    }
}
