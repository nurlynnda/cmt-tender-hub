<?php

use App\Support\Money;

it('parses human ringgit amounts into sen', function (string $input, int $sen) {
    expect(Money::parse($input))->toBe($sen);
})->with([
    ['1234', 123400],
    ['1234.5', 123450],
    ['1,234.56', 123456],
    ['RM 1,234.50', 123450],
    ['rm1000', 100000],
    [' 1,000 ', 100000],
    ['0', 0],
    ['0.05', 5],
]);

it('treats empty input as no amount', function (?string $input) {
    expect(Money::parse($input))->toBeNull();
})->with([null, '', '   ', 'RM ']);

it('rejects invalid amounts', function (string $input) {
    Money::parse($input);
})->with(['-5', '1.234', '1,23', 'abc', '12a', '1,2345'])->throws(InvalidArgumentException::class);

it('formats sen as ringgit', function () {
    expect(Money::format(123456))->toBe('RM 1,234.56')
        ->and(Money::format(5))->toBe('RM 0.05')
        ->and(Money::format(0))->toBe('RM 0.00')
        ->and(Money::format(null))->toBe('—');
});

it('formats sen for an input box', function () {
    expect(Money::toInput(123450))->toBe('1234.50')
        ->and(Money::toInput(null))->toBe('');
});

it('computes a variant percentage to one decimal place', function () {
    expect(Money::variant(86617900, 40275400))->toBe('215.1%')
        ->and(Money::variant(85444700, 26658700))->toBe('320.5%')
        ->and(Money::variant(null, 100))->toBeNull()
        ->and(Money::variant(100, null))->toBeNull()
        ->and(Money::variant(100, 0))->toBeNull();
});
