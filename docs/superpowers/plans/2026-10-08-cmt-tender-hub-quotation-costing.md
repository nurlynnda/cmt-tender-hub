# Quotation Costing, Simpler Tender Costing, Per-item SST — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:**
- Tender costing gets a whole-number frequency and an editable selling price (with the margin worked backwards), and loses Year and Group.
- Quotation items get the same costing (cost, margin, sub-items, vendor, quote link, editable price), a profit summary, and a per-item SST tick.

**Architecture:**
- **One calculator.** `CostingCalculator` does all the maths for both screens: frequency, price override, effective margin.
- **Shared form rules.** `CostingForm` exposes per-line rules and conversion that quotation items reuse.
- **Quotation items save the selling price they work out** into the existing `unit_price_sen` column on every save. So `QuotationTotals`, the PDF, Duplicate, Revise and old sent quotations keep reading one stored price. They only learn about frequency and the SST tick.

**Tech Stack:** Laravel 13, Livewire 4.4, Pest 5, MySQL 8.4.
- PHP runs only in Docker: from `C:\Projects\cmt-tender-hub` run `MSYS_NO_PATHCONV=1 docker compose exec -T app …`.
- The pre-commit hook runs the full suite (~5–10 min). Never run tests while a commit is running, because they share the test database.

**Spec:** `docs/superpowers/specs/2026-10-08-cmt-tender-hub-quotation-costing-design.md`

**Plan rulings (deliberate differences from the spec, all keeping its behaviour):**
1. `quotation_items.unit_price_sen` is **kept** as the saved selling unit price, worked out on every item save. It is not dropped as the spec said. This means:
   - totals, the PDF, Duplicate/Revise and reports stay simple
   - sent quotations keep exactly their figures
2. Quotation **sub-items are a JSON column** `quotation_items.sub_items`, not a separate table. An item then saves in one step with its sub-items, which suits the quotation page's save-as-you-type design.
3. `CostingImport` (Bulk Import) has no frequency/year/group columns today, so it needs no change. Imported lines get frequency 1.

## Global Constraints

- Money in integer sen; percentages in basis points (1% = 100 bp); no floating point in prices.
- **Price rule:** price per unit = cost ÷ (1 − margin), rounded up to the whole ringgit (existing `pricePerUnitSen`), unless a selling price is typed. A typed price is used exactly.
- **Line maths:**
  - line cost = qty × unit cost × frequency
  - selling = price × qty × frequency
  - frequency is a whole number from 1 to 1000
- **Effective margin** = round((price − cost) × 10000 ÷ price) bp, or 0 when the price is 0. It can be negative.
- **SST** = the quotation's SST % of the sum of the **ticked** lines, rounded half up to the sen. The total = subtotal + SST.
- **Hidden from customers:** cost, margin, vendor, quote link and sub-items never appear on the PDF.
- **Text:** plain English in the interface. Reuse the existing input class `$in`, the `btn` classes and `x-card`.
- **Process:** tests first (RED → GREEN); coverage ≥ 80%; write PHP/Blade with the Edit/Write tools (the shell mangles backslashes).

## Review Focus

1. **A typed selling price below cost** (negative margin): the price is used, the shown margin is negative in red, the stored margin is clamped to 0–99.99%, and the summary's target warning shows. *Pinned in Task 1 and Task 2.*
2. **Clearing the typed price, or editing the margin after typing a price:** the price goes back to the worked-out one. *Pinned in Task 2 (tender) and Task 4 (quotation).*
3. **An item with sub-items and a typed price:** the cost comes from the sub-items, and the typed price wins. *Pinned in Task 1.*
4. **A quotation with no SST-ticked items:** SST is RM 0.00 and the total equals the subtotal. *Pinned in Task 3.*
5. **An existing (pre-migration) sent quotation:** its subtotal, SST and total are unchanged (frequency 1, SST ticked, stored price kept). *Pinned in Task 3; checked on real data in Task 6.*

---

### Task 1: Calculator and tender costing data (frequency number, price override, no Year/Group)

**Files:**
- Modify:
  - `app/Costing/CostingCalculator.php`
  - `app/Costing/CostingForm.php`
  - `app/Models/CostingLine.php`
  - `app/Models/Tender.php` (`costingSummary`)
  - `app/Actions/Pd/CreateProjectFromCosting.php`
  - `app/Import/RegisterImporter.php:175`
  - `database/factories/CostingLineFactory.php`
- Create: `database/migrations/2026_10_13_000001_simplify_costing_lines.php`
- Test:
  - `tests/Unit/Costing/CostingCalculatorTest.php`
  - `tests/Feature/Costing/SaveCostingTest.php`
  - `tests/Feature/Pd/AwardCreatesProjectTest.php`

**Interfaces — Produces:**
- `CostingCalculator::line(array $l): array`
  - input keys: `quantity:int, frequency:int, unit_cost_sen:int, margin_bp:int, sub_items:list, unit_price_override_sen:?int` (optional)
  - returns `unit_cost_sen, line_cost_sen, price_per_unit_sen, selling_sen, effective_margin_bp, is_price_override`
- `CostingCalculator::marginFromPriceBp(int $costSen, int $priceSen): int`
- `CostingCalculator::summary()`: no `cost_by_year` key
- `CostingForm::lineRules(string $prefix): array`
  - covers `description, unit, quantity, frequency, unit_cost, margin, unit_price, vendor, quote_url, sub_items`
  - for prefix `lines.*`
- `CostingForm::lineToData(array $l, int $defaultBp, bool $lenient): array`
  - returns `description, unit, quantity, frequency, unit_cost_sen, margin_bp, unit_price_override_sen, vendor, quote_url, sub_items`
- `CostingForm::lineFromModel(object $l): array` (screen strings, from a CostingLine)
- Line screen state keys: `description, unit, quantity, frequency, unit_cost, margin, unit_price, vendor, quote_url, sub_items`
- `costing_lines`:
  - `frequency` unsigned int
  - `unit_price_override_sen` nullable
  - `months`, `project_year` and `pd_group` removed

- [ ] **Step 1: Failing tests.**

