<?php

namespace App\Quotations;

use App\Pd\PdCalculator;

final class QuotationTotals
{
    /** @param list<array{quantity:int, unit_price_sen:int}> $items */
    public static function of(array $items, int $sstBp): array
    {
        $lines = array_map(fn (array $i) => $i['quantity'] * $i['unit_price_sen'], array_values($items));
        $subtotal = array_sum($lines);
        $sst = PdCalculator::pct($subtotal, $sstBp); // half up to the sen

        return [
            'lines' => $lines,
            'subtotal_sen' => $subtotal,
            'sst_sen' => $sst,
            'total_sen' => $subtotal + $sst,
            'words' => AmountInWords::ringgit($subtotal + $sst),
        ];
    }
}
