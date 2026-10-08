# CMT Tender Hub — Stage 3 Implementation Plan (Costing)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give each pipeline tender a costing (cost lines with sub-items, margin %, frequency, project year), compute the suggested and actual bid price and margin, and make Mark Done use the costing's bid price.

**Architecture:** One pure `CostingCalculator` does all maths on normalised integer data (sen, basis points). `CostingForm` converts between saved rows, on-screen string inputs and calculator input, and holds the validation rules. A `SaveCosting` action writes the whole costing in one transaction with the Stage 1 version check. A `TenderCosting` Livewire component (always mounted inside the tender page, shown on the Costing tab) holds unsaved edits and tells the parent page when it is dirty or saved.

**Tech Stack:** Laravel 13, Livewire 4 (`#[Reactive]`, events), Pest 5, MySQL 8.4.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-stage3-design.md` — read first.

## Global Constraints

- Branch `stage-3`. Commit per task; never commit red; the pre-commit hook runs `pest --coverage --min=80`.
- PHP runs only in Docker: `docker compose exec -T app …`. Write PHP with the editor tool (Git Bash mangles backslashes).
- Money = integer sen; margin % = integer basis points (20% = 2000). No floating point in price maths.
- Price per unit = ceil(unit cost ÷ (1 − margin)) to the whole ringgit: `intdiv(costSen*10000 + den - 1, den) * 100` with `den = (10000 - marginBp) * 100`.
- Company target margin: 1800 bp (18%). Target-cost guide: 1200…2100 bp in steps of 100.
- Quantity ≥ 1 (integer); months ≥ 1 (forced 1 for one-off); project year 1–7; margin 0–9999 bp; links http/https only.
- Costing saves bump `tenders.version` and write one activity row (`costing_saved`).
- Editing allowed only for `update` policy holders while the tender is In Progress (enforced in the action).
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Plain-English UI copy.

## Review Focus

1. **Rounding drift** — prices must match the spreadsheet to the ringgit for every margin (e.g. 18,599 @ 20% → 23,249; exact multiples must not round up). Pinned in Task 2.
2. **Leaving with unsaved costing edits / Mark Done with unsaved edits** — must warn and block. Pinned in Task 5/6.
3. **Costing saved from one tab while the tender changed elsewhere** — refused with "changed by …", edits kept. Pinned in Task 3/5.
4. **Pasted Excel rows with commas inside descriptions, thousands separators, blank lines** — parsed or reported per row, never crash. Pinned in Task 4.
5. **Margin of 100% or more, zero bid price, no estimated value** — no division by zero. Pinned in Task 2.

---

## File Structure

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_08_000001_create_costing_tables.php` | costing tables + tender columns |
| `app/Models/{CostingLine,CostingSubItem}.php`, `database/factories/CostingLineFactory.php` | models |
| `app/Support/Percent.php`, `app/Rules/Percentage.php` | % parsing/formatting and validation |
| `app/Costing/CostingCalculator.php` | all maths |
| `app/Costing/CostingForm.php` | rows ⇄ screen strings ⇄ calculator input; validation rules |
| `app/Costing/CostingImport.php` | paste-from-Excel parsing |
| `app/Actions/Tenders/SaveCosting.php` | save + version + activity |
| `app/Exceptions/CostingRequired.php` | Mark Done without a costing |
| `app/Livewire/TenderCosting.php`, `resources/views/livewire/tender-costing.blade.php` | Costing tab |
| Modified: `Tender` model, `MarkTenderDone`, `TenderDetail` (+ views), `TenderListQuery`, tender-list view, `DatabaseSeeder` | integration |

---

### Task 1: Costing tables, models, percent helpers

**Files:**
- Create: migration, `app/Models/CostingLine.php`, `app/Models/CostingSubItem.php`, `database/factories/CostingLineFactory.php`, `app/Support/Percent.php`, `app/Rules/Percentage.php`
- Modify: `app/Models/Tender.php`
- Test: `tests/Unit/Support/PercentTest.php`, `tests/Feature/Models/CostingModelsTest.php`

**Interfaces:**
- Produces: `Percent::parseBp(?string): ?int` (null for empty; throws `InvalidArgumentException` for non-numbers or >2 decimals), `Percent::format(int $bp, int $decimals = 1): string` (`20.0%`), `new Percentage()` rule (0–9999 bp); `Tender::costingLines()` (ordered, with `subItems`), `CostingLine::subItems()`; `tenders.default_margin_bp` (default 2000), `tenders.bid_price_override_sen`; factory `CostingLine::factory()->for($tender)`.

- [ ] **Step 1: Failing tests**

`tests/Unit/Support/PercentTest.php`:

```php
<?php

use App\Rules\Percentage;
use App\Support\Percent;
use Illuminate\Support\Facades\Validator;

it('parses percentages into basis points', function (string $in, int $bp) {
    expect(Percent::parseBp($in))->toBe($bp);
})->with([['20', 2000], ['20.5', 2050], ['18.25', 1825], [' 7 % ', 700], ['0', 0], ['99.99', 9999]]);

it('treats empty as none and rejects junk', function () {
    expect(Percent::parseBp(''))->toBeNull()->and(Percent::parseBp(null))->toBeNull();
    expect(fn () => Percent::parseBp('abc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Percent::parseBp('1.234'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Percent::parseBp('-5'))->toThrow(InvalidArgumentException::class);
});

it('formats basis points', function () {
    expect(Percent::format(2000))->toBe('20.0%')
        ->and(Percent::format(1825, 2))->toBe('18.25%')
        ->and(Percent::format(-350))->toBe('-3.5%');
});

it('validates margins from 0% to 99.99%', function (string $v, bool $ok) {
    expect(Validator::make(['m' => $v], ['m' => [new Percentage]])->passes())->toBe($ok);
})->with([['20', true], ['0', true], ['99.99', true], ['100', false], ['abc', false]]);
```

`tests/Feature/Models/CostingModelsTest.php`:

```php
<?php

use App\Models\{CostingLine, Tender};

it('stores lines with sub-items in order, and tender costing settings', function () {
    $tender = Tender::factory()->create();
    $b = CostingLine::factory()->for($tender)->create(['position' => 2, 'description' => 'B']);
    $a = CostingLine::factory()->for($tender)->create(['position' => 1, 'description' => 'A']);
    $a->subItems()->create(['position' => 1, 'description' => 'PC', 'unit' => 'unit', 'quantity' => 1, 'unit_cost_sen' => 300000]);

    $fresh = $tender->fresh();
    expect($fresh->costingLines->pluck('description')->all())->toBe(['A', 'B'])
        ->and($fresh->costingLines->first()->subItems->pluck('description')->all())->toBe(['PC'])
        ->and($fresh->default_margin_bp)->toBe(2000)
        ->and($fresh->bid_price_override_sen)->toBeNull();
});

it('deletes lines and sub-items with the tender', function () {
    $tender = Tender::factory()->create();
    CostingLine::factory()->for($tender)->create()->subItems()->create(['position' => 1, 'description' => 'X', 'unit' => 'u', 'quantity' => 1, 'unit_cost_sen' => 1]);

    $tender->delete();

    expect(CostingLine::count())->toBe(0)->and(\App\Models\CostingSubItem::count())->toBe(0);
});
```

- [ ] **Step 2: Run — expect FAIL** (classes missing).

- [ ] **Step 3: Implement**

Migration `2026_10_08_000001_create_costing_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_margin_bp')->default(2000)->after('estimated_value_sen');
            $table->unsignedBigInteger('bid_price_override_sen')->nullable()->after('default_margin_bp');
        });

        Schema::create('costing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->string('unit', 50)->default('unit');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('frequency', 10)->default('one_off');
            $table->unsignedSmallInteger('months')->default(1);
            $table->unsignedTinyInteger('project_year')->default(1);
            $table->unsignedBigInteger('unit_cost_sen')->default(0);
            $table->unsignedSmallInteger('margin_bp')->default(2000);
            $table->string('vendor')->nullable();
            $table->string('quote_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('costing_sub_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->string('unit', 50)->default('unit');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_cost_sen')->default(0);
            $table->string('vendor')->nullable();
            $table->string('quote_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_sub_items');
        Schema::dropIfExists('costing_lines');
        Schema::table('tenders', fn (Blueprint $table) => $table->dropColumn(['default_margin_bp', 'bid_price_override_sen']));
    }
};
```

`app/Models/CostingLine.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CostingLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'months' => 'integer', 'project_year' => 'integer',
            'unit_cost_sen' => 'integer', 'margin_bp' => 'integer', 'position' => 'integer'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function subItems(): HasMany
    {
        return $this->hasMany(CostingSubItem::class)->orderBy('position');
    }
}
```

`app/Models/CostingSubItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostingSubItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost_sen' => 'integer', 'position' => 'integer'];
    }
}
```