Replace the helper and the frequency tests in `tests/Unit/Costing/CostingCalculatorTest.php`:
```php
function oneOff(int $costSen, int $bp = 2000, int $qty = 1): array
{
    return ['quantity' => $qty, 'frequency' => 1, 'unit_cost_sen' => $costSen, 'margin_bp' => $bp, 'sub_items' => []];
}
```
Replace the tests "uses sub-items as the cost of one unit, and multiplies monthly lines" and "treats one-off lines as one month whatever months says" with:
```php
it('uses sub-items as the cost of one unit, and multiplies by the frequency', function () {
    $line = ['quantity' => 20, 'frequency' => 12, 'unit_cost_sen' => 999,
        'margin_bp' => 2000, 'sub_items' => [['quantity' => 1, 'unit_cost_sen' => 300000], ['quantity' => 2, 'unit_cost_sen' => 40000]]];

    expect(C::line($line))->toBe([
        'unit_cost_sen' => 380000,                 // 3,000 + 2 × 400 per set
        'line_cost_sen' => 20 * 380000 * 12,
        'price_per_unit_sen' => 475000,            // 3,800 ÷ 0.8
        'selling_sen' => 20 * 475000 * 12,
        'effective_margin_bp' => 2000,
        'is_price_override' => false,
    ]);
});

it('uses a typed selling price exactly and works the margin out backwards', function () {
    $typed = C::line(['unit_price_override_sen' => 125000] + oneOff(100000, 3000, 2));
    $belowCost = C::line(['unit_price_override_sen' => 90000] + oneOff(100000));
    $subs = C::line(['unit_price_override_sen' => 500000, 'sub_items' => [['quantity' => 2, 'unit_cost_sen' => 200000]]] + oneOff(1));

    expect($typed)->toMatchArray(['price_per_unit_sen' => 125000, 'selling_sen' => 250000, 'effective_margin_bp' => 2000, 'is_price_override' => true])
        ->and($belowCost['effective_margin_bp'])->toBe(-1111)                 // (900 − 1,000) ÷ 900
        ->and($subs)->toMatchArray(['unit_cost_sen' => 400000, 'price_per_unit_sen' => 500000, 'effective_margin_bp' => 2000])
        ->and(C::line(['unit_price_override_sen' => 0] + oneOff(100))['effective_margin_bp'])->toBe(0)
        ->and(C::marginFromPriceBp(100000, 125000))->toBe(2000);
});

it('no longer reports cost by year', function () {
    expect(C::summary([oneOff(100)], null, null))->not->toHaveKey('cost_by_year');
});
```
In `tests/Feature/Costing/SaveCostingTest.php`, replace the tests "converts screen input to saved data", "forces months to 1 …" and the year/months/group dataset rows, plus the `pd_group` test (lines ~125–137), with:
```php
it('converts screen input to saved data', function () {
    $state = costingState(['quantity' => '2', 'frequency' => '6', 'margin' => '18.5', 'unit_price' => '',
        'sub_items' => [array_merge(CostingForm::blankSubItem(), ['description' => 'PC', 'quantity' => '1', 'unit_cost' => '3,000'])]]);
    $state['override'] = 'RM 130,000';

    $data = CostingForm::toData($state);

    expect($data['default_margin_bp'])->toBe(2000)
        ->and($data['bid_price_override_sen'])->toBe(13000000)
        ->and($data['lines'][0])->toMatchArray(['quantity' => 2, 'frequency' => 6, 'unit_cost_sen' => 10000000, 'margin_bp' => 1850,
            'unit_price_override_sen' => null, 'description' => 'Server'])
        ->and($data['lines'][0])->not->toHaveKeys(['months', 'project_year', 'pd_group'])
        ->and($data['lines'][0]['sub_items'][0])->toMatchArray(['description' => 'PC', 'quantity' => 1, 'unit_cost_sen' => 300000]);
});

it('keeps a typed selling price, and tolerates junk in lenient mode', function () {
    expect(CostingForm::toData(costingState(['unit_price' => '1,250']))['lines'][0]['unit_price_override_sen'])->toBe(125000);

    $lenient = CostingForm::toData(costingState(['unit_cost' => 'abc', 'quantity' => 'x', 'margin' => '??', 'frequency' => 'monthly', 'unit_price' => 'zz']), lenient: true)['lines'][0];
    expect($lenient)->toMatchArray(['unit_cost_sen' => 0, 'quantity' => 1, 'margin_bp' => 2000, 'frequency' => 1, 'unit_price_override_sen' => null]);
});

it('saves the frequency and typed price and reads them back', function () {
    [$pic, $tender] = picTender();

    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData(costingState(['frequency' => '12', 'unit_price' => '150,000'])));

    expect($t->costingLines->first()->only(['frequency', 'unit_price_override_sen']))->toBe(['frequency' => 12, 'unit_price_override_sen' => 15000000])
        ->and(CostingForm::fromTender($t)['lines'][0])->toMatchArray(['frequency' => '12', 'unit_price' => '150000.00'])
        ->and($t->costingSummary()['bid_price_sen'])->toBe(15000000 * 12);
});
```
Change the "validates every field" dataset: remove the `months` and `project_year` rows, and add:
```php
    [['frequency' => '0'], 'lines.0.frequency'],
    [['frequency' => '1.5'], 'lines.0.frequency'],
    [['frequency' => 'monthly'], 'lines.0.frequency'],
    [['unit_price' => '-5'], 'lines.0.unit_price'],
```
In `tests/Feature/Pd/AwardCreatesProjectTest.php`, the first test becomes:
```php
    CostingLine::factory()->for($tender)->create(['position' => 1, 'description' => 'Laptops', 'vendor' => 'Dell',
        'unit_cost_sen' => 100000, 'quantity' => 2]);
    CostingLine::factory()->for($tender)->create(['position' => 2, 'description' => 'Support', 'frequency' => 12, 'unit_cost_sen' => 50000]);
    // …
            [PdGroup::Collection, 'Contract value', null, $bid],
            [PdGroup::Principal, 'Laptops', 'Dell', 200000],
            [PdGroup::Principal, 'Support', null, 600000],   // frequency 12: the whole cost
```

- [ ] **Step 2: Run** `MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan test tests/Unit/Costing tests/Feature/Costing tests/Feature/Pd/AwardCreatesProjectTest.php`.
  - Expected: FAIL (unknown keys `effective_margin_bp`, the `frequency` rule and the columns).

- [ ] **Step 3: Implement.**

