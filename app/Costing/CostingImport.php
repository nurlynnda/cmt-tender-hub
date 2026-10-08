<?php

namespace App\Costing;

use App\Support\Money;
use InvalidArgumentException;

/** Rows pasted from Excel: description, quantity, unit, unit cost — tab-separated, or comma-separated. */
final class CostingImport
{
    /** @return array{rows: list<array{description:string,quantity:string,unit:string,unit_cost:string}>, errors: list<string>} */
    public static function parse(string $text): array
    {
        $rows = [];
        $errors = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $raw) {
            $n = $i + 1;
            if (trim($raw) === '') {
                continue;
            }
            if (str_contains($raw, "\t")) {
                $parts = explode("\t", $raw);
            } else {
                // Commas may appear inside the description, so the last three fields are quantity, unit and cost.
                $parts = explode(',', $raw);
                if (count($parts) > 4) {
                    $parts = [implode(',', array_slice($parts, 0, -3)), ...array_slice($parts, -3)];
                }
            }
            $parts = array_map('trim', $parts);
            if (count($parts) < 4 || $parts[0] === '') {
                $errors[] = "Row {$n}: needs description, quantity, unit and unit cost.";

                continue;
            }
            [$description, $quantity, $unit, $cost] = $parts;
            if (! ctype_digit($quantity) || (int) $quantity < 1) {
                $errors[] = "Row {$n}: quantity must be a whole number of at least 1.";

                continue;
            }
            try {
                $sen = Money::parse($cost) ?? 0;
            } catch (InvalidArgumentException) {
                $errors[] = "Row {$n}: unit cost is not a valid amount.";

                continue;
            }
            $rows[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $unit !== '' ? $unit : 'unit',
                'unit_cost' => Money::toInput($sen),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
