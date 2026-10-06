# CMT Tender Hub — Stage 4 Implementation Plan (PD: project finance)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every Awarded tender a PD tab — budget vs actual P&L, cost/collection lines with PR/PO/invoice/payment/receipt entries, cash flow — created from the costing at Award, with Admin-managed project types and company defaults.

**Architecture:** A `projects` record per awarded tender owns `pd_lines`, which own `pd_entries`. One pure `PdCalculator` does all maths on plain arrays (sen, basis points). Small action classes (`App\Actions\Pd\*`) make each change in its own transaction with a per-line (or per-project) version check and one activity entry. A `TenderPd` Livewire component saves every edit immediately.

**Tech Stack:** Laravel 13, Livewire 4.4, Pest 5, MySQL 8.4.

**Spec:** `docs/superpowers/specs/2026-10-08-cmt-tender-hub-stage4-design.md` — read first.

## Global Constraints

- Branch `stage-4`. Commit per task; never commit red; the pre-commit hook runs `pest --coverage --min=80`.
- PHP runs only in Docker: `docker compose exec -T app …` (Git Bash: prefix `MSYS_NO_PATHCONV=1`). Write PHP with the editor tool.
- Money = integer sen; percentages = integer basis points (9% = 900). Percent-of-money rounds half up to the sen, in integers.
- Groups: `collection, principal, distributor, partner, finance_cost, tax, misc, internal` (column name `pd_group`, because `group` is an SQL keyword).
- Entry types: cost lines `pr, po, invoice, payment`; collection lines `invoice, receipt`.
- Entry amount > 0; budget ≥ 0; end date ≥ start date; percentages 0–99.99%.
- Livewire 4.4: use `wire:model.live.blur` / `wire:model.live` (plain `.blur` never reaches the server); never name a property `dirty`.
- Permissions: edit = tender `update` policy and project open and tender Awarded; per-project rates + close/reopen = `manage-projects` gate (Manager/Admin); project types + defaults = `manage-finance` gate (Admin).
- Messages: conflict "This line was changed by X — reload to see their changes." / "This project was changed by X — reload to see their changes."; closed "This project is closed. A Manager can reopen it."
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Plain-English UI copy.

## Review Focus

1. **Two people keying entries on the same project** — only edits to the *same line* conflict; different lines never block each other. Pinned in Task 5.
2. **Money arithmetic on large or negative figures** — losses (negative GP), commission never negative, revenue 0 gives 0% not an error, rounding half-up to the sen. Pinned in Task 2.
3. **Reopen tender then re-award** — no duplicate project or lines; entries survive. Pinned in Task 3.
4. **Wrong entry type for the line** (receipt on a supplier line, PO on a collection line) and **removing a line that has entries** — refused with a clear message. Pinned in Task 5.
5. **Closed project or tender no longer Awarded** — every change refused server-side, even from a crafted request. Pinned in Tasks 4–5.

---

## File Structure

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_09_000001_create_pd_tables.php` | project_types (+22 rows), finance_settings (+1 row), projects, pd_lines, pd_entries, costing_lines.pd_group |
| `app/Enums/{PdGroup,PdEntryType}.php` | groups and entry types |
| `app/Models/{ProjectType,FinanceSetting,Project,PdLine,PdEntry}.php` + factories | models |
| `app/Pd/PdCalculator.php` | all PD maths |
| `app/Actions/Pd/*` | create-from-costing, header, rates, close/reopen, lines, entries, project types, defaults |
| `app/Actions/Pd/Concerns/GuardsProject.php` | locking, permission, open checks |
| `app/Exceptions/{StalePdRecord,ProjectLocked}.php` | conflict / locked |
| `app/Livewire/TenderPd.php` + `resources/views/livewire/tender-pd.blade.php` | PD tab |
| `app/Livewire/FinanceSettings.php` + view | Admin settings |
| Modified: `MarkTenderAwarded`, `Tender`, costing form/view, `TenderDetail` (+view), `TenderListQuery`, tender-list view, sidebar, routes, `AppServiceProvider`, `DatabaseSeeder`, README | integration |

---

### Task 1: Tables, enums, models

**Files:**
- Create: migration, `app/Enums/PdGroup.php`, `app/Enums/PdEntryType.php`, `app/Models/ProjectType.php`, `app/Models/FinanceSetting.php`, `app/Models/Project.php`, `app/Models/PdLine.php`, `app/Models/PdEntry.php`, `database/factories/{ProjectFactory,PdLineFactory,PdEntryFactory}.php`
- Modify: `app/Models/Tender.php` (`project()`), `app/Models/CostingLine.php` (cast `pd_group`)
- Test: `tests/Feature/Pd/PdModelsTest.php`

**Interfaces — Produces:**
- `PdGroup` cases `Collection, Principal, Distributor, Partner, FinanceCost, Tax, Misc, Internal`; `label(): string`, `description(): string`, `isCollection(): bool`, `isCostOfSales(): bool`, `entryTypes(): list<PdEntryType>`, `static costGroups(): list<PdGroup>`.
- `PdEntryType` cases `Pr, Po, Invoice, Payment, Receipt`; `label(): string`.
- `FinanceSetting::current(): FinanceSetting` (`project_charge_bp`, `commission_share_bp`).
- `Project`: `tender()`, `projectType()`, `lines()` (ordered by position, with `entries`), `updatedBy()`, `closedBy()`, `isOpen(): bool`; `PdLine`: `project()`, `entries()` (ordered by date, id), `updatedBy()`, cast `pd_group` → `PdGroup`; `PdEntry`: `line()`, cast `type` → `PdEntryType`, `date` → `immutable_date`.
- `Tender::project(): HasOne`.

- [ ] **Step 1: Failing test** `tests/Feature/Pd/PdModelsTest.php`

```php
<?php

use App\Enums\{PdEntryType, PdGroup, TenderStatus};
use App\Models\{CostingLine, FinanceSetting, PdEntry, PdLine, Project, ProjectType, Tender};

it('ships the 22 project types and the company defaults', function () {
    expect(ProjectType::count())->toBe(22)
        ->and(ProjectType::where('name', 'Managed Services')->value('approved_margin_bp'))->toBe(1500)
        ->and(ProjectType::where('name', 'Leasing (ICT Peripherals)')->value('approved_margin_bp'))->toBe(400)
        ->and(FinanceSetting::current()->project_charge_bp)->toBe(900)
        ->and(FinanceSetting::current()->commission_share_bp)->toBe(5000);
});

it('links a project to its tender with ordered lines and entries', function () {
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create();
    $project = Project::factory()->for($tender)->create();
    PdLine::factory()->for($project)->create(['position' => 2, 'name' => 'B']);
    $a = PdLine::factory()->for($project)->create(['position' => 1, 'name' => 'A', 'pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($a, 'line')->create(['type' => PdEntryType::Po, 'date' => '2026-02-01', 'amount_sen' => 500]);
    PdEntry::factory()->for($a, 'line')->create(['type' => PdEntryType::Pr, 'date' => '2026-01-01', 'amount_sen' => 400]);

    $fresh = $tender->fresh()->project;
    expect($fresh->id)->toBe($project->id)
        ->and($fresh->isOpen())->toBeTrue()
        ->and($fresh->lines->pluck('name')->all())->toBe(['A', 'B'])
        ->and($fresh->lines->first()->pd_group)->toBe(PdGroup::Principal)
        ->and($fresh->lines->first()->entries->pluck('type')->all())->toBe([PdEntryType::Pr, PdEntryType::Po]);
});

it('knows which entry types each group takes', function () {
    expect(PdGroup::Collection->entryTypes())->toBe([PdEntryType::Invoice, PdEntryType::Receipt])
        ->and(PdGroup::Principal->entryTypes())->toBe([PdEntryType::Pr, PdEntryType::Po, PdEntryType::Invoice, PdEntryType::Payment])
        ->and(PdGroup::costGroups())->not->toContain(PdGroup::Collection)
        ->and(PdGroup::Partner->isCostOfSales())->toBeTrue()
        ->and(PdGroup::Tax->isCostOfSales())->toBeFalse()
        ->and(PdGroup::Tax->label())->toBe('Tax (SST)');
});

it('gives costing lines a group, Principal by default', function () {
    $line = CostingLine::factory()->create();
    expect($line->fresh()->pd_group)->toBe(PdGroup::Principal);
});

it('refuses to delete a line that still has entries', function () {
    $line = PdLine::factory()->create();
    PdEntry::factory()->for($line, 'line')->create();

    $line->delete();
})->throws(Illuminate\Database\QueryException::class);
```

- [ ] **Step 2: Run** `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest tests/Feature/Pd/PdModelsTest.php` — Expected: FAIL (classes missing).

- [ ] **Step 3: Implement**

`database/migrations/2026_10_09_000001_create_pd_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    private const TYPES = [
        ['Audio Visual', 1500], ['Consultancy (BIM)', 3000], ['Consultancy (ICT)', 3000], ['DC Infrastructure', 1500],
        ['Distributorship', 0], ['Enterprise Solution', 1500], ['Installation Services', 3000], ['Leasing (Audio Visual)', 600],
        ['Leasing (Enterprise Solution)', 1100], ['Leasing (ICT Peripherals)', 400], ['Leasing (Networking)', 1000],
        ['Leasing (Others)', 1000], ['Managed Services', 1500], ['Networking', 2000], ['Project Management', 2000],
        ['Security Solution', 1000], ['Trading - General', 1500], ['Trading - ICT Peripherals', 1500],
        ['Trading - Medical (disposable)', 1500], ['Trading - Medical (Drugs)', 1500], ['Maintenance Services', 3000],
        ['Support (ASP)', 3000],
    ];

    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('approved_margin_bp');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $now = now();
        DB::table('project_types')->insert(array_map(
            fn ($t) => ['name' => $t[0], 'approved_margin_bp' => $t[1], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::TYPES,
        ));

        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('project_charge_bp');
            $table->unsignedSmallInteger('commission_share_bp');
            $table->timestamps();
        });
        DB::table('finance_settings')->insert(['project_charge_bp' => 900, 'commission_share_bp' => 5000, 'created_at' => $now, 'updated_at' => $now]);

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('project_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('approved_margin_bp')->default(0);
            $table->unsignedSmallInteger('project_charge_bp');
            $table->unsignedSmallInteger('commission_share_bp');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('pd_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('pd_group', 20);
            $table->string('name', 255);
            $table->string('reference', 100)->nullable();
            $table->unsignedBigInteger('budget_sen')->default(0);
            $table->date('scheduled_date')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['project_id', 'pd_group', 'position']);
        });

        Schema::create('pd_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pd_line_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->string('number', 100)->nullable();
            $table->date('date');
            $table->unsignedBigInteger('amount_sen');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('costing_lines', function (Blueprint $table) {
            $table->string('pd_group', 20)->default('principal')->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('costing_lines', fn (Blueprint $table) => $table->dropColumn('pd_group'));
        Schema::dropIfExists('pd_entries');
        Schema::dropIfExists('pd_lines');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('finance_settings');
        Schema::dropIfExists('project_types');
    }
};
```

Note: `projects.tender_id` cascades, but `pd_entries` restrict deletion of their line, so deleting a tender with entries would fail — tenders are never hard-deleted in this app (spec §History), so this is acceptable and protects money records.

`app/Enums/PdGroup.php`:

```php
<?php

namespace App\Enums;

enum PdGroup: string
{
    case Collection = 'collection';
    case Principal = 'principal';
    case Distributor = 'distributor';
    case Partner = 'partner';
    case FinanceCost = 'finance_cost';
    case Tax = 'tax';
    case Misc = 'misc';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Collection',
            self::Principal => 'Principal',
            self::Distributor => 'Distributor',
            self::Partner => 'Partner',
            self::FinanceCost => 'Finance Cost',
            self::Tax => 'Tax (SST)',
            self::Misc => 'Misc, Training & Travel',
            self::Internal => 'Internal Resources (Manpower)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Collection => 'Customer payment schedule according to the contract terms.',
            self::Principal => 'Costs paid to principals.',
            self::Distributor => 'Costs paid to distributors.',
            self::Partner => 'Costs paid to partners and subcontractors.',
            self::FinanceCost => 'Trade finance, leasing, insurance bonds and other financial costs.',
            self::Tax => 'SST and other taxes on costs.',
            self::Misc => 'Entertainment, training, travelling and other fees.',
            self::Internal => 'Internal manpower charged to the project.',
        };
    }

    public function isCollection(): bool
    {
        return $this === self::Collection;
    }

    public function isCostOfSales(): bool
    {
        return in_array($this, [self::Principal, self::Distributor, self::Partner], true);
    }

    /** @return list<PdEntryType> */
    public function entryTypes(): array
    {
        return $this->isCollection()
            ? [PdEntryType::Invoice, PdEntryType::Receipt]
            : [PdEntryType::Pr, PdEntryType::Po, PdEntryType::Invoice, PdEntryType::Payment];
    }

    /** @return list<self> */
    public static function costGroups(): array
    {
        return array_values(array_filter(self::cases(), fn (self $g) => ! $g->isCollection()));
    }
}
```

`app/Enums/PdEntryType.php`:

```php
<?php

namespace App\Enums;

enum PdEntryType: string
{
    case Pr = 'pr';
    case Po = 'po';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Pr => 'PR',
            self::Po => 'PO',
            self::Invoice => 'Invoice',
            self::Payment => 'Payment',
            self::Receipt => 'Receipt',
        };
    }
}
```

`app/Models/ProjectType.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_margin_bp' => 'integer', 'is_active' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
```

`app/Models/FinanceSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Company-wide defaults copied into each new project. Exactly one row (inserted by the migration). */
class FinanceSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['project_charge_bp' => 'integer', 'commission_share_bp' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrFail();
    }
}
```

`app/Models/Project.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'approved_margin_bp' => 'integer', 'project_charge_bp' => 'integer', 'commission_share_bp' => 'integer',
            'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'closed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PdLine::class)->orderBy('position')->orderBy('id')->with('entries');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
```

`app/Models/PdLine.php`:

```php
<?php

namespace App\Models;

