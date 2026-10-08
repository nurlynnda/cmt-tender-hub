<?php

use App\Quotations\{AmountInWords, QuotationTotals};

it('adds up the prototype quotation to the sen', function () {
    $t = QuotationTotals::of([['quantity' => 6, 'unit_price_sen' => 485000], ['quantity' => 1, 'unit_price_sen' => 650000]], 800);

    expect($t)->toBe([
        'lines' => [2910000, 650000], 'subtotal_sen' => 3560000, 'taxable_sen' => 3560000, 'sst_sen' => 284800, 'total_sen' => 3844800,
        'words' => 'Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only', 'has_frequency' => false,
    ]);
});

it('charges SST only on ticked items and multiplies by the frequency', function () {
    $t = QuotationTotals::of([
        ['quantity' => 2, 'frequency' => 12, 'unit_price_sen' => 10000, 'has_sst' => true],   // service: 2,400.00
        ['quantity' => 1, 'frequency' => 1, 'unit_price_sen' => 500000, 'has_sst' => false],  // hardware: 5,000.00
    ], 800);

    expect($t)->toMatchArray(['lines' => [240000, 500000], 'subtotal_sen' => 740000, 'taxable_sen' => 240000,
        'sst_sen' => 19200, 'total_sen' => 759200, 'has_frequency' => true]);
});

it('charges no SST when no item is ticked', function () {
    expect(QuotationTotals::of([['quantity' => 1, 'unit_price_sen' => 1000, 'has_sst' => false]], 800))
        ->toMatchArray(['sst_sen' => 0, 'total_sen' => 1000, 'has_frequency' => false]);
});

it('rounds SST half up and allows 0%', function () {
    expect(QuotationTotals::of([['quantity' => 1, 'unit_price_sen' => 7]], 800)['sst_sen'])->toBe(1)     // 0.56 → 1
        ->and(QuotationTotals::of([['quantity' => 1, 'unit_price_sen' => 6]], 800)['sst_sen'])->toBe(0)  // 0.48 → 0
        ->and(QuotationTotals::of([['quantity' => 3, 'unit_price_sen' => 1000]], 0)['total_sen'])->toBe(3000)
        ->and(QuotationTotals::of([], 800)['total_sen'])->toBe(0);
});

it('writes amounts in words', function (int $sen, string $words) {
    expect(AmountInWords::ringgit($sen))->toBe($words);
})->with([
    [0, 'Ringgit Malaysia Zero Only'],
    [50, 'Ringgit Malaysia Zero and Fifty Sen Only'],
    [1050, 'Ringgit Malaysia Ten and Fifty Sen Only'],
    [101, 'Ringgit Malaysia One and One Sen Only'],
    [1500000, 'Ringgit Malaysia Fifteen Thousand Only'],
    [10000000, 'Ringgit Malaysia One Hundred Thousand Only'],
    [2052000, 'Ringgit Malaysia Twenty Thousand Five Hundred Twenty Only'],
    [100000100, 'Ringgit Malaysia One Million One Only'],
    [100000000000, 'Ringgit Malaysia One Billion Only'],
    [1911999, 'Ringgit Malaysia Nineteen Thousand One Hundred Nineteen and Ninety Nine Sen Only'],
]);
