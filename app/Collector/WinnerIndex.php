<?php

namespace App\Collector;

use App\Models\CollectedTender;
use Illuminate\Support\Facades\DB;

/** Keeps collected_tender_winners in step with a tender's published winners. */
final class WinnerIndex
{
    public static function sync(CollectedTender $t): void
    {
        DB::table('collected_tender_winners')->where('collected_tender_id', $t->id)->delete();
        $rows = self::rows($t->id, $t->winners);
        if ($rows !== []) {
            DB::table('collected_tender_winners')->insert($rows);
        }
    }

    /** @return list<array<string, mixed>> insertable rows; blank names are skipped */
    public static function rows(int $tenderId, mixed $winners): array
    {
        $rows = [];
        foreach (is_array($winners) ? $winners : [] as $w) {
            $name = trim((string) (is_array($w) ? ($w['name'] ?? '') : ''));
            $key = ContractorName::key($name);
            if ($key === '') {
                continue;
            }
            $price = $w['price_sen'] ?? null;
            $rows[] = [
                'collected_tender_id' => $tenderId, 'name' => mb_substr($name, 0, 500), 'name_key' => mb_substr($key, 0, 500),
                'price_sen' => is_numeric($price) && $price >= 0 ? (int) $price : null, 'position' => count($rows) + 1,
            ];
        }

        return $rows;
    }
}