Migration `2026_10_13_000001_simplify_costing_lines.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Costing frequency becomes a whole number (monthly × N → N); Year and Group go; a line can carry a typed selling price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_lines', function (Blueprint $table) {
            $table->unsignedInteger('frequency_count')->default(1)->after('quantity');
            $table->unsignedBigInteger('unit_price_override_sen')->nullable()->after('margin_bp');
        });
        DB::table('costing_lines')->update(['frequency_count' => DB::raw("CASE WHEN frequency = 'monthly' THEN GREATEST(months, 1) ELSE 1 END")]);
        Schema::table('costing_lines', fn (Blueprint $table) => $table->dropColumn(['frequency', 'months', 'project_year', 'pd_group']));
        Schema::table('costing_lines', fn (Blueprint $table) => $table->renameColumn('frequency_count', 'frequency'));
    }

    public function down(): void
    {
        Schema::table('costing_lines', fn (Blueprint $table) => $table->renameColumn('frequency', 'frequency_count'));
        Schema::table('costing_lines', function (Blueprint $table) {
            $table->string('frequency', 10)->default('one_off')->after('quantity');
            $table->unsignedSmallInteger('months')->default(1)->after('frequency');
            $table->unsignedTinyInteger('project_year')->default(1)->after('months');
            $table->string('pd_group', 20)->default('principal');
        });
        DB::table('costing_lines')->update([
            'frequency' => DB::raw("CASE WHEN frequency_count > 1 THEN 'monthly' ELSE 'one_off' END"),
            'months' => DB::raw('LEAST(frequency_count, 600)'),
        ]);
        Schema::table('costing_lines', fn (Blueprint $table) => $table->dropColumn(['frequency_count', 'unit_price_override_sen']));
    }
};
```
(Before writing it, check how `pd_group` was added to `costing_lines`: `grep -rn "pd_group" database/migrations`. If an index or foreign key exists on it, drop that first in `up()`, and copy its exact type into `down()`.)

`CostingCalculator`:
- replace `line()`
- add `marginFromPriceBp()`
- delete the `$byYear` block and the `'cost_by_year'` key in `summary()`

```php
    /** The margin a selling price gives over a cost: (price − cost) ÷ price, in bp; negative below cost; 0 when the price is 0. */
    public static function marginFromPriceBp(int $costSen, int $priceSen): int
    {
        return $priceSen > 0 ? (int) round(($priceSen - $costSen) * 10000 / $priceSen) : 0;
    }

    public static function line(array $l): array
    {
        $unitCost = ($l['sub_items'] ?? []) !== []
            ? array_sum(array_map(fn ($s) => $s['quantity'] * $s['unit_cost_sen'], $l['sub_items']))
            : $l['unit_cost_sen'];
        $frequency = max(1, (int) ($l['frequency'] ?? 1));
        $override = $l['unit_price_override_sen'] ?? null;
        $price = $override ?? self::pricePerUnitSen($unitCost, $l['margin_bp']);

        return [
            'unit_cost_sen' => $unitCost,
            'line_cost_sen' => $l['quantity'] * $unitCost * $frequency,
            'price_per_unit_sen' => $price,
            'selling_sen' => $price * $l['quantity'] * $frequency,
            'effective_margin_bp' => self::marginFromPriceBp($unitCost, $price),
            'is_price_override' => $override !== null,
        ];
    }
```

`CostingForm`:
- remove `use App\Enums\PdGroup;`, `use Illuminate\Validation\Rule;` and `costGroupValues()`
- `blankLine()`: `'description' => '', 'unit' => 'unit', 'quantity' => '1', 'frequency' => '1', 'unit_cost' => '0', 'margin' => Percent::toInput($defaultBp), 'unit_price' => '', 'vendor' => '', 'quote_url' => '', 'sub_items' => []`
- `fromTender()`: `'lines' => $t->costingLines->map(fn ($l) => self::lineFromModel($l))->all()`, plus:
```php
    /** Screen strings for a saved costing line or quotation item (same column names). */
    public static function lineFromModel(object $l): array
    {
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
            'sub_items' => collect($l->subItems ?? $l->sub_items ?? [])->map(fn ($s) => [
                'description' => (string) data_get($s, 'description'),
                'unit' => (string) data_get($s, 'unit', 'unit'),
                'quantity' => (string) data_get($s, 'quantity', 1),
                'unit_cost' => Money::toInput((int) data_get($s, 'unit_cost_sen', 0)),
                'vendor' => (string) data_get($s, 'vendor'),
                'quote_url' => (string) data_get($s, 'quote_url'),
            ])->values()->all(),
        ];
    }
```
- `rules()`: `['defaultMargin' => ['required', new Percentage], 'override' => ['nullable', new MoneyAmount], 'lines' => ['array'], ...self::lineRules('lines.*')]`, plus:
```php
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
```
  - Note: `integer` accepts "1.0"? No: Laravel's `integer` uses FILTER_VALIDATE_INT, so "1.5" and "monthly" fail. Good.
- `attributes()`:
  - remove `months`, `project_year` and `pd_group`
  - change the frequency label to `'lines.*.frequency' => 'frequency'`
  - add `'lines.*.unit_price' => 'selling price'`
- `toData()`: `'lines' => array_map(fn (array $l) => self::lineToData($l, $default, $lenient), array_values($state['lines'] ?? []))`, plus:
```php
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
```
  - `self::sen('-5', …)`: `Money::parse` rejects negatives by throwing in strict mode. In lenient mode it gives the fallback (null, so no override), which is what the lenient test expects.

`CostingLine` casts: remove `months`, `project_year` and `pd_group`; add `'frequency' => 'integer', 'unit_price_override_sen' => 'integer'`.

`Tender::costingSummary` mapping:
```php
                'quantity' => $l->quantity, 'frequency' => $l->frequency, 'unit_cost_sen' => $l->unit_cost_sen,
                'margin_bp' => $l->margin_bp, 'unit_price_override_sen' => $l->unit_price_override_sen,
```
`CreateProjectFromCosting`: `'pd_group' => PdGroup::Principal,` for cost lines (the comment becomes `// the whole cost over every repeat`).
`RegisterImporter:175`: `'frequency' => 1,` (remove months and project_year).
`CostingLineFactory`: `'frequency' => 1,` (remove months and project_year).
Then run `grep -rn "pd_group\|project_year\|'months'\|one_off\|monthly" app resources tests database/factories database/seeders`. Every hit about **costing lines** must be updated or removed. `PdLine`/`pd_lines` `pd_group` hits stay.

- [ ] **Step 4: Run** the same command, then `tests/Feature/Pd tests/Feature/Import tests/Feature/Reports`.
  - Expected: PASS. The tender costing screen test may fail on the Year/Group markup; leave it for Task 2 only if the failure is in `tests/Feature/Livewire/TenderCosting*`. Otherwise fix it here.
- [ ] **Step 5: Commit** `feat: costing frequency as a number, typed selling price with margin worked back; Year and Group removed`

---

### Task 2: Tender costing screen

**Files:**
- Modify: `app/Livewire/TenderCosting.php`, `resources/views/livewire/tender-costing.blade.php`
- Test: the existing tender costing Livewire test file (`grep -rln "TenderCosting::class" tests`); add these tests there.