`database/factories/CostingLineFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostingLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(), 'position' => 1, 'description' => fake()->words(4, true),
            'unit' => 'unit', 'quantity' => 1, 'frequency' => 'one_off', 'months' => 1, 'project_year' => 1,
            'unit_cost_sen' => 10000000, 'margin_bp' => 2000,
        ];
    }
}
```

`Tender.php` — add casts `'default_margin_bp' => 'integer', 'bid_price_override_sen' => 'integer'` and:

```php
    public function costingLines(): HasMany
    {
        return $this->hasMany(CostingLine::class)->orderBy('position')->with('subItems');
    }
```

`app/Support/Percent.php`:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

final class Percent
{
    public static function parseBp(?string $input): ?int
    {
        $clean = trim(str_replace('%', '', (string) $input));
        if ($clean === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,4})(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Not a valid percentage: {$input}");
        }

        return (int) $m[1] * 100 + (isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0);
    }

    public static function format(int $bp, int $decimals = 1): string
    {
        return number_format($bp / 100, $decimals).'%';
    }

    /** For input boxes: 2000 → "20", 1825 → "18.25". */
    public static function toInput(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2, '.', ''), '0'), '.');
    }
}
```

`app/Rules/Percentage.php`:

```php
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
```

- [ ] **Step 4: Run full suite — PASS.** Also `docker compose exec -T app php artisan migrate --force`.
- [ ] **Step 5: Commit** — `feat: costing tables, models and percentage helpers`

---

### Task 2: CostingCalculator

**Files:** Create `app/Costing/CostingCalculator.php`; Test `tests/Unit/Costing/CostingCalculatorTest.php`

**Interfaces:**
- Consumes: nothing (pure).
- Produces: `CostingCalculator::pricePerUnitSen(int $unitCostSen, int $marginBp): int`; `CostingCalculator::line(array $line): array` → `['unit_cost_sen' (effective), 'line_cost_sen', 'price_per_unit_sen', 'selling_sen']`; `CostingCalculator::summary(array $lines, ?int $overrideSen, ?int $estimatedSen): array` → `lines` (each input merged with `line()`), `total_cost_sen`, `suggested_bid_sen`, `bid_price_sen`, `is_override`, `margin_sen`, `margin_bp`, `below_target`, `under_budget_bp` (?int), `guide` (list of `['margin_bp','max_cost_sen']`), `cost_by_year` (array year ⇒ sen, ksorted). Constant `COMPANY_TARGET_MARGIN_BP = 1800`.
- Line input shape: `['quantity'=>int,'frequency'=>'one_off'|'monthly','months'=>int,'project_year'=>int,'unit_cost_sen'=>int,'margin_bp'=>int,'sub_items'=>[['quantity'=>int,'unit_cost_sen'=>int], …]]`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Costing\CostingCalculator as C;

function oneOff(int $costSen, int $bp = 2000, int $qty = 1, int $year = 1): array
{
    return ['quantity' => $qty, 'frequency' => 'one_off', 'months' => 1, 'project_year' => $year,
        'unit_cost_sen' => $costSen, 'margin_bp' => $bp, 'sub_items' => []];
}

it('rounds the price per unit up to the whole ringgit, exactly', function (int $cost, int $bp, int $price) {
    expect(C::pricePerUnitSen($cost, $bp))->toBe($price);
})->with([
    [1859900, 2000, 2324900],  // 18,599 @ 20% = 23,248.75 → 23,249
    [800000, 2000, 1000000],   // exact: 8,000 @ 20% = 10,000 (no extra ringgit)
    [100, 0, 100],             // 0% margin: price = cost
    [1, 2000, 100],            // 1 sen still rounds up to RM 1
    [0, 2000, 0],
    [1000000, 9999, 10000000000], // 99.99%
]);

it('reproduces the prototype JPNIN costing to the sen', function () {
    $lines = array_map(fn ($l) => oneOff($l['unit_cost_sen']),
        json_decode(file_get_contents(base_path('docs/superpowers/plans/assets/jpnin-costing.json')), true));

    $s = C::summary($lines, null, 17480000);

    expect($s['total_cost_sen'])->toBe(13284500)
        ->and($s['suggested_bid_sen'])->toBe(16605900)
        ->and($s['bid_price_sen'])->toBe(16605900)
        ->and($s['margin_sen'])->toBe(3321400)
        ->and($s['margin_bp'])->toBe(2000)
        ->and($s['below_target'])->toBeFalse()
        ->and($s['under_budget_bp'])->toBe(500); // (174,800 − 166,059) ÷ 174,800 = 5.0%
});

it('uses sub-items as the cost of one unit, and multiplies monthly lines', function () {
    $line = ['quantity' => 20, 'frequency' => 'monthly', 'months' => 12, 'project_year' => 2, 'unit_cost_sen' => 999,
        'margin_bp' => 2000, 'sub_items' => [['quantity' => 1, 'unit_cost_sen' => 300000], ['quantity' => 2, 'unit_cost_sen' => 40000]]];

    expect(C::line($line))->toBe([
        'unit_cost_sen' => 380000,                 // 3,000 + 2 × 400 per set
        'line_cost_sen' => 20 * 380000 * 12,
        'price_per_unit_sen' => 475000,            // 3,800 ÷ 0.8
        'selling_sen' => 20 * 475000 * 12,
    ]);
});

it('treats one-off lines as one month whatever months says', function () {
    expect(C::line(oneOff(100000) + ['months' => 9])['line_cost_sen'])->toBe(100000);
});

it('uses the override as the bid price and recomputes the margin from it', function () {
    $s = C::summary([oneOff(10000000)], 11000000, null);

    expect($s['suggested_bid_sen'])->toBe(12500000)
        ->and($s['bid_price_sen'])->toBe(11000000)
        ->and($s['is_override'])->toBeTrue()
        ->and($s['margin_sen'])->toBe(1000000)
        ->and($s['margin_bp'])->toBe(909)
        ->and($s['below_target'])->toBeTrue()
        ->and($s['under_budget_bp'])->toBeNull();
});

it('handles a loss, an empty costing and a zero bid without dividing by zero', function () {
    expect(C::summary([oneOff(10000000)], 5000000, 0)['margin_bp'])->toBe(-10000)
        ->and(C::summary([], null, 100)['margin_bp'])->toBe(0)
        ->and(C::summary([], null, 100)['bid_price_sen'])->toBe(0)
        ->and(C::summary([oneOff(0)], null, null)['margin_bp'])->toBe(0);
});

it('reports over-budget bids as negative under-budget', function () {
    expect(C::summary([oneOff(10000000)], null, 10000000)['under_budget_bp'])->toBe(-2500);
});

it('lists the most you can spend for 12% to 21% margin', function () {
    $guide = C::summary([oneOff(10000000)], null, null)['guide']; // bid 125,000

    expect(count($guide))->toBe(10)
        ->and($guide[0])->toBe(['margin_bp' => 1200, 'max_cost_sen' => 11000000])
        ->and($guide[6])->toBe(['margin_bp' => 1800, 'max_cost_sen' => 10250000])
        ->and($guide[9])->toBe(['margin_bp' => 2100, 'max_cost_sen' => 9875000]);
});

it('totals cost by project year', function () {
    $s = C::summary([oneOff(100, year: 3), oneOff(200, year: 1), oneOff(300, year: 3)], null, null);

    expect($s['cost_by_year'])->toBe([1 => 200, 3 => 400]);
});
```

- [ ] **Step 2: Run — FAIL.**

- [ ] **Step 3: Implement `app/Costing/CostingCalculator.php`**

```php
<?php

namespace App\Costing;

/** All costing maths, in integer sen and basis points (1% = 100 bp). No floating point. */
final class CostingCalculator
{
    public const COMPANY_TARGET_MARGIN_BP = 1800;

    /** cost ÷ (1 − margin), rounded up to the whole ringgit, like the Excel sheet. */
    public static function pricePerUnitSen(int $unitCostSen, int $marginBp): int
    {
        $den = (10000 - $marginBp) * 100;

        return intdiv($unitCostSen * 10000 + $den - 1, $den) * 100;
    }

    public static function line(array $l): array
    {
        $unitCost = ($l['sub_items'] ?? []) !== []
            ? array_sum(array_map(fn ($s) => $s['quantity'] * $s['unit_cost_sen'], $l['sub_items']))
            : $l['unit_cost_sen'];
        $months = $l['frequency'] === 'monthly' ? max(1, $l['months']) : 1;
        $price = self::pricePerUnitSen($unitCost, $l['margin_bp']);

        return [
            'unit_cost_sen' => $unitCost,
            'line_cost_sen' => $l['quantity'] * $unitCost * $months,
            'price_per_unit_sen' => $price,
            'selling_sen' => $price * $l['quantity'] * $months,
        ];
    }

    public static function summary(array $lines, ?int $overrideSen, ?int $estimatedSen): array
    {
        $computed = array_map(fn ($l) => $l + self::line($l), $lines);
        $totalCost = array_sum(array_column($computed, 'line_cost_sen'));
        $suggested = array_sum(array_column($computed, 'selling_sen'));
        $bid = $overrideSen ?? $suggested;
        $margin = $bid - $totalCost;
        $marginBp = $bid > 0 ? (int) round($margin * 10000 / $bid) : 0;

        $byYear = [];
        foreach ($computed as $l) {
            $byYear[$l['project_year']] = ($byYear[$l['project_year']] ?? 0) + $l['line_cost_sen'];
        }
        ksort($byYear);

        return [
            'lines' => $computed,
            'total_cost_sen' => $totalCost,
            'suggested_bid_sen' => $suggested,
            'bid_price_sen' => $bid,
            'is_override' => $overrideSen !== null,
            'margin_sen' => $margin,
            'margin_bp' => $marginBp,
            'below_target' => $bid > 0 && $marginBp < self::COMPANY_TARGET_MARGIN_BP,
            'under_budget_bp' => $estimatedSen ? (int) round(($estimatedSen - $bid) * 10000 / $estimatedSen) : null,
            'guide' => array_map(fn ($m) => ['margin_bp' => $m, 'max_cost_sen' => intdiv($bid * (10000 - $m), 10000)], range(1200, 2100, 100)),
            'cost_by_year' => $byYear,
        ];
    }
}
```

