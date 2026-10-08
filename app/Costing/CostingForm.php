<?php

namespace App\Costing;

use App\Models\Tender;
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
use InvalidArgumentException;

/**
 * Converts between saved costing rows, on-screen text inputs and calculator/saver data; owns the validation rules.
 * Quotation items reuse the per-line parts (lineRules, lineToData, lineFromModel).
 */
final class CostingForm
{
    public static function blankLine(int $defaultBp): array
    {
        return [
            'description' => '', 'unit' => 'unit', 'quantity' => '1', 'frequency' => '1', 'unit_cost' => '0',
            'margin' => Percent::toInput($defaultBp), 'unit_price' => '', 'vendor' => '', 'quote_url' => '', 'sub_items' => [],
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
            'lines' => $t->costingLines->map(fn ($l) => self::lineFromModel($l))->all(),
        ];
    }

    /** Screen strings for a saved costing line or quotation item (same column names; sub-items as rows or JSON). */
    public static function lineFromModel(object $l): array
    {
        $subs = $l instanceof \App\Models\CostingLine ? $l->subItems : ($l->sub_items ?? []);

        return [
            'description' => (string) ($l->description ?? ''),
            'unit' => (string) $l->unit,
            'quantity' => (string) $l->quantity,
            'frequency' => (string) $l->frequency,
            'unit_cost' => Money::toInput($l->unit_cost_sen),
            'margin' => Percent::toInput($l->margin_bp),
            'unit_price' => Money::toInput($l->unit_price_override_sen),
            'vendor' => (string) $l->vendor,
            'quote_url' => (string) $l->quote_url,
            'sub_items' => collect($subs)->map(fn ($s) => [
                'description' => (string) data_get($s, 'description'),
                'unit' => (string) data_get($s, 'unit', 'unit'),
                'quantity' => (string) data_get($s, 'quantity', 1),
                'unit_cost' => Money::toInput((int) data_get($s, 'unit_cost_sen', 0)),
                'vendor' => (string) data_get($s, 'vendor'),
                'quote_url' => (string) data_get($s, 'quote_url'),
            ])->values()->all(),
        ];
    }

    public static function rules(): array
    {
        return [
            'defaultMargin' => ['required', new Percentage],
            'override' => ['nullable', new MoneyAmount],
            'lines' => ['array'],
            ...self::lineRules('lines.*'),
        ];
    }

    /** Rules for costing lines (or quotation items) under $prefix, e.g. "lines.*" or "items.i12". */
    public static function lineRules(string $prefix): array
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
            ...$common($prefix),
            "{$prefix}.frequency" => ['required', 'integer', 'min:1', 'max:1000'],
            "{$prefix}.margin" => ['required', new Percentage],
            "{$prefix}.unit_price" => ['nullable', new MoneyAmount],
            "{$prefix}.sub_items" => ['array'],
            ...$common("{$prefix}.sub_items.*"),
        ];
    }

    /** Plain-English names for validation messages. */
    public static function attributes(): array
    {
        return [
            'defaultMargin' => 'default margin', 'override' => 'bid price',
            'lines.*.description' => 'description', 'lines.*.unit' => 'unit', 'lines.*.quantity' => 'quantity',
            'lines.*.frequency' => 'frequency', 'lines.*.unit_cost' => 'unit cost', 'lines.*.margin' => 'margin',
            'lines.*.unit_price' => 'selling price', 'lines.*.vendor' => 'vendor', 'lines.*.quote_url' => 'quotation link',
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
            'lines' => array_map(fn (array $l) => self::lineToData($l, $default, $lenient), array_values($state['lines'] ?? [])),
        ];
    }

    /** One line's screen strings → saved/calculator values. An empty selling price means "work it out from the margin". */
    public static function lineToData(array $l, int $defaultBp, bool $lenient): array
    {
        $price = trim((string) ($l['unit_price'] ?? ''));

        return [
            'description' => trim((string) ($l['description'] ?? '')),
            'unit' => trim((string) ($l['unit'] ?? '')) ?: 'unit',
            'quantity' => self::int($l['quantity'] ?? '1'),
            'frequency' => min(1000, self::int($l['frequency'] ?? '1')),
            'unit_cost_sen' => self::sen($l['unit_cost'] ?? '0', 0, $lenient) ?? 0,
            'margin_bp' => self::bp($l['margin'] ?? '', $defaultBp, $lenient),
            'unit_price_override_sen' => $price === '' ? null : self::sen($price, null, $lenient),
            'vendor' => trim((string) ($l['vendor'] ?? '')) ?: null,
            'quote_url' => trim((string) ($l['quote_url'] ?? '')) ?: null,
            'sub_items' => array_map(fn (array $s) => [
                'description' => trim((string) ($s['description'] ?? '')),
                'unit' => trim((string) ($s['unit'] ?? '')) ?: 'unit',
                'quantity' => self::int($s['quantity'] ?? '1'),
                'unit_cost_sen' => self::sen($s['unit_cost'] ?? '0', 0, $lenient) ?? 0,
                'vendor' => trim((string) ($s['vendor'] ?? '')) ?: null,
                'quote_url' => trim((string) ($s['quote_url'] ?? '')) ?: null,
            ], array_values($l['sub_items'] ?? [])),
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