use App\Enums\PdGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PdLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pd_group' => PdGroup::class, 'budget_sen' => 'integer', 'scheduled_date' => 'immutable_date',
            'position' => 'integer', 'version' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PdEntry::class)->orderBy('date')->orderBy('id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

`app/Models/PdEntry.php`:

```php
<?php

namespace App\Models;

use App\Enums\PdEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => PdEntryType::class, 'date' => 'immutable_date', 'amount_sen' => 'integer'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PdLine::class, 'pd_line_id');
    }
}
```

Factories:

```php
<?php
// database/factories/ProjectFactory.php

namespace Database\Factories;

use App\Enums\TenderStatus;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory()->status(TenderStatus::Awarded),
            'approved_margin_bp' => 1500, 'project_charge_bp' => 900, 'commission_share_bp' => 5000, 'version' => 1,
        ];
    }
}
```

```php
<?php
// database/factories/PdLineFactory.php

namespace Database\Factories;

use App\Enums\PdGroup;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PdLine> */
class PdLineFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'position' => 1, 'pd_group' => PdGroup::Principal,
            'name' => fake()->words(3, true), 'budget_sen' => 1000000, 'version' => 1];
    }
}
```

```php
<?php
// database/factories/PdEntryFactory.php

namespace Database\Factories;

use App\Enums\PdEntryType;
use App\Models\PdLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PdEntry> */
class PdEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['pd_line_id' => PdLine::factory(), 'type' => PdEntryType::Invoice, 'number' => 'INV-'.fake()->numberBetween(1, 999),
            'date' => '2026-01-15', 'amount_sen' => 100000];
    }
}
```

`Tender.php` — add (import `HasOne` in the relations `use` line):

```php
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
```

`CostingLine.php` — add `'pd_group' => \App\Enums\PdGroup::class` to `casts()`.

- [ ] **Step 4: Run the test** — Expected: PASS. Then `MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan migrate --force` on the dev database.
- [ ] **Step 5: Commit** — `feat: PD tables, groups, entry types and models` (the hook runs the full suite).

---

### Task 2: PdCalculator

**Files:** Create `app/Pd/PdCalculator.php`; Modify `app/Models/Project.php` (`summary()`); Test `tests/Unit/Pd/PdCalculatorTest.php`

**Interfaces — Produces:**
- `PdCalculator::summary(array $lines, array $rates, ?string $start, ?string $end, string $today): array`
  - line input: `['group' => string, 'budget_sen' => int, 'scheduled_date' => ?string 'Y-m-d', 'entries' => list<['type' => string, 'amount_sen' => int, 'date' => 'Y-m-d']>]` (+ any other keys, passed through)
  - rates: `['approved_margin_bp' => int, 'project_charge_bp' => int, 'commission_share_bp' => int]`
  - returns `lines` (input + `pr, po, invoiced, paid, received, owed, variance, status, status_label, over_budget, overpaid`), `pnl` = `['budget' => row, 'actual' => row]` with row keys `revenue, cost_of_sales, other_costs, charges, gp, gp_bp, approved_sen, commission, net, net_bp`, `below_margin` (bool), `cash_flow` (list of `['month' => 'Y-m', 'expected_in', 'received', 'paid_out', 'balance']`), `duration_pct` (?int).
- `PdCalculator::pct(int $sen, int $bp): int` (half-up, sign-aware).
- `Project::summary(?string $today = null): array` — builds input from `lines.entries` (today defaults to `MalaysiaTime::today()`).

- [ ] **Step 1: Failing test** `tests/Unit/Pd/PdCalculatorTest.php`

```php
<?php

use App\Pd\PdCalculator as P;

const RATES = ['approved_margin_bp' => 1500, 'project_charge_bp' => 900, 'commission_share_bp' => 5000];

function pdLine(string $group, int $budget, array $entries = [], ?string $scheduled = null): array
{
    return ['group' => $group, 'budget_sen' => $budget, 'scheduled_date' => $scheduled,
        'entries' => array_map(fn ($e) => ['type' => $e[0], 'amount_sen' => $e[1], 'date' => $e[2] ?? '2026-01-15'], $entries)];
}

it('works out the budget P&L in the spec reference example', function () {
    $s = P::summary([
        pdLine('collection', 125000000), pdLine('principal', 65100000), pdLine('internal', 13200000),
    ], RATES, null, null, '2026-01-01');

    expect($s['pnl']['budget'])->toBe([
        'revenue' => 125000000, 'cost_of_sales' => 65100000, 'other_costs' => 13200000, 'charges' => 11250000,
        'gp' => 35450000, 'gp_bp' => 2836, 'approved_sen' => 18750000, 'commission' => 8350000,
        'net' => 27100000, 'net_bp' => 2168,
    ]);
});

it('uses invoices for actual revenue and costs', function () {
    $s = P::summary([
        pdLine('collection', 100000, [['invoice', 50000], ['receipt', 50000]]),
        pdLine('distributor', 30000, [['po', 30000], ['invoice', 20000]]),
        pdLine('tax', 1000, [['invoice', 1000]]),
    ], RATES, null, null, '2026-01-01');

    expect($s['pnl']['actual'])->toMatchArray(['revenue' => 50000, 'cost_of_sales' => 20000, 'other_costs' => 1000, 'charges' => 4500, 'gp' => 24500]);
});

it('never pays negative commission and handles a loss', function () {
    $s = P::summary([pdLine('collection', 100000), pdLine('principal', 150000)], RATES, null, null, '2026-01-01');

    expect($s['pnl']['budget'])->toMatchArray(['gp' => -59000, 'gp_bp' => -5900, 'commission' => 0, 'net' => -59000]);
});

it('gives 0% rather than an error when revenue is zero', function () {
    $s = P::summary([pdLine('principal', 5000)], RATES, null, null, '2026-01-01');

    expect($s['pnl']['budget']['gp_bp'])->toBe(0)->and($s['pnl']['budget']['net_bp'])->toBe(0)
        ->and($s['below_margin'])->toBeFalse();
});

it('rounds percentages of money half up to the sen', function () {
    expect(P::pct(150, 900))->toBe(14)    // 13.5 → 14
        ->and(P::pct(149, 900))->toBe(13)  // 13.41 → 13
        ->and(P::pct(-150, 900))->toBe(-14)
        ->and(P::pct(0, 900))->toBe(0);
});

it('flags actual gross profit below the approved margin', function () {
    $low = P::summary([pdLine('collection', 100000, [['invoice', 100000]]), pdLine('principal', 0, [['invoice', 85000]])], RATES, null, null, '2026-01-01');
    $ok = P::summary([pdLine('collection', 100000, [['invoice', 100000]]), pdLine('principal', 0, [['invoice', 50000]])], RATES, null, null, '2026-01-01');

    expect($low['below_margin'])->toBeTrue()->and($ok['below_margin'])->toBeFalse();
});

it('gives each cost line its totals and status', function (array $entries, int $budget, string $status, bool $over, bool $overpaid) {
    $l = P::summary([pdLine('principal', $budget, $entries)], RATES, null, null, '2026-01-01')['lines'][0];

    expect($l['status'])->toBe($status)->and($l['over_budget'])->toBe($over)->and($l['overpaid'])->toBe($overpaid);
})->with([
    'nothing yet' => [[], 100, 'pending', false, false],
    'PR raised' => [[['pr', 100]], 100, 'pr', false, false],
    'PO issued' => [[['pr', 100], ['po', 100]], 100, 'po', false, false],
    'invoiced' => [[['po', 100], ['invoice', 60]], 100, 'invoiced', false, false],
    'part paid' => [[['invoice', 100], ['payment', 40]], 100, 'invoiced', false, false],
    'paid' => [[['invoice', 100], ['payment', 100]], 100, 'paid', false, false],
    'over budget' => [[['invoice', 150]], 100, 'invoiced', true, false],
    'paid in advance' => [[['payment', 50]], 100, 'overpaid', false, true],
    'overpaid' => [[['invoice', 50], ['payment', 80]], 100, 'overpaid', false, true],
]);

it('works out still-owed and variance', function () {
    $l = P::summary([pdLine('principal', 1000, [['pr', 900], ['po', 900], ['invoice', 800], ['payment', 300]])], RATES, null, null, '2026-01-01')['lines'][0];

    expect($l)->toMatchArray(['pr' => 900, 'po' => 900, 'invoiced' => 800, 'paid' => 300, 'owed' => 500, 'variance' => 200, 'status_label' => 'Invoiced']);
});

it('gives each collection line its totals and status', function (array $entries, string $status) {
    expect(P::summary([pdLine('collection', 100, $entries)], RATES, null, null, '2026-01-01')['lines'][0]['status'])->toBe($status);
})->with([
    [[], 'pending'],
    [[['invoice', 100]], 'invoiced'],
    [[['invoice', 100], ['receipt', 30]], 'partly'],
    [[['invoice', 100], ['receipt', 100]], 'received'],
    [[['invoice', 100], ['receipt', 120]], 'overpaid'],
]);

it('builds a month-by-month cash flow with a running balance', function () {
    $s = P::summary([
        pdLine('collection', 300, [['invoice', 300, '2026-01-10'], ['receipt', 300, '2026-03-05']], '2026-01-20'),
        pdLine('collection', 200, [], '2026-04-01'),
        pdLine('principal', 0, [['payment', 100, '2026-02-01'], ['invoice', 100, '2026-01-02']]),
    ], RATES, null, null, '2026-01-01');

    expect($s['cash_flow'])->toBe([
        ['month' => '2026-01', 'expected_in' => 300, 'received' => 0, 'paid_out' => 0, 'balance' => 0],
        ['month' => '2026-02', 'expected_in' => 0, 'received' => 0, 'paid_out' => 100, 'balance' => -100],
        ['month' => '2026-03', 'expected_in' => 0, 'received' => 300, 'paid_out' => 0, 'balance' => 200],
        ['month' => '2026-04', 'expected_in' => 200, 'received' => 0, 'paid_out' => 0, 'balance' => 200],
    ]);
});

it('stretches the cash flow to the project dates and is empty with no dates', function () {
    expect(array_column(P::summary([], RATES, '2025-11-15', '2026-01-31', '2026-01-01')['cash_flow'], 'month'))->toBe(['2025-11', '2025-12', '2026-01'])
        ->and(P::summary([pdLine('principal', 5)], RATES, null, null, '2026-01-01')['cash_flow'])->toBe([]);
});

it('measures how far through the project today is', function () {
    expect(P::summary([], RATES, '2026-01-01', '2026-01-11', '2026-01-06')['duration_pct'])->toBe(50)
        ->and(P::summary([], RATES, '2026-01-01', '2026-01-11', '2025-12-01')['duration_pct'])->toBe(0)
        ->and(P::summary([], RATES, '2026-01-01', '2026-01-11', '2026-05-01')['duration_pct'])->toBe(100)
        ->and(P::summary([], RATES, '2026-01-01', '2026-01-01', '2026-01-01')['duration_pct'])->toBe(100)
        ->and(P::summary([], RATES, '2026-01-01', null, '2026-01-06')['duration_pct'])->toBeNull();
});
```

- [ ] **Step 2: Run** — Expected: FAIL (`PdCalculator` not found).

- [ ] **Step 3: Implement** `app/Pd/PdCalculator.php`

```php
<?php

namespace App\Pd;

use DateTimeImmutable;

/** All PD maths in integer sen and basis points (1% = 100 bp). */
final class PdCalculator
{
    private const COST_OF_SALES = ['principal', 'distributor', 'partner'];

    private const COST_LABELS = ['pending' => 'Pending', 'pr' => 'PR Raised', 'po' => 'PO Issued', 'invoiced' => 'Invoiced',
        'paid' => 'Paid', 'overpaid' => 'Paid more than invoiced'];

    private const COLLECTION_LABELS = ['pending' => 'Pending', 'invoiced' => 'Invoiced', 'partly' => 'Partly received',
        'received' => 'Received', 'overpaid' => 'Received more than invoiced'];

    /** $sen × $bp ÷ 10000, rounded half up (away from zero) to the sen. */
    public static function pct(int $sen, int $bp): int
    {
        $p = $sen * $bp;

        return $p >= 0 ? intdiv($p + 5000, 10000) : -intdiv(-$p + 5000, 10000);
    }

    public static function summary(array $lines, array $rates, ?string $start, ?string $end, string $today): array
    {
        $computed = array_map(fn (array $l) => $l + self::line($l), $lines);

        $budget = ['revenue' => 0, 'cost_of_sales' => 0, 'other_costs' => 0];
        $actual = $budget;
        foreach ($computed as $l) {
            $key = $l['group'] === 'collection' ? 'revenue' : (in_array($l['group'], self::COST_OF_SALES, true) ? 'cost_of_sales' : 'other_costs');
            $budget[$key] += $l['budget_sen'];
            $actual[$key] += $l['invoiced'];
        }
        $budgetRow = self::pnlRow($budget, $rates);
        $actualRow = self::pnlRow($actual, $rates);

        return [
            'lines' => $computed,
            'pnl' => ['budget' => $budgetRow, 'actual' => $actualRow],
            'below_margin' => $actualRow['revenue'] > 0 && $actualRow['gp_bp'] < $rates['approved_margin_bp'],
            'cash_flow' => self::cashFlow($computed, $start, $end),
            'duration_pct' => self::duration($start, $end, $today),
        ];
    }

    private static function line(array $l): array
    {
        $sum = ['pr' => 0, 'po' => 0, 'invoice' => 0, 'payment' => 0, 'receipt' => 0];
        foreach ($l['entries'] ?? [] as $e) {
            $sum[$e['type']] += $e['amount_sen'];
        }
        $collection = $l['group'] === 'collection';
        $settled = $collection ? $sum['receipt'] : $sum['payment'];
        $overpaid = $settled > $sum['invoice'];

        if ($collection) {
            $status = match (true) {
                $overpaid => 'overpaid',
                $sum['invoice'] > 0 && $settled >= $sum['invoice'] => 'received',
                $settled > 0 => 'partly',
                $sum['invoice'] > 0 => 'invoiced',
                default => 'pending',
            };
        } else {
            $status = match (true) {
                $overpaid => 'overpaid',
                $sum['invoice'] > 0 && $settled >= $sum['invoice'] => 'paid',
                $sum['invoice'] > 0 => 'invoiced',
                $sum['po'] > 0 => 'po',
                $sum['pr'] > 0 => 'pr',
                default => 'pending',
            };
        }

        return [
            'pr' => $sum['pr'], 'po' => $sum['po'], 'invoiced' => $sum['invoice'], 'paid' => $sum['payment'], 'received' => $sum['receipt'],
            'owed' => $sum['invoice'] - $settled,
            'variance' => $l['budget_sen'] - $sum['invoice'],
            'status' => $status,
            'status_label' => ($collection ? self::COLLECTION_LABELS : self::COST_LABELS)[$status],
            'over_budget' => ! $collection && $sum['invoice'] > $l['budget_sen'],
            'overpaid' => $overpaid,
        ];
    }

    private static function pnlRow(array $t, array $rates): array
    {
        $charges = self::pct($t['revenue'], $rates['project_charge_bp']);
        $gp = $t['revenue'] - $t['cost_of_sales'] - $t['other_costs'] - $charges;
        $approved = self::pct($t['revenue'], $rates['approved_margin_bp']);
        $commission = self::pct(max(0, $gp - $approved), $rates['commission_share_bp']);
        $net = $gp - $commission;
        $ofRevenue = fn (int $v) => $t['revenue'] > 0 ? (int) round($v * 10000 / $t['revenue']) : 0;

        return [
            'revenue' => $t['revenue'], 'cost_of_sales' => $t['cost_of_sales'], 'other_costs' => $t['other_costs'],
            'charges' => $charges, 'gp' => $gp, 'gp_bp' => $ofRevenue($gp), 'approved_sen' => $approved,
            'commission' => $commission, 'net' => $net, 'net_bp' => $ofRevenue($net),
        ];
    }

    private static function cashFlow(array $lines, ?string $start, ?string $end): array
    {
        $months = [];
        $bump = function (string $date, string $key, int $sen) use (&$months) {
            $m = substr($date, 0, 7);
            $months[$m] ??= ['expected_in' => 0, 'received' => 0, 'paid_out' => 0];
            $months[$m][$key] += $sen;
        };
        foreach (array_filter([$start, $end]) as $d) {
            $months[substr($d, 0, 7)] ??= ['expected_in' => 0, 'received' => 0, 'paid_out' => 0];
        }
        foreach ($lines as $l) {
            if ($l['group'] === 'collection' && $l['scheduled_date']) {
                $bump($l['scheduled_date'], 'expected_in', $l['budget_sen']);
            }
            foreach ($l['entries'] ?? [] as $e) {
                match ($e['type']) {
                    'receipt' => $bump($e['date'], 'received', $e['amount_sen']),
                    'payment' => $bump($e['date'], 'paid_out', $e['amount_sen']),
                    default => $bump($e['date'], 'expected_in', 0), // other entries still widen the range
                };
            }
        }
        if ($months === []) {
            return [];
        }
        ksort($months);
        $cursor = new DateTimeImmutable(array_key_first($months).'-01');
        $last = array_key_last($months);
        $rows = [];
        $balance = 0;
        while (($m = $cursor->format('Y-m')) <= $last) {
            $row = $months[$m] ?? ['expected_in' => 0, 'received' => 0, 'paid_out' => 0];
            $balance += $row['received'] - $row['paid_out'];
            $rows[] = ['month' => $m] + $row + ['balance' => $balance];
            $cursor = $cursor->modify('+1 month');
        }

        return $rows;
    }

    private static function duration(?string $start, ?string $end, string $today): ?int
    {
        if (! $start || ! $end) {
            return null;
        }
        $s = new DateTimeImmutable($start);
        $total = (int) $s->diff(new DateTimeImmutable($end))->format('%r%a');
        $done = (int) $s->diff(new DateTimeImmutable($today))->format('%r%a');
        if ($total <= 0) {
            return $done >= 0 ? 100 : 0;
        }

        return max(0, min(100, intdiv($done * 100, $total)));
    }
}
```

Check the cash-flow test by hand: Jan has the scheduled 300 and the Jan invoices (range only); Feb payment 100 → balance −100; Mar receipt 300 → 200; Apr scheduled 200 → balance 200. ✓

`Project.php` — add:

```php
    /** @see \App\Pd\PdCalculator::summary() */
    public function summary(?string $today = null): array
    {
        return \App\Pd\PdCalculator::summary(
            $this->lines->map(fn (PdLine $l) => [
                'id' => $l->id, 'group' => $l->pd_group->value, 'budget_sen' => $l->budget_sen,
                'scheduled_date' => $l->scheduled_date?->format('Y-m-d'),
                'entries' => $l->entries->map(fn (PdEntry $e) => [
                    'type' => $e->type->value, 'amount_sen' => $e->amount_sen, 'date' => $e->date->format('Y-m-d'),
                ])->all(),
            ])->all(),
            ['approved_margin_bp' => $this->approved_margin_bp, 'project_charge_bp' => $this->project_charge_bp,
                'commission_share_bp' => $this->commission_share_bp],
            $this->start_date?->format('Y-m-d'),
            $this->end_date?->format('Y-m-d'),
            $today ?? \App\Support\MalaysiaTime::today()->format('Y-m-d'),
        );
    }
```

Add to `PdModelsTest.php`:

```php
it('summarises a saved project', function () {
    $project = Project::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $line = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection, 'budget_sen' => 100000, 'scheduled_date' => '2026-02-01']);
    PdEntry::factory()->for($line, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 40000, 'date' => '2026-02-03']);

    $s = $project->fresh()->summary('2026-07-02');

    expect($s['pnl']['budget']['revenue'])->toBe(100000)
        ->and($s['pnl']['actual']['revenue'])->toBe(40000)
        ->and($s['lines'][0]['id'])->toBe($line->id)
        ->and($s['duration_pct'])->toBe(50);   // 182 of 364 days
});
```

- [ ] **Step 4: Run** `pest tests/Unit/Pd tests/Feature/Pd` — Expected: PASS. If the reference example differs by any sen, the formula is wrong — fix the code, not the expected numbers.
- [ ] **Step 5: Commit** — `feat: PD calculator (P&L, line statuses, cash flow)`

---

### Task 3: Costing group + project created at Award

**Files:**
- Create: `app/Actions/Pd/CreateProjectFromCosting.php`
- Modify: `app/Actions/Tenders/MarkTenderAwarded.php`, `app/Costing/CostingForm.php`, `resources/views/livewire/tender-costing.blade.php`
- Test: `tests/Feature/Pd/AwardCreatesProjectTest.php`; extend `tests/Feature/Costing/SaveCostingTest.php`

**Interfaces:**
- Consumes: `Tender::costingSummary()`, `Tender::costingLines`, `FinanceSetting::current()`, `PdGroup`.
- Produces: `CreateProjectFromCosting::handle(Tender $tender, User $actor): Project` (idempotent); costing screen line key `pd_group` (string, default `'principal'`), saved as `costing_lines.pd_group`.

- [ ] **Step 1: Failing tests**

`tests/Feature/Pd/AwardCreatesProjectTest.php`:

```php
<?php

use App\Actions\Tenders\{MarkTenderAwarded, ReopenTender};
use App\Enums\{PdGroup, TenderStatus};
use App\Models\{CostingLine, PdEntry, Tender, User};

function doneTender(array $attrs = []): array
{
    $pic = User::factory()->create();

    return [$pic, Tender::factory()->status(TenderStatus::Done)->create(array_merge(['pic_id' => $pic->id], $attrs))];
}

it('creates the project from the costing when a tender is awarded', function () {
    [$pic, $tender] = doneTender();
    CostingLine::factory()->for($tender)->create(['position' => 1, 'description' => 'Laptops', 'vendor' => 'Dell',
        'unit_cost_sen' => 100000, 'quantity' => 2, 'pd_group' => PdGroup::Distributor]);
    CostingLine::factory()->for($tender)->create(['position' => 2, 'description' => 'Support', 'frequency' => 'monthly',
        'months' => 12, 'unit_cost_sen' => 50000, 'pd_group' => PdGroup::Internal]);

    $awarded = app(MarkTenderAwarded::class)->handle($pic, $tender, 1);
    $project = $awarded->project;
    $bid = $tender->costingSummary()['bid_price_sen'];

    expect($project)->not->toBeNull()
        ->and($project->project_charge_bp)->toBe(900)
        ->and($project->commission_share_bp)->toBe(5000)
        ->and($project->approved_margin_bp)->toBe(0)
        ->and($project->lines->map(fn ($l) => [$l->pd_group, $l->name, $l->reference, $l->budget_sen])->all())->toBe([
            [PdGroup::Collection, 'Contract value', null, $bid],
            [PdGroup::Distributor, 'Laptops', 'Dell', 200000],
            [PdGroup::Internal, 'Support', null, 600000],   // monthly: whole 12-month cost
        ])
        ->and($awarded->activity->first()->description)->toBe('Project created from the costing');
});

it('uses the submitted price when there is no costing', function () {
    [$pic, $tender] = doneTender(['submitted_price_sen' => 7184620]);

    $project = app(MarkTenderAwarded::class)->handle($pic, $tender, 1)->project;

    expect($project->lines)->toHaveCount(1)->and($project->lines->first()->budget_sen)->toBe(7184620);
});

it('keeps the same project when a tender is reopened and awarded again', function () {
    [$pic, $tender] = doneTender(['submitted_price_sen' => 100]);
    $manager = User::factory()->manager()->create();
    $project = app(MarkTenderAwarded::class)->handle($pic, $tender, 1)->project;
    PdEntry::factory()->for($project->lines->first(), 'line')->create(['type' => 'receipt']);

    $t = app(ReopenTender::class)->handle($manager, $tender, 2);
    $t->update(['status' => TenderStatus::Done, 'version' => $t->version + 1]);
    $again = app(MarkTenderAwarded::class)->handle($pic, $t->fresh(), $t->fresh()->version)->project;

    expect($again->id)->toBe($project->id)
        ->and($again->lines)->toHaveCount(1)
        ->and(PdEntry::count())->toBe(1);
});
```

Add to `tests/Feature/Costing/SaveCostingTest.php`:

```php
it('saves each costing line\'s PD group and rejects unknown groups', function () {
    [$pic, $tender] = picTender();
    $t = app(SaveCosting::class)->handle($pic, $tender, 1, CostingForm::toData(costingState(['pd_group' => 'partner'])));

    expect($t->costingLines->first()->pd_group)->toBe(\App\Enums\PdGroup::Partner)
        ->and(CostingForm::fromTender($t)['lines'][0]['pd_group'])->toBe('partner')
        ->and(Validator::make(costingState(['pd_group' => 'collection']), CostingForm::rules())->errors()->has('lines.0.pd_group'))->toBeTrue()
        ->and(CostingForm::toData(costingState(['pd_group' => 'bogus']), lenient: true)['lines'][0]['pd_group'])->toBe('principal');
});
```

- [ ] **Step 2: Run** — Expected: FAIL (`project` null / `pd_group` missing).

- [ ] **Step 3: Implement**

`app/Actions/Pd/CreateProjectFromCosting.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Enums\PdGroup;
use App\Models\{ActivityLog, FinanceSetting, Project, Tender, User};

/** Called inside the Award transaction. Does nothing if the tender already has a project (re-award). */
final class CreateProjectFromCosting
{
    public function handle(Tender $tender, User $actor): Project
    {
        if ($existing = $tender->project()->first()) {
            return $existing;
        }

        $defaults = FinanceSetting::current();
        $project = $tender->project()->create([
            'approved_margin_bp' => 0,
            'project_charge_bp' => $defaults->project_charge_bp,
            'commission_share_bp' => $defaults->commission_share_bp,
            'updated_by' => $actor->id,
            'version' => 1,
        ]);

        $summary = $tender->costingSummary();
        $position = 1;
        $project->lines()->create([
            'position' => $position++, 'pd_group' => PdGroup::Collection, 'name' => 'Contract value',
            'budget_sen' => $summary['bid_price_sen'] ?? $tender->submitted_price_sen ?? 0, 'updated_by' => $actor->id,
        ]);
        foreach ($tender->costingLines as $i => $line) {
            $project->lines()->create([
                'position' => $position++,
                'pd_group' => $line->pd_group,
                'name' => mb_substr($line->description, 0, 255),
                'reference' => $line->vendor ? mb_substr($line->vendor, 0, 100) : null,
                'budget_sen' => $summary['lines'][$i]['line_cost_sen'],
                'updated_by' => $actor->id,
            ]);
        }

        ActivityLog::record($tender, $actor, 'project_created', $summary ? 'Project created from the costing' : 'Project created');

        return $project;
    }
}
```

`MarkTenderAwarded` — after `$t->forceFill([...])->save();` and the existing `ActivityLog::record(... 'Marked Awarded')`, add:

```php
            app(\App\Actions\Pd\CreateProjectFromCosting::class)->handle($t, $actor);
```

(`$t->fresh()` is then returned, so `->project` loads the new project.)

`CostingForm`:
- `blankLine()` — add `'pd_group' => 'principal',`.
- `fromTender()` line map — add `'pd_group' => $l->pd_group->value,`.
- `rules()` — add `'lines.*.pd_group' => ['required', Rule::in(array_map(fn ($g) => $g->value, PdGroup::costGroups()))],` (import `Illuminate\Validation\Rule`, `App\Enums\PdGroup`).
- `attributes()` — add `'lines.*.pd_group' => 'group',`.
- `toData()` line array — add:

```php
                    'pd_group' => in_array($l['pd_group'] ?? '', array_map(fn ($g) => $g->value, PdGroup::costGroups()), true)
                        ? $l['pd_group'] : 'principal',
```

`tender-costing.blade.php`:
- Header: insert `<th class="px-2">Group</th>` after `<th class="px-2">Year</th>`; bump `min-w-[1550px]` → `min-w-[1720px]`.
- Line row, after the Year `<td>`:

```blade
                    <td class="px-2 py-1">
                        <select wire:model.live="lines.{{ $i }}.pd_group" @disabled(! $editable) class="{{ $in }} w-40" aria-label="Group">
                            @foreach (\App\Enums\PdGroup::costGroups() as $g) <option value="{{ $g->value }}">{{ $g->label() }}</option> @endforeach
                        </select>
                    </td>
```
- Sub-item row: change the first `<td colspan="2"></td>` to `<td colspan="3"></td>`; empty-state `colspan="13"` → `colspan="14"`.

- [ ] **Step 4: Run** `pest tests/Feature/Pd tests/Feature/Costing tests/Feature/Livewire tests/Feature/Actions` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: costing lines get a PD group; Award creates the project from the costing`

---

### Task 4: Project guard, header, rates, close/reopen

**Files:**
- Create: `app/Actions/Pd/Concerns/GuardsProject.php`, `app/Exceptions/StalePdRecord.php`, `app/Exceptions/ProjectLocked.php`, `app/Actions/Pd/{UpdateProjectDetails,UpdateProjectRates,CloseProject,ReopenProject}.php`
- Modify: `app/Providers/AppServiceProvider.php` (gates)
- Test: `tests/Feature/Pd/ProjectActionsTest.php`

**Interfaces — Produces:**
- Gates `manage-projects` (Manager/Admin), `manage-finance` (Admin).
- `GuardsProject::lockOpenProject(User $actor, Project $project): Project` (authorizes tender `update`, requires tender Awarded and project open, row-locks); `lockManagedProject(User, Project, int $expectedVersion): Project` (authorizes `manage-projects`, tender Awarded, version check; does NOT require open); `checkProjectVersion(Project $locked, int $expected)`.
- `StalePdRecord::line(PdLine)`, `StalePdRecord::project(Project)`; `ProjectLocked::closed()`, `ProjectLocked::notAwarded()`.
- `UpdateProjectDetails::handle(User, Project, int $expectedVersion, array{project_type_id:?int,start_date:?string,end_date:?string}): Project` — picking a (different) type copies its approved margin.
- `UpdateProjectRates::handle(User, Project, int $expectedVersion, int $approvedBp, int $chargeBp, int $shareBp): Project`.
- `CloseProject::handle(User, Project, int $expectedVersion): Project`; `ReopenProject::handle(...)`.

- [ ] **Step 1: Failing test** `tests/Feature/Pd/ProjectActionsTest.php`

```php
<?php

use App\Actions\Pd\{CloseProject, ReopenProject, UpdateProjectDetails, UpdateProjectRates};
use App\Enums\TenderStatus;
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{Project, ProjectType, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;

function picProject(): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $pic->id]);

    return [$pic, Project::factory()->for($tender)->create(['approved_margin_bp' => 0])];
}

