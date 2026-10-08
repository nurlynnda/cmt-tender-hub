<?php

use App\Rules\MoneyAmount;
use Illuminate\Support\Facades\Validator;

function moneyPasses(mixed $value, bool $positive = false): bool
{
    return Validator::make(['amount' => $value], ['amount' => [new MoneyAmount($positive)]])->passes();
}

it('accepts valid and empty amounts', function () {
    expect(moneyPasses('1,234.50'))->toBeTrue()
        ->and(moneyPasses(''))->toBeTrue()
        ->and(moneyPasses(null))->toBeTrue()
        ->and(moneyPasses('0'))->toBeTrue();
});

it('rejects invalid amounts with a helpful message', function () {
    $v = Validator::make(['amount' => '-5'], ['amount' => [new MoneyAmount()]]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->first('amount'))->toBe('Enter an amount like 12,345.67.');
});

it('can require a positive amount', function () {
    expect(moneyPasses('0', positive: true))->toBeFalse()
        ->and(moneyPasses('0.01', positive: true))->toBeTrue();
});