**Interfaces — Consumes:**
- `CostingCalculator::line()` keys `effective_margin_bp`, `is_price_override` (Task 1)
- line state `frequency`, `unit_price` (Task 1)

- [ ] **Step 1: Failing tests** (use that file's existing setup helpers for an in-progress tender the user can edit; `$tender` and `$pic` below stand for those):
```php
it('shows a frequency box and no Year or Group columns', function () {
    // tender with one costing line, frequency 3, as the PIC
    Livewire::actingAs($pic)->test(TenderCosting::class, ['tender' => $tender, 'version' => $tender->version, 'canEdit' => true])
        ->assertSeeHtml('aria-label="Frequency"')->assertDontSeeHtml('aria-label="Year"')->assertDontSeeHtml('aria-label="Group"')
        ->assertDontSee('Cost by year');
});

it('works the margin out backwards from a typed selling price, and back again from the margin', function () {
    $c = Livewire::actingAs($pic)->test(TenderCosting::class, ['tender' => $tender, 'version' => $tender->version, 'canEdit' => true])
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '1,000')
        ->set('lines.0.unit_price', '1,250')
        ->assertSet('lines.0.margin', '20')->assertSee('from price');

    $c->set('lines.0.unit_price', '900')                                  // below cost
        ->assertSet('lines.0.margin', '0')->assertSee('-11.1%')->assertSee('below cost');

    $c->set('lines.0.margin', '25')->assertSet('lines.0.unit_price', '')  // editing the margin goes back to the worked-out price
        ->assertSee('RM 1,334.00');                                        // 1,000 ÷ 0.75 = 1,333.33 → 1,334
});
```
(If the file has no setup helper, create the tender with `Tender::factory()->create(['pic_id' => $pic->id])`. The factory's default status is In Progress; confirm with `grep -n "status" database/factories/TenderFactory.php`.)

- [ ] **Step 2: Run** that file. Expected: FAIL.

- [ ] **Step 3: Implement.**

`TenderCosting::updated()`: after `markDirty`, keep the margin and typed price in step:
```php
    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        if (in_array($parts[0], self::EDITABLE, true)) {
            $this->markDirty();
        }
        if ($parts[0] === 'lines' && isset($parts[1], $parts[2]) && isset($this->lines[(int) $parts[1]])) {
            $i = (int) $parts[1];
            if ($parts[2] === 'margin') {
                $this->lines[$i]['unit_price'] = '';            // a new margin means "work the price out again"
            } elseif ($parts[2] === 'unit_price' || $parts[2] === 'unit_cost' || $parts[2] === 'sub_items') {
                $this->syncMarginFromPrice($i);
            }
        }
    }

    /** With a typed price, the margin box shows the margin that price gives (kept within 0–99.99 so the line still saves). */
    private function syncMarginFromPrice(int $i): void
    {
        $line = CostingForm::lineToData($this->lines[$i], $this->defaultBp(), lenient: true);
        if ($line['unit_price_override_sen'] === null) {
            return;
        }
        $bp = CostingCalculator::line($line)['effective_margin_bp'];
        $this->lines[$i]['margin'] = Percent::toInput(max(0, min(9999, $bp)));
    }
```
`applyDefaultToAll()`: also set `$this->lines[$i]['unit_price'] = '';` for every line.

View `tender-costing.blade.php`:
- **Header row:** `<th>Item</th><th>Qty</th><th>Unit</th><th>Frequency</th><th class="text-right">Unit cost</th><th class="text-right">Line cost</th><th>Margin %</th><th class="text-right">Price/unit</th><th class="text-right">Selling price</th><th>Vendor</th><th>Quotation link</th><th><span class="sr-only">Actions</span></th>`.
  - Remove Year and Group.
  - The table `min-w-[1720px]` becomes `min-w-[1480px]`.
- **Frequency cell:**
```blade
                    <td class="px-2 py-1">
                        <input wire:model.live.blur="lines.{{ $i }}.frequency" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Frequency" title="How many times this repeats, e.g. 12 for monthly over a year">
                        @error("lines.$i.frequency") <span class="block text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
```
- **Delete** the Year and Group `<td>`s.
- **Margin cell:** keep the input, and add underneath:
```blade
                        @if ($calc['is_price_override'] ?? false)
                            <span @class(['block text-[11px]', 'text-bad-ink' => $calc['effective_margin_bp'] < 0, 'text-muted' => $calc['effective_margin_bp'] >= 0])>
                                {{ Percent::format($calc['effective_margin_bp']) }} from price@if ($calc['effective_margin_bp'] < 0) · below cost @endif
                            </span>
                        @endif
```
- **Price/unit cell** becomes an input whose placeholder is the worked-out price:
```blade
                    <td class="whitespace-nowrap px-2 py-1 text-right">
                        <input wire:model.live.blur="lines.{{ $i }}.unit_price" @disabled(! $editable) placeholder="{{ Money::toInput($calc['price_per_unit_sen'] ?? 0) }}"
                               class="{{ $in }} w-28 text-right placeholder:text-ink" aria-label="Selling price per unit" title="Type a price to work the margin out from it; clear it to use the margin">
                        @error("lines.$i.unit_price") <span class="block text-xs text-bad-ink">{{ $message }}</span> @enderror
                    </td>
```
  - If the input is disabled (not editable) and empty, the placeholder still shows the price.
- **Sub-item rows:** the `<td colspan="3"></td>` before the sub-item unit cost becomes `<td></td>` (only the Frequency column sits between Unit and Unit cost now). The empty-state `colspan="14"` becomes `colspan="12"`.
- **Summary cards:** delete the whole "Cost by year" `<div>`, and change `md:grid-cols-2` to nothing (one card).

- [ ] **Step 4: Run** the tender costing test file, plus `tests/Feature/Livewire`. Expected: PASS. (Test 2's `assertSee('RM 1,334.00')` needs the "Price/unit" or "Selling price" cell to show `Money::format`. The selling cell shows `Money::format($calc['selling_sen'])` = RM 1,334.00 for qty 1, frequency 1.)
- [ ] **Step 5: Commit** `feat(ui): costing frequency box and editable selling price; Year and Group columns removed`

---

### Task 3: Quotation item data — costing fields, per-item SST, frequency, project cost lines

**Files:**
- Create: `database/migrations/2026_10_13_000002_quotation_item_costing.php`
- Modify:
  - `app/Models/QuotationItem.php`
  - `app/Models/Quotation.php`
  - `app/Quotations/QuotationTotals.php`
  - `app/Actions/Quotations/{UpdateQuotationItem,AddQuotationItem,DuplicateQuotation,ReviseQuotation,UpdateQuotation,CreateProjectFromQuotation}.php`
  - `database/factories/QuotationItemFactory.php`
  - `database/seeders/DatabaseSeeder.php:121`
- Test:
  - `tests/Unit/Quotations/QuotationTotalsTest.php`
  - `tests/Feature/Quotations/EditQuotationTest.php`
  - `tests/Feature/Quotations/QuotationLifecycleTest.php`

**Interfaces:**
- Consumes: `CostingCalculator::line()`, `CostingCalculator::summary()` (Task 1)
- Produces:
  - `quotation_items` columns: `frequency` (uint, 1), `unit_cost_sen` (ubigint, 0), `margin_bp` (usmallint, 2000), `unit_price_override_sen` (nullable), `vendor` (nullable), `quote_url` (nullable 500), `has_sst` (bool, true), `sub_items` (json nullable); `unit_price_sen` kept as the saved selling price
  - `quotations.default_margin_bp` (usmallint, 2000)
  - `QuotationTotals::of(list<array{quantity:int, frequency?:int, unit_price_sen:int, has_sst?:bool}>, int $sstBp)` returns `lines, subtotal_sen, taxable_sen, sst_sen, total_sen, words, has_frequency`
  - `Quotation::costing(): array` (`CostingCalculator::summary` over the items)
  - `UpdateQuotationItem::handle($actor, $item, $version, array $data)`, where `$data` = `title, details` + `CostingForm::lineToData()` keys (no `description`) + `has_sst:bool`

- [ ] **Step 1: Failing tests.**

`QuotationTotalsTest.php`: add
```php
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
```
(The two existing tests stay. Items without `has_sst`/`frequency` count as ticked and 1, which is exactly an old quotation. Their `toBe([...])` array gains `'taxable_sen' => 3560000` and `'has_frequency' => false` in the right order. Change that `toBe` to `toMatchArray` with the same values.)

`EditQuotationTest.php` (use its existing helpers for a draft quotation and its preparer):
```php
it('works an item price out from its cost and margin, or uses a typed price, and saves it', function () {
    // $q = draft quotation with one item; $item = $q->items->first(); $user = its preparer
    $data = ['title' => 'Switch', 'details' => null, 'quantity' => 2, 'unit' => 'Unit', 'frequency' => 3, 'unit_cost_sen' => 100000,
        'margin_bp' => 2000, 'unit_price_override_sen' => null, 'vendor' => 'Cisco', 'quote_url' => null, 'has_sst' => false,
        'sub_items' => []];

    app(UpdateQuotationItem::class)->handle($user, $item, $q->version, $data);
    expect($item->fresh()->only(['unit_price_sen', 'frequency', 'has_sst', 'vendor']))
        ->toBe(['unit_price_sen' => 125000, 'frequency' => 3, 'has_sst' => false, 'vendor' => 'Cisco']);

    app(UpdateQuotationItem::class)->handle($user, $item, $q->fresh()->version, ['unit_price_override_sen' => 130000,
        'sub_items' => [['description' => 'Rack', 'unit' => 'unit', 'quantity' => 2, 'unit_cost_sen' => 60000, 'vendor' => null, 'quote_url' => null]]] + $data);
    $fresh = $q->fresh();
    expect($item->fresh()->unit_price_sen)->toBe(130000)
        ->and($fresh->costing())->toMatchArray(['total_cost_sen' => 2 * 120000 * 3, 'suggested_bid_sen' => 2 * 130000 * 3])
        ->and($fresh->totals()['sst_sen'])->toBe(0);
});

it('starts new items at the quotation default margin', function () {
    // $q draft, $user its preparer
    app(\App\Actions\Quotations\UpdateQuotation::class)->handle($user, $q, $q->version, ['default_margin_bp' => 1500]);
    app(\App\Actions\Quotations\AddQuotationItem::class)->handle($user, $q->fresh(), $q->fresh()->version);

    expect($q->fresh()->items->last()->only(['margin_bp', 'has_sst', 'frequency']))->toBe(['margin_bp' => 1500, 'has_sst' => true, 'frequency' => 1]);
});

it('keeps an old item exactly as it was priced', function () {
    // an item saved before this change: only a stored unit price
    $item = \App\Models\QuotationItem::factory()->create(['quantity' => 6, 'unit_price_sen' => 485000]);

    expect($item->quotation->fresh()->totals())->toMatchArray(['subtotal_sen' => 2910000, 'sst_sen' => (int) round(2910000 * $item->quotation->sst_bp / 10000)]);
});
```
`QuotationLifecycleTest.php`, in "creates one project from an accepted quotation…": give `readyQuotation`'s items costs before creating the project. Then expect:
```php
    $q->items->each(fn ($i, $n) => $i->update(['unit_cost_sen' => $n === 0 ? 300000 : 0, 'vendor' => $n === 0 ? 'Cisco' : null]));
    // …
        ->and($project->lines->map(fn ($l) => [$l->pd_group, $l->name, $l->reference, $l->budget_sen])->all())->toBe([
            [PdGroup::Collection, 'Contract value', null, 3560000],
            [PdGroup::Principal, $q->items[0]->title, 'Cisco', 300000 * $q->items[0]->quantity],   // items with no cost are skipped
        ])
```
(Read `readyQuotation()` first: it must give 6 × 4,850 and 1 × 6,500 = 3,560,000. Adjust the item index if its order differs.)

Duplicate and Revise: in the existing duplicate/revise tests, add `'frequency' => 3, 'has_sst' => false, 'unit_cost_sen' => 1, 'sub_items' => [...]` to an item and assert the copy has the same values.

- [ ] **Step 2: Run** `tests/Unit/Quotations tests/Feature/Quotations`. Expected: FAIL.

- [ ] **Step 3: Implement.**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Quotation items get the tender-costing fields and their own SST tick; existing items keep their exact price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->unsignedInteger('frequency')->default(1)->after('unit');
            $table->unsignedBigInteger('unit_cost_sen')->default(0)->after('frequency');
            $table->unsignedSmallInteger('margin_bp')->default(2000)->after('unit_cost_sen');
            $table->unsignedBigInteger('unit_price_override_sen')->nullable()->after('margin_bp');
            $table->string('vendor')->nullable()->after('unit_price_sen');
            $table->string('quote_url', 500)->nullable()->after('vendor');
            $table->boolean('has_sst')->default(true)->after('quote_url');
            $table->json('sub_items')->nullable()->after('has_sst');
        });
        DB::table('quotation_items')->update(['unit_price_override_sen' => DB::raw('unit_price_sen')]); // old prices stay exact
        Schema::table('quotations', fn (Blueprint $table) => $table->unsignedSmallInteger('default_margin_bp')->default(2000)->after('sst_bp'));
    }

    public function down(): void
    {
        Schema::table('quotation_items', fn (Blueprint $table) => $table->dropColumn(['frequency', 'unit_cost_sen', 'margin_bp',
            'unit_price_override_sen', 'vendor', 'quote_url', 'has_sst', 'sub_items']));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('default_margin_bp'));
    }
};
```
`QuotationItem` casts:
```php
        return ['position' => 'integer', 'quantity' => 'integer', 'unit_price_sen' => 'integer', 'frequency' => 'integer',
            'unit_cost_sen' => 'integer', 'margin_bp' => 'integer', 'unit_price_override_sen' => 'integer',
            'has_sst' => 'boolean', 'sub_items' => 'array'];