it('sets the project type, copying its approved margin, and the dates', function () {
    [$pic, $project] = picProject();
    $type = ProjectType::where('name', 'Networking')->first();

    $p = app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => $type->id, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']);

    expect($p->approved_margin_bp)->toBe(2000)
        ->and($p->start_date->format('Y-m-d'))->toBe('2026-01-01')
        ->and($p->version)->toBe(2)
        ->and($p->tender->activity->first()->description)->toBe('Project details updated — Networking, 01 Jan 2026 to 30 Jun 2026');
});

it('keeps a manually adjusted margin when only the dates change', function () {
    [$pic, $project] = picProject();
    $type = ProjectType::where('name', 'Networking')->first();
    $project->update(['project_type_id' => $type->id, 'approved_margin_bp' => 1234]);

    $p = app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => $type->id, 'start_date' => null, 'end_date' => null]);

    expect($p->approved_margin_bp)->toBe(1234);
});

it('rejects an end date before the start date and switched-off types', function () {
    [$pic, $project] = picProject();
    $off = ProjectType::create(['name' => 'Old type', 'approved_margin_bp' => 100, 'is_active' => false]);

    expect(fn () => app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => null, 'start_date' => '2026-02-01', 'end_date' => '2026-01-01']))
        ->toThrow(InvalidArgumentException::class, 'The end date must be on or after the start date.')
        ->and(fn () => app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => $off->id, 'start_date' => null, 'end_date' => null]))
        ->toThrow(InvalidArgumentException::class, 'That project type is switched off.');
});

