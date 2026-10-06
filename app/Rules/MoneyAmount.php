<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class MoneyAmount implements ValidationRule
{
    public function __construct(private bool $mustBePositive = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        try {
            $sen = Money::parse((string) $value);
        } catch (InvalidArgumentException) {
            $fail('Enter an amount like 12,345.67.');

            return;
        }

        if ($this->mustBePositive && $sen <= 0) {
            $fail('The amount must be more than RM 0.00.');
        }
    }
}
