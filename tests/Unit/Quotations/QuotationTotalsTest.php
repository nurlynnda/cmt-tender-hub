<?php

use App\Quotations\{AmountInWords, QuotationTotals};

it('adds up the prototype quotation to the sen', function () {
    $t = QuotationTotals::of([['quantity' => 6, 'unit_price_sen' => 485000], ['quantity' => 1, 'unit_price_sen' => 650000]], 800);

    expect($t)->toBe([
        'lines' => [2910000, 650000], 'subtotal_sen' => 3560000, 'sst_sen' => 284800, 'total_sen' => 3844800,
        'words' => 'Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only',
    ]);
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