it('lets only managers and admins change the rates', function () {
    [$pic, $project] = picProject();
    $manager = User::factory()->manager()->create();

    expect(fn () => app(UpdateProjectRates::class)->handle($pic, $project, 1, 1500, 900, 5000))->toThrow(AuthorizationException::class);

    $p = app(UpdateProjectRates::class)->handle($manager, $project, 1, 1500, 1000, 4000);
    expect([$p->approved_margin_bp, $p->project_charge_bp, $p->commission_share_bp])->toBe([1500, 1000, 4000])
        ->and($p->tender->activity->first()->description)->toBe('Project rates changed — approved margin 15.0%, project charges 10.0%, commission share 40.0%');
});

it('closes and reopens a project, refusing edits while closed', function () {
    [$pic, $project] = picProject();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);

    expect(fn () => app(CloseProject::class)->handle($pic, $project, 1))->toThrow(AuthorizationException::class);

    $closed = app(CloseProject::class)->handle($manager, $project, 1);
    expect($closed->isOpen())->toBeFalse()->and($closed->closed_by)->toBe($manager->id);

    expect(fn () => app(UpdateProjectDetails::class)->handle($pic, $closed, 2, ['project_type_id' => null, 'start_date' => null, 'end_date' => null]))
        ->toThrow(ProjectLocked::class, 'This project is closed. A Manager can reopen it.');

    $open = app(ReopenProject::class)->handle($manager, $closed, 2);
    expect($open->isOpen())->toBeTrue()->and($open->tender->activity->first()->description)->toBe('Project reopened');
});

it('refuses changes from an out-of-date page, naming who changed it', function () {
    [$pic, $project] = picProject();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);
    app(UpdateProjectRates::class)->handle($manager, $project, 1, 1500, 900, 5000);

    app(UpdateProjectDetails::class)->handle($pic, $project, 1, ['project_type_id' => null, 'start_date' => null, 'end_date' => null]);
})->throws(StalePdRecord::class, 'This project was changed by Ahmad Faizal — reload to see their changes.');

it('refuses changes once the tender is no longer Awarded, and from staff who are not the PIC', function () {
    [$pic, $project] = picProject();
    $data = ['project_type_id' => null, 'start_date' => null, 'end_date' => null];

    expect(fn () => app(UpdateProjectDetails::class)->handle(User::factory()->create(), $project, 1, $data))->toThrow(AuthorizationException::class);

    $project->tender->update(['status' => TenderStatus::InProgress]);
    expect(fn () => app(UpdateProjectDetails::class)->handle($pic, $project, 1, $data))->toThrow(ProjectLocked::class);
});
```

- [ ] **Step 2: Run** — Expected: FAIL (classes missing).

- [ ] **Step 3: Implement**

`AppServiceProvider::boot()` — after the existing gates:

```php
        Gate::define('manage-projects', fn (User $user) => $user->role->canManageAllTenders());
        Gate::define('manage-finance', fn (User $user) => $user->role === Role::Admin);
```

`app/Exceptions/StalePdRecord.php`:

```php
<?php

namespace App\Exceptions;

use App\Models\{PdLine, Project};
use RuntimeException;

class StalePdRecord extends RuntimeException
{
    public static function line(PdLine $fresh): self
    {
        return new self('This line was changed by '.($fresh->updatedBy?->name ?? 'someone else').' — reload to see their changes.');
    }

    public static function project(Project $fresh): self
    {
        return new self('This project was changed by '.($fresh->updatedBy?->name ?? 'someone else').' — reload to see their changes.');
    }
}
```

`app/Exceptions/ProjectLocked.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class ProjectLocked extends RuntimeException
{
    public static function closed(): self
    {
        return new self('This project is closed. A Manager can reopen it.');
    }

    public static function notAwarded(): self
    {
        return new self('This project is on hold because its tender is no longer Awarded.');
    }
}
```

`app/Actions/Pd/Concerns/GuardsProject.php`:

```php
<?php

namespace App\Actions\Pd\Concerns;

use App\Enums\TenderStatus;
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{PdLine, Project, User};
use Illuminate\Support\Facades\Gate;

trait GuardsProject
{
    /** Call inside DB::transaction. Locks the project row; the actor must be able to edit the tender. */
    private function lockOpenProject(User $actor, Project $project): Project
    {
        $p = Project::query()->lockForUpdate()->findOrFail($project->id);
        Gate::forUser($actor)->authorize('update', $p->tender);
        $this->requireAwarded($p);
        if (! $p->isOpen()) {
            throw ProjectLocked::closed();
        }

        return $p;
    }

    /** For per-project rates and close/reopen (Manager/Admin). Does not require the project to be open. */
    private function lockManagedProject(User $actor, Project $project, int $expectedVersion): Project
    {
        $p = Project::query()->lockForUpdate()->findOrFail($project->id);
        Gate::forUser($actor)->authorize('manage-projects');
        $this->requireAwarded($p);
        $this->checkProjectVersion($p, $expectedVersion);

        return $p;
    }

    private function checkProjectVersion(Project $locked, int $expected): void
    {
        if ($locked->version !== $expected) {
            throw StalePdRecord::project($locked);
        }
    }

    /** Locks the line after the project; the line's version must match. */
    private function lockLine(User $actor, PdLine $line, int $expectedVersion): PdLine
    {
        $this->lockOpenProject($actor, $line->project);
        $l = PdLine::query()->lockForUpdate()->findOrFail($line->id);
        if ($l->version !== $expectedVersion) {
            throw StalePdRecord::line($l);
        }

        return $l;
    }

    private function requireAwarded(Project $p): void
    {
        if ($p->tender->status !== TenderStatus::Awarded) {
            throw ProjectLocked::notAwarded();
        }
    }
}
```

`app/Actions/Pd/UpdateProjectDetails.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, Project, ProjectType, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateProjectDetails
{
    use GuardsProject;

    /** @param array{project_type_id:?int, start_date:?string, end_date:?string} $data */
    public function handle(User $actor, Project $project, int $expectedVersion, array $data): Project
    {
        $start = $data['start_date'] ?: null;
        $end = $data['end_date'] ?: null;
        if ($start && $end && $end < $start) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }

        return DB::transaction(function () use ($actor, $project, $expectedVersion, $data, $start, $end) {
            $p = $this->lockOpenProject($actor, $project);
            $this->checkProjectVersion($p, $expectedVersion);

            $type = $data['project_type_id'] ? ProjectType::findOrFail($data['project_type_id']) : null;
            if ($type && ! $type->is_active && $type->id !== $p->project_type_id) {
                throw new InvalidArgumentException('That project type is switched off.');
            }
            if ($type && $type->id !== $p->project_type_id) {
                $p->approved_margin_bp = $type->approved_margin_bp;
            }
            $p->forceFill([
                'project_type_id' => $type?->id, 'start_date' => $start, 'end_date' => $end,
                'updated_by' => $actor->id, 'version' => $p->version + 1,
            ])->save();

            $fmt = fn (?string $d) => $d ? CarbonImmutable::parse($d)->format('d M Y') : '—';
            ActivityLog::record($p->tender, $actor, 'project_updated',
                'Project details updated — '.($type?->name ?? 'no type').', '.$fmt($start).' to '.$fmt($end));

            return $p->fresh();
        });
    }
}
```

`app/Actions/Pd/UpdateProjectRates.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Exceptions\ProjectLocked;
use App\Models\{ActivityLog, Project, User};
use App\Support\Percent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateProjectRates
{
    use GuardsProject;

    public function handle(User $actor, Project $project, int $expectedVersion, int $approvedBp, int $chargeBp, int $shareBp): Project
    {
        foreach ([$approvedBp, $chargeBp, $shareBp] as $bp) {
            if ($bp < 0 || $bp > 9999) {
                throw new InvalidArgumentException('Percentages must be from 0 to 99.99%.');
            }
        }

        return DB::transaction(function () use ($actor, $project, $expectedVersion, $approvedBp, $chargeBp, $shareBp) {
            $p = $this->lockManagedProject($actor, $project, $expectedVersion);
            if (! $p->isOpen()) {
                throw ProjectLocked::closed();
            }
            $p->forceFill([
                'approved_margin_bp' => $approvedBp, 'project_charge_bp' => $chargeBp, 'commission_share_bp' => $shareBp,
                'updated_by' => $actor->id, 'version' => $p->version + 1,
            ])->save();

            ActivityLog::record($p->tender, $actor, 'project_rates_changed', sprintf(
                'Project rates changed — approved margin %s, project charges %s, commission share %s',
                Percent::format($approvedBp), Percent::format($chargeBp), Percent::format($shareBp),
            ));

            return $p->fresh();
        });
    }
}
```

`app/Actions/Pd/CloseProject.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Exceptions\ProjectLocked;
use App\Models\{ActivityLog, Project, User};
use Illuminate\Support\Facades\DB;

final class CloseProject
{
    use GuardsProject;

    public function handle(User $actor, Project $project, int $expectedVersion): Project
    {
        return DB::transaction(function () use ($actor, $project, $expectedVersion) {
            $p = $this->lockManagedProject($actor, $project, $expectedVersion);
            if (! $p->isOpen()) {
                throw ProjectLocked::closed();
            }
            $p->forceFill(['closed_at' => now(), 'closed_by' => $actor->id, 'updated_by' => $actor->id, 'version' => $p->version + 1])->save();
            ActivityLog::record($p->tender, $actor, 'project_closed', 'Project closed');

            return $p->fresh();
        });
    }
}
```

`app/Actions/Pd/ReopenProject.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, Project, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReopenProject
{
    use GuardsProject;

    public function handle(User $actor, Project $project, int $expectedVersion): Project
    {
        return DB::transaction(function () use ($actor, $project, $expectedVersion) {
            $p = $this->lockManagedProject($actor, $project, $expectedVersion);
            if ($p->isOpen()) {
                throw new InvalidArgumentException('This project is already open.');
            }
            $p->forceFill(['closed_at' => null, 'closed_by' => null, 'updated_by' => $actor->id, 'version' => $p->version + 1])->save();
            ActivityLog::record($p->tender, $actor, 'project_reopened', 'Project reopened');

            return $p->fresh();
        });
    }
}
```

- [ ] **Step 4: Run** `pest tests/Feature/Pd` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: project details, rates and close/reopen actions`

---

### Task 5: Line and entry actions

**Files:** Create `app/Actions/Pd/{AddPdLine,UpdatePdLine,RemovePdLine,SavePdEntry,RemovePdEntry}.php`; Test `tests/Feature/Pd/LineAndEntryActionsTest.php`

**Interfaces — Produces:**
- `AddPdLine::handle(User, Project, PdGroup $group): PdLine` — name "New line", budget 0, appended (max position + 1).
- `UpdatePdLine::handle(User, PdLine, int $expectedVersion, array{name:string, reference:?string, budget_sen:int, scheduled_date:?string}): PdLine` (scheduled date ignored unless Collection).
- `RemovePdLine::handle(User, PdLine, int $expectedVersion): void` — `DomainException('Remove this line\'s documents first.')` when entries exist.
- `SavePdEntry::handle(User, PdLine, int $expectedLineVersion, ?PdEntry $entry, array{type:string, number:?string, date:string, amount_sen:int, note:?string}): PdEntry` — add when `$entry` null, else update; `InvalidArgumentException` for a type the group does not take or amount ≤ 0.
- `RemovePdEntry::handle(User, PdEntry, int $expectedLineVersion): void`.
- Every change bumps the line's `version` and sets `updated_by`.

- [ ] **Step 1: Failing test** `tests/Feature/Pd/LineAndEntryActionsTest.php`

```php
<?php

use App\Actions\Pd\{AddPdLine, RemovePdEntry, RemovePdLine, SavePdEntry, UpdatePdLine};
use App\Enums\{PdGroup, TenderStatus};
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{PdEntry, PdLine, Project, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;

function pdFixture(): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $pic->id]);

    return [$pic, Project::factory()->for($tender)->create()];
}

function entryData(array $o = []): array
{
    return array_merge(['type' => 'invoice', 'number' => 'INV-0012', 'date' => '2026-02-01', 'amount_sen' => 5000000, 'note' => null], $o);
}

it('adds, edits and removes a line, logging each change', function () {
    [$pic, $project] = pdFixture();

    $line = app(AddPdLine::class)->handle($pic, $project, PdGroup::Principal);
    expect($line->name)->toBe('New line')->and($line->budget_sen)->toBe(0)->and($line->version)->toBe(1);

    $line = app(UpdatePdLine::class)->handle($pic, $line, 1, ['name' => 'Dell laptops', 'reference' => 'Q-123', 'budget_sen' => 9000000, 'scheduled_date' => '2026-03-01']);
    expect($line->version)->toBe(2)->and($line->budget_sen)->toBe(9000000)
        ->and($line->scheduled_date)->toBeNull()   // only Collection lines have a scheduled date
        ->and($project->tender->activity->first()->description)->toBe('PD line updated — Principal: Dell laptops, budget RM 90,000.00');

    app(RemovePdLine::class)->handle($pic, $line, 2);
    expect(PdLine::count())->toBe(0)->and($project->tender->activity->first()->description)->toBe('PD line removed — Principal: Dell laptops');
});

it('appends new lines at the end', function () {
    [$pic, $project] = pdFixture();
    PdLine::factory()->for($project)->create(['position' => 7]);

    expect(app(AddPdLine::class)->handle($pic, $project, PdGroup::Tax)->position)->toBe(8);
});

it('records, edits and removes document entries', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create(['name' => 'Dell laptops', 'pd_group' => PdGroup::Principal]);

    $entry = app(SavePdEntry::class)->handle($pic, $line, 1, null, entryData());
    expect($entry->amount_sen)->toBe(5000000)->and($line->fresh()->version)->toBe(2)
        ->and($project->tender->activity->first()->description)->toBe('Invoice INV-0012 RM 50,000.00 recorded on Principal: Dell laptops');

    app(SavePdEntry::class)->handle($pic, $line->fresh(), 2, $entry, entryData(['type' => 'payment', 'amount_sen' => 2000000, 'number' => null]));
    expect($entry->fresh()->type->value)->toBe('payment')
        ->and($project->tender->activity->first()->description)->toBe('Payment RM 20,000.00 updated on Principal: Dell laptops');

    app(RemovePdEntry::class)->handle($pic, $entry->fresh(), 3);
    expect(PdEntry::count())->toBe(0)->and($line->fresh()->version)->toBe(4);
});

it('only accepts entry types that suit the line', function () {
    [$pic, $project] = pdFixture();
    $cost = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Principal]);
    $collection = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection]);

    expect(fn () => app(SavePdEntry::class)->handle($pic, $cost, 1, null, entryData(['type' => 'receipt'])))
        ->toThrow(InvalidArgumentException::class, 'Principal lines take PR, PO, Invoice and Payment entries only.')
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $collection, 1, null, entryData(['type' => 'po'])))
        ->toThrow(InvalidArgumentException::class, 'Collection lines take Invoice and Receipt entries only.')
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $cost, 1, null, entryData(['amount_sen' => 0])))
        ->toThrow(InvalidArgumentException::class, 'The amount must be more than RM 0.00.');
});

it('will not remove a line that still has entries', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create();
    PdEntry::factory()->for($line, 'line')->create();

    app(RemovePdLine::class)->handle($pic, $line, 1);
})->throws(DomainException::class, "Remove this line's documents first.");

it('only conflicts when the same line was changed by someone else', function () {
    [$pic, $project] = pdFixture();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);
    $a = PdLine::factory()->for($project)->create(['name' => 'A']);
    $b = PdLine::factory()->for($project)->create(['name' => 'B']);

    app(SavePdEntry::class)->handle($manager, $a, 1, null, entryData());
    app(SavePdEntry::class)->handle($pic, $b, 1, null, entryData());   // different line: fine

    expect(fn () => app(UpdatePdLine::class)->handle($pic, $a, 1, ['name' => 'A2', 'reference' => null, 'budget_sen' => 0, 'scheduled_date' => null]))
        ->toThrow(StalePdRecord::class, 'This line was changed by Ahmad Faizal — reload to see their changes.');
});

it('refuses every change on a closed project or by staff who are not the PIC', function () {
    [$pic, $project] = pdFixture();
    $line = PdLine::factory()->for($project)->create();
    $stranger = User::factory()->create();

    expect(fn () => app(AddPdLine::class)->handle($stranger, $project, PdGroup::Tax))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SavePdEntry::class)->handle($stranger, $line, 1, null, entryData()))->toThrow(AuthorizationException::class);

    $project->update(['closed_at' => now()]);
    expect(fn () => app(AddPdLine::class)->handle($pic, $project, PdGroup::Tax))->toThrow(ProjectLocked::class)
        ->and(fn () => app(SavePdEntry::class)->handle($pic, $line, 1, null, entryData()))->toThrow(ProjectLocked::class)
        ->and(fn () => app(RemovePdLine::class)->handle($pic, $line, 1))->toThrow(ProjectLocked::class);
});

it('does not let an entry be moved through a different line', function () {
    [$pic, $project] = pdFixture();
    $a = PdLine::factory()->for($project)->create();
    $b = PdLine::factory()->for($project)->create();
    $entry = PdEntry::factory()->for($a, 'line')->create();

    app(SavePdEntry::class)->handle($pic, $b, 1, $entry, entryData());
})->throws(InvalidArgumentException::class, 'That document belongs to a different line.');
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Actions/Pd/AddPdLine.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Enums\PdGroup;
use App\Models\{ActivityLog, PdLine, Project, User};
use Illuminate\Support\Facades\DB;

final class AddPdLine
{
    use GuardsProject;

    public function handle(User $actor, Project $project, PdGroup $group): PdLine
    {
        return DB::transaction(function () use ($actor, $project, $group) {
            $p = $this->lockOpenProject($actor, $project);
            $line = $p->lines()->create([
                'position' => (int) PdLine::where('project_id', $p->id)->max('position') + 1,
                'pd_group' => $group, 'name' => 'New line', 'budget_sen' => 0, 'updated_by' => $actor->id, 'version' => 1,
            ]);
            ActivityLog::record($p->tender, $actor, 'pd_line_added', "PD line added — {$group->label()}");

            return $line->fresh();
        });
    }
}
```

