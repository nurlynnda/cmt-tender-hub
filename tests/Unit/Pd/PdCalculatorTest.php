<?php

use App\Pd\PdCalculator as P;

const PD_RATES = ['approved_margin_bp' => 1500, 'project_charge_bp' => 900, 'commission_share_bp' => 5000];

function pdLine(string $group, int $budget, array $entries = [], ?string $scheduled = null): array
{
    return ['group' => $group, 'budget_sen' => $budget, 'scheduled_date' => $scheduled,
        'entries' => array_map(fn ($e) => ['type' => $e[0], 'amount_sen' => $e[1], 'date' => $e[2] ?? '2026-01-15'], $entries)];
}

it('works out the budget P&L in the spec reference example', function () {
    $s = P::summary([
        pdLine('collection', 125000000), pdLine('principal', 65100000), pdLine('internal', 13200000),
    ], PD_RATES, null, null, '2026-01-01');

    expect($s['pnl']['budget'])->toBe([
        'revenue' => 125000000, 'cost_of_sales' => 65100000, 'other_costs' => 13200000, 'charges' => 11250000,
        'gp' => 35450000, 'gp_bp' => 2836, 'approved_sen' => 18750000, 'commission' => 8350000,
        'net' => 27100000, 'net_bp' => 2168,
    ]);
});

it('uses invoices for actual revenue and costs', function () {
    $s = P::summary([
        pdLine('collection', 100000, [['invoice', 50000], ['receipt', 50000]]),
        pdLine('distributor', 30000, [['po', 30000], ['invoice', 20000]]),
        pdLine('tax', 1000, [['invoice', 1000]]),
    ], PD_RATES, null, null, '2026-01-01');

    expect($s['pnl']['actual'])->toMatchArray(['revenue' => 50000, 'cost_of_sales' => 20000, 'other_costs' => 1000, 'charges' => 4500, 'gp' => 24500]);
});

it('never pays negative commission and handles a loss', function () {
    $s = P::summary([pdLine('collection', 100000), pdLine('principal', 150000)], PD_RATES, null, null, '2026-01-01');

    expect($s['pnl']['budget'])->toMatchArray(['gp' => -59000, 'gp_bp' => -5900, 'commission' => 0, 'net' => -59000]);
});

it('gives 0% rather than an error when revenue is zero', function () {
    $s = P::summary([pdLine('principal', 5000)], PD_RATES, null, null, '2026-01-01');

    expect($s['pnl']['budget']['gp_bp'])->toBe(0)->and($s['pnl']['budget']['net_bp'])->toBe(0)
        ->and($s['below_margin'])->toBeFalse();
});

it('rounds percentages of money half up to the sen', function () {
    expect(P::pct(150, 900))->toBe(14)    // 13.5 → 14
        ->and(P::pct(149, 900))->toBe(13)  // 13.41 → 13
        ->and(P::pct(-150, 900))->toBe(-14)
        ->and(P::pct(0, 900))->toBe(0);
});

it('flags actual gross profit below the approved margin', function () {
    $low = P::summary([pdLine('collection', 100000, [['invoice', 100000]]), pdLine('principal', 0, [['invoice', 85000]])], PD_RATES, null, null, '2026-01-01');
    $ok = P::summary([pdLine('collection', 100000, [['invoice', 100000]]), pdLine('principal', 0, [['invoice', 50000]])], PD_RATES, null, null, '2026-01-01');

    expect($low['below_margin'])->toBeTrue()->and($ok['below_margin'])->toBeFalse();
});

