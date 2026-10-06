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

    public static function line(array $l): array
    {
        $unitCost = ($l['sub_items'] ?? []) !== []
            ? array_sum(array_map(fn ($s) => $s['quantity'] * $s['unit_cost_sen'], $l['sub_items']))
            : $l['unit_cost_sen'];
        $months = $l['frequency'] === 'monthly' ? max(1, $l['months']) : 1;
        $price = self::pricePerUnitSen($unitCost, $l['margin_bp']);

        return [
            'unit_cost_sen' => $unitCost,
            'line_cost_sen' => $l['quantity'] * $unitCost * $months,
            'price_per_unit_sen' => $price,
            'selling_sen' => $price * $l['quantity'] * $months,
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

        $byYear = [];
        foreach ($computed as $l) {
            $byYear[$l['project_year']] = ($byYear[$l['project_year']] ?? 0) + $l['line_cost_sen'];
        }
        ksort($byYear);

        return [
            'lines' => $computed,
            'total_cost_sen' => $totalCost,
            'suggested_bid_sen' => $suggested,
            'bid_price_sen' => $bid,
            'is_override' => $overrideSen !== null,
            'margin_sen' => $margin,
            'margin_bp' => $marginBp,
            'below_target' => $bid > 0 && $marginBp < self::COMPANY_TARGET_MARGIN_BP,
            'under_budget_bp' => $estimatedSen ? (int) round(($estimatedSen - $bid) * 10000 / $estimatedSen) : null,
            'guide' => array_map(
                fn ($m) => ['margin_bp' => $m, 'max_cost_sen' => intdiv($bid * (10000 - $m), 10000)],
                range(1200, 2100, 100),
            ),
            'cost_by_year' => $byYear,
        ];
    }
}