`app/Actions/Pd/UpdatePdLine.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, PdLine, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdatePdLine
{
    use GuardsProject;

    /** @param array{name:string, reference:?string, budget_sen:int, scheduled_date:?string} $data */
    public function handle(User $actor, PdLine $line, int $expectedVersion, array $data): PdLine
    {
        $name = trim($data['name']);
        if ($name === '' || $data['budget_sen'] < 0) {
            throw new InvalidArgumentException('A line needs a name and a budget of RM 0.00 or more.');
        }

        return DB::transaction(function () use ($actor, $line, $expectedVersion, $data, $name) {
            $l = $this->lockLine($actor, $line, $expectedVersion);
            $l->forceFill([
                'name' => mb_substr($name, 0, 255),
                'reference' => trim((string) $data['reference']) ?: null,
                'budget_sen' => $data['budget_sen'],
                'scheduled_date' => $l->pd_group->isCollection() ? ($data['scheduled_date'] ?: null) : null,
                'updated_by' => $actor->id,
                'version' => $l->version + 1,
            ])->save();
            ActivityLog::record($l->project->tender, $actor, 'pd_line_updated',
                "PD line updated — {$l->pd_group->label()}: {$l->name}, budget ".Money::format($l->budget_sen));

            return $l->fresh();
        });
    }
}
```

`app/Actions/Pd/RemovePdLine.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, PdLine, User};
use DomainException;
use Illuminate\Support\Facades\DB;

final class RemovePdLine
{
    use GuardsProject;

    public function handle(User $actor, PdLine $line, int $expectedVersion): void
    {
        DB::transaction(function () use ($actor, $line, $expectedVersion) {
            $l = $this->lockLine($actor, $line, $expectedVersion);
            if ($l->entries()->exists()) {
                throw new DomainException("Remove this line's documents first.");
            }
            $tender = $l->project->tender;
            $l->delete();
            ActivityLog::record($tender, $actor, 'pd_line_removed', "PD line removed — {$l->pd_group->label()}: {$l->name}");
        });
    }
}
```

`app/Actions/Pd/SavePdEntry.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Enums\PdEntryType;
use App\Models\{ActivityLog, PdEntry, PdLine, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SavePdEntry
{
    use GuardsProject;

    /** @param array{type:string, number:?string, date:string, amount_sen:int, note:?string} $data */
    public function handle(User $actor, PdLine $line, int $expectedLineVersion, ?PdEntry $entry, array $data): PdEntry
    {
        $type = PdEntryType::tryFrom($data['type']);
        if (! in_array($type, $line->pd_group->entryTypes(), true)) {
            $allowed = array_map(fn (PdEntryType $t) => $t->label(), $line->pd_group->entryTypes());
            $list = count($allowed) > 2
                ? implode(', ', array_slice($allowed, 0, -1)).' and '.end($allowed)
                : implode(' and ', $allowed);
            throw new InvalidArgumentException("{$line->pd_group->label()} lines take {$list} entries only.");
        }
        if ($data['amount_sen'] <= 0) {
            throw new InvalidArgumentException('The amount must be more than RM 0.00.');
        }
        if ($entry && $entry->pd_line_id !== $line->id) {
            throw new InvalidArgumentException('That document belongs to a different line.');
        }

        return DB::transaction(function () use ($actor, $line, $expectedLineVersion, $entry, $data, $type) {
            $l = $this->lockLine($actor, $line, $expectedLineVersion);
            $values = [
                'type' => $type, 'number' => trim((string) $data['number']) ?: null, 'date' => $data['date'],
                'amount_sen' => $data['amount_sen'], 'note' => trim((string) $data['note']) ?: null,
            ];
            if ($entry) {
                $entry->update($values);
                $saved = $entry;
            } else {
                $saved = $l->entries()->create($values + ['created_by' => $actor->id]);
            }
            $l->forceFill(['updated_by' => $actor->id, 'version' => $l->version + 1])->save();

            $doc = trim($type->label().' '.($values['number'] ?? ''));
            ActivityLog::record($l->project->tender, $actor, $entry ? 'pd_entry_updated' : 'pd_entry_added', sprintf(
                '%s %s %s on %s: %s', $doc, Money::format($values['amount_sen']), $entry ? 'updated' : 'recorded',
                $l->pd_group->label(), $l->name,
            ));

            return $saved->fresh();
        });
    }
}
```

`app/Actions/Pd/RemovePdEntry.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, PdEntry, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class RemovePdEntry
{
    use GuardsProject;

    public function handle(User $actor, PdEntry $entry, int $expectedLineVersion): void
    {
        DB::transaction(function () use ($actor, $entry, $expectedLineVersion) {
            $l = $this->lockLine($actor, $entry->line, $expectedLineVersion);
            $entry->delete();
            $l->forceFill(['updated_by' => $actor->id, 'version' => $l->version + 1])->save();
            ActivityLog::record($l->project->tender, $actor, 'pd_entry_removed', sprintf(
                '%s %s removed from %s: %s', trim($entry->type->label().' '.$entry->number), Money::format($entry->amount_sen),
                $l->pd_group->label(), $l->name,
            ));
        });
    }
}
```

- [ ] **Step 4: Run** `pest tests/Feature/Pd` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: PD line and document entry actions`

---

### Task 6: Finance settings page (Admin)

**Files:**
- Create: `app/Actions/Pd/{SaveProjectType,DeleteProjectType,SaveFinanceDefaults}.php`, `app/Livewire/FinanceSettings.php`, `resources/views/livewire/finance-settings.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/partials/sidebar.blade.php`
- Test: `tests/Feature/Livewire/FinanceSettingsTest.php`

**Interfaces — Produces:** route `finance.settings` (`/settings/finance`, `can:manage-finance`); `SaveProjectType::handle(User, ?ProjectType, string $name, int $marginBp, bool $active): ProjectType`; `DeleteProjectType::handle(User, ProjectType): void` (`DomainException` when used); `SaveFinanceDefaults::handle(User, int $chargeBp, int $shareBp): FinanceSetting`.

- [ ] **Step 1: Failing test** `tests/Feature/Livewire/FinanceSettingsTest.php`

```php
<?php

use App\Livewire\FinanceSettings;
use App\Models\{FinanceSetting, Project, ProjectType, User};
use Livewire\Livewire;

it('is for admins only', function () {
    $this->actingAs(User::factory()->manager()->create())->get(route('finance.settings'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('finance.settings'))->assertOk()->assertSee('Managed Services');
});

it('adds, edits and switches off project types', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->set('typeName', 'Cloud Services')->set('typeMargin', '18.5')->call('saveType')
        ->assertHasNoErrors();
    $type = ProjectType::where('name', 'Cloud Services')->first();
    expect($type->approved_margin_bp)->toBe(1850);

    Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->call('editType', $type->id)->assertSet('typeName', 'Cloud Services')->assertSet('typeMargin', '18.5')
        ->set('typeMargin', '20')->call('saveType')
        ->call('toggleType', $type->id);
    expect($type->fresh()->approved_margin_bp)->toBe(2000)->and($type->fresh()->is_active)->toBeFalse();
});

it('validates names and margins', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->set('typeName', 'Managed Services')->set('typeMargin', '100')->call('saveType')
        ->assertHasErrors(['typeName', 'typeMargin']);
});

it('deletes unused types only', function () {
    $admin = User::factory()->admin()->create();
    $used = ProjectType::where('name', 'Networking')->first();
    Project::factory()->create(['project_type_id' => $used->id]);
    $unused = ProjectType::where('name', 'Audio Visual')->first();

    Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->call('deleteType', $used->id)->assertSee('Networking is used by 1 project — switch it off instead.')
        ->call('deleteType', $unused->id);

    expect(ProjectType::find($used->id))->not->toBeNull()->and(ProjectType::find($unused->id))->toBeNull();
});

it('saves the company defaults', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->assertSet('charge', '9')->assertSet('share', '50')
        ->set('charge', '8.5')->set('share', '45')->call('saveDefaults')->assertHasNoErrors()
        ->assertSee('Saved. New projects will use these numbers.');

    expect(FinanceSetting::current()->project_charge_bp)->toBe(850)->and(FinanceSetting::current()->commission_share_bp)->toBe(4500);
});

it('refuses crafted calls from non-admins', function () {
    expect(fn () => app(\App\Actions\Pd\SaveFinanceDefaults::class)->handle(User::factory()->manager()->create(), 100, 100))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
```

- [ ] **Step 2: Run** — Expected: FAIL (route missing).

- [ ] **Step 3: Implement**

`app/Actions/Pd/SaveProjectType.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Models\{ProjectType, User};
use Illuminate\Support\Facades\Gate;

final class SaveProjectType
{
    public function handle(User $actor, ?ProjectType $type, string $name, int $marginBp, bool $active): ProjectType
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $type ??= new ProjectType;
        $type->fill(['name' => trim($name), 'approved_margin_bp' => $marginBp, 'is_active' => $active])->save();

        return $type->fresh();
    }
}
```

`app/Actions/Pd/DeleteProjectType.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Models\{ProjectType, User};
use DomainException;
use Illuminate\Support\Facades\Gate;

final class DeleteProjectType
{
    public function handle(User $actor, ProjectType $type): void
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $used = $type->projects()->count();
        if ($used > 0) {
            throw new DomainException("{$type->name} is used by {$used} ".($used === 1 ? 'project' : 'projects').' — switch it off instead.');
        }
        $type->delete();
    }
}
```

`app/Actions/Pd/SaveFinanceDefaults.php`:

```php
<?php

namespace App\Actions\Pd;

use App\Models\{FinanceSetting, User};
use Illuminate\Support\Facades\Gate;

final class SaveFinanceDefaults
{
    public function handle(User $actor, int $chargeBp, int $shareBp): FinanceSetting
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $s = FinanceSetting::current();
        $s->update(['project_charge_bp' => $chargeBp, 'commission_share_bp' => $shareBp]);

        return $s;
    }
}
```

`app/Livewire/FinanceSettings.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Pd\{DeleteProjectType, SaveFinanceDefaults, SaveProjectType};
use App\Models\{FinanceSetting, ProjectType};
use App\Rules\Percentage;
use App\Support\Percent;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Finance Settings')]
class FinanceSettings extends Component
{
    public ?int $editingType = null;
    public string $typeName = '';
    public string $typeMargin = '';
    public string $charge = '';
    public string $share = '';
    public ?string $notice = null;
    public ?string $problem = null;

    public function mount(): void
    {
        $this->authorize('manage-finance');
        $s = FinanceSetting::current();
        $this->charge = Percent::toInput($s->project_charge_bp);
        $this->share = Percent::toInput($s->commission_share_bp);
    }

    public function editType(int $id): void
    {
        $t = ProjectType::findOrFail($id);
        $this->editingType = $t->id;
        $this->typeName = $t->name;
        $this->typeMargin = Percent::toInput($t->approved_margin_bp);
        $this->resetValidation();
    }

    public function cancelType(): void
    {
        $this->reset('editingType', 'typeName', 'typeMargin');
    }

    public function saveType(): void
    {
        $this->validate([
            'typeName' => ['required', 'string', 'max:100', Rule::unique('project_types', 'name')->ignore($this->editingType)],
            'typeMargin' => ['required', new Percentage],
        ], [], ['typeName' => 'name', 'typeMargin' => 'approved margin']);
        $type = $this->editingType ? ProjectType::findOrFail($this->editingType) : null;
        app(SaveProjectType::class)->handle(auth()->user(), $type, $this->typeName, Percent::parseBp($this->typeMargin), $type?->is_active ?? true);
        $this->notice = 'Project type saved.';
        $this->cancelType();
    }

    public function toggleType(int $id): void
    {
        $t = ProjectType::findOrFail($id);
        app(SaveProjectType::class)->handle(auth()->user(), $t, $t->name, $t->approved_margin_bp, ! $t->is_active);
    }

    public function deleteType(int $id): void
    {
        $this->problem = null;
        try {
            app(DeleteProjectType::class)->handle(auth()->user(), ProjectType::findOrFail($id));
        } catch (DomainException $e) {
            $this->problem = $e->getMessage();
        }
    }

