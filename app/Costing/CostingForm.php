<?php

namespace App\Costing;

use App\Enums\PdGroup;
use App\Models\Tender;
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Converts between saved costing rows, on-screen text inputs and calculator/saver data; owns the validation rules. */
final class CostingForm
{
    public static function blankLine(int $defaultBp): array
    {
        return [
            'description' => '', 'unit' => 'unit', 'quantity' => '1', 'frequency' => 'one_off', 'months' => '1',
            'project_year' => '1', 'unit_cost' => '0', 'margin' => Percent::toInput($defaultBp),
            'vendor' => '', 'quote_url' => '', 'sub_items' => [], 'pd_group' => PdGroup::Principal->value,
        ];
    }

    public static function blankSubItem(): array
    {
        return ['description' => '', 'unit' => 'unit', 'quantity' => '1', 'unit_cost' => '0', 'vendor' => '', 'quote_url' => ''];
    }

    public static function fromTender(Tender $t): array
    {
        return [
            'defaultMargin' => Percent::toInput($t->default_margin_bp ?? 2000),
            'override' => Money::toInput($t->bid_price_override_sen),
            'lines' => $t->costingLines->map(fn ($l) => [
                'description' => $l->description,
                'unit' => $l->unit,
                'quantity' => (string) $l->quantity,
                'frequency' => $l->frequency,
                'months' => (string) $l->months,
                'project_year' => (string) $l->project_year,
                'unit_cost' => Money::toInput($l->unit_cost_sen),
                'margin' => Percent::toInput($l->margin_bp),
                'vendor' => (string) $l->vendor,
                'quote_url' => (string) $l->quote_url,
                'pd_group' => $l->pd_group->value,
                'sub_items' => $l->subItems->map(fn ($s) => [
                    'description' => $s->description,
                    'unit' => $s->unit,
                    'quantity' => (string) $s->quantity,
                    'unit_cost' => Money::toInput($s->unit_cost_sen),
                    'vendor' => (string) $s->vendor,
                    'quote_url' => (string) $s->quote_url,
                ])->all(),
            ])->all(),
        ];
    }

    /** The PD groups a costing line can go to (all but Collection). */
    private static function costGroupValues(): array
    {
        return array_map(fn (PdGroup $g) => $g->value, PdGroup::costGroups());
    }

    public static function rules(): array
    {
        $common = fn (string $p) => [
            "{$p}.description" => ['required', 'string', 'max:500'],
            "{$p}.unit" => ['required', 'string', 'max:50'],
            "{$p}.quantity" => ['required', 'integer', 'min:1', 'max:1000000'],
            "{$p}.unit_cost" => ['required', new MoneyAmount],
            "{$p}.vendor" => ['nullable', 'string', 'max:255'],
            "{$p}.quote_url" => ['nullable', 'url:http,https', 'max:500'],
        ];

        return [
            'defaultMargin' => ['required', new Percentage],
            'override' => ['nullable', new MoneyAmount],
            'lines' => ['array'],
            ...$common('lines.*'),
            'lines.*.frequency' => ['required', 'in:one_off,monthly'],
            'lines.*.months' => ['required', 'integer', 'min:1', 'max:600'],
            'lines.*.project_year' => ['required', 'integer', 'between:1,7'],
            'lines.*.margin' => ['required', new Percentage],
            'lines.*.pd_group' => ['required', Rule::in(self::costGroupValues())],
            'lines.*.sub_items' => ['array'],
            ...$common('lines.*.sub_items.*'),
        ];
    }

    /** Plain-English names for validation messages. */
    public static function attributes(): array
    {
        return [
            'defaultMargin' => 'default margin', 'override' => 'bid price',
            'lines.*.description' => 'description', 'lines.*.unit' => 'unit', 'lines.*.quantity' => 'quantity',
            'lines.*.months' => 'months', 'lines.*.project_year' => 'year', 'lines.*.unit_cost' => 'unit cost',
            'lines.*.margin' => 'margin', 'lines.*.vendor' => 'vendor', 'lines.*.quote_url' => 'quotation link',
            'lines.*.frequency' => 'frequency', 'lines.*.pd_group' => 'group',
            'lines.*.sub_items.*.description' => 'description', 'lines.*.sub_items.*.unit' => 'unit',
            'lines.*.sub_items.*.quantity' => 'quantity', 'lines.*.sub_items.*.unit_cost' => 'unit cost',
            'lines.*.sub_items.*.vendor' => 'vendor', 'lines.*.sub_items.*.quote_url' => 'quotation link',
        ];
    }

    /** Lenient mode turns unreadable numbers into defaults instead of throwing (used for live totals while typing). */
    public static function toData(array $state, bool $lenient = false): array
    {
        $default = self::bp($state['defaultMargin'] ?? '', 2000, $lenient);

        return [
            'default_margin_bp' => $default,
            'bid_price_override_sen' => self::sen($state['override'] ?? '', null, $lenient),
            'lines' => array_map(function (array $l) use ($default, $lenient) {
                $frequency = ($l['frequency'] ?? 'one_off') === 'monthly' ? 'monthly' : 'one_off';

                return [
                    'description' => trim((string) ($l['description'] ?? '')),
                    'unit' => trim((string) ($l['unit'] ?? '')) ?: 'unit',
                    'quantity' => self::int($l['quantity'] ?? '1'),
                    'frequency' => $frequency,
                    'months' => $frequency === 'monthly' ? self::int($l['months'] ?? '1') : 1,
                    'project_year' => min(7, self::int($l['project_year'] ?? '1')),
                    'unit_cost_sen' => self::sen($l['unit_cost'] ?? '0', 0, $lenient) ?? 0,
                    'margin_bp' => self::bp($l['margin'] ?? '', $default, $lenient),
                    'vendor' => trim((string) ($l['vendor'] ?? '')) ?: null,
                    'quote_url' => trim((string) ($l['quote_url'] ?? '')) ?: null,
                    'pd_group' => in_array($l['pd_group'] ?? '', self::costGroupValues(), true) ? $l['pd_group'] : PdGroup::Principal->value,
                    'sub_items' => array_map(fn (array $s) => [
                        'description' => trim((string) ($s['description'] ?? '')),
                        'unit' => trim((string) ($s['unit'] ?? '')) ?: 'unit',
                        'quantity' => self::int($s['quantity'] ?? '1'),
                        'unit_cost_sen' => self::sen($s['unit_cost'] ?? '0', 0, $lenient) ?? 0,
                        'vendor' => trim((string) ($s['vendor'] ?? '')) ?: null,
                        'quote_url' => trim((string) ($s['quote_url'] ?? '')) ?: null,
                    ], array_values($l['sub_items'] ?? [])),
                ];
            }, array_values($state['lines'] ?? [])),
        ];
    }

    /** Whole numbers of at least 1; anything else becomes 1 (validation reports it before a save). */
    private static function int(mixed $v): int
    {
        $v = trim((string) $v);

        return ctype_digit($v) ? max(1, min(1000000, (int) $v)) : 1;
    }

    private static function sen(mixed $v, ?int $fallback, bool $lenient): ?int
    {
        try {
            return Money::parse((string) $v);
        } catch (InvalidArgumentException $e) {
            if ($lenient) {
                return $fallback;
            }
            throw $e;
        }
    }

    private static function bp(mixed $v, int $fallback, bool $lenient): int
    {
        try {
            $bp = Percent::parseBp((string) $v);
        } catch (InvalidArgumentException $e) {
            if ($lenient) {
                return $fallback;
            }
            throw $e;
        }

        return $bp === null || $bp > 9999 ? $fallback : $bp;
    }
}