```
plus:
```php
    /** The item as the costing calculator reads it. */
    public function costingLine(): array
    {
        return [
            'quantity' => $this->quantity, 'frequency' => $this->frequency, 'unit_cost_sen' => $this->unit_cost_sen,
            'margin_bp' => $this->margin_bp, 'unit_price_override_sen' => $this->unit_price_override_sen,
            'sub_items' => array_map(fn ($s) => ['quantity' => (int) $s['quantity'], 'unit_cost_sen' => (int) $s['unit_cost_sen']], $this->sub_items ?? []),
        ];
    }
```
`QuotationTotals::of`:
```php
    /** @param list<array{quantity:int, frequency?:int, unit_price_sen:int, has_sst?:bool}> $items (missing frequency = 1, missing has_sst = ticked) */
    public static function of(array $items, int $sstBp): array
    {
        $items = array_values($items);
        $lines = array_map(fn (array $i) => $i['quantity'] * max(1, $i['frequency'] ?? 1) * $i['unit_price_sen'], $items);
        $subtotal = array_sum($lines);
        $taxable = array_sum(array_map(fn (array $i, int $line) => ($i['has_sst'] ?? true) ? $line : 0, $items, $lines));
        $sst = PdCalculator::pct($taxable, $sstBp); // half up to the sen

        return [
            'lines' => $lines,
            'subtotal_sen' => $subtotal,
            'taxable_sen' => $taxable,
            'sst_sen' => $sst,
            'total_sen' => $subtotal + $sst,
            'words' => AmountInWords::ringgit($subtotal + $sst),
            'has_frequency' => collect($items)->contains(fn ($i) => ($i['frequency'] ?? 1) > 1),
        ];
    }