    public function saveDefaults(): void
    {
        $this->validate(['charge' => ['required', new Percentage], 'share' => ['required', new Percentage]], [],
            ['charge' => 'project charges', 'share' => 'commission share']);
        app(SaveFinanceDefaults::class)->handle(auth()->user(), Percent::parseBp($this->charge), Percent::parseBp($this->share));
        $this->notice = 'Saved. New projects will use these numbers.';
    }

    public function render()
    {
        return view('livewire.finance-settings', [
            'types' => ProjectType::withCount('projects')->orderBy('name')->get(),
        ]);
    }
}
```

`resources/views/livewire/finance-settings.blade.php`:

```blade
@php $input = 'rounded-lg border border-line bg-surface px-3 py-1.5 text-sm'; @endphp
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Finance Settings</h1>
    @if ($notice) <div class="rounded-lg bg-good-bg p-3 text-sm text-good-ink" role="status">{{ $notice }}</div> @endif
    @if ($problem) <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">{{ $problem }}</div> @endif

    <section class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Company defaults</h2>
        <p class="text-sm text-muted">Copied into each new project when its tender is awarded. Changing them does not alter running projects.</p>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label>Project charges %<input wire:model="charge" class="{{ $input }} ml-2 w-24"></label>
            <label>Commission share %<input wire:model="share" class="{{ $input }} ml-2 w-24"></label>
            <button type="button" wire:click="saveDefaults" class="rounded-lg bg-chip px-4 py-1.5 font-medium text-chip-ink hover:bg-chip-hover">Save</button>
        </div>
        @error('charge') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('share') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
    </section>

    <section class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Project types</h2>
        <p class="text-sm text-muted">The approved margin is the profit management expects; profit above it earns commission.</p>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label>Name<input wire:model="typeName" class="{{ $input }} ml-2 w-64"></label>
            <label>Approved margin %<input wire:model="typeMargin" class="{{ $input }} ml-2 w-24"></label>
            <button type="button" wire:click="saveType" class="rounded-lg bg-chip px-4 py-1.5 font-medium text-chip-ink hover:bg-chip-hover">
                {{ $editingType ? 'Save changes' : 'Add type' }}</button>
            @if ($editingType) <button type="button" wire:click="cancelType" class="rounded-lg px-3 py-1.5 hover:bg-hover">Cancel</button> @endif
        </div>
        @error('typeName') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        @error('typeMargin') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-subtle text-left text-xs uppercase text-muted">
                    <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2 text-right">Approved margin</th><th class="px-3 py-2 text-right">Projects</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody>
                @foreach ($types as $t)
                    <tr wire:key="type-{{ $t->id }}" class="border-t border-line">
                        <td class="px-3 py-2">{{ $t->name }}</td>
                        <td class="px-3 py-2 text-right">{{ \App\Support\Percent::format($t->approved_margin_bp) }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->projects_count }}</td>
                        <td class="px-3 py-2">{{ $t->is_active ? 'In use' : 'Switched off' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right text-xs">
                            <button type="button" wire:click="editType({{ $t->id }})" class="underline">Edit</button>
                            <button type="button" wire:click="toggleType({{ $t->id }})" class="ml-2 underline">{{ $t->is_active ? 'Switch off' : 'Switch on' }}</button>
                            @if ($t->projects_count === 0)
                                <button type="button" wire:click="deleteType({{ $t->id }})" wire:confirm="Delete {{ $t->name }}?" class="ml-2 text-bad-ink">Delete</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
```

Note: the "deletes unused types only" test calls `deleteType` on a used type even though the button is hidden — the server check is what matters.

`routes/web.php` — after the users route:

```php
    Route::get('/settings/finance', \App\Livewire\FinanceSettings::class)
        ->middleware('can:manage-finance')->name('finance.settings');
```

Sidebar — after the Manage Users link:

```blade
            @can('manage-finance')
                <a href="{{ route('finance.settings') }}" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-hover">Finance Settings</a>
            @endcan
```

- [ ] **Step 4: Run** `pest tests/Feature/Livewire/FinanceSettingsTest.php tests/Feature/LayoutTest.php` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: Admin finance settings (project types, company defaults)`

---

### Task 7: The PD tab

**Files:**
- Create: `app/Livewire/TenderPd.php`, `resources/views/livewire/tender-pd.blade.php`
- Modify: `app/Livewire/TenderDetail.php` (pass `hasPd`), `resources/views/livewire/tender-detail.blade.php` (tab + mount)
- Test: `tests/Feature/Livewire/TenderPdTest.php`

**Interfaces:**
- Consumes: all Task 1–5 actions and `Project::summary()`.
- Produces: Livewire `tender-pd` with prop `Tender $tender`; public state `group` (string), `rows` (`['l{id}' => [name, reference, budget, scheduled_date]]`), `versions` (`['l{id}' => int]`), `header` (`[project_type_id, start_date, end_date]`), `rates` (`[approved, charge, share]`), `projectVersion`, `openLine` (?int), `entry` (`[type, number, date, amount, note]`), `editingEntry` (?int), `problem` (?string); methods `selectGroup, addLine, removeLine(int), openDocuments(int), editEntry(int), cancelEntry, saveEntry, removeEntry(int), closeProject, reopenProject`; saves `rows.*`, `header.*`, `rates.*` from the `updated()` hook.

- [ ] **Step 1: Failing test** `tests/Feature/Livewire/TenderPdTest.php`

```php
<?php

use App\Enums\{PdGroup, TenderStatus};
use App\Livewire\{TenderDetail, TenderPd};
use App\Models\{PdEntry, PdLine, Project, ProjectType, Tender, User};
use Livewire\Livewire;

function pdTab(array $tenderAttrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create(array_merge(['pic_id' => $pic->id], $tenderAttrs));
    $project = Project::factory()->for($tender)->create(['approved_margin_bp' => 1500]);
    PdLine::factory()->for($project)->create(['position' => 1, 'pd_group' => PdGroup::Collection, 'name' => 'Contract value', 'budget_sen' => 125000000]);
    PdLine::factory()->for($project)->create(['position' => 2, 'pd_group' => PdGroup::Principal, 'name' => 'Dell laptops', 'budget_sen' => 65100000]);

    return [$pic, $tender->fresh(), $project->fresh()];
}

it('shows the PD tab only for awarded tenders with a project', function () {
    [$pic, $tender] = pdTab();
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('PD')->set('tab', 'pd')->assertSeeLivewire(TenderPd::class);

    $other = Tender::factory()->create(['pic_id' => $pic->id]);
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $other])->set('tab', 'pd')->assertDontSeeLivewire(TenderPd::class);
});

it('shows the P&L figures and lines', function () {
    [$pic, $tender] = pdTab();
    $collectionId = $tender->project->lines[0]->id;

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->assertSee('Profit & Loss')
        ->assertSee('RM 1,250,000.00')   // revenue
        ->assertSee('RM 112,500.00')     // 9% project charges
        ->assertSee('RM 486,500.00')     // GP = 1,250,000 − 651,000 − 112,500
        ->assertSee('Approved margin')
        ->assertSet("rows.l{$collectionId}.name", 'Contract value');
});

it('edits a line in place and saves it straight away', function () {
    [$pic, $tender, $project] = pdTab();
    $id = $project->lines[1]->id;

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->set('group', 'principal')
        ->set("rows.l{$id}.budget", '700,000')
        ->assertHasNoErrors()
        ->assertSet("versions.l{$id}", 2);

    expect(PdLine::find($id)->budget_sen)->toBe(70000000);
});

it('shows field errors and saves nothing for bad input', function () {
    [$pic, $tender, $project] = pdTab();
    $id = $project->lines[1]->id;

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->set("rows.l{$id}.budget", 'abc')->assertHasErrors("rows.l{$id}.budget")
        ->set("rows.l{$id}.name", '')->assertHasErrors("rows.l{$id}.name");

    expect(PdLine::find($id)->version)->toBe(1);
});

it('adds lines to the selected group and removes empty ones', function () {
    [$pic, $tender, $project] = pdTab();

    $c = Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->call('selectGroup', 'tax')->call('addLine');
    $line = PdLine::where('pd_group', 'tax')->first();
    expect($line)->not->toBeNull();

    $c->call('removeLine', $line->id);
    expect(PdLine::find($line->id))->toBeNull();
});

it('records, edits and removes documents on a line', function () {
    [$pic, $tender, $project] = pdTab();
    $id = $project->lines[1]->id;

    $c = Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->call('selectGroup', 'principal')->call('openDocuments', $id)
        ->set('entry.type', 'invoice')->set('entry.number', 'INV-9')->set('entry.date', '2026-02-01')->set('entry.amount', '50,000')
        ->call('saveEntry')->assertHasNoErrors()
        ->assertSee('INV-9');
    $entry = PdEntry::first();
    expect($entry->amount_sen)->toBe(5000000);

    $c->call('editEntry', $entry->id)->assertSet('entry.amount', '50000.00')
        ->set('entry.amount', '40000')->call('saveEntry');
    expect($entry->fresh()->amount_sen)->toBe(4000000);

    $c->call('removeEntry', $entry->id);
    expect(PdEntry::count())->toBe(0);
});

it('rejects a document type the line does not take', function () {
    [$pic, $tender, $project] = pdTab();
    $collectionId = $project->lines[0]->id;

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->call('openDocuments', $collectionId)
        ->set('entry.type', 'po')->set('entry.date', '2026-02-01')->set('entry.amount', '10')
        ->call('saveEntry')
        ->assertHasErrors('entry.type');
    expect(PdEntry::count())->toBe(0);
});

it('explains why a line with documents cannot be removed', function () {
    [$pic, $tender, $project] = pdTab();
    $line = $project->lines[1];
    PdEntry::factory()->for($line, 'line')->create();

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->call('removeLine', $line->id)->assertSee("Remove this line's documents first.");
});

it('keeps the typed value and explains when someone else changed the line', function () {
    [$pic, $tender, $project] = pdTab();
    $line = $project->lines[1];
    $c = Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender]);
    $line->update(['version' => 5, 'updated_by' => User::factory()->manager()->create(['name' => 'Ahmad Faizal'])->id]);

    $c->set("rows.l{$line->id}.name", 'Mine')
        ->assertSee('This line was changed by Ahmad Faizal')
        ->assertSet("rows.l{$line->id}.name", 'Mine');
});

it('picks a project type and dates, and lets managers change rates and close', function () {
    [$pic, $tender, $project] = pdTab();
    $type = ProjectType::where('name', 'Networking')->first();
    $manager = User::factory()->manager()->create();

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->set('header.project_type_id', (string) $type->id)
        ->assertSet('rates.approved', '20')
        ->set('header.start_date', '2026-01-01')->set('header.end_date', '2025-12-01')
        ->assertHasErrors('header.end_date')
        ->assertDontSee('Close project');

    Livewire::actingAs($manager)->test(TenderPd::class, ['tender' => $tender])
        ->set('rates.charge', '10')->assertHasNoErrors()
        ->call('closeProject')->assertSee('Closed')->assertSee('Reopen project')
        ->call('reopenProject')->assertSee('Close project');

    expect($project->fresh()->project_charge_bp)->toBe(1000);
});

it('refuses rate changes from staff even if the page is crafted', function () {
    [$pic, $tender] = pdTab();

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->set('rates.charge', '1')->assertForbidden();
});

it('is read-only for other staff and when closed', function () {
    [, $tender, $project] = pdTab();

    Livewire::actingAs(User::factory()->create())->test(TenderPd::class, ['tender' => $tender])
        ->assertDontSee('+ Add line');

    $project->update(['closed_at' => now()]);
    $pic = $tender->pic;
    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender->fresh()])
        ->assertDontSee('+ Add line')->assertSee('This project is closed');
});

it('shows the cash flow', function () {
    [$pic, $tender, $project] = pdTab();
    $project->lines[0]->update(['scheduled_date' => '2026-03-01']);

    Livewire::actingAs($pic)->test(TenderPd::class, ['tender' => $tender])
        ->assertSee('Cash flow')->assertSee('Mar 2026');
});
```

- [ ] **Step 2: Run** — Expected: FAIL (component missing).

- [ ] **Step 3: Implement** `app/Livewire/TenderPd.php`

```php
<?php

namespace App\Livewire;

use App\Actions\Pd\{AddPdLine, CloseProject, RemovePdEntry, RemovePdLine, ReopenProject, SavePdEntry, UpdatePdLine, UpdateProjectDetails, UpdateProjectRates};
use App\Enums\{PdEntryType, PdGroup};
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{PdEntry, PdLine, Project, ProjectType, Tender};
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
use DomainException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

/** The PD tab. Every edit is saved straight away. */
class TenderPd extends Component
{
    public Tender $tender;
    public string $group = 'collection';
    public array $rows = [];
    public array $versions = [];
    public array $header = [];
    public array $rates = [];
    public int $projectVersion = 1;
    public ?int $openLine = null;
    public array $entry = [];
    public ?int $editingEntry = null;
    public ?string $problem = null;

    public function mount(): void
    {
        $this->loadProject();
        $this->resetEntry();
    }

    private function project(): Project
    {
        return $this->tender->project()->firstOrFail();
    }

    private function loadProject(): void
    {
        $p = $this->project()->load('lines');
        $this->projectVersion = $p->version;
        $this->header = [
            'project_type_id' => (string) ($p->project_type_id ?? ''),
            'start_date' => $p->start_date?->format('Y-m-d') ?? '',
            'end_date' => $p->end_date?->format('Y-m-d') ?? '',
        ];
        $this->rates = [
            'approved' => Percent::toInput($p->approved_margin_bp),
            'charge' => Percent::toInput($p->project_charge_bp),
            'share' => Percent::toInput($p->commission_share_bp),
        ];
        $this->rows = [];
        $this->versions = [];
        foreach ($p->lines as $l) {
            $this->loadLine($l);
        }
    }

    private function loadLine(PdLine $l): void
    {
        $this->rows["l{$l->id}"] = [
            'name' => $l->name, 'reference' => (string) $l->reference,
            'budget' => Money::toInput($l->budget_sen), 'scheduled_date' => $l->scheduled_date?->format('Y-m-d') ?? '',
        ];
        $this->versions["l{$l->id}"] = $l->version;
    }

    private function resetEntry(): void
    {
        $this->editingEntry = null;
        $this->entry = ['type' => '', 'number' => '', 'date' => '', 'amount' => '', 'note' => ''];
    }

    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        match ($parts[0]) {
            'rows' => isset($parts[1]) ? $this->saveLine((int) substr($parts[1], 1)) : null,
            'header' => $this->saveHeader(),
            'rates' => $this->saveRates(),
            default => null,
        };
    }

    public function selectGroup(string $group): void
    {
        $this->group = PdGroup::tryFrom($group)?->value ?? 'collection';
        $this->openLine = null;
        $this->resetEntry();
    }

    private function saveLine(int $id): void
    {
        $key = "l{$id}";
        $this->validate([
            "rows.$key.name" => ['required', 'string', 'max:255'],
            "rows.$key.reference" => ['nullable', 'string', 'max:100'],
            "rows.$key.budget" => ['required', new MoneyAmount],
            "rows.$key.scheduled_date" => ['nullable', 'date_format:Y-m-d'],
        ], [], ["rows.$key.name" => 'name', "rows.$key.reference" => 'reference', "rows.$key.budget" => 'budget', "rows.$key.scheduled_date" => 'scheduled date']);
        $row = $this->rows[$key];
        $this->run(function () use ($id, $key, $row) {
            $line = app(UpdatePdLine::class)->handle(auth()->user(), PdLine::findOrFail($id), $this->versions[$key], [
                'name' => $row['name'], 'reference' => $row['reference'], 'budget_sen' => Money::parse($row['budget']) ?? 0,
                'scheduled_date' => $row['scheduled_date'] ?: null,
            ]);
            $this->versions[$key] = $line->version;
        });
    }

    private function saveHeader(): void
    {
        $this->validate([
            'header.project_type_id' => ['nullable', Rule::exists('project_types', 'id')],
            'header.start_date' => ['nullable', 'date_format:Y-m-d'],
            'header.end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:header.start_date'],
        ], [], ['header.project_type_id' => 'project type', 'header.start_date' => 'start date', 'header.end_date' => 'end date']);
        $this->run(function () {
            $p = app(UpdateProjectDetails::class)->handle(auth()->user(), $this->project(), $this->projectVersion, [
                'project_type_id' => $this->header['project_type_id'] !== '' ? (int) $this->header['project_type_id'] : null,
                'start_date' => $this->header['start_date'] ?: null, 'end_date' => $this->header['end_date'] ?: null,
            ]);
            $this->projectVersion = $p->version;
            $this->rates['approved'] = Percent::toInput($p->approved_margin_bp);
        });
    }

    private function saveRates(): void
    {
        $this->authorize('manage-projects');
        $this->validate(['rates.approved' => ['required', new Percentage], 'rates.charge' => ['required', new Percentage], 'rates.share' => ['required', new Percentage]],
            [], ['rates.approved' => 'approved margin', 'rates.charge' => 'project charges', 'rates.share' => 'commission share']);
        $this->run(function () {
            $p = app(UpdateProjectRates::class)->handle(auth()->user(), $this->project(), $this->projectVersion,
                Percent::parseBp($this->rates['approved']), Percent::parseBp($this->rates['charge']), Percent::parseBp($this->rates['share']));
            $this->projectVersion = $p->version;
        });
    }

    public function addLine(): void
    {
        $this->run(function () {
            $line = app(AddPdLine::class)->handle(auth()->user(), $this->project(), PdGroup::from($this->group));
            $this->loadLine($line);
        });
    }

    public function removeLine(int $id): void
    {
        $this->run(function () use ($id) {
            app(RemovePdLine::class)->handle(auth()->user(), PdLine::findOrFail($id), $this->versions["l{$id}"] ?? 0);
            unset($this->rows["l{$id}"], $this->versions["l{$id}"]);
            if ($this->openLine === $id) {
                $this->openLine = null;
            }
        });
    }

    public function openDocuments(int $id): void
    {
        $this->openLine = $this->openLine === $id ? null : $id;
        $this->resetEntry();
        $this->resetValidation();
    }

    public function editEntry(int $id): void
    {
        $e = PdEntry::findOrFail($id);
        $this->openLine = $e->pd_line_id;
        $this->editingEntry = $e->id;
        $this->entry = ['type' => $e->type->value, 'number' => (string) $e->number, 'date' => $e->date->format('Y-m-d'),
            'amount' => Money::toInput($e->amount_sen), 'note' => (string) $e->note];
    }

    public function cancelEntry(): void
    {
        $this->resetEntry();
        $this->resetValidation();
    }

    public function saveEntry(): void
    {
        $line = PdLine::findOrFail($this->openLine);
        $this->validate([
            'entry.type' => ['required', Rule::in(array_map(fn (PdEntryType $t) => $t->value, $line->pd_group->entryTypes()))],
            'entry.number' => ['nullable', 'string', 'max:100'],
            'entry.date' => ['required', 'date_format:Y-m-d'],
            'entry.amount' => ['required', new MoneyAmount(mustBePositive: true)],
            'entry.note' => ['nullable', 'string', 'max:255'],
        ], ['entry.type.in' => 'Choose a document type this line takes.'],
            ['entry.type' => 'type', 'entry.number' => 'number', 'entry.date' => 'date', 'entry.amount' => 'amount', 'entry.note' => 'note']);
        $this->run(function () use ($line) {
            app(SavePdEntry::class)->handle(auth()->user(), $line, $this->versions["l{$line->id}"],
                $this->editingEntry ? PdEntry::findOrFail($this->editingEntry) : null, [
                    'type' => $this->entry['type'], 'number' => $this->entry['number'], 'date' => $this->entry['date'],
                    'amount_sen' => Money::parse($this->entry['amount']), 'note' => $this->entry['note'],
                ]);
            $this->versions["l{$line->id}"] = $line->fresh()->version;
            $this->resetEntry();
        });
    }

    public function removeEntry(int $id): void
    {
        $e = PdEntry::findOrFail($id);
        $this->run(function () use ($e) {
            app(RemovePdEntry::class)->handle(auth()->user(), $e, $this->versions["l{$e->pd_line_id}"] ?? 0);
            $this->versions["l{$e->pd_line_id}"] = $e->line->fresh()->version;
            if ($this->editingEntry === $e->id) {
                $this->resetEntry();
            }
        });
    }

    public function closeProject(): void
    {
        $this->run(fn () => $this->projectVersion = app(CloseProject::class)->handle(auth()->user(), $this->project(), $this->projectVersion)->version);
    }

    public function reopenProject(): void
    {
        $this->run(fn () => $this->projectVersion = app(ReopenProject::class)->handle(auth()->user(), $this->project(), $this->projectVersion)->version);
    }

    /** Runs an action; refusals become a message and typed values stay on screen. */
    private function run(callable $action): void
    {
        $this->problem = null;
        try {
            $action();
        } catch (StalePdRecord|ProjectLocked|DomainException $e) {
            $this->problem = $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $this->addError('form', $e->getMessage());
        }
    }

    public function render()
    {
        $project = $this->project()->load(['lines', 'projectType', 'closedBy']);
        $summary = $project->summary();
        $canEdit = Gate::allows('update', $this->tender) && $project->isOpen();

        return view('livewire.tender-pd', [
            'project' => $project,
            'summary' => $summary,
            'groupLines' => array_values(array_filter($summary['lines'], fn ($l) => $l['group'] === $this->group)),
            'counts' => array_count_values(array_column($summary['lines'], 'group')),
            'groupEnum' => PdGroup::from($this->group),
            'entries' => $this->openLine ? PdEntry::where('pd_line_id', $this->openLine)->orderBy('date')->orderBy('id')->get() : collect(),
            'types' => ProjectType::where('is_active', true)->orWhere('id', $project->project_type_id)->orderBy('name')->get(),
            'canEdit' => $canEdit,
            'canManage' => Gate::allows('manage-projects'),
        ]);
    }
}
```

`resources/views/livewire/tender-pd.blade.php`:

```blade
@php
    use App\Support\{Money, Percent};
    $in = 'rounded border border-line bg-surface px-1.5 py-1 text-sm disabled:border-transparent disabled:bg-transparent';
    $card = 'rounded-xl border border-line bg-surface p-4';
    $b = $summary['pnl']['budget'];
    $a = $summary['pnl']['actual'];
    $collection = $groupEnum->isCollection();
    $tone = ['pending' => 'bg-subtle text-muted', 'pr' => 'bg-info-bg text-info-ink', 'po' => 'bg-info-bg text-info-ink',
        'invoiced' => 'bg-warn-bg text-warn-ink', 'partly' => 'bg-warn-bg text-warn-ink', 'paid' => 'bg-good-bg text-good-ink',
        'received' => 'bg-good-bg text-good-ink', 'overpaid' => 'bg-warn-bg text-warn-ink'];
    $neg = fn (int $v) => $v < 0 ? 'text-bad-ink' : '';
@endphp
<section class="space-y-4">
    @if ($problem)
        <div class="flex items-center justify-between gap-3 rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $problem }}</span>
            <a href="{{ route('tenders.show', $tender) }}?tab=pd" class="shrink-0 font-medium underline">Reload</a>
        </div>
    @endif
    @error('form') <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">{{ $message }}</div> @enderror
    @if (! $project->isOpen())
        <div class="rounded-lg bg-subtle p-3 text-sm">This project is closed{{ $project->closedBy ? ' by '.$project->closedBy->name : '' }}
            on {{ $project->closed_at->timezone(App\Support\MalaysiaTime::TZ)->format('d M Y') }}. A Manager can reopen it.</div>
    @endif

    {{-- Header --}}
    <div class="{{ $card }} flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">Project type
            <select wire:model.live="header.project_type_id" @disabled(! $canEdit) class="{{ $in }} w-60">
                <option value="">Choose…</option>
                @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }} ({{ Percent::format($t->approved_margin_bp, 0) }})</option> @endforeach
            </select>
            @error('header.project_type_id') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
        </label>
        @foreach (['approved' => 'Approved margin %', 'charge' => 'Project charges %', 'share' => 'Commission share %'] as $k => $label)
            <label class="flex flex-col gap-1">{{ $label }}
                <input wire:model.live.blur="rates.{{ $k }}" @disabled(! ($canManage && $project->isOpen())) class="{{ $in }} w-24">
                @error("rates.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
        @endforeach
        <label class="flex flex-col gap-1">Start date
            <input type="date" wire:model.live.blur="header.start_date" @disabled(! $canEdit) class="{{ $in }}">
            @error('header.start_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1">End date
            <input type="date" wire:model.live.blur="header.end_date" @disabled(! $canEdit) class="{{ $in }}">
            @error('header.end_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
        </label>
        @if ($canManage)
            <span class="ml-auto">
                @if ($project->isOpen())
                    <button type="button" wire:click="closeProject" wire:confirm="Close this project? It becomes read-only." class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">Close project</button>
                @else
                    <span class="mr-2 rounded-full bg-subtle px-2 py-0.5 text-xs">Closed</span>
                    <button type="button" wire:click="reopenProject" class="rounded-lg border border-line px-3 py-1.5 hover:bg-hover">Reopen project</button>
                @endif
            </span>
        @endif
    </div>

    {{-- Profit & Loss --}}
    <div class="{{ $card }}">
        <h3 class="mb-2 font-medium">Profit &amp; Loss</h3>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead class="text-left text-xs uppercase text-muted"><tr><th class="py-1">Line</th><th class="py-1 text-right">Budget</th><th class="py-1 text-right">Actual</th></tr></thead>
                <tbody>
                @foreach ([
                    ['Revenue', 'revenue', 'font-medium', null],
                    ['Less: Cost of sales', 'cost_of_sales', '', 'Principal, distributor, partner'],
                    ['Less: Other costs', 'other_costs', '', 'Finance, tax, misc, internal resources'],
                    ['Less: Project charges', 'charges', '', Percent::format($project->project_charge_bp).' of revenue'],
                    ['Gross profit (GP)', 'gp', 'font-semibold', null],
                    ['Commission', 'commission', '', '(GP − approved margin) × '.Percent::format($project->commission_share_bp, 0)],
                    ['Net profit', 'net', 'font-semibold', null],
                ] as [$label, $key, $weight, $hint])
                    <tr class="border-t border-line {{ $weight }}">
                        <td class="py-1.5">{{ $label }} @if ($hint) <span class="text-xs font-normal text-muted">({{ $hint }})</span> @endif</td>
                        @foreach ([$b, $a] as $col)
                            <td class="whitespace-nowrap py-1.5 text-right {{ $neg($col[$key]) }}">{{ Money::format($col[$key]) }}
                                @if (in_array($key, ['gp', 'net'], true)) <span class="text-xs font-normal text-muted">({{ Percent::format($col[$key === 'gp' ? 'gp_bp' : 'net_bp']) }})</span> @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-line pt-3 text-sm">
            <div><p class="text-xs uppercase text-muted">Approved margin</p>
                <p class="text-lg font-semibold">{{ Money::format($b['approved_sen']) }}</p>
                <p class="text-xs text-muted">{{ Percent::format($project->approved_margin_bp) }} · {{ $project->projectType?->name ?? 'No project type yet' }}</p></div>
            @if ($summary['below_margin'])
                <p class="rounded-lg bg-bad-bg px-3 py-2 text-bad-ink">⚠ Actual gross profit ({{ Percent::format($a['gp_bp']) }}) is below the approved margin ({{ Percent::format($project->approved_margin_bp) }}).</p>
            @endif
        </div>
    </div>

    {{-- Lines --}}
    <div class="{{ $card }} space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="mr-2 font-medium">Cost lines</h3>
            @foreach (App\Enums\PdGroup::cases() as $g)
                <button type="button" wire:click="selectGroup('{{ $g->value }}')" @class([
                    'rounded-full border px-3 py-1 text-xs',
                    'border-chip bg-chip text-chip-ink' => $group === $g->value,
                    'border-line hover:bg-hover' => $group !== $g->value,
                ])>{{ $g->label() }} @if ($counts[$g->value] ?? 0) <span class="opacity-70">{{ $counts[$g->value] }}</span> @endif</button>
            @endforeach
            @if ($canEdit)
                <button type="button" wire:click="addLine" class="ml-auto rounded-lg bg-chip px-3 py-1.5 text-sm font-medium text-chip-ink hover:bg-chip-hover">+ Add line</button>
            @endif
        </div>
        <p class="text-xs text-muted">{{ $groupEnum->description() }}</p>

        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[1100px] text-sm">
                <thead class="bg-subtle text-left text-xs uppercase text-muted">
                    <tr>
                        <th class="px-2 py-2">Name</th>
                        @if ($collection)
                            <th class="px-2">Scheduled date</th><th class="px-2 text-right">Scheduled amount</th><th class="px-2 text-right">Invoiced</th>
                            <th class="px-2 text-right">Received</th><th class="px-2 text-right">Still owed</th>
                        @else
                            <th class="px-2">Reference</th><th class="px-2 text-right">Budget</th><th class="px-2 text-right">PR</th><th class="px-2 text-right">PO</th>
                            <th class="px-2 text-right">Invoiced</th><th class="px-2 text-right">Paid</th><th class="px-2 text-right">Still owed</th><th class="px-2 text-right">Variance</th>
                        @endif
                        <th class="px-2">Status</th><th class="px-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($groupLines as $l)
                    @php $key = 'l'.$l['id']; @endphp
                    <tr wire:key="pd-{{ $l['id'] }}" class="border-t border-line align-top">
                        <td class="px-2 py-1">
                            <input wire:model.live.blur="rows.{{ $key }}.name" @disabled(! $canEdit) class="{{ $in }} w-full min-w-56" aria-label="Name">
                            @error("rows.$key.name") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                        </td>
                        @if ($collection)
                            <td class="px-2 py-1"><input type="date" wire:model.live.blur="rows.{{ $key }}.scheduled_date" @disabled(! $canEdit) class="{{ $in }}" aria-label="Scheduled date"></td>
                            <td class="px-2 py-1 text-right">
                                <input wire:model.live.blur="rows.{{ $key }}.budget" @disabled(! $canEdit) class="{{ $in }} w-32 text-right" aria-label="Scheduled amount">
                                @error("rows.$key.budget") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($l['invoiced']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($l['received']) }}</td>
                            <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($l['owed']) }}</td>
                        @else
                            <td class="px-2 py-1"><input wire:model.live.blur="rows.{{ $key }}.reference" @disabled(! $canEdit) class="{{ $in }} w-28" aria-label="Reference"></td>
                            <td class="px-2 py-1 text-right">
                                <input wire:model.live.blur="rows.{{ $key }}.budget" @disabled(! $canEdit) class="{{ $in }} w-32 text-right" aria-label="Budget">
                                @error("rows.$key.budget") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                            </td>
                            @foreach (['pr', 'po', 'invoiced', 'paid', 'owed'] as $k)
                                <td class="whitespace-nowrap px-2 py-1 text-right">{{ Money::format($l[$k]) }}</td>
                            @endforeach
                            <td class="whitespace-nowrap px-2 py-1 text-right {{ $neg($l['variance']) }}">{{ Money::format($l['variance']) }}</td>
                        @endif
                        <td class="whitespace-nowrap px-2 py-1">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$l['status']] }}">{{ $l['status_label'] }}</span>
                            @if ($l['over_budget']) <span class="ml-1 rounded-full bg-bad-bg px-2 py-0.5 text-xs text-bad-ink">Over budget</span> @endif
                        </td>
                        <td class="whitespace-nowrap px-2 py-1 text-right text-xs">
                            <button type="button" wire:click="openDocuments({{ $l['id'] }})" class="underline">Documents ({{ count($l['entries']) }})</button>
                            @if ($canEdit && count($l['entries']) === 0)
                                <button type="button" wire:click="removeLine({{ $l['id'] }})" wire:confirm="Remove this line?" class="ml-2 text-bad-ink">Remove</button>
                            @endif
                        </td>
                    </tr>
                    @if ($openLine === $l['id'])
                        <tr wire:key="docs-{{ $l['id'] }}" class="bg-subtle">
                            <td colspan="{{ $collection ? 8 : 11 }}" class="space-y-2 px-4 py-3">
                                <table class="w-full text-xs">
                                    <thead class="text-left text-muted"><tr><th class="py-1">Type</th><th>Number</th><th>Date</th><th class="text-right">Amount</th><th>Note</th><th></th></tr></thead>
                                    <tbody>
                                    @forelse ($entries as $e)
                                        <tr wire:key="entry-{{ $e->id }}" class="border-t border-line">
                                            <td class="py-1">{{ $e->type->label() }}</td><td>{{ $e->number ?? '—' }}</td><td>{{ $e->date->format('d M Y') }}</td>
                                            <td class="text-right">{{ Money::format($e->amount_sen) }}</td><td>{{ $e->note }}</td>
                                            <td class="text-right">@if ($canEdit)
                                                <button type="button" wire:click="editEntry({{ $e->id }})" class="underline">Edit</button>
                                                <button type="button" wire:click="removeEntry({{ $e->id }})" wire:confirm="Remove this document?" class="ml-2 text-bad-ink">Remove</button>
                                            @endif</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="py-2 text-muted">No documents yet.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                                @if ($canEdit)
                                    <div class="flex flex-wrap items-end gap-2 text-xs">
                                        <select wire:model="entry.type" class="{{ $in }}" aria-label="Document type">
                                            <option value="">Type…</option>
                                            @foreach ($groupEnum->entryTypes() as $t) <option value="{{ $t->value }}">{{ $t->label() }}</option> @endforeach
                                        </select>
                                        <input wire:model="entry.number" placeholder="Number" class="{{ $in }} w-32" aria-label="Document number">
                                        <input type="date" wire:model="entry.date" class="{{ $in }}" aria-label="Document date">
                                        <input wire:model="entry.amount" placeholder="Amount" class="{{ $in }} w-32 text-right" aria-label="Amount">
                                        <input wire:model="entry.note" placeholder="Note (optional)" class="{{ $in }} w-48" aria-label="Note">
                                        <button type="button" wire:click="saveEntry" class="rounded-lg bg-chip px-3 py-1 font-medium text-chip-ink hover:bg-chip-hover">{{ $editingEntry ? 'Save' : 'Add' }}</button>
                                        @if ($editingEntry) <button type="button" wire:click="cancelEntry" class="rounded-lg px-2 py-1 hover:bg-hover">Cancel</button> @endif
                                    </div>
                                    @foreach (['entry.type', 'entry.date', 'entry.amount', 'entry.number', 'entry.note'] as $f)
                                        @error($f) <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="11" class="px-3 py-6 text-center text-muted">No {{ strtolower($groupEnum->label()) }} lines yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Cash flow --}}
    <div class="{{ $card }}">
        <h3 class="mb-2 font-medium">Cash flow</h3>
        @if ($summary['cash_flow'] === [])
            <p class="text-sm text-muted">Add a start date, scheduled dates or documents to see the cash flow.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead class="text-left text-xs uppercase text-muted"><tr><th class="py-1">Month</th><th class="py-1 text-right">Expected in</th><th class="py-1 text-right">Received</th><th class="py-1 text-right">Paid out</th><th class="py-1 text-right">Balance</th></tr></thead>
                    <tbody>
                    @foreach ($summary['cash_flow'] as $m)
                        <tr class="border-t border-line">
                            <td class="py-1">{{ \Carbon\CarbonImmutable::parse($m['month'].'-01')->format('M Y') }}</td>
                            @foreach (['expected_in', 'received', 'paid_out'] as $k)
                                <td class="py-1 text-right">{{ $m[$k] ? Money::format($m[$k]) : '—' }}</td>
                            @endforeach
                            <td class="py-1 text-right font-medium {{ $neg($m['balance']) }}">{{ Money::format($m['balance']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($summary['duration_pct'] !== null)
            <div class="mt-3 text-xs text-muted">
                <div class="flex justify-between"><span>Project duration</span><span>{{ $summary['duration_pct'] }}%</span></div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-subtle"><div class="h-full bg-good-ink" style="width: {{ $summary['duration_pct'] }}%"></div></div>
            </div>
        @endif
    </div>
</section>
```

`TenderDetail::render()` — add `'hasPd' => $this->tender->status === \App\Enums\TenderStatus::Awarded && $this->tender->project()->exists(),`.

`tender-detail.blade.php`:
- Tabs: build the array before the `@foreach`:

```blade
    @php
        $tabs = ['overview' => 'Overview', 'costing' => 'Costing'] + ($hasPd ? ['pd' => 'PD'] : []) + ['documents' => 'Documents', 'activity' => 'Activity'];
    @endphp
```
  and loop `@foreach ($tabs as $key => $label)`.
- Content: change `@if ($tab !== 'costing')` to `@if (! in_array($tab, ['costing', 'pd'], true) || ($tab === 'pd' && ! $hasPd))`, and after the costing `<div>` add:

```blade
    @if ($tab === 'pd' && $hasPd)
        <livewire:tender-pd :tender="$tender" wire:key="pd-{{ $tender->id }}" />
    @endif
```

- [ ] **Step 4: Run** `pest tests/Feature/Livewire` — Expected: PASS.
- [ ] **Step 5: Browser check** — award a tender (or use the seeded one in Task 8), open the PD tab: edit a budget (totals refresh), add a line in Principal, add PR/PO/invoice/payment entries (status moves along), receipt on Collection, wrong type refused, remove-with-documents refused, project type + dates, cash flow, close/reopen as Manager, read-only as other staff, dark mode, 375px (no sideways page scroll).
- [ ] **Step 6: Commit** — `feat: PD tab (P&L, cost lines, documents, cash flow)`

---

### Task 8: Awarded list column, sample PD, walkthrough

**Files:** Modify `app/Queries/TenderListQuery.php`, `resources/views/livewire/tender-list.blade.php`, `database/seeders/DatabaseSeeder.php`, `README.md`; Test `tests/Feature/Pd/AwardedListAndSeedTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

use App\Enums\{PdEntryType, PdGroup, TenderStatus};
use App\Livewire\TenderList;
use App\Models\{PdEntry, PdLine, Project, Tender, User};
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

it('shows actual GP % and closed projects on the Awarded list', function () {
    $this->actingAs(User::factory()->create());
    $project = Project::factory()->create(['approved_margin_bp' => 1500, 'closed_at' => now()]);
    $rev = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection]);
    $cost = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($rev, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 100000]);
    PdEntry::factory()->for($cost, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 85000]);
    Tender::factory()->status(TenderStatus::Awarded)->create(); // no project → dash

    Livewire::test(TenderList::class, ['list' => 'awarded'])
        ->assertSee('Actual GP')->assertSee('6.0%')   // (1,000 − 850 − 90) ÷ 1,000
        ->assertSee('Closed');
});

it('seeds a sample PD on an awarded tender', function () {
    $this->seed(DatabaseSeeder::class);

    $project = Tender::where('wo_number', '200-15122025-006')->first()->project;
    $s = $project->summary('2026-03-01');

    expect($project->projectType->name)->toBe('Managed Services')
        ->and($s['pnl']['budget']['revenue'])->toBe(125000000)
        ->and(collect($s['lines'])->where('group', 'collection')->count())->toBe(3)
        ->and($s['pnl']['actual']['revenue'])->toBe(75000000)
        ->and(Tender::where('status', TenderStatus::Awarded)->whereDoesntHave('project')->count())->toBe(0);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`TenderListQuery::build()` — after the Done `when`, add:

```php
            ->when($status === TenderStatus::Awarded, fn (Builder $q) => $q->with('project.lines'))
```

`tender-list.blade.php` — header: add an Awarded branch before `@else` (Documents):

```blade
                    @elseif ($status === TenderStatus::Awarded)
                        <th class="px-3 py-2 text-right">Submit Price</th>
                        <th class="px-3 py-2 text-right" title="Actual gross profit from the PD">Actual GP</th>
```
  row:

```blade
                    @elseif ($status === TenderStatus::Awarded)
                        @php $pd = $t->project?->summary(); @endphp
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->submitted_price_sen) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($pd)
                                <span @class(['text-bad-ink' => $pd['below_margin']])>{{ \App\Support\Percent::format($pd['pnl']['actual']['gp_bp']) }}</span>
                                @if (! $t->project->isOpen()) <span class="ml-1 rounded-full bg-subtle px-2 py-0.5 text-xs">Closed</span> @endif
                            @else — @endif
                        </td>
```

`DatabaseSeeder::run()` — after `self::seedJpninCosting(...)`, add `self::seedSamplePd();`, and add:

```php
    /** Every awarded sample tender gets a project; 200-15122025-006 gets the prototype's PD. */
    private static function seedSamplePd(): void
    {
        $pic = User::where('email', 'ahmad.faizal@cmt.test')->firstOrFail();
        foreach (Tender::where('status', TenderStatus::Awarded)->get() as $t) {
            app(\App\Actions\Pd\CreateProjectFromCosting::class)->handle($t, $pic);
        }

        $project = Tender::where('wo_number', '200-15122025-006')->firstOrFail()->project;
        $project->lines()->delete();
        $project->update(['project_type_id' => \App\Models\ProjectType::where('name', 'Managed Services')->value('id'),
            'approved_margin_bp' => 1500, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30']);

        $add = function (string $group, string $name, int $budget, array $entries = [], ?string $scheduled = null, ?string $ref = null) use ($project, $pic) {
            static $position = 0;
            $line = $project->lines()->create(['position' => ++$position, 'pd_group' => $group, 'name' => $name, 'reference' => $ref,
                'budget_sen' => $budget, 'scheduled_date' => $scheduled, 'updated_by' => $pic->id]);
            foreach ($entries as [$type, $number, $date, $amount]) {
                $line->entries()->create(['type' => $type, 'number' => $number, 'date' => $date, 'amount_sen' => $amount, 'created_by' => $pic->id]);
            }
        };
        $add('collection', 'Payment 1 (Down Payment)', 37500000, [['invoice', 'INV-001', '2026-01-20', 37500000], ['receipt', 'RCV-001', '2026-01-28', 37500000]], '2026-01-20');
        $add('collection', 'Payment 2 (Progress)', 37500000, [['invoice', 'INV-002', '2026-04-15', 37500000]], '2026-04-15');
        $add('collection', 'Payment 3 (Final)', 50000000, [], '2026-07-15');
        $add('principal', 'Workstations and laptops', 45000000, [['pr', 'PR-101', '2026-01-05', 45000000], ['po', 'PO-101', '2026-01-08', 45000000],
            ['invoice', 'SI-5531', '2026-02-10', 45000000], ['payment', 'PV-201', '2026-02-25', 26300000]], null, 'Dell');
        $add('distributor', 'Software licences', 20100000, [['pr', 'PR-102', '2026-01-05', 20100000], ['po', 'PO-102', '2026-01-09', 20100000]]);
        $add('internal', 'Project engineer (6 manmonths)', 9000000);
        $add('tax', 'SST on costs', 4200000);
    }
```

(Import `App\Models\User` if not already imported — it is, for the people loop.) Note on the expected test values: budget revenue 375,000 + 375,000 + 500,000 = RM 1,250,000; actual revenue (invoices) 375,000 + 375,000 = RM 750,000 → `75000000`.

README — add a "PD (project finance)" section after "Costing":

```markdown
## PD (project finance)

- When a tender is marked **Awarded** it gets a **PD tab**. Its budget is copied from the costing
  (each costing line's Group decides where it goes) plus a "Contract value" collection line.
- Budget vs actual Profit & Loss: GP = revenue − costs − project charges; commission =
  (GP − approved margin) × commission share; net = GP − commission. Actual figures use invoices.
- Each line keeps its documents (PR, PO, invoice, payment; or invoice and receipt for collections).
  Every change saves immediately; two people only clash if they edit the same line.
- Admins manage project types (approved margins) and company defaults under **Finance Settings**.
- Managers/Admins can adjust a project's rates and close/reopen it.
```

- [ ] **Step 4: Run full suite with coverage** — `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest --coverage --min=80` — Expected: PASS.
- [ ] **Step 5: Dev database** (do **not** reseed — it holds ~211k real tenders): `php artisan migrate --force` (already done in Task 1), then create empty projects for tenders already Awarded:

```bash
MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan tinker --execute='$u = App\Models\User::where("role","admin")->first(); foreach (App\Models\Tender::where("status","awarded")->whereDoesntHave("project")->get() as $t) { app(App\Actions\Pd\CreateProjectFromCosting::class)->handle($t, $u); echo $t->wo_number, PHP_EOL; }'
```
  Expected: the awarded WO numbers printed once; running it again prints nothing.
- [ ] **Step 6: Full browser walkthrough** (Playwright MCP): Awarded list Actual GP column; PD tab on an awarded tender — project type picks margin, dates, budgets, Principal line with PR → PO → invoice → payment (status moves, still-owed and variance), collection invoice + receipt, wrong type refused, cash flow months/balance, duration bar, close/reopen as Admin, Finance Settings add/edit/switch off/delete-unused, costing Group column, dark mode, 375px.
- [ ] **Step 7: Commit** — `feat: Awarded list Actual GP, sample PD and docs`

---

## Self-review notes (spec coverage)

| Spec section | Task |
|---|---|
| §2 project_types (22), finance_settings, projects, pd_lines, pd_entries, costing group | 1, 3 |
| §2 award hook (groups, monthly totals, Contract value, fallback, no re-copy) | 3 |
| §3 line statuses, P&L, rounding, below-margin, cash flow, duration | 2 |
| §4 PD tab (header, P&L, lines, documents, cash flow, read-only) | 7 |
| §4 costing Group column, Awarded list, Finance Settings, reopen keeps project | 3, 8, 6, 3 |
| §5 permissions (server-side) | 4, 5, 6, 7 |
| §6 errors (validation, overpayment flagged, same-line conflict, closed, transactions) | 2, 4, 5, 7 |
| §7 sample data + dev DB | 8 |
| §8 testing | all |
