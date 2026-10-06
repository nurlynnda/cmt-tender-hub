<?php

use App\Costing\CostingCalculator as C;

function oneOff(int $costSen, int $bp = 2000, int $qty = 1, int $year = 1): array
{
    return ['quantity' => $qty, 'frequency' => 'one_off', 'months' => 1, 'project_year' => $year,
        'unit_cost_sen' => $costSen, 'margin_bp' => $bp, 'sub_items' => []];
}

it('rounds the price per unit up to the whole ringgit, exactly', function (int $cost, int $bp, int $price) {
    expect(C::pricePerUnitSen($cost, $bp))->toBe($price);
})->with([
    [1859900, 2000, 2324900],      // 18,599 @ 20% = 23,248.75 → 23,249
    [800000, 2000, 1000000],       // exact: 8,000 @ 20% = 10,000 (no extra ringgit)
    [100, 0, 100],                 // 0% margin: price = cost
    [1, 2000, 100],                // 1 sen still rounds up to RM 1
    [0, 2000, 0],
    [1000000, 9999, 10000000000],  // 99.99%
]);

it('reproduces the prototype JPNIN costing to the sen', function () {
    $lines = array_map(fn ($l) => oneOff($l['unit_cost_sen']),
        json_decode(file_get_contents(base_path('docs/superpowers/plans/assets/jpnin-costing.json')), true));

    $s = C::summary($lines, null, 17480000);

    expect($s['total_cost_sen'])->toBe(13284500)
        ->and($s['suggested_bid_sen'])->toBe(16605900)
        ->and($s['bid_price_sen'])->toBe(16605900)
        ->and($s['margin_sen'])->toBe(3321400)
        ->and($s['margin_bp'])->toBe(2000)
        ->and($s['below_target'])->toBeFalse()
        ->and($s['under_budget_bp'])->toBe(500); // (174,800 − 166,059) ÷ 174,800 = 5.0%
});

it('uses sub-items as the cost of one unit, and multiplies monthly lines', function () {
    $line = ['quantity' => 20, 'frequency' => 'monthly', 'months' => 12, 'project_year' => 2, 'unit_cost_sen' => 999,
        'margin_bp' => 2000, 'sub_items' => [['quantity' => 1, 'unit_cost_sen' => 300000], ['quantity' => 2, 'unit_cost_sen' => 40000]]];

    expect(C::line($line))->toBe([
        'unit_cost_sen' => 380000,                 // 3,000 + 2 × 400 per set
        'line_cost_sen' => 20 * 380000 * 12,
        'price_per_unit_sen' => 475000,            // 3,800 ÷ 0.8
        'selling_sen' => 20 * 475000 * 12,
    ]);
});

it('treats one-off lines as one month whatever months says', function () {
    expect(C::line(['months' => 9] + oneOff(100000))['line_cost_sen'])->toBe(100000);
});

it('uses the override as the bid price and recomputes the margin from it', function () {
    $s = C::summary([oneOff(10000000)], 11000000, null);

    expect($s['suggested_bid_sen'])->toBe(12500000)
        ->and($s['bid_price_sen'])->toBe(11000000)
        ->and($s['is_override'])->toBeTrue()
        ->and($s['margin_sen'])->toBe(1000000)
        ->and($s['margin_bp'])->toBe(909)
        ->and($s['below_target'])->toBeTrue()
        ->and($s['under_budget_bp'])->toBeNull();
});

it('handles a loss, an empty costing and a zero bid without dividing by zero', function () {
    expect(C::summary([oneOff(10000000)], 5000000, 0)['margin_bp'])->toBe(-10000)
        ->and(C::summary([], null, 100)['margin_bp'])->toBe(0)
        ->and(C::summary([], null, 100)['bid_price_sen'])->toBe(0)
        ->and(C::summary([], null, 100)['below_target'])->toBeFalse()
        ->and(C::summary([oneOff(0)], null, null)['margin_bp'])->toBe(0);
});

it('shows no under-budget figure while there is no bid price yet', function () {
    expect(C::summary([], null, 10000000)['under_budget_bp'])->toBeNull()
        ->and(C::summary([oneOff(0)], null, 10000000)['under_budget_bp'])->toBeNull();
});

it('reports over-budget bids as negative under-budget', function () {
    expect(C::summary([oneOff(10000000)], null, 10000000)['under_budget_bp'])->toBe(-2500);
});

it('lists the most you can spend for 12% to 21% margin', function () {
    $guide = C::summary([oneOff(10000000)], null, null)['guide']; // bid 125,000

    expect(count($guide))->toBe(10)
        ->and($guide[0])->toBe(['margin_bp' => 1200, 'max_cost_sen' => 11000000])
        ->and($guide[6])->toBe(['margin_bp' => 1800, 'max_cost_sen' => 10250000])
        ->and($guide[9])->toBe(['margin_bp' => 2100, 'max_cost_sen' => 9875000]);
});

it('totals cost by project year', function () {
    $s = C::summary([oneOff(100, year: 3), oneOff(200, year: 1), oneOff(300, year: 3)], null, null);

    expect($s['cost_by_year'])->toBe([1 => 200, 3 => 400]);
});