```
`Quotation::totals()` maps `['quantity' => $i->quantity, 'frequency' => $i->frequency, 'unit_price_sen' => $i->unit_price_sen, 'has_sst' => $i->has_sst]`. Add:
```php
    /** Internal profit view: cost, worked-out total, margin and the 18% warning (never on the PDF). */
    public function costing(): array
    {
        return CostingCalculator::summary($this->items->map(fn (QuotationItem $i) => $i->costingLine())->all(), null, null);
    }
```
(with `use App\Costing\CostingCalculator;`).

`UpdateQuotationItem`:
```php
    /** @param array $data title, details, has_sst, plus CostingForm::lineToData() keys (description ignored) */
    public function handle(User $actor, QuotationItem $item, int $expectedVersion, array $data): Quotation
    {
        $title = trim($data['title']);
        if ($title === '' || $data['quantity'] < 1 || $data['frequency'] < 1) {
            throw new InvalidArgumentException('An item needs a title, a quantity of at least 1 and a frequency of at least 1.');
        }
        $price = CostingCalculator::line($data)['price_per_unit_sen'];

        return DB::transaction(function () use ($actor, $item, $expectedVersion, $data, $title, $price) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $item->update([
                'title' => mb_substr($title, 0, 255),
                'details' => trim((string) $data['details']) ?: null,
                'quantity' => $data['quantity'],
                'unit' => trim($data['unit']) ?: 'Unit',
                'frequency' => $data['frequency'],
                'unit_cost_sen' => $data['unit_cost_sen'],
                'margin_bp' => $data['margin_bp'],
                'unit_price_override_sen' => $data['unit_price_override_sen'],
                'unit_price_sen' => $price,                      // the selling price the customer sees, saved as worked out
                'vendor' => $data['vendor'],
                'quote_url' => $data['quote_url'],
                'has_sst' => (bool) $data['has_sst'],
                'sub_items' => $data['sub_items'] ?: null,
            ]);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
```
`AddQuotationItem`: add `'margin_bp' => $q->default_margin_bp, 'frequency' => 1, 'has_sst' => true,` (keep `'unit_price_sen' => 0`).
`DuplicateQuotation` and `ReviseQuotation`:
- the `only([...])` lists become `['position', 'title', 'details', 'quantity', 'unit', 'frequency', 'unit_cost_sen', 'margin_bp', 'unit_price_override_sen', 'unit_price_sen', 'vendor', 'quote_url', 'has_sst', 'sub_items']`
- copy `default_margin_bp` with the quotation's other fields (find where `sst_bp` is copied and add it beside)

`UpdateQuotation::FIELDS`: add `'default_margin_bp'`.
`CreateProjectFromQuotation`: after the Collection line:
```php
            $position = 2;
            foreach ($q->items as $item) {
                $cost = CostingCalculator::line($item->costingLine())['line_cost_sen'];
                if ($cost === 0) {
                    continue; // nothing to budget
                }
                $project->lines()->create([
                    'position' => $position++, 'pd_group' => PdGroup::Principal, 'name' => mb_substr($item->title, 0, 255),
                    'reference' => $item->vendor ? mb_substr($item->vendor, 0, 100) : null, 'budget_sen' => $cost, 'updated_by' => $actor->id,
                ]);
            }
```
  - Update the class docblock to "cost lines come from the items' costs".
  - Make sure `$q` has items loaded (`$q->load('items')` after locking).

`QuotationItemFactory`: add `'frequency' => 1, 'unit_cost_sen' => 0, 'margin_bp' => 2000, 'unit_price_override_sen' => 100000, 'has_sst' => true`.
- Keep `unit_price_sen` 100000.
- Tests that create items with a custom `unit_price_sen` and later save them through `UpdateQuotationItem` must pass the override. Grep for them.

`DatabaseSeeder:121`: add `'unit_price_override_sen' => $priceSen`.

- [ ] **Step 4: Run** `tests/Unit/Quotations tests/Feature/Quotations tests/Feature/Livewire/QuotationScreensTest.php tests/Feature/Reports`. Expected: PASS, except QuotationScreensTest's item-editing test. That test is rewritten in Task 4; if it fails here only on the item save payload, leave it for Task 4 and record a ruling.
- [ ] **Step 5: Commit** `feat: quotation items carry costing, frequency and their own SST tick; accepted quotations budget item costs`

---

### Task 4: Quotation editor — costing fields, sub-items, default margin, summary

**Files:**
- Modify: `app/Livewire/QuotationPage.php`, `resources/views/livewire/quotation-page.blade.php`
- Test: `tests/Feature/Livewire/QuotationScreensTest.php`

**Interfaces — Consumes:**
- `CostingForm::lineRules()`, `lineToData()`, `lineFromModel()`, `blankSubItem()` (Task 1)
- `UpdateQuotationItem` `$data` shape, `Quotation::costing()`, `default_margin_bp` (Task 3)

- [ ] **Step 1: Failing tests** (replace the existing item-editing test that sets `items.i{id}.unit_price`; use the file's setup for a draft quotation `$q` with one item, as its preparer):
```php
it('prices an item from cost and margin, or from a typed price, and shows the profit summary', function () {
    $k = 'i'.$q->items->first()->id;
    $c = Livewire::test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->set("items.$k.title", 'Switch')->set("items.$k.quantity", '2')->set("items.$k.unit_cost", '1,000')->set("items.$k.margin", '20');

    expect($q->items->first()->fresh()->unit_price_sen)->toBe(125000);
    $c->assertSee('Total cost')->assertSee('RM 2,000.00')->assertSee('RM 2,500.00')
        ->set("items.$k.unit_price", '1,100')->assertSet("items.$k.margin", '9.09')->assertSee('Below the 18% company target')
        ->set("items.$k.margin", '20')->assertSet("items.$k.unit_price", '');
    expect($q->items->first()->fresh()->unit_price_sen)->toBe(125000);
});

it('adds sub-items, ticks SST off and sets frequency on an item', function () {
    $k = 'i'.$q->items->first()->id;
    Livewire::test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->call('addSubItem', $q->items->first()->id)
        ->set("items.$k.sub_items.0.description", 'Rack')->set("items.$k.sub_items.0.unit_cost", '600')
        ->set("items.$k.frequency", '12')->set("items.$k.sst", false)
        ->assertSee('SST (8.0%) on items marked *');

    $item = $q->items->first()->fresh();
    expect($item->only(['frequency', 'has_sst']))->toBe(['frequency' => 12, 'has_sst' => false])
        ->and($item->sub_items[0])->toMatchArray(['description' => 'Rack', 'unit_cost_sen' => 60000])
        ->and($q->fresh()->totals()['sst_sen'])->toBe(0);
});

it('saves the default margin used for new items', function () {
    Livewire::test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')->set('form.default_margin', '15')->call('addItem');
    expect($q->fresh()->items->last()->margin_bp)->toBe(1500);
});
```
(Use the SST label exactly as rendered in Step 3. The quotation's `sst_bp` comes from its factory; adjust "8.0%" to `Percent::format($q->sst_bp)` if it differs.)

- [ ] **Step 2: Run** `tests/Feature/Livewire/QuotationScreensTest.php`. Expected: FAIL.

- [ ] **Step 3: Implement.**

`QuotationPage`:
- `load()`:
  - `$this->form['default_margin'] = Percent::toInput($q->default_margin_bp);`
  - items:
```php
            $this->items["i{$i->id}"] = ['title' => $i->title, 'details' => (string) $i->details, 'sst' => $i->has_sst]
                + CostingForm::lineFromModel($i);
```
  - `lineFromModel` reads `$i->sub_items`, the JSON array; `$i->subItems` is null on QuotationItem, so `?? $l->sub_items` applies.
- `formRules()`: add `'form.default_margin' => ['required', new Percentage],`. `saveField` match: add `'default_margin' => ['default_margin_bp' => Percent::parseBp($value)],`. `formAttributes`: add `'form.default_margin' => 'default margin'`.
- `updated()` for items: before `saveItem`, keep the margin and price in step:
```php
        } elseif ($parts[0] === 'items' && isset($parts[1])) {
            $k = $parts[1];
            if (($parts[2] ?? '') === 'margin') {
                $this->items[$k]['unit_price'] = '';
            }
            $this->saveItem((int) substr($k, 1));
            if (in_array($parts[2] ?? '', ['unit_price', 'unit_cost', 'sub_items'], true)) {
                $this->syncMarginFromPrice($k);
            }
        }
```
- `syncMarginFromPrice($k)`: same as in TenderCosting, but sets `$this->items[$k]['margin']`. Do **not** save again; the stored margin is irrelevant while a price is typed, and the next save stores it.
- `saveItem()`:
```php
    private function saveItem(int $id): void
    {
        $k = "i{$id}";
        $rules = CostingForm::lineRules("items.$k");
        unset($rules["items.$k.description"]);
        $this->validate($rules + [
            "items.$k.title" => ['required', 'string', 'max:255'],
            "items.$k.details" => ['nullable', 'string', 'max:2000'],
            "items.$k.sst" => ['boolean'],
        ], [], [
            "items.$k.title" => 'title', "items.$k.quantity" => 'quantity', "items.$k.unit" => 'unit', "items.$k.frequency" => 'frequency',
            "items.$k.unit_cost" => 'unit cost', "items.$k.margin" => 'margin', "items.$k.unit_price" => 'selling price',
            "items.$k.quote_url" => 'quotation link', "items.$k.sub_items.*.description" => 'description',
            "items.$k.sub_items.*.unit_cost" => 'unit cost', "items.$k.sub_items.*.quantity" => 'quantity',
        ]);
        $row = $this->items[$k];
        $data = ['title' => $row['title'], 'details' => $row['details'], 'has_sst' => (bool) $row['sst']]
            + CostingForm::lineToData($row + ['description' => $row['title']], $this->quotation->default_margin_bp, lenient: false);
        $saved = $this->run(fn () => $this->version = app(UpdateQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version, $data)->version);
        if ($saved) {
            $this->dispatch('saved');
        }
    }
```
  - The sub-item description is required by `lineRules`. A freshly added blank sub-item fails validation until it's named; that's expected.
- Sub-item buttons:
```php
    public function addSubItem(int $id): void
    {
        $this->items["i{$id}"]['sub_items'][] = CostingForm::blankSubItem();   // saved once it has a description
    }

    public function removeSubItem(int $id, int $j): void
    {
        unset($this->items["i{$id}"]['sub_items'][$j]);
        $this->items["i{$id}"]['sub_items'] = array_values($this->items["i{$id}"]['sub_items']);
        $this->saveItem($id);
    }
```
- `render()`: add `'costing' => $q->costing(), 'target' => Percent::format(CostingCalculator::COMPANY_TARGET_MARGIN_BP, 0),`.
- Imports: `use App\Costing\{CostingCalculator, CostingForm};`. Remove the now-unused `MoneyAmount` and `Money` if no longer used (check with grep in the file).

View `quotation-page.blade.php`, Items tab:
- **Above the table:** `<label class="mb-3 flex items-center gap-2 text-[13px] font-semibold text-muted">Default margin % <input wire:model.live.blur="form.default_margin" @disabled(! $editable) class="{{ $in }} w-20 font-normal"></label>` with its `@error`.
- **Header:** No · Description · Qty · Unit · Freq. · Unit price (RM) · Amount (RM) · SST · actions.
- **Item row cells:**
  - Qty, Unit (as now)
  - Frequency: `items.$k.frequency`, `w-16`, `aria-label="Frequency"`
  - **Unit price:** `items.$k.unit_price` with `placeholder="{{ Money::toInput($item->unit_price_sen) }}"`, `class="… placeholder:text-ink"`, `aria-label="Unit price"`
  - Amount (as now)
  - **SST:** `<input type="checkbox" wire:model.live="items.{{ $k }}.sst" @disabled(! $editable) aria-label="SST on this item" class="h-4 w-4 accent-[var(--accent-solid)]">`
  - actions + a `+ Sub-item` button (`wire:click="addSubItem({{ $item->id }})"`)
- **A second row per item** (`<tr class="bg-subtle text-xs">`, `colspan` across), headed "Costing (not shown to the customer)", with:
  - **Unit cost:** an input, or the sub-items' total when it has sub-items, using `Money::format(CostingCalculator::line($item->costingLine())['unit_cost_sen'])`
  - **Margin %:** an input, plus the "x% from price · below cost" hint exactly as on tender costing, using `$line = CostingCalculator::line($item->costingLine())`
  - **Vendor** and **Quote link** inputs (`items.$k.vendor`, `items.$k.quote_url`)
  - **one row per sub-item:** description, qty, unit, unit cost, vendor, quote link, Remove (`removeSubItem({{ $item->id }}, {{ $j }})`)
- **Totals box:** the SST line reads `SST ({{ Percent::format($q->sst_bp) }}) on items marked *`.
- **New card "Profit (internal)"** under the totals:
  - Total cost `Money::format($costing['total_cost_sen'])`
  - Quotation subtotal `Money::format($costing['suggested_bid_sen'])`
  - Margin `Money::format($costing['margin_sen'])` · `Percent::format($costing['margin_bp'])`
  - when `$costing['below_target']`: `<p class="text-bad-ink">Below the {{ $target }} company target</p>`

- [ ] **Step 4: Run** `tests/Feature/Livewire/QuotationScreensTest.php tests/Feature/Quotations`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(ui): quotation items priced from cost and margin, with sub-items, SST tick, frequency and a profit summary`

---

### Task 5: Customer PDF — Freq. column and SST markers

**Files:**
- Modify: `resources/views/quotations/pdf.blade.php`
- Test: `tests/Feature/Quotations/QuotationPdfTest.php`

**Interfaces — Consumes:** `QuotationTotals` `has_frequency`, `taxable_sen` (Task 3); `QuotationItem` `frequency`, `has_sst` (Task 3).

- [ ] **Step 1: Failing test.** Read how the file renders the PDF's HTML; it likely renders the Blade view directly with `view('quotations.pdf', …)->render()`. Reuse that.
```php
it('shows frequency only when needed, marks SST items and never shows costs', function () {
    // $q with two items: A (frequency 12, SST ticked, unit_cost_sen 777700, vendor 'SecretVendor'), B (frequency 1, SST off)
    $html = /* render the PDF view for $q as the existing tests do */;

    expect($html)->toContain('Freq.')->toContain('on items marked *')->toContain('*</td>')
        ->not->toContain('SecretVendor')->not->toContain('7,777.00');

    $q->items->each->update(['frequency' => 1]);
    expect(/* render again */)->not->toContain('Freq.');
});
```

- [ ] **Step 2: Run** `tests/Feature/Quotations/QuotationPdfTest.php`. Expected: FAIL.

- [ ] **Step 3: Implement** in `pdf.blade.php`:
- **Header:** after Unit, `@if ($totals['has_frequency']) <th class="right" style="width: 34px">Freq.</th> @endif`.
- **Rows:** after Unit, `@if ($totals['has_frequency']) <td class="right">{{ $item->frequency }}</td> @endif`. The Amount cell becomes `<td class="right">{{ $num($totals['lines'][$i]) }}@if ($item->has_sst) *@endif</td>`.
  - The test's `'*</td>'` needs no space before `</td>`. Write `@if ($item->has_sst)*@endif</td>` and check the output.
- **SST total row:** `<tr><td>SST ({{ Percent::format($q->sst_bp) }}) on items marked *</td><td class="right">{{ $num($totals['sst_sen']) }}</td></tr>`.

- [ ] **Step 4: Run** the PDF tests. Expected: PASS.
- [ ] **Step 5: Commit** `feat(pdf): frequency column when needed, SST marked per item`

---

### Task 6: README, real-data check, browser check

- [ ] **Step 1: README.**
  - Under Costing: frequency is a number; a typed selling price works the margin out backwards; Year and Group are gone; costs go to PD under Principal.
  - Under Quotations: item costing, sub-items, the per-item SST tick with \* on the PDF, the default margin, the profit summary, and that accepted quotations budget item costs under Principal.
- [ ] **Step 2: Before migrating the real local database**, save every costing and quotation total:
```bash
MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan tinker --execute="file_put_contents('/tmp/before.json', json_encode(['tenders' => App\Models\Tender::has('costingLines')->get()->mapWithKeys(fn (\$t) => [\$t->id => \$t->costingSummary()['bid_price_sen']]), 'quotations' => App\Models\Quotation::with('items')->get()->mapWithKeys(fn (\$q) => [\$q->id => \$q->totals()['total_sen']])]));"
```
  Then run `php artisan migrate --force`, recompute the same JSON into `/tmp/after.json`, and compare (`diff`). **Expected: identical.** Record the counts.
- [ ] **Step 3: Full suite** with coverage (it runs on commit). Expected: pass, ≥ 80%.
- [ ] **Step 4: Browser** (built-in browser, local test admin), then reset the viewport:
  - tender costing: typed price → margin; margin → price; frequency 12
  - quotation items: cost/margin/typed price, a sub-item, SST untick, frequency
  - PDF preview: Freq. column and \*
  - 1440 and 375 widths
  - the test draft QTN-2026-0005's totals unchanged
- [ ] **Step 5: Commit** `docs: quotation costing and simpler tender costing`