- [ ] **Step 4: Run — PASS.** (If JPNIN differs by any sen, the rounding formula is wrong — fix the code, not the test.)
- [ ] **Step 5: Commit** — `feat: costing calculator matching the Excel sheet to the sen`

---

### Task 3: CostingForm and the SaveCosting action

**Files:** Create `app/Costing/CostingForm.php`, `app/Actions/Tenders/SaveCosting.php`; Modify `app/Models/Tender.php` (`costingSummary()`, `hasCosting()`); Test `tests/Feature/Costing/SaveCostingTest.php`

**Interfaces:**
- Produces:
  - `CostingForm::blankLine(int $defaultBp): array` (screen strings), `CostingForm::blankSubItem(): array`, `CostingForm::fromTender(Tender): array{defaultMargin:string, override:string, lines:list<array>}`, `CostingForm::rules(): array` (keys `defaultMargin`, `override`, `lines.*.…`, `lines.*.sub_items.*.…`), `CostingForm::toData(array $state, bool $lenient = false): array{default_margin_bp:int, bid_price_override_sen:?int, lines:list<array>}` (lenient: unparseable numbers → 0/defaults, used for live totals).
  - Screen line keys: `description, unit, quantity, frequency, months, project_year, unit_cost, margin, vendor, quote_url, sub_items` (sub: `description, unit, quantity, unit_cost, vendor, quote_url`), all strings except `sub_items`.
  - `SaveCosting::handle(User $actor, Tender $tender, int $expectedVersion, array $data): Tender` where `$data` is `toData()` output.
  - `Tender::costingSummary(): ?array` (null when no lines), `Tender::hasCosting(): bool`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Actions\Tenders\SaveCosting;
use App\Costing\CostingForm;
use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

function costingState(array $o = []): array
{
    $line = array_merge(CostingForm::blankLine(2000), ['description' => 'Server', 'unit_cost' => '100,000'], $o);

    return ['defaultMargin' => '20', 'override' => '', 'lines' => [$line]];
}

function picTender(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);

    return [$pic, Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'estimated_value_sen' => 15000000], $attrs))];
}

it('converts screen input to saved data', function () {
    $state = costingState(['quantity' => '2', 'frequency' => 'monthly', 'months' => '6', 'project_year' => '3', 'margin' => '18.5',
        'sub_items' => [array_merge(CostingForm::blankSubItem(), ['description' => 'PC', 'quantity' => '1', 'unit_cost' => '3,000'])]]);
    $state['override'] = 'RM 130,000';

    $data = CostingForm::toData($state);

    expect($data['default_margin_bp'])->toBe(2000)
        ->and($data['bid_price_override_sen'])->toBe(13000000)
        ->and($data['lines'][0])->toMatchArray(['quantity' => 2, 'frequency' => 'monthly', 'months' => 6, 'project_year' => 3,
            'unit_cost_sen' => 10000000, 'margin_bp' => 1850, 'description' => 'Server'])
        ->and($data['lines'][0]['sub_items'][0])->toMatchArray(['description' => 'PC', 'quantity' => 1, 'unit_cost_sen' => 300000]);
});

it('forces months to 1 for one-off lines, and tolerates junk in lenient mode', function () {
    expect(CostingForm::toData(costingState(['months' => '9']))['lines'][0]['months'])->toBe(1);
    $lenient = CostingForm::toData(costingState(['unit_cost' => 'abc', 'quantity' => 'x', 'margin' => '??']), lenient: true)['lines'][0];
    expect($lenient)->toMatchArray(['unit_cost_sen' => 0, 'quantity' => 1, 'margin_bp' => 2000]);
});

it('validates every field', function (array $bad, string $key) {
    $v = Validator::make(costingState($bad), CostingForm::rules());
    expect($v->errors()->has($key))->toBeTrue();
})->with([
    [['description' => ''], 'lines.0.description'],
    [['quantity' => '0'], 'lines.0.quantity'],
    [['months' => '0', 'frequency' => 'monthly'], 'lines.0.months'],
    [['project_year' => '8'], 'lines.0.project_year'],
    [['unit_cost' => '-5'], 'lines.0.unit_cost'],
    [['margin' => '100'], 'lines.0.margin'],
    [['quote_url' => 'javascript:alert(1)'], 'lines.0.quote_url'],
    [['frequency' => 'weekly'], 'lines.0.frequency'],
]);

it('saves the whole costing, replacing what was there, and logs it', function () {
    [$pic, $tender] = picTender();
    $save = app(SaveCosting::class);

    $t = $save->handle($pic, $tender, 1, CostingForm::toData(costingState(['description' => 'Old'])));
    $t = $save->handle($pic, $t, 2, CostingForm::toData(costingState()));

    expect($t->version)->toBe(3)
        ->and($t->costingLines->pluck('description')->all())->toBe(['Server'])
        ->and($t->hasCosting())->toBeTrue()
        ->and($t->costingSummary()['bid_price_sen'])->toBe(12500000)
        ->and($t->activity->first()->description)->toBe('Costing saved — bid price RM 125,000.00, margin 20.0%');
});

it('saves sub-items and the override', function () {
    [$pic, $tender] = picTender();
    $state = costingState(['sub_items' => [array_merge(CostingForm::blankSubItem(), ['description' => 'PC', 'unit_cost' => '3,000'])]]);
    $state['override'] = '9000';

    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData($state));

    expect($t->bid_price_override_sen)->toBe(900000)
        ->and($t->costingLines->first()->subItems->pluck('unit_cost_sen')->all())->toBe([300000])
        ->and($t->costingSummary()['total_cost_sen'])->toBe(300000);
});

it('round-trips saved rows back to the screen', function () {
    [$pic, $tender] = picTender();
    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData(costingState(['margin' => '18.5'])));

    $state = CostingForm::fromTender($t);
    expect($state['defaultMargin'])->toBe('20')
        ->and($state['lines'][0])->toMatchArray(['description' => 'Server', 'unit_cost' => '100000.00', 'margin' => '18.5', 'quantity' => '1']);
});

it('refuses staff who are not the PIC, closed tenders, and out-of-date pages', function () {
    [$pic, $tender] = picTender();
    $data = CostingForm::toData(costingState());

    expect(fn () => app(SaveCosting::class)->handle(User::factory()->create(), $tender, 1, $data))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveCosting::class)->handle($pic, $tender, 9, $data))->toThrow(StaleTenderException::class);

    $tender->update(['status' => TenderStatus::Done]);
    expect(fn () => app(SaveCosting::class)->handle($pic, $tender, 1, $data))->toThrow(InvalidTenderTransition::class);
});

it('has no costing until there is a line and a bid price above zero', function () {
    [, $tender] = picTender();
    expect($tender->hasCosting())->toBeFalse()->and($tender->costingSummary())->toBeNull();

    \App\Models\CostingLine::factory()->for($tender)->create(['unit_cost_sen' => 0]);
    expect($tender->fresh()->hasCosting())->toBeFalse();
});
```

- [ ] **Step 2: Run — FAIL.**

- [ ] **Step 3: Implement**

`app/Costing/CostingForm.php`:

```php
<?php

namespace App\Costing;

use App\Models\Tender;
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
use InvalidArgumentException;

/** Converts between saved costing rows, on-screen string inputs and calculator/saver data. */
final class CostingForm
{
    public static function blankLine(int $defaultBp): array
    {
        return ['description' => '', 'unit' => 'unit', 'quantity' => '1', 'frequency' => 'one_off', 'months' => '1',
            'project_year' => '1', 'unit_cost' => '0', 'margin' => Percent::toInput($defaultBp),
            'vendor' => '', 'quote_url' => '', 'sub_items' => []];
    }

    public static function blankSubItem(): array
    {
        return ['description' => '', 'unit' => 'unit', 'quantity' => '1', 'unit_cost' => '0', 'vendor' => '', 'quote_url' => ''];
    }

