<?php

namespace App\Pd;

use DateTimeImmutable;

/** All PD maths in integer sen and basis points (1% = 100 bp). */
final class PdCalculator
{
    private const COST_OF_SALES = ['principal', 'distributor', 'partner'];

    private const COST_LABELS = [
        'pending' => 'Pending', 'pr' => 'PR Raised', 'po' => 'PO Issued', 'invoiced' => 'Invoiced',
        'paid' => 'Paid', 'overpaid' => 'Paid more than invoiced',
    ];

    private const COLLECTION_LABELS = [
        'pending' => 'Pending', 'invoiced' => 'Invoiced', 'partly' => 'Partly received',
        'received' => 'Received', 'overpaid' => 'Received more than invoiced',
    ];

    private const EMPTY_MONTH = ['expected_in' => 0, 'received' => 0, 'paid_out' => 0];

    /** $sen × $bp ÷ 10000, rounded half up (away from zero) to the sen. */
    public static function pct(int $sen, int $bp): int
    {
        $p = $sen * $bp;

        return $p >= 0 ? intdiv($p + 5000, 10000) : -intdiv(-$p + 5000, 10000);
    }

    public static function summary(array $lines, array $rates, ?string $start, ?string $end, string $today): array
    {
        $computed = array_map(fn (array $l) => $l + self::line($l), $lines);

        $budget = ['revenue' => 0, 'cost_of_sales' => 0, 'other_costs' => 0];
        $actual = $budget;
        foreach ($computed as $l) {
            $key = match (true) {
                $l['group'] === 'collection' => 'revenue',
                in_array($l['group'], self::COST_OF_SALES, true) => 'cost_of_sales',
                default => 'other_costs',
            };
            $budget[$key] += $l['budget_sen'];
            $actual[$key] += $l['invoiced'];
        }
        $budgetRow = self::pnlRow($budget, $rates);
        $actualRow = self::pnlRow($actual, $rates);

        return [
            'lines' => $computed,
            'pnl' => ['budget' => $budgetRow, 'actual' => $actualRow],
            'below_margin' => $actualRow['revenue'] > 0 && $actualRow['gp_bp'] < $rates['approved_margin_bp'],
            'cash_flow' => self::cashFlow($computed, $start, $end),
            'duration_pct' => self::duration($start, $end, $today),
        ];
    }

    private static function line(array $l): array
    {
        $sum = ['pr' => 0, 'po' => 0, 'invoice' => 0, 'payment' => 0, 'receipt' => 0];
        foreach ($l['entries'] ?? [] as $e) {
            $sum[$e['type']] += $e['amount_sen'];
        }
        $collection = $l['group'] === 'collection';
        $settled = $collection ? $sum['receipt'] : $sum['payment'];
        $overpaid = $settled > $sum['invoice'];

        $status = $collection
            ? match (true) {
                $overpaid => 'overpaid',
                $sum['invoice'] > 0 && $settled >= $sum['invoice'] => 'received',
                $settled > 0 => 'partly',
                $sum['invoice'] > 0 => 'invoiced',
                default => 'pending',
            }
            : match (true) {
                $overpaid => 'overpaid',
                $sum['invoice'] > 0 && $settled >= $sum['invoice'] => 'paid',
                $sum['invoice'] > 0 => 'invoiced',
                $sum['po'] > 0 => 'po',
                $sum['pr'] > 0 => 'pr',
                default => 'pending',
            };

        return [
            'pr' => $sum['pr'],
            'po' => $sum['po'],
            'invoiced' => $sum['invoice'],
            'paid' => $sum['payment'],
            'received' => $sum['receipt'],
            'owed' => $sum['invoice'] - $settled,
            'variance' => $l['budget_sen'] - $sum['invoice'],
            'status' => $status,
            'status_label' => ($collection ? self::COLLECTION_LABELS : self::COST_LABELS)[$status],
            'over_budget' => ! $collection && $sum['invoice'] > $l['budget_sen'],
            'overpaid' => $overpaid,
        ];
    }

    private static function pnlRow(array $t, array $rates): array
    {
        $charges = self::pct($t['revenue'], $rates['project_charge_bp']);
        $gp = $t['revenue'] - $t['cost_of_sales'] - $t['other_costs'] - $charges;
        $approved = self::pct($t['revenue'], $rates['approved_margin_bp']);
        $commission = self::pct(max(0, $gp - $approved), $rates['commission_share_bp']);
        $net = $gp - $commission;
        $ofRevenue = fn (int $v) => $t['revenue'] > 0 ? (int) round($v * 10000 / $t['revenue']) : 0;

        return [
            'revenue' => $t['revenue'],
            'cost_of_sales' => $t['cost_of_sales'],
            'other_costs' => $t['other_costs'],
            'charges' => $charges,
            'gp' => $gp,
            'gp_bp' => $ofRevenue($gp),
            'approved_sen' => $approved,
            'commission' => $commission,
            'net' => $net,
            'net_bp' => $ofRevenue($net),
        ];
    }

    private static function cashFlow(array $lines, ?string $start, ?string $end): array
    {
        $months = [];
        $add = function (string $date, string $key, int $sen) use (&$months) {
            $m = substr($date, 0, 7);
            $months[$m] ??= self::EMPTY_MONTH;
            $months[$m][$key] += $sen;
        };
        foreach (array_filter([$start, $end]) as $d) {
            $add($d, 'expected_in', 0);
        }
        foreach ($lines as $l) {
            if ($l['group'] === 'collection' && $l['scheduled_date']) {
                $add($l['scheduled_date'], 'expected_in', $l['budget_sen']);
            }
            foreach ($l['entries'] ?? [] as $e) {
                match ($e['type']) {
                    'receipt' => $add($e['date'], 'received', $e['amount_sen']),
                    'payment' => $add($e['date'], 'paid_out', $e['amount_sen']),
                    default => $add($e['date'], 'expected_in', 0), // other documents only widen the range
                };
            }
        }
        if ($months === []) {
            return [];
        }

        ksort($months);
        $last = array_key_last($months);
        $cursor = new DateTimeImmutable(array_key_first($months).'-01');
        $rows = [];
        $balance = 0;
        while (($m = $cursor->format('Y-m')) <= $last) {
            $row = $months[$m] ?? self::EMPTY_MONTH;
            $balance += $row['received'] - $row['paid_out'];
            $rows[] = ['month' => $m] + $row + ['balance' => $balance];
            $cursor = $cursor->modify('+1 month');
        }

        return $rows;
    }

    private static function duration(?string $start, ?string $end, string $today): ?int
    {
        if (! $start || ! $end) {
            return null;
        }
        $s = new DateTimeImmutable($start);
        $total = (int) $s->diff(new DateTimeImmutable($end))->format('%r%a');
        $done = (int) $s->diff(new DateTimeImmutable($today))->format('%r%a');
        if ($total <= 0) {
            return $done >= 0 ? 100 : 0;
        }

        return max(0, min(100, intdiv($done * 100, $total)));
    }
}
