<?php

namespace App\Rules;

use App\Support\Percent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class Percentage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $bp = Percent::parseBp((string) $value);
        } catch (InvalidArgumentException) {
            $fail('Enter a percentage like 20 or 18.5.');

            return;
        }
        if ($bp === null || $bp > 9999) {
            $fail('Enter a margin from 0 to 99.99%.');
        }
    }
}