    public static function fromTender(Tender $t): array
    {
        return [
            'defaultMargin' => Percent::toInput($t->default_margin_bp),
            'override' => Money::toInput($t->bid_price_override_sen),
            'lines' => $t->costingLines->map(fn ($l) => [
                'description' => $l->description, 'unit' => $l->unit, 'quantity' => (string) $l->quantity,
                'frequency' => $l->frequency, 'months' => (string) $l->months, 'project_year' => (string) $l->project_year,
                'unit_cost' => Money::toInput($l->unit_cost_sen), 'margin' => Percent::toInput($l->margin_bp),
                'vendor' => (string) $l->vendor, 'quote_url' => (string) $l->quote_url,
                'sub_items' => $l->subItems->map(fn ($s) => [
                    'description' => $s->description, 'unit' => $s->unit, 'quantity' => (string) $s->quantity,
                    'unit_cost' => Money::toInput($s->unit_cost_sen), 'vendor' => (string) $s->vendor, 'quote_url' => (string) $s->quote_url,
                ])->all(),
            ])->all(),
        ];
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
            'lines.*.sub_items' => ['array'],
            ...$common('lines.*.sub_items.*'),
        ];
    }

    public static function attributes(): array
    {
        return ['lines.*.description' => 'description', 'lines.*.quantity' => 'quantity', 'lines.*.months' => 'months',
            'lines.*.project_year' => 'year', 'lines.*.unit_cost' => 'unit cost', 'lines.*.margin' => 'margin',
            'lines.*.quote_url' => 'quotation link', 'lines.*.sub_items.*.description' => 'description',
            'lines.*.sub_items.*.quantity' => 'quantity', 'lines.*.sub_items.*.unit_cost' => 'unit cost',
            'lines.*.sub_items.*.quote_url' => 'quotation link', 'defaultMargin' => 'default margin', 'override' => 'bid price'];
    }

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
                    'unit' => trim((string) ($l['unit'] ?? 'unit')) ?: 'unit',
                    'quantity' => self::int($l['quantity'] ?? '1', 1, 1),
                    'frequency' => $frequency,
                    'months' => $frequency === 'monthly' ? self::int($l['months'] ?? '1', 1, 1) : 1,
                    'project_year' => min(7, self::int($l['project_year'] ?? '1', 1, 1)),
                    'unit_cost_sen' => self::sen($l['unit_cost'] ?? '0', 0, $lenient) ?? 0,
                    'margin_bp' => self::bp($l['margin'] ?? '', $default, $lenient),
                    'vendor' => trim((string) ($l['vendor'] ?? '')) ?: null,
                    'quote_url' => trim((string) ($l['quote_url'] ?? '')) ?: null,
                    'sub_items' => array_map(fn (array $s) => [
                        'description' => trim((string) ($s['description'] ?? '')),
                        'unit' => trim((string) ($s['unit'] ?? 'unit')) ?: 'unit',
                        'quantity' => self::int($s['quantity'] ?? '1', 1, 1),
                        'unit_cost_sen' => self::sen($s['unit_cost'] ?? '0', 0, $lenient) ?? 0,
                        'vendor' => trim((string) ($s['vendor'] ?? '')) ?: null,
                        'quote_url' => trim((string) ($s['quote_url'] ?? '')) ?: null,
                    ], array_values($l['sub_items'] ?? [])),
                ];
            }, array_values($state['lines'] ?? [])),
        ];
    }

    private static function int(string $v, int $fallback, int $min): int
    {
        return ctype_digit(trim($v)) ? max($min, (int) trim($v)) : $fallback;
    }

    private static function sen(string $v, ?int $fallback, bool $lenient): ?int
    {
        try {
            return Money::parse($v);
        } catch (InvalidArgumentException $e) {
            if ($lenient) {
                return $fallback;
            }
            throw $e;
        }
    }

    private static function bp(string $v, int $fallback, bool $lenient): int
    {
        try {
            $bp = Percent::parseBp($v);

            return $bp === null || $bp > 9999 ? $fallback : $bp;
        } catch (InvalidArgumentException $e) {
            if ($lenient) {
                return $fallback;
            }
            throw $e;
        }
    }
}
```

`app/Actions/Tenders/SaveCosting.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use App\Support\{Money, Percent};
use Illuminate\Support\Facades\DB;

final class SaveCosting
{
    use GuardsTender;

    /** @param array{default_margin_bp:int, bid_price_override_sen:?int, lines:list<array>} $data from CostingForm::toData() */
    public function handle(User $actor, Tender $tender, int $expectedVersion, array $data): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $data) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the costing of');

            $t->costingLines()->delete(); // sub-items cascade
            foreach ($data['lines'] as $i => $line) {
                $saved = $t->costingLines()->create(['position' => $i + 1] + collect($line)->except('sub_items')->all());
                foreach ($line['sub_items'] as $j => $sub) {
                    $saved->subItems()->create(['position' => $j + 1] + $sub);
                }
            }
            $t->forceFill([
                'default_margin_bp' => $data['default_margin_bp'],
                'bid_price_override_sen' => $data['bid_price_override_sen'],
                'version' => $t->version + 1,
            ])->save();

            $summary = $t->fresh()->costingSummary();
            ActivityLog::record($t, $actor, 'costing_saved', $summary
                ? 'Costing saved — bid price '.Money::format($summary['bid_price_sen']).', margin '.Percent::format($summary['margin_bp'])
                : 'Costing cleared');

            return $t->fresh();
        });
    }
}
```

`Tender.php` — add:

```php
    public function costingSummary(): ?array
    {
        $lines = $this->costingLines;
        if ($lines->isEmpty()) {
            return null;
        }

        return \App\Costing\CostingCalculator::summary(
            $lines->map(fn ($l) => [
                'quantity' => $l->quantity, 'frequency' => $l->frequency, 'months' => $l->months,
                'project_year' => $l->project_year, 'unit_cost_sen' => $l->unit_cost_sen, 'margin_bp' => $l->margin_bp,
                'sub_items' => $l->subItems->map(fn ($s) => ['quantity' => $s->quantity, 'unit_cost_sen' => $s->unit_cost_sen])->all(),
            ])->all(),
            $this->bid_price_override_sen,
            $this->estimated_value_sen,
        );
    }

    public function hasCosting(): bool
    {
        return ($this->costingSummary()['bid_price_sen'] ?? 0) > 0;
    }
```

- [ ] **Step 4: Run full suite — PASS.**
- [ ] **Step 5: Commit** — `feat: costing form conversion and SaveCosting action`

---

### Task 4: Bulk import from Excel

**Files:** Create `app/Costing/CostingImport.php`; Test `tests/Unit/Costing/CostingImportTest.php`

**Interfaces:** `CostingImport::parse(string $text): array{rows: list<array{description:string,quantity:string,unit:string,unit_cost:string}>, errors: list<string>}` — rows are screen strings ready to merge into `CostingForm::blankLine()`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Costing\CostingImport;

it('reads tab-separated rows copied from Excel', function () {
    $r = CostingImport::parse("Server rack\t2\tunit\t12,500.00\nLicence, annual\t1\tlot\t3000");

    expect($r['errors'])->toBe([])
        ->and($r['rows'])->toBe([
            ['description' => 'Server rack', 'quantity' => '2', 'unit' => 'unit', 'unit_cost' => '12500.00'],
            ['description' => 'Licence, annual', 'quantity' => '1', 'unit' => 'lot', 'unit_cost' => '3000.00'],
        ]);
});

it('reads comma-separated rows, keeping commas inside the description', function () {
    $r = CostingImport::parse('Supply, deliver and install switch, 4, unit, 1500');

    expect($r['rows'][0])->toBe(['description' => 'Supply, deliver and install switch', 'quantity' => '4', 'unit' => 'unit', 'unit_cost' => '1500.00']);
});

it('skips blank lines and reports bad rows by number without stopping', function () {
    $r = CostingImport::parse("Good\t1\tunit\t10\n\nNo qty\tx\tunit\t10\nNo cost\t1\tunit\tabc\nToo short\t1");

    expect($r['rows'])->toHaveCount(1)
        ->and($r['errors'])->toBe([
            'Row 3: quantity must be a whole number of at least 1.',
            'Row 4: unit cost is not a valid amount.',
            'Row 5: needs description, quantity, unit and unit cost.',
        ]);
});
```

- [ ] **Step 2: Run — FAIL.**

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Costing;

use App\Support\Money;
use InvalidArgumentException;