it('gives each cost line its totals and status', function (array $entries, int $budget, string $status, bool $over, bool $overpaid) {
    $l = P::summary([pdLine('principal', $budget, $entries)], PD_RATES, null, null, '2026-01-01')['lines'][0];

    expect($l['status'])->toBe($status)->and($l['over_budget'])->toBe($over)->and($l['overpaid'])->toBe($overpaid);
})->with([
    'nothing yet' => [[], 100, 'pending', false, false],
    'PR raised' => [[['pr', 100]], 100, 'pr', false, false],
    'PO issued' => [[['pr', 100], ['po', 100]], 100, 'po', false, false],
    'invoiced' => [[['po', 100], ['invoice', 60]], 100, 'invoiced', false, false],
    'part paid' => [[['invoice', 100], ['payment', 40]], 100, 'invoiced', false, false],
    'paid' => [[['invoice', 100], ['payment', 100]], 100, 'paid', false, false],
    'over budget' => [[['invoice', 150]], 100, 'invoiced', true, false],
    'paid in advance' => [[['payment', 50]], 100, 'overpaid', false, true],
    'overpaid' => [[['invoice', 50], ['payment', 80]], 100, 'overpaid', false, true],
]);

it('works out still-owed and variance', function () {
    $l = P::summary([pdLine('principal', 1000, [['pr', 900], ['po', 900], ['invoice', 800], ['payment', 300]])], PD_RATES, null, null, '2026-01-01')['lines'][0];

    expect($l)->toMatchArray(['pr' => 900, 'po' => 900, 'invoiced' => 800, 'paid' => 300, 'owed' => 500, 'variance' => 200, 'status_label' => 'Invoiced']);
});

it('gives each collection line its totals and status', function (array $entries, string $status) {
    expect(P::summary([pdLine('collection', 100, $entries)], PD_RATES, null, null, '2026-01-01')['lines'][0]['status'])->toBe($status);
})->with([
    [[], 'pending'],
    [[['invoice', 100]], 'invoiced'],
    [[['invoice', 100], ['receipt', 30]], 'partly'],
    [[['invoice', 100], ['receipt', 100]], 'received'],
    [[['invoice', 100], ['receipt', 120]], 'overpaid'],
]);

it('builds a month-by-month cash flow with a running balance', function () {
    $s = P::summary([
        pdLine('collection', 300, [['invoice', 300, '2026-01-10'], ['receipt', 300, '2026-03-05']], '2026-01-20'),
        pdLine('collection', 200, [], '2026-04-01'),
        pdLine('principal', 0, [['payment', 100, '2026-02-01'], ['invoice', 100, '2026-01-02']]),
    ], PD_RATES, null, null, '2026-01-01');

    expect($s['cash_flow'])->toBe([
        ['month' => '2026-01', 'expected_in' => 300, 'received' => 0, 'paid_out' => 0, 'balance' => 0],
        ['month' => '2026-02', 'expected_in' => 0, 'received' => 0, 'paid_out' => 100, 'balance' => -100],
        ['month' => '2026-03', 'expected_in' => 0, 'received' => 300, 'paid_out' => 0, 'balance' => 200],
        ['month' => '2026-04', 'expected_in' => 200, 'received' => 0, 'paid_out' => 0, 'balance' => 200],
    ]);
});

it('stretches the cash flow to the project dates and is empty with no dates', function () {
    expect(array_column(P::summary([], PD_RATES, '2025-11-15', '2026-01-31', '2026-01-01')['cash_flow'], 'month'))->toBe(['2025-11', '2025-12', '2026-01'])
        ->and(P::summary([pdLine('principal', 5)], PD_RATES, null, null, '2026-01-01')['cash_flow'])->toBe([]);
});

it('measures how far through the project today is', function () {
    expect(P::summary([], PD_RATES, '2026-01-01', '2026-01-11', '2026-01-06')['duration_pct'])->toBe(50)
        ->and(P::summary([], PD_RATES, '2026-01-01', '2026-01-11', '2025-12-01')['duration_pct'])->toBe(0)
        ->and(P::summary([], PD_RATES, '2026-01-01', '2026-01-11', '2026-05-01')['duration_pct'])->toBe(100)
        ->and(P::summary([], PD_RATES, '2026-01-01', '2026-01-01', '2026-01-01')['duration_pct'])->toBe(100)
        ->and(P::summary([], PD_RATES, '2026-01-01', null, '2026-01-06')['duration_pct'])->toBeNull();
});
