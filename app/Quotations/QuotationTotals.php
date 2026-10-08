<?php

namespace App\Quotations;

use App\Pd\PdCalculator;

final class QuotationTotals
{
    /**
     * SST is charged only on ticked lines; each line repeats by its frequency.
     *
     * @param list<array{quantity:int, frequency?:int, unit_price_sen:int, has_sst?:bool}> $items (missing frequency = 1, missing has_sst = ticked)
     */
    public static function of(array $items, int $sstBp): array
    {
        $items = array_values($items);
        $lines = array_map(fn (array $i) => $i['quantity'] * max(1, $i['frequency'] ?? 1) * $i['unit_price_sen'], $items);
        $subtotal = array_sum($lines);
        $taxable = array_sum(array_map(fn (array $i, int $line) => ($i['has_sst'] ?? true) ? $line : 0, $items, $lines));
        $sst = PdCalculator::pct($taxable, $sstBp); // half up to the sen

        return [
            'lines' => $lines,
            'subtotal_sen' => $subtotal,
            'taxable_sen' => $taxable,
            'sst_sen' => $sst,
            'total_sen' => $subtotal + $sst,
            'words' => AmountInWords::ringgit($subtotal + $sst),
            'has_frequency' => collect($items)->contains(fn ($i) => ($i['frequency'] ?? 1) > 1),
        ];
    }
}