/** Rows pasted from Excel: description, quantity, unit, unit cost (tab- or comma-separated). */
final class CostingImport
{
    public static function parse(string $text): array
    {
        $rows = [];
        $errors = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $raw) {
            $n = $i + 1;
            if (trim($raw) === '') {
                continue;
            }
            $parts = str_contains($raw, "\t") ? explode("\t", $raw) : explode(',', $raw);
            if (! str_contains($raw, "\t") && count($parts) > 4) {
                $parts = [implode(',', array_slice($parts, 0, -3)), ...array_slice($parts, -3)];
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
            $rows[] = ['description' => $description, 'quantity' => $quantity, 'unit' => $unit !== '' ? $unit : 'unit', 'unit_cost' => Money::toInput($sen)];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
```

- [ ] **Step 4: Run — PASS.**
- [ ] **Step 5: Commit** — `feat: paste costing lines from Excel`

---

### Task 5: The Costing tab

**Files:** Create `app/Livewire/TenderCosting.php`, `resources/views/livewire/tender-costing.blade.php`; Modify `resources/views/livewire/tender-detail.blade.php`, `app/Livewire/TenderDetail.php` (listeners); Test `tests/Feature/Livewire/TenderCostingTest.php`

**Interfaces:**
- Consumes: `CostingForm`, `CostingCalculator`, `CostingImport`, `SaveCosting`.
- Produces: Livewire `tender-costing` with props `Tender $tender`, `#[Reactive] int $version`, `bool $canEdit`; public state `defaultMargin, override, lines, dirty, importText, importErrors, showImport, conflict`; methods `addLine, removeLine(int), moveLine(int, int $dir), addSubItem(int), removeSubItem(int,int), applyDefaultToAll, resetOverride, import, save`. Dispatches `costing-dirty` (`dirty: bool`) and `costing-saved` (`version: int`). `TenderDetail` listens: `#[On('costing-dirty')] costingDirty(bool $dirty)` sets `public bool $costingDirty`; `#[On('costing-saved')] costingSaved(int $version)` sets `$this->version` and refreshes `$this->tender`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Livewire\{TenderCosting, TenderDetail};
use App\Models\{CostingLine, Tender, User};
use Livewire\Livewire;

function costingFixture(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'estimated_value_sen' => 15000000], $attrs));

    return [$pic, $tender];
}

function costingComponent(User $u, Tender $t)
{
    return Livewire::actingAs($u)->test(TenderCosting::class, ['tender' => $t, 'version' => $t->version, 'canEdit' => true]);
}

it('adds lines, shows live totals and marks itself unsaved', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')
        ->set('lines.0.description', 'Server')
        ->set('lines.0.unit_cost', '100,000')
        ->assertSet('dirty', true)
        ->assertDispatched('costing-dirty', dirty: true)
        ->assertSee('RM 125,000.00')   // suggested bid
        ->assertSee('20.0%')
        ->assertSee('Unsaved changes');
});

it('saves, tells the page its new version, and logs it', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '100000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('dirty', false)
        ->assertDispatched('costing-saved', version: 2);

    expect($tender->fresh()->costingSummary()['bid_price_sen'])->toBe(12500000);
});

it('shows validation errors beside the fields and saves nothing', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.quantity', '0')->set('lines.0.margin', '120')
        ->call('save')
        ->assertHasErrors(['lines.0.description', 'lines.0.quantity', 'lines.0.margin']);

    expect($tender->fresh()->costingLines)->toHaveCount(0);
});

it('handles sub-items, reordering and removal', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Set')
        ->call('addLine')->set('lines.1.description', 'Second')
        ->call('addSubItem', 0)->set('lines.0.sub_items.0.description', 'PC')->set('lines.0.sub_items.0.unit_cost', '3000')
        ->assertSee('RM 3,000.00')                 // line unit cost now from sub-items
        ->call('moveLine', 1, -1)
        ->assertSet('lines.0.description', 'Second')
        ->call('removeSubItem', 1, 0)
        ->assertCount('lines.1.sub_items', 0)
        ->call('removeLine', 0)
        ->assertCount('lines', 1);
});

it('applies the default margin to every line', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->call('addLine')->set('lines.1.margin', '30')
        ->set('defaultMargin', '15')
        ->call('applyDefaultToAll')
        ->assertSet('lines.0.margin', '15')->assertSet('lines.1.margin', '15');
});

it('overrides the bid price and resets back to the suggested one', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '100000')
        ->set('override', '110,000')
        ->assertSee('Your price')->assertSee('Below the 18% target')
        ->call('resetOverride')
        ->assertSet('override', '')->assertDontSee('Below the 18% target');
});

it('imports pasted rows and reports bad ones', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->set('importText', "Router\t2\tunit\t1,000\nBad\tx\tunit\t1")
        ->call('import')
        ->assertCount('lines', 1)
        ->assertSet('lines.0.description', 'Router')
        ->assertSet('lines.0.margin', '20')
        ->assertSee('Row 2: quantity must be a whole number of at least 1.');
});

it('loads the saved costing and is read-only when you cannot edit or the tender is closed', function () {
    [$pic, $tender] = costingFixture();
    CostingLine::factory()->for($tender)->create(['description' => 'Saved line']);

    Livewire::actingAs($pic)->test(TenderCosting::class, ['tender' => $tender->fresh(), 'version' => 1, 'canEdit' => false])
        ->assertSee('Saved line')->assertDontSee('+ Add line')->assertDontSee('Save costing');

    $other = User::factory()->create();
    Livewire::actingAs($other)->test(TenderCosting::class, ['tender' => $tender->fresh(), 'version' => 1, 'canEdit' => true])
        ->call('save')->assertForbidden(); // canEdit is a display hint only; the action checks permission
});

it('keeps your edits and explains when someone else saved first', function () {
    [$pic, $tender] = costingFixture();
    $c = costingComponent($pic, $tender)->call('addLine')->set('lines.0.description', 'Mine')->set('lines.0.unit_cost', '10');
    app(\App\Actions\Tenders\UpdateTender::class)->handle(User::factory()->manager()->create(['name' => 'Ahmad Faizal']), $tender, 1, ['title' => 'X']);

    $c->call('save')->assertSee('This tender was changed by Ahmad Faizal')->assertSet('lines.0.description', 'Mine');
});

it('shows the Costing tab on the tender page and tracks unsaved edits', function () {
    [$pic, $tender] = costingFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('Costing')
        ->dispatch('costing-dirty', dirty: true)->assertSet('costingDirty', true)
        ->dispatch('costing-saved', version: 5)->assertSet('version', 5)->assertSet('costingDirty', false);
});
```

- [ ] **Step 2: Run — FAIL.**

- [ ] **Step 3: Implement `app/Livewire/TenderCosting.php`**

```php
<?php

namespace App\Livewire;

use App\Actions\Tenders\SaveCosting;
use App\Costing\{CostingCalculator, CostingForm, CostingImport};
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\Tender;
use App\Support\Percent;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class TenderCosting extends Component
{
    public Tender $tender;
    #[Reactive] public int $version;
    public bool $canEdit = false;

    public string $defaultMargin = '20';
    public string $override = '';
    public array $lines = [];
    public bool $dirty = false;
    public string $importText = '';
    public array $importErrors = [];
    public bool $showImport = false;
    public ?string $conflict = null;

    public function mount(): void
    {
        $this->loadSaved();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['importText', 'showImport'], true)) {
            $this->markDirty();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = CostingForm::blankLine($this->defaultBp());
        $this->markDirty();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        $this->markDirty();
    }

    public function moveLine(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (isset($this->lines[$i], $this->lines[$j])) {
            [$this->lines[$i], $this->lines[$j]] = [$this->lines[$j], $this->lines[$i]];
            $this->markDirty();
        }
    }

    public function addSubItem(int $i): void
    {
        $this->lines[$i]['sub_items'][] = CostingForm::blankSubItem();
        $this->markDirty();
    }

    public function removeSubItem(int $i, int $j): void
    {
        unset($this->lines[$i]['sub_items'][$j]);
        $this->lines[$i]['sub_items'] = array_values($this->lines[$i]['sub_items']);
        $this->markDirty();
    }

    public function applyDefaultToAll(): void
    {
        $this->validateOnly('defaultMargin', CostingForm::rules(), [], CostingForm::attributes());
        foreach ($this->lines as $i => $_) {
            $this->lines[$i]['margin'] = $this->defaultMargin;
        }
        $this->markDirty();
    }

    public function resetOverride(): void
    {
        $this->override = '';
        $this->markDirty();
    }

    public function import(): void
    {
        $parsed = CostingImport::parse($this->importText);
        foreach ($parsed['rows'] as $row) {
            $this->lines[] = array_merge(CostingForm::blankLine($this->defaultBp()), $row);
        }
        $this->importErrors = $parsed['errors'];
        $this->importText = '';
        if ($parsed['rows'] !== []) {
            $this->markDirty();
        }
        $this->showImport = $parsed['errors'] !== [];
    }

    public function save(): void
    {
        $this->validate(CostingForm::rules(), [], CostingForm::attributes());
        try {
            $fresh = app(SaveCosting::class)->handle(auth()->user(), $this->tender, $this->version,
                CostingForm::toData(['defaultMargin' => $this->defaultMargin, 'override' => $this->override, 'lines' => $this->lines]));
        } catch (StaleTenderException|InvalidTenderTransition $e) {
            $this->conflict = $e->getMessage(); // edits stay on screen

            return;
        }
        $this->tender = $fresh;
        $this->conflict = null;
        $this->dirty = false;
        $this->dispatch('costing-dirty', dirty: false);
        $this->dispatch('costing-saved', version: $fresh->version);
    }

    private function loadSaved(): void
    {
        $state = CostingForm::fromTender($this->tender);
        $this->defaultMargin = $state['defaultMargin'];
        $this->override = $state['override'];
        $this->lines = $state['lines'];
    }

    private function markDirty(): void
    {
        if (! $this->dirty) {
            $this->dirty = true;
            $this->dispatch('costing-dirty', dirty: true);
        }
    }

    private function defaultBp(): int
    {
        return CostingForm::toData(['defaultMargin' => $this->defaultMargin, 'lines' => []], lenient: true)['default_margin_bp'];
    }

    public function render()
    {
        $data = CostingForm::toData(['defaultMargin' => $this->defaultMargin, 'override' => $this->override, 'lines' => $this->lines], lenient: true);

        return view('livewire.tender-costing', [
            'summary' => CostingCalculator::summary($data['lines'], $data['bid_price_override_sen'], $this->tender->estimated_value_sen),
            'editable' => $this->canEdit && ! $this->tender->isLocked(),
            'target' => Percent::format(CostingCalculator::COMPANY_TARGET_MARGIN_BP, 0),
            'vendors' => \App\Models\CostingLine::query()->whereNotNull('vendor')->distinct()->orderBy('vendor')->limit(200)->pluck('vendor'),
        ]);
    }
}
```

`resources/views/livewire/tender-costing.blade.php`:

```blade
@php
    use App\Support\{Money, Percent};
    $in = 'w-full rounded border border-line bg-surface px-1.5 py-1 text-sm disabled:border-transparent disabled:bg-transparent';
    $err = fn ($k) => $errors->first($k);
    $cost = $summary['total_cost_sen'];
    $bid = $summary['bid_price_sen'];
    $costPct = $bid > 0 ? min(100, max(0, round($cost * 100 / $bid))) : 0;
