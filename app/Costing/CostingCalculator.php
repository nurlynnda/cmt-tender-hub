<?php

namespace App\Costing;

/** All costing maths, in integer sen and basis points (1% = 100 bp). No floating point in prices. */
final class CostingCalculator
{
    public const COMPANY_TARGET_MARGIN_BP = 1800;

    /** cost ÷ (1 − margin), rounded up to the whole ringgit, like the Excel sheet. */
    public static function pricePerUnitSen(int $unitCostSen, int $marginBp): int
    {
        $den = (10000 - $marginBp) * 100;

        return intdiv($unitCostSen * 10000 + $den - 1, $den) * 100;
    }

    /** The margin a selling price gives over a cost: (price − cost) ÷ price, in bp; negative below cost; 0 when the price is 0. */
    public static function marginFromPriceBp(int $costSen, int $priceSen): int
    {
        return $priceSen > 0 ? (int) round(($priceSen - $costSen) * 10000 / $priceSen) : 0;
    }

    /** One line (or quotation item): a typed selling price wins over cost + margin; frequency repeats the whole line. */
    public static function line(array $l): array
    {
        $unitCost = ($l['sub_items'] ?? []) !== []
            ? array_sum(array_map(fn ($s) => $s['quantity'] * $s['unit_cost_sen'], $l['sub_items']))
            : $l['unit_cost_sen'];
        $frequency = max(1, (int) ($l['frequency'] ?? 1));
        $override = $l['unit_price_override_sen'] ?? null;
        $price = $override ?? self::pricePerUnitSen($unitCost, $l['margin_bp']);

        return [
            'unit_cost_sen' => $unitCost,
            'line_cost_sen' => $l['quantity'] * $unitCost * $frequency,
            'price_per_unit_sen' => $price,
            'selling_sen' => $price * $l['quantity'] * $frequency,
            'effective_margin_bp' => self::marginFromPriceBp($unitCost, $price),
            'is_price_override' => $override !== null,
        ];
    }

    public static function summary(array $lines, ?int $overrideSen, ?int $estimatedSen): array
    {
        $computed = array_map(fn ($l) => self::line($l) + $l, $lines);
        $totalCost = array_sum(array_column($computed, 'line_cost_sen'));
        $suggested = array_sum(array_column($computed, 'selling_sen'));
        $bid = $overrideSen ?? $suggested;
        $margin = $bid - $totalCost;
        $marginBp = $bid > 0 ? (int) round($margin * 10000 / $bid) : 0;

        return [
            'lines' => $computed,
            'total_cost_sen' => $totalCost,
            'suggested_bid_sen' => $suggested,
            'bid_price_sen' => $bid,
            'is_override' => $overrideSen !== null,
            'margin_sen' => $margin,
            'margin_bp' => $marginBp,
            'below_target' => $bid > 0 && $marginBp < self::COMPANY_TARGET_MARGIN_BP,
            'under_budget_bp' => $estimatedSen && $bid > 0 ?(int) round(($estimatedSen - $bid) * 10000 / $estimatedSen) : null,
            'guide' => array_map(
                fn ($m) => ['margin_bp' => $m, 'max_cost_sen' => intdiv($bid * (10000 - $m), 10000)],
                range(1200, 2100, 100),
            ),
        ];
    }
}