@endphp
<section class="space-y-4" x-data="{ dirty: @entangle('dirty') }"
         x-init="window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } })">
    @if ($conflict)
        <div class="flex items-center justify-between rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $conflict }} Your edits are still on screen — copy anything you need first.</span>
            <a href="{{ route('tenders.show', $tender) }}?tab=costing" class="font-medium underline">Reload</a>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-line bg-surface p-3"><p class="text-xs uppercase text-muted">Total cost</p><p class="text-lg font-semibold">{{ Money::format($cost) }}</p></div>
        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs uppercase text-muted">Bid price {{ $summary['is_override'] ? '· Your price' : '· Suggested' }}</p>
            <p class="text-lg font-semibold">{{ Money::format($bid) }}</p>
            @if ($summary['is_override'])
                <p class="text-xs text-muted">Suggested {{ Money::format($summary['suggested_bid_sen']) }}
                    @if ($editable) · <button type="button" wire:click="resetOverride" class="underline">Reset to suggested</button> @endif</p>
            @endif
        </div>
        <div class="rounded-xl border border-line bg-surface p-3"><p class="text-xs uppercase text-muted">Margin</p><p class="text-lg font-semibold">{{ Money::format($summary['margin_sen']) }}</p></div>
        <div @class(['rounded-xl border p-3', 'border-line bg-surface' => ! $summary['below_target'], 'border-bad-ink bg-bad-bg' => $summary['below_target']])>
            <p class="text-xs uppercase text-muted">Margin %</p>
            <p class="text-lg font-semibold">{{ Percent::format($summary['margin_bp']) }}</p>
            @if ($summary['below_target']) <p class="text-xs text-bad-ink">⚠ Below the {{ $target }} target</p> @endif
        </div>
        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs uppercase text-muted">Under budget</p>
            <p class="text-lg font-semibold">{{ $summary['under_budget_bp'] === null ? '—' : Percent::format($summary['under_budget_bp']) }}</p>
        </div>
    </div>
    <div class="h-2 overflow-hidden rounded-full bg-good-bg" aria-label="Cost versus margin"><div class="h-full bg-chip" style="width: {{ $costPct }}%"></div></div>

    <div class="flex flex-wrap items-end gap-3 text-sm">
        <label>Default margin %
            <input wire:model.blur="defaultMargin" @disabled(! $editable) class="ml-1 w-20 rounded border border-line bg-surface px-2 py-1"></label>
        @if ($editable)
            <button type="button" wire:click="applyDefaultToAll" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Apply to all lines</button>
        @endif
        <label class="ml-auto">Your bid price (optional)
            <input wire:model.blur="override" @disabled(! $editable) placeholder="{{ Money::toInput($summary['suggested_bid_sen']) }}" class="ml-1 w-36 rounded border border-line bg-surface px-2 py-1"></label>
        @if ($err('defaultMargin') || $err('override')) <p class="w-full text-xs text-bad-ink">{{ $err('defaultMargin') ?: $err('override') }}</p> @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[1300px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase text-muted">
                <tr><th class="px-2 py-2">Item</th><th class="px-2">Qty</th><th class="px-2">Unit</th><th class="px-2">Frequency</th><th class="px-2">Year</th>
                    <th class="px-2 text-right">Unit cost</th><th class="px-2 text-right">Line cost</th><th class="px-2">Margin %</th>
                    <th class="px-2 text-right">Price/unit</th><th class="px-2 text-right">Selling price</th><th class="px-2">Vendor</th><th class="px-2">Quotation link</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($lines as $i => $line)
                @php $calc = $summary['lines'][$i] ?? null; $hasSubs = ! empty($line['sub_items']); @endphp
                <tr wire:key="line-{{ $i }}" class="border-t border-line align-top">
                    <td class="px-2 py-1"><input wire:model.blur="lines.{{ $i }}.description" @disabled(! $editable) class="{{ $in }} min-w-56" aria-label="Item">
                        @if ($e = $err("lines.$i.description")) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif</td>
                    <td class="px-2 py-1"><input wire:model.blur="lines.{{ $i }}.quantity" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Quantity">
                        @if ($e = $err("lines.$i.quantity")) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif</td>
                    <td class="px-2 py-1"><input wire:model.blur="lines.{{ $i }}.unit" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Unit"></td>
                    <td class="px-2 py-1 whitespace-nowrap">
                        <select wire:model.live="lines.{{ $i }}.frequency" @disabled(! $editable) class="{{ $in }} w-24" aria-label="Frequency">
                            <option value="one_off">One-off</option><option value="monthly">Monthly</option>
                        </select>
                        @if (($line['frequency'] ?? '') === 'monthly')
                            × <input wire:model.blur="lines.{{ $i }}.months" @disabled(! $editable) class="{{ $in }} inline w-14" aria-label="Months"> mo
                            @if ($e = $err("lines.$i.months")) <span class="block text-xs text-bad-ink">{{ $e }}</span> @endif
                        @endif
                    </td>
                    <td class="px-2 py-1"><select wire:model.live="lines.{{ $i }}.project_year" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Year">
                        @foreach (range(1, 7) as $y) <option value="{{ $y }}">Y{{ $y }}</option> @endforeach</select></td>
                    <td class="px-2 py-1 text-right">
                        @if ($hasSubs) {{ Money::format($calc['unit_cost_sen'] ?? 0) }}
                        @else <input wire:model.blur="lines.{{ $i }}.unit_cost" @disabled(! $editable) class="{{ $in }} w-28 text-right" aria-label="Unit cost">
                            @if ($e = $err("lines.$i.unit_cost")) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
                        @endif
                    </td>
                    <td class="px-2 py-1 text-right whitespace-nowrap">{{ Money::format($calc['line_cost_sen'] ?? 0) }}</td>
                    <td class="px-2 py-1"><input wire:model.blur="lines.{{ $i }}.margin" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Margin %">
                        @if ($e = $err("lines.$i.margin")) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif</td>
                    <td class="px-2 py-1 text-right whitespace-nowrap">{{ Money::format($calc['price_per_unit_sen'] ?? 0) }}</td>
                    <td class="px-2 py-1 text-right whitespace-nowrap">{{ Money::format($calc['selling_sen'] ?? 0) }}</td>
                    <td class="px-2 py-1"><input wire:model.blur="lines.{{ $i }}.vendor" list="costing-vendors" @disabled(! $editable) class="{{ $in }} w-32" aria-label="Vendor"></td>
                    <td class="px-2 py-1">
                        @if (! $editable && ($line['quote_url'] ?? '') !== '')
                            <a href="{{ $line['quote_url'] }}" target="_blank" rel="noopener noreferrer" class="text-info-ink underline">Open</a>
                        @else
                            <input wire:model.blur="lines.{{ $i }}.quote_url" @disabled(! $editable) placeholder="https://…" class="{{ $in }} w-40" aria-label="Quotation link">
                            @if ($e = $err("lines.$i.quote_url")) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
                        @endif
                    </td>
                    <td class="px-2 py-1 whitespace-nowrap text-xs">
                        @if ($editable)
                            <button type="button" wire:click="moveLine({{ $i }}, -1)" aria-label="Move up">↑</button>
                            <button type="button" wire:click="moveLine({{ $i }}, 1)" aria-label="Move down">↓</button>
                            <button type="button" wire:click="addSubItem({{ $i }})" class="ml-1 underline">+ Sub-item</button>
                            <button type="button" wire:click="removeLine({{ $i }})" wire:confirm="Remove this line?" class="ml-1 text-bad-ink">Remove</button>
                        @endif
                    </td>
                </tr>
                @foreach ($line['sub_items'] ?? [] as $j => $sub)
                    <tr wire:key="sub-{{ $i }}-{{ $j }}" class="bg-subtle text-xs">
                        <td class="py-1 pl-6 pr-2">↳ <input wire:model.blur="lines.{{ $i }}.sub_items.{{ $j }}.description" @disabled(! $editable) class="{{ $in }} inline w-52" aria-label="Sub-item">
                            @if ($e = $err("lines.$i.sub_items.$j.description")) <span class="text-bad-ink">{{ $e }}</span> @endif</td>
                        <td class="px-2"><input wire:model.blur="lines.{{ $i }}.sub_items.{{ $j }}.quantity" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Sub-item quantity"></td>
                        <td class="px-2"><input wire:model.blur="lines.{{ $i }}.sub_items.{{ $j }}.unit" @disabled(! $editable) class="{{ $in }} w-16" aria-label="Sub-item unit"></td>
                        <td colspan="2"></td>
                        <td class="px-2 text-right"><input wire:model.blur="lines.{{ $i }}.sub_items.{{ $j }}.unit_cost" @disabled(! $editable) class="{{ $in }} w-28 text-right" aria-label="Sub-item unit cost">
                            @if ($e = $err("lines.$i.sub_items.$j.unit_cost")) <span class="text-bad-ink">{{ $e }}</span> @endif</td>
                        <td colspan="4"></td>
                        <td class="px-2"><input wire:model.blur="lines.{{ $i }}.sub_items.{{ $j }}.vendor" list="costing-vendors" @disabled(! $editable) class="{{ $in }} w-32" aria-label="Sub-item vendor"></td>
                        <td class="px-2"><input wire:model.blur="lines.{{ $i }}.sub_items.{{ $j }}.quote_url" @disabled(! $editable) placeholder="https://…" class="{{ $in }} w-40" aria-label="Sub-item quotation link"></td>
                        <td class="px-2">@if ($editable) <button type="button" wire:click="removeSubItem({{ $i }}, {{ $j }})" class="text-bad-ink">Remove</button> @endif</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="13" class="px-3 py-8 text-center text-muted">No cost lines yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <datalist id="costing-vendors">@foreach ($vendors as $v) <option value="{{ $v }}"></option> @endforeach</datalist>
    </div>

    @if ($editable)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button type="button" wire:click="addLine" class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">+ Add line</button>
            <button type="button" wire:click="$toggle('showImport')" class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">Bulk import</button>
            <span class="ml-auto">@if ($dirty) <span class="text-warn-ink">Unsaved changes</span> @endif</span>
            <button type="button" wire:click="save" class="rounded-lg bg-chip px-4 py-1.5 font-medium text-chip-ink hover:bg-chip-hover">Save costing</button>
        </div>
        @if ($showImport || $importErrors)
            <div class="space-y-2 rounded-xl border border-line bg-surface p-3 text-sm">
                <p class="text-muted">Copy rows from Excel and paste them here: description, quantity, unit, unit cost.</p>
                <textarea wire:model="importText" rows="4" class="w-full rounded border border-line bg-surface p-2 font-mono text-xs"></textarea>
                <button type="button" wire:click="import" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Add these lines</button>
                @foreach ($importErrors as $e) <p class="text-xs text-bad-ink">{{ $e }}</p> @endforeach
            </div>
        @endif
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-line bg-surface p-3 text-sm">
            <h3 class="mb-2 font-medium">Target-cost guide</h3>
            <p class="mb-2 text-xs text-muted">At this bid price, keep total cost under:</p>
            <table class="w-full"><tbody>
                @foreach ($summary['guide'] as $g)
                    <tr class="border-t border-line"><td class="py-1">{{ Percent::format($g['margin_bp'], 0) }} margin</td><td class="py-1 text-right">{{ Money::format($g['max_cost_sen']) }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
        <div class="rounded-xl border border-line bg-surface p-3 text-sm">
            <h3 class="mb-2 font-medium">Cost by year</h3>
            <table class="w-full"><tbody>
                @forelse ($summary['cost_by_year'] as $year => $sen)
                    <tr class="border-t border-line"><td class="py-1">Year {{ $year }}</td><td class="py-1 text-right">{{ Money::format($sen) }}</td></tr>
                @empty
                    <tr><td class="py-1 text-muted">—</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
</section>
```

**Tender page wiring:**

In `tender-detail.blade.php`, change the tab list to `['overview' => 'Overview', 'costing' => 'Costing', 'documents' => 'Documents', 'activity' => 'Activity']`, change the include line to

```blade
    @if ($tab !== 'costing')
        @include('livewire.tender-detail.'.(in_array($tab, ['overview', 'documents', 'activity'], true) ? $tab : 'overview'))
    @endif
    {{-- Always mounted so unsaved costing edits survive switching tabs --}}
    <div @class(['hidden' => $tab !== 'costing'])>
        <livewire:tender-costing :tender="$tender" :version="$version" :can-edit="$canEdit" wire:key="costing-{{ $tender->id }}" />
    </div>
```

In `TenderDetail.php` add `public bool $costingDirty = false;` and:

```php
    #[On('costing-dirty')]
    public function costingDirty(bool $dirty): void
    {
        $this->costingDirty = $dirty;
    }

    #[On('costing-saved')]
    public function costingSaved(int $version): void
    {
        $this->version = $version;
        $this->costingDirty = false;
        $this->tender = $this->tender->fresh();
    }
```

(import `Livewire\Attributes\On`). Also in `TenderDetail::apply()` success path nothing changes — the child receives the new `$version` via `#[Reactive]`.

- [ ] **Step 4: Run full suite — PASS.** If `#[Reactive]` + `wire:key` cause the child to remount and lose edits when the parent re-renders, verify with the "keeps your edits" test and the browser walkthrough; Livewire keeps child state across parent renders when the key is stable.
- [ ] **Step 5: Browser check** — open JPNIN-like tender, add lines/sub-items/monthly, override and reset, apply default, bulk import (incl. bad row), save, switch tabs with unsaved edits (edits kept), try leaving the page (browser warning), dark mode.
- [ ] **Step 6: Commit** — `feat: Costing tab with live totals, sub-items, bulk import and save`

---

### Task 6: Mark Done uses the costing

**Files:** Create `app/Exceptions/CostingRequired.php`; Modify `app/Actions/Tenders/MarkTenderDone.php`, `app/Livewire/TenderDetail.php`, `resources/views/livewire/tender-detail/modals.blade.php`, `tests/Feature/Actions/StatusChangesTest.php`, `tests/Feature/Livewire/TenderDetailTest.php`; Test `tests/Feature/Costing/MarkDoneWithCostingTest.php`

**Interfaces:** `MarkTenderDone::handle(User $actor, Tender $tender, int $expectedVersion): Tender` (price argument removed; throws `CostingRequired`); `TenderDetail::$costingProblem` (?string) shown as an alert.

- [ ] **Step 1: Failing test** `tests/Feature/Costing/MarkDoneWithCostingTest.php`:

```php
<?php

use App\Actions\Tenders\MarkTenderDone;
use App\Enums\TenderStatus;
use App\Exceptions\CostingRequired;
use App\Livewire\TenderDetail;
use App\Models\{CostingLine, Tender, TenderDocument, User};
use Livewire\Livewire;

function readyTender(bool $withCosting = true): array
{
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id]);
    TenderDocument::factory()->for($tender)->create(['is_done' => true]);
    if ($withCosting) {
        CostingLine::factory()->for($tender)->create(['unit_cost_sen' => 10000000, 'margin_bp' => 2000]); // bid RM 125,000
    }

    return [$pic, $tender->fresh()];
}

it('submits the costing bid price', function () {
    [$pic, $tender] = readyTender();

    $done = app(MarkTenderDone::class)->handle($pic, $tender, 1);

    expect($done->status)->toBe(TenderStatus::Done)
        ->and($done->submitted_price_sen)->toBe(12500000)
        ->and($done->activity->first()->description)->toBe('Marked Done — submitted price RM 125,000.00');
});

it('refuses without a costing', function () {
    [$pic, $tender] = readyTender(withCosting: false);

    app(MarkTenderDone::class)->handle($pic, $tender, 1);
})->throws(CostingRequired::class);

it('shows the bid price in the confirm dialog, with no price box', function () {
    [$pic, $tender] = readyTender();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'done')
        ->assertSet('modal', 'done')
        ->assertSee('RM 125,000.00')
        ->assertDontSeeHtml('wire:model="submittedPrice"')
        ->call('markDone');

    expect($tender->fresh()->submitted_price_sen)->toBe(12500000);
});

it('blocks Mark Done without a costing, or with unsaved costing edits', function () {
    [$pic, $none] = readyTender(withCosting: false);
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $none])
        ->call('openModal', 'done')->assertSet('modal', null)->assertSee('Add a costing before marking this tender Done');

    [$pic2, $tender] = readyTender();
    Livewire::actingAs($pic2)->test(TenderDetail::class, ['tender' => $tender])
        ->set('costingDirty', true)
        ->call('openModal', 'done')->assertSet('modal', null)->assertSee('Save your costing changes first');
});
```

- [ ] **Step 2: Run — FAIL.**

- [ ] **Step 3: Implement**

`app/Exceptions/CostingRequired.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class CostingRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Add a costing before marking this tender Done.');
    }
}
```

`MarkTenderDone` — new body:

```php
    public function handle(User $actor, Tender $tender, int $expectedVersion): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'mark as Done');

            $pending = $t->documents()->where('is_done', false)->pluck('name')->all();
            if ($pending !== []) {
                throw new DocumentsIncomplete($pending);
            }
            if (! $t->hasCosting()) {
                throw new CostingRequired;
            }
            $price = $t->costingSummary()['bid_price_sen'];

            $t->forceFill([
                'status' => TenderStatus::Done,
                'submitted_price_sen' => $price,
                'done_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'marked_done', 'Marked Done — submitted price '.Money::format($price));

            return $t->fresh();
        });
    }
```

(remove the `InvalidArgumentException` import if unused; add `use App\Exceptions\CostingRequired;`).

`TenderDetail` changes:
- Remove `public string $submittedPrice` and its `reset(...)` entry; add `public ?string $costingProblem = null;`.
- In `openModal()`, after the pending-documents check for `'done'`:

```php
            if ($this->costingDirty) {
                $this->costingProblem = 'Save your costing changes first.';

                return;
            }
            if (! $this->tender->fresh()->hasCosting()) {
                $this->costingProblem = 'Add a costing before marking this tender Done.';

                return;
            }
```
  and set `$this->costingProblem = null;` at the top of `openModal()`.
- `markDone()` becomes:

```php
    public function markDone(): void
    {
        $this->apply(fn () => app(MarkTenderDone::class)->handle(auth()->user(), $this->tender, $this->version));
    }
```
- In `apply()`, also catch `CostingRequired $e` → `$this->costingProblem = $e->getMessage(); $this->modal = null; return false;`.
- In `render()` pass `'costing' => $this->tender->costingSummary()`.

`tender-detail.blade.php` — under the pending-documents alert add:

```blade
    @if ($costingProblem)
        <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">{{ $costingProblem }}</div>
    @endif
```

`modals.blade.php` — replace the `done` case body:

```blade
                @case('done')
                    <h2 class="text-lg font-semibold">Mark as Done</h2>
                    <p class="text-sm text-muted">This records that the bid was submitted and locks the tender and its costing.</p>
                    <p class="text-sm">Submitted price (from the costing): <strong>{{ \App\Support\Money::format($costing['bid_price_sen'] ?? null) }}</strong></p>
                    @php $confirm = ['markDone', 'Mark Done']; @endphp
                    @break
```

**Update Stage 1 tests that used the old signature:**
- `tests/Feature/Actions/StatusChangesTest.php`:
  - In `tenderWithDocs()`, after creating documents add `\App\Models\CostingLine::factory()->for($tender)->create(['unit_cost_sen' => 10000000, 'margin_bp' => 2000]);`.
  - 'marks a tender Done with its submitted price': call `->handle($pic, $tender, 1)` and expect `submitted_price_sen` `12500000` and description `'Marked Done — submitted price RM 125,000.00'`.
  - 'blocks Mark Done while documents are unticked': call `->handle($pic, $tender, 1)`.
  - Replace 'requires a positive submitted price' with: `it('refuses without a costing', …)` deleting the tender's costing lines then expecting `CostingRequired`.
  - In the transitions dataset closure: `'done' => fn () => app(MarkTenderDone::class)->handle($pic, $tender, 1)`.
- `tests/Feature/Livewire/TenderDetailTest.php` — 'marks Done after all documents are ticked': create a costing line (`unit_cost_sen` 13284500, margin 2000 → bid 16605700) before `openModal`, remove the `submittedPrice` sets, call `markDone` and expect `submitted_price_sen` `16605700`.

- [ ] **Step 4: Run full suite — PASS.**
- [ ] **Step 5: Commit** — `feat: Mark Done requires a costing and submits its bid price`

---

### Task 7: Gross column, sample costing, walkthrough

**Files:** Modify `app/Queries/TenderListQuery.php`, `resources/views/livewire/tender-list.blade.php`, `database/seeders/DatabaseSeeder.php`, `README.md`; Create `database/seeders/data/jpnin-costing.json` (copy); Test `tests/Feature/Costing/CostingListAndSeedTest.php`

- [ ] **Step 1: Copy data** — `cp docs/superpowers/plans/assets/jpnin-costing.json database/seeders/data/`

- [ ] **Step 2: Failing test**

```php
<?php

use App\Enums\TenderStatus;
use App\Livewire\TenderList;
use App\Models\{CostingLine, Tender, User};
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

it('shows the costing margin as Gross on the Done list', function () {
    $this->actingAs(User::factory()->create());
    $t = Tender::factory()->status(TenderStatus::Done)->create();
    CostingLine::factory()->for($t)->create(['unit_cost_sen' => 10000000, 'margin_bp' => 2000]);
    Tender::factory()->status(TenderStatus::Done)->create(); // no costing → blank

    Livewire::test(TenderList::class, ['list' => 'done'])->assertSee('Gross')->assertSee('20.0%');
});

it('seeds the JPNIN costing', function () {
    $this->seed(DatabaseSeeder::class);

    $s = Tender::where('wo_number', '200-10092026-001')->first()->costingSummary();
    expect($s['total_cost_sen'])->toBe(13284500)->and($s['bid_price_sen'])->toBe(16605900);
});
```

- [ ] **Step 3: Run — FAIL.**

- [ ] **Step 4: Implement**
  - `TenderListQuery::build()`: when `$status === TenderStatus::Done`, add `->with('costingLines')` (sub-items are already eager-loaded by the relation).
  - `tender-list.blade.php` Done header: add `<th class="px-3 py-2 text-right">Gross</th>` after Company Variant; row: `<td class="px-3 py-2 text-right">{{ ($g = $t->costingSummary()) ? \App\Support\Percent::format($g['margin_bp']) : '—' }}</td>`.
  - `DatabaseSeeder` — after the tenders loop:

```php
        $jpnin = Tender::where('wo_number', '200-10092026-001')->first();
        foreach (json_decode(file_get_contents(database_path('seeders/data/jpnin-costing.json')), true) as $i => $line) {
            $jpnin->costingLines()->create([
                'position' => $i + 1, 'description' => $line['description'], 'unit' => 'Unit', 'quantity' => 1,
                'frequency' => 'one_off', 'months' => 1, 'project_year' => 1,
                'unit_cost_sen' => $line['unit_cost_sen'], 'margin_bp' => 2000,
            ]);
        }
```
  - README: add a "Costing" bullet under the features (price = cost ÷ (1 − margin), rounded up to the ringgit; Mark Done uses the costing's bid price).

- [ ] **Step 5: Run full suite with coverage** — `docker compose exec -T app ./vendor/bin/pest --coverage --min=80`.
- [ ] **Step 6: Reseed dev DB?** No — the dev database holds 211k real collected tenders. Instead add the JPNIN costing to the existing dev tender only: `docker compose exec -T app php artisan tinker --execute="…"` running the same loop for WO 200-10092026-001 if it has no lines.
- [ ] **Step 7: Full browser walkthrough** (Playwright MCP): Costing tab on JPNIN (figures RM 132,845 / RM 166,059 / 20.0%); edit, sub-item, monthly line, override + reset, apply default, bulk import with a bad row, save; switch tabs with unsaved edits; leave-page warning; Mark Done blocked when dirty, then shows RM bid and submits; Done list Gross; staff non-PIC sees read-only costing; dark mode; 375px.
- [ ] **Step 8: Commit** — `feat: Gross column, JPNIN sample costing and docs`

---

## Self-review notes (spec coverage)

| Spec section | Task |
|---|---|
| §2 calculation rules, JPNIN reference, rounding, guide, cost by year | 2 |
| §3 data model | 1 |
| §4 Costing tab (summary, default + apply, table, sub-items, bulk import, guide, save, unsaved, read-only) | 3, 4, 5 |
| §4 Mark Done changes | 6 |
| §4 Done list Gross | 7 |
| §4 permissions (server-side) | 3, 5 |
| §5 errors (validation, conflict keeps edits, import never aborts, single transaction) | 3, 4, 5 |
| §6 sample data | 7 |
| §7 testing | all |
