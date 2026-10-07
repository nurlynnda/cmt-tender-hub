# CMT Tender Hub — Stage 5 Implementation Plan (Quotation)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand-alone quotations — list, edit (Draft only), lock on Sent, Accept/Reject/Revise/Duplicate, server-made PDF, and "Create project" on an Accepted quotation that opens the Stage 4 PD.

**Architecture:** `quotations` + `quotation_items` with small action classes (`App\Actions\Quotations\*`) that lock the row, check the `QuotationPolicy`, check a version number and write history. Totals and amount-in-words are pure helpers. Projects (Stage 4) gain an *owner* that is either a tender or a quotation; the PD Livewire component becomes `ProjectPd` and works for both. PDFs are rendered by Dompdf from one Blade template.

**Tech Stack:** Laravel 13, Livewire 4.4 (`WithFileUploads`, `WithPagination`), Pest 5, MySQL 8.4, `barryvdh/laravel-dompdf`, PHP GD.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-stage5-design.md` — read first.

## Global Constraints

- Branch `stage-5`. Commit per task; never commit red; the pre-commit hook runs `pest --coverage --min=80`.
- PHP runs only in Docker: `docker compose exec -T app …` (Git Bash: prefix `MSYS_NO_PATHCONV=1`). Write PHP with the editor tool.
- Money = integer sen; percentages = basis points; SST rounds half up to the sen (`PdCalculator::pct`).
- Numbers `QTN-YYYY-NNNN` (per-year sequence, never reused); revisions `…-R1`, `-R2`.
- Statuses `draft, sent, accepted, rejected, revised`; **Expired is derived** (sent and today > quote_date + validity_days, Malaysia date).
- Editing only while Draft, by the preparer, Managers, Admins. Back to Draft: Managers/Admins, not once a project exists.
- Messages: "This quotation was changed by X — reload to see their changes." / "This quotation has been sent. Revise it to make changes." / "Upload a PNG or JPG image of 1 MB or less."
- Livewire 4.4: use `wire:model.live.blur` / `wire:model.live`; never a property called `dirty`.
- Stamp files live on the private `local` disk and are **never deleted** (quotations keep pointing at the file they were created with).
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Plain-English UI copy.

## Review Focus

1. **Two people creating quotations or revisions at the same moment** — numbers never collide (sequence row lock + unique index). Pinned in Tasks 1 and 5.
2. **A sent quotation changed through a crafted request** (item edit, field save, terms) — refused server-side. Pinned in Task 4.
3. **Stage 4 PD for tenders after projects gain a quotation owner** — still works unchanged; quotation projects follow quotation rules. Pinned in Task 3.
4. **Stamp switched on but no stamp uploaded, or the stamp file missing** — PDF still renders without it. Pinned in Task 6.
5. **Amount in words for edge amounts** (0, sen only, exact thousands, billions) — correct English, no double spaces. Pinned in Task 2.

---

## File Structure

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_10_000001_create_quotation_tables.php` | company_profile (+1 row), quotation_sequences, quotations, quotation_items; projects/activity_logs gain quotation owner |
| `app/Enums/QuotationStatus.php`, `app/Models/{CompanyProfile,Quotation,QuotationItem}.php`, factories, `app/Policies/QuotationPolicy.php` | data + permissions |
| `app/Quotations/{AmountInWords,QuotationTotals,QuotationListQuery}.php` | pure helpers / list query |
| `app/Actions/Quotations/*`, `app/Actions/Quotations/Concerns/GuardsQuotation.php` | numbering, create/edit, items, life cycle, project |
| `app/Exceptions/{StaleQuotation,QuotationLocked,InvalidQuotationTransition,QuotationIncomplete}.php` | refusals |
| `app/Pdf/QuotationPdf.php`, `app/Http/Controllers/{QuotationPdfController,CompanyStampController}.php`, `resources/views/quotations/{pdf,pdf-failed}.blade.php` | PDF |
| `app/Actions/Company/{SaveCompanyProfile,SaveCompanyStamp,RemoveCompanyStamp}.php` | letterhead |
| `app/Livewire/{QuotationList,QuotationPage,QuotationProjectPage}.php` + views | screens |
| Modified: Project, ActivityLog, GuardsProject, PD actions, `TenderPd` → `ProjectPd`, FinanceSettings, sidebar, routes, Dockerfile, composer, seeder, README | integration |

---

### Task 1: Quotation storage, numbering, policy, owners

**Files:**
- Create: migration, `app/Enums/QuotationStatus.php`, `app/Models/{CompanyProfile,Quotation,QuotationItem}.php`, `database/factories/{QuotationFactory,QuotationItemFactory}.php`, `app/Policies/QuotationPolicy.php`, `app/Actions/Quotations/GenerateQuotationNumber.php`
- Modify: `app/Models/ActivityLog.php` (`record()` accepts a quotation), `app/Models/Project.php` (`quotation()`)
- Test: `tests/Feature/Quotations/QuotationStorageTest.php`

**Interfaces — Produces:**
- `QuotationStatus` cases `Draft, Sent, Accepted, Rejected, Revised`; `label()`.
- `CompanyProfile::current(): CompanyProfile`; `->letterhead(): array` (keys `name, registration_no, sst_no, address, phone, email, website, stamp_path`).
- `Quotation`: casts; `items()` (by position), `preparer()`, `revisionOf()`, `project()` (HasOne), `activity()` (latest first), `updatedBy()`; `validUntil(): CarbonImmutable`; `isExpired(?CarbonImmutable $today = null): bool`; `displayStatus(): string` (`expired` or the status value); `displayLabel(): string`; `isDraft(): bool`. (`totals()` arrives in Task 2.)
- `QuotationPolicy`: `viewAny`, `view` (true), `update` (Manager/Admin or preparer), `backToDraft` (Manager/Admin).
- `GenerateQuotationNumber::next(int $year): string`.
- `ActivityLog::record(Tender|Quotation $subject, ?User $user, string $event, string $description)`; `Project::quotation()`.

- [ ] **Step 1: Failing test** `tests/Feature/Quotations/QuotationStorageTest.php`

```php
<?php

use App\Actions\Quotations\GenerateQuotationNumber;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, CompanyProfile, Project, Quotation, QuotationItem, User};
use Illuminate\Support\Facades\Gate;

it('ships the company letterhead, default terms and SST', function () {
    $c = CompanyProfile::current();

    expect($c->name)->toBe('CMT Sdn. Bhd.')
        ->and($c->default_sst_bp)->toBe(800)
        ->and(preg_split('/\R/', $c->default_terms))->toHaveCount(6)
        ->and($c->letterhead())->toMatchArray(['name' => 'CMT Sdn. Bhd.', 'website' => 'www.cmt.com.my', 'stamp_path' => null]);
});

it('numbers quotations per year without gaps', function () {
    $n = app(GenerateQuotationNumber::class);

    expect([$n->next(2026), $n->next(2026), $n->next(2027), $n->next(2026)])
        ->toBe(['QTN-2026-0001', 'QTN-2026-0002', 'QTN-2027-0001', 'QTN-2026-0003']);
});

it('stores a quotation with ordered items and works out validity', function () {
    $q = Quotation::factory()->create(['quote_date' => '2026-09-18', 'validity_days' => 30, 'status' => QuotationStatus::Sent]);
    QuotationItem::factory()->for($q)->create(['position' => 2, 'title' => 'B']);
    QuotationItem::factory()->for($q)->create(['position' => 1, 'title' => 'A']);

    $q = $q->fresh();
    expect($q->items->pluck('title')->all())->toBe(['A', 'B'])
        ->and($q->validUntil()->format('Y-m-d'))->toBe('2026-10-18')
        ->and($q->isExpired(Carbon\CarbonImmutable::parse('2026-10-18')))->toBeFalse()
        ->and($q->isExpired(Carbon\CarbonImmutable::parse('2026-10-19')))->toBeTrue()
        ->and($q->displayStatus())->toBeIn(['sent', 'expired'])
        ->and(Quotation::factory()->create(['status' => QuotationStatus::Draft, 'quote_date' => '2020-01-01'])->isExpired())->toBeFalse();
});

it('labels each status and shows Expired', function () {
    $q = Quotation::factory()->make(['status' => QuotationStatus::Sent, 'quote_date' => '2020-01-01', 'validity_days' => 30]);

    expect($q->displayStatus())->toBe('expired')->and($q->displayLabel())->toBe('Expired')
        ->and(QuotationStatus::Revised->label())->toBe('Revised');
});

it('lets the preparer, managers and admins edit; only managers and admins move back to draft', function () {
    $preparer = User::factory()->create();
    $q = Quotation::factory()->create(['prepared_by' => $preparer->id]);
    $other = User::factory()->create();
    $manager = User::factory()->manager()->create();

    expect(Gate::forUser($preparer)->allows('update', $q))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $q))->toBeFalse()
        ->and(Gate::forUser($other)->allows('view', $q))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('update', $q))->toBeTrue()
        ->and(Gate::forUser($preparer)->allows('backToDraft', $q))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('backToDraft', $q))->toBeTrue();
});

it('records history and projects against a quotation', function () {
    $q = Quotation::factory()->create();
    ActivityLog::record($q, null, 'quotation_created', 'Quotation created');
    $p = Project::factory()->create(['tender_id' => null, 'quotation_id' => $q->id]);

    expect($q->activity->first()->description)->toBe('Quotation created')
        ->and($q->activity->first()->tender_id)->toBeNull()
        ->and($q->fresh()->project->id)->toBe($p->id)
        ->and($p->quotation->id)->toBe($q->id);
});
```

- [ ] **Step 2: Run** `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest tests/Feature/Quotations/QuotationStorageTest.php` — Expected: FAIL (classes missing).

- [ ] **Step 3: Implement**

`database/migrations/2026_10_10_000001_create_quotation_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    private const TERMS = [
        'Prices quoted are in Ringgit Malaysia (RM).',
        'This quotation is valid for the number of days stated above from the date of issue.',
        'Payment terms: 30 days from the date of invoice.',
        'Delivery within 4–6 weeks upon receipt of official Purchase Order (PO) / Letter of Award.',
        'Any changes to scope, quantity or specification may affect the quoted price.',
        'Warranty as per principal / manufacturer terms unless stated otherwise.',
    ];

    public function up(): void
    {
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_no')->nullable();
            $table->string('sst_no')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('stamp_path')->nullable();
            $table->text('default_terms')->nullable();
            $table->unsignedSmallInteger('default_sst_bp')->default(800);
            $table->timestamps();
        });
        DB::table('company_profile')->insert([
            'name' => 'CMT Sdn. Bhd.', 'registration_no' => '201901000000 (1234567-X)', 'sst_no' => null,
            'address' => "Level 8, Menara Example, Jalan Tun Razak,\n50400 Kuala Lumpur, Malaysia",
            'phone' => '+603-0000 0000', 'email' => 'sales@cmt.com.my', 'website' => 'www.cmt.com.my',
            'default_terms' => implode("\n", self::TERMS), 'default_sst_bp' => 800,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('quotation_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_seq');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('revision_of_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('status', 10)->default('draft');
            $table->date('quote_date');
            $table->unsignedSmallInteger('validity_days')->default(30);
            $table->string('customer_name')->nullable();
            $table->string('attention')->nullable();
            $table->string('attention_phone', 50)->nullable();
            $table->string('attention_email')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('subject', 500)->nullable();
            $table->foreignId('prepared_by')->constrained('users');
            $table->string('preparer_position')->nullable();
            $table->string('preparer_phone', 50)->nullable();
            $table->string('preparer_email')->nullable();
            $table->boolean('show_signature')->default(true);
            $table->boolean('show_stamp')->default(true);
            $table->unsignedSmallInteger('sst_bp')->default(800);
            $table->text('terms')->nullable();
            $table->json('letterhead');
            foreach (['sent', 'accepted', 'rejected'] as $s) {
                $table->timestamp("{$s}_at")->nullable();
                $table->foreignId("{$s}_by")->nullable()->constrained('users')->nullOnDelete();
            }
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'quote_date']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title', 255);
            $table->text('details')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 50)->default('Unit');
            $table->unsignedBigInteger('unit_price_sen')->default(0);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('tender_id')->nullable()->change();
            $table->foreignId('quotation_id')->nullable()->unique()->after('tender_id')->constrained()->restrictOnDelete();
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('tender_id')->nullable()->change();
            $table->foreignId('quotation_id')->nullable()->after('tender_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropConstrainedForeignId('quotation_id'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('quotation_id'));
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_sequences');
        Schema::dropIfExists('company_profile');
    }
};
```

`app/Enums/QuotationStatus.php`:

```php
<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Revised = 'revised';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
```

`app/Models/CompanyProfile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The one company letterhead (Admin, Finance Settings). Exactly one row, inserted by the migration. */
class CompanyProfile extends Model
{
    protected $table = 'company_profile';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['default_sst_bp' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrFail();
    }

    /** The copy stored on each new quotation. */
    public function letterhead(): array
    {
        return $this->only(['name', 'registration_no', 'sst_no', 'address', 'phone', 'email', 'website', 'stamp_path']);
    }
}
```

`app/Models/Quotation.php`:

```php
<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Quotation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'quote_date' => 'immutable_date',
            'validity_days' => 'integer',
            'show_signature' => 'boolean',
            'show_stamp' => 'boolean',
            'sst_bp' => 'integer',
            'letterhead' => 'array',
            'sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('position')->orderBy('id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function validUntil(): CarbonImmutable
    {
        return $this->quote_date->addDays($this->validity_days);
    }

    /** Sent and past its valid-until date (Malaysia calendar day). */
    public function isExpired(?CarbonImmutable $today = null): bool
    {
        $today ??= MalaysiaTime::today();

        return $this->status === QuotationStatus::Sent && $today->format('Y-m-d') > $this->validUntil()->format('Y-m-d');
    }

    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status->value;
    }

    public function displayLabel(): string
    {
        return $this->isExpired() ? 'Expired' : $this->status->label();
    }

    public function isDraft(): bool
    {
        return $this->status === QuotationStatus::Draft;
    }
}
```

`app/Models/QuotationItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'quantity' => 'integer', 'unit_price_sen' => 'integer'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
```

`database/factories/QuotationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Quotation> */
class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'QTN-2026-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => QuotationStatus::Draft,
            'quote_date' => '2026-09-18',
            'validity_days' => 30,
            'customer_name' => fake()->company(),
            'subject' => fake()->sentence(5),
            'prepared_by' => User::factory(),
            'sst_bp' => 800,
            'terms' => "Prices quoted are in Ringgit Malaysia (RM).\nPayment terms: 30 days.",
            'letterhead' => ['name' => 'CMT Sdn. Bhd.', 'registration_no' => null, 'sst_no' => null, 'address' => 'Kuala Lumpur',
                'phone' => null, 'email' => null, 'website' => null, 'stamp_path' => null],
            'version' => 1,
        ];
    }
}
```

`database/factories/QuotationItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\QuotationItem> */
class QuotationItemFactory extends Factory
{
    public function definition(): array
    {
        return ['quotation_id' => Quotation::factory(), 'position' => 1, 'title' => fake()->words(3, true),
            'quantity' => 1, 'unit' => 'Unit', 'unit_price_sen' => 100000];
    }
}
```

`app/Policies/QuotationPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\{Quotation, User};

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return true;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->role->canManageAllTenders() || $quotation->prepared_by === $user->id;
    }

    public function backToDraft(User $user, Quotation $quotation): bool
    {
        return $user->role->canManageAllTenders();
    }
}
```

`app/Actions/Quotations/GenerateQuotationNumber.php`:

```php
<?php

namespace App\Actions\Quotations;

use Illuminate\Support\Facades\DB;

final class GenerateQuotationNumber
{
    public function next(int $year): string
    {
        return DB::transaction(function () use ($year) {
            DB::table('quotation_sequences')->insertOrIgnore(['year' => $year, 'last_seq' => 0]);
            // Row lock: two people creating at the same moment queue here instead of sharing a number.
            $next = DB::table('quotation_sequences')->where('year', $year)->lockForUpdate()->value('last_seq') + 1;
            DB::table('quotation_sequences')->where('year', $year)->update(['last_seq' => $next]);

            return sprintf('QTN-%d-%04d', $year, $next);
        });
    }
}
```

`ActivityLog::record()` — replace with:

```php
    public static function record(Tender|Quotation $subject, ?User $user, string $event, string $description): self
    {
        return static::create([
            'tender_id' => $subject instanceof Tender ? $subject->id : null,
            'quotation_id' => $subject instanceof Quotation ? $subject->id : null,
            'user_id' => $user?->id,
            'event' => $event,
            'description' => $description,
        ]);
    }
```

`Project.php` — add:

```php
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
```

- [ ] **Step 4: Run the test** — Expected: PASS. Then `php artisan migrate --force` on the dev database.
- [ ] **Step 5: Commit** — `feat: quotation tables, numbering, policy; projects and history can belong to a quotation`

---

### Task 2: Totals and amount in words

**Files:** Create `app/Quotations/AmountInWords.php`, `app/Quotations/QuotationTotals.php`; Modify `app/Models/Quotation.php` (`totals()`); Test `tests/Unit/Quotations/QuotationTotalsTest.php`

**Interfaces — Produces:** `AmountInWords::ringgit(int $sen): string`, `AmountInWords::number(int $n): string`; `QuotationTotals::of(array $items, int $sstBp): array{lines: list<int>, subtotal_sen:int, sst_sen:int, total_sen:int, words:string}` (items: `['quantity' => int, 'unit_price_sen' => int]`); `Quotation::totals(): array` (same shape).

- [ ] **Step 1: Failing test**

```php
<?php

use App\Quotations\{AmountInWords, QuotationTotals};

it('adds up the prototype quotation to the sen', function () {
    $t = QuotationTotals::of([['quantity' => 6, 'unit_price_sen' => 485000], ['quantity' => 1, 'unit_price_sen' => 650000]], 800);

    expect($t)->toBe([
        'lines' => [2910000, 650000], 'subtotal_sen' => 3560000, 'sst_sen' => 284800, 'total_sen' => 3844800,
        'words' => 'Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only',
    ]);
});

it('rounds SST half up and allows 0%', function () {
    expect(QuotationTotals::of([['quantity' => 1, 'unit_price_sen' => 7]], 800)['sst_sen'])->toBe(1)     // 0.56 → 1
        ->and(QuotationTotals::of([['quantity' => 1, 'unit_price_sen' => 6]], 800)['sst_sen'])->toBe(0)  // 0.48 → 0
        ->and(QuotationTotals::of([['quantity' => 3, 'unit_price_sen' => 1000]], 0)['total_sen'])->toBe(3000)
        ->and(QuotationTotals::of([], 800)['total_sen'])->toBe(0);
});

it('writes amounts in words', function (int $sen, string $words) {
    expect(AmountInWords::ringgit($sen))->toBe($words);
})->with([
    [0, 'Ringgit Malaysia Zero Only'],
    [50, 'Ringgit Malaysia Zero and Fifty Sen Only'],
    [1050, 'Ringgit Malaysia Ten and Fifty Sen Only'],
    [101, 'Ringgit Malaysia One and One Sen Only'],
    [1500000, 'Ringgit Malaysia Fifteen Thousand Only'],
    [10000000, 'Ringgit Malaysia One Hundred Thousand Only'],
    [2052000, 'Ringgit Malaysia Twenty Thousand Five Hundred Twenty Only'],
    [100000100, 'Ringgit Malaysia One Million One Only'],
    [100000000000, 'Ringgit Malaysia One Billion Only'],
    [1911999, 'Ringgit Malaysia Nineteen Thousand One Hundred Nineteen and Ninety Nine Sen Only'],
]);
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Quotations/AmountInWords.php`:

```php
<?php

namespace App\Quotations;

/** "Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only" — the line under the quotation total. */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private const SCALES = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];

    public static function ringgit(int $sen): string
    {
        $words = 'Ringgit Malaysia '.self::number(intdiv($sen, 100));
        if ($sen % 100 > 0) {
            $words .= ' and '.self::number($sen % 100).' Sen';
        }

        return $words.' Only';
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        $parts = [];
        for ($scale = 0; $n > 0; $scale++, $n = intdiv($n, 1000)) {
            if ($n % 1000) {
                array_unshift($parts, trim(self::chunk($n % 1000).' '.self::SCALES[$scale]));
            }
        }

        return implode(' ', $parts);
    }

    private static function chunk(int $n): string
    {
        $w = [];
        if ($n >= 100) {
            $w[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $w[] = self::TENS[intdiv($n, 10)];
            $n %= 10;
        }
        if ($n > 0) {
            $w[] = self::ONES[$n];
        }

        return implode(' ', $w);
    }
}
```

`app/Quotations/QuotationTotals.php`:

```php
<?php

namespace App\Quotations;

use App\Pd\PdCalculator;

final class QuotationTotals
{
    /** @param list<array{quantity:int, unit_price_sen:int}> $items */
    public static function of(array $items, int $sstBp): array
    {
        $lines = array_map(fn (array $i) => $i['quantity'] * $i['unit_price_sen'], array_values($items));
        $subtotal = array_sum($lines);
        $sst = PdCalculator::pct($subtotal, $sstBp);

        return [
            'lines' => $lines,
            'subtotal_sen' => $subtotal,
            'sst_sen' => $sst,
            'total_sen' => $subtotal + $sst,
            'words' => AmountInWords::ringgit($subtotal + $sst),
        ];
    }
}
```

`Quotation.php` — add:

```php
    public function totals(): array
    {
        return QuotationTotals::of(
            $this->items->map(fn (QuotationItem $i) => ['quantity' => $i->quantity, 'unit_price_sen' => $i->unit_price_sen])->all(),
            $this->sst_bp,
        );
    }
```
(import `App\Quotations\QuotationTotals`).

- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: quotation totals and amount in words`

---

### Task 3: Projects owned by a tender or a quotation

**Files:**
- Modify: `app/Models/Project.php`, `app/Actions/Pd/Concerns/GuardsProject.php`, `app/Exceptions/ProjectLocked.php`, every `app/Actions/Pd/*` that logs (`AddPdLine, UpdatePdLine, RemovePdLine, SavePdEntry, RemovePdEntry, UpdateProjectDetails, UpdateProjectRates, CloseProject, ReopenProject`)
- Rename: `app/Livewire/TenderPd.php` → `app/Livewire/ProjectPd.php`, `resources/views/livewire/tender-pd.blade.php` → `project-pd.blade.php`, `tests/Feature/Livewire/TenderPdTest.php` → `ProjectPdTest.php`
- Modify: `resources/views/livewire/tender-detail.blade.php`
- Test: `tests/Feature/Pd/QuotationProjectTest.php`

**Interfaces — Produces:** `Project::owner(): Tender|Quotation`; `Project::isActive(): bool` (tender Awarded / quotation Accepted); `Project::logActivity(?User, string $event, string $description): void`; `Project::pdUrl(): string`; `ProjectLocked::notActive(Project)`; Livewire `project-pd` with prop `Project $project`.

- [ ] **Step 1: Failing test** `tests/Feature/Pd/QuotationProjectTest.php`

```php
<?php

use App\Actions\Pd\{AddPdLine, UpdateProjectRates};
use App\Enums\{PdGroup, QuotationStatus};
use App\Exceptions\ProjectLocked;
use App\Livewire\ProjectPd;
use App\Models\{Project, Quotation, User};
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function quotationProject(QuotationStatus $status = QuotationStatus::Accepted): array
{
    $preparer = User::factory()->create();
    $q = Quotation::factory()->create(['prepared_by' => $preparer->id, 'status' => $status]);

    return [$preparer, $q, Project::factory()->create(['tender_id' => null, 'quotation_id' => $q->id])];
}

it('lets the preparer work on an accepted quotation\'s project, logging on the quotation', function () {
    [$preparer, $q, $project] = quotationProject();

    app(AddPdLine::class)->handle($preparer, $project, PdGroup::Principal);

    expect($project->owner()->is($q))->toBeTrue()
        ->and($project->pdUrl())->toBe(route('quotations.pd', $q))
        ->and($q->activity()->first()->description)->toBe('PD line added — Principal');
});

it('refuses other staff, and everyone once the quotation is no longer accepted', function () {
    [$preparer, , $project] = quotationProject();
    expect(fn () => app(AddPdLine::class)->handle(User::factory()->create(), $project, PdGroup::Tax))->toThrow(AuthorizationException::class);

    [$preparer2, , $sentProject] = quotationProject(QuotationStatus::Sent);
    expect(fn () => app(AddPdLine::class)->handle($preparer2, $sentProject, PdGroup::Tax))
        ->toThrow(ProjectLocked::class, 'This project is on hold because its quotation is no longer Accepted.')
        ->and(fn () => app(UpdateProjectRates::class)->handle(User::factory()->manager()->create(), $sentProject, 1, 1, 1, 1))
        ->toThrow(ProjectLocked::class);
});

it('shows the PD screen for a quotation project', function () {
    [$preparer, , $project] = quotationProject();

    Livewire::actingAs($preparer)->test(ProjectPd::class, ['project' => $project])
        ->assertSee('Profit & Loss')->assertSee('+ Add line');
});
```

- [ ] **Step 2: Run** — Expected: FAIL (`owner`/`ProjectPd`/`quotations.pd` missing).

- [ ] **Step 3a: Quotation routes and pages needed by this task**

The quotation's PD page (final in this task) and a minimal quotation page (replaced in full by Task 8) are created now, because `Project::pdUrl()` and the PD page's back link need their routes. In `routes/web.php`, inside the auth group:

```php
    Route::get('/quotations/{quotation}', \App\Livewire\QuotationPage::class)->whereNumber('quotation')->name('quotations.show');
    Route::get('/quotations/{quotation}/pd', \App\Livewire\QuotationProjectPage::class)->whereNumber('quotation')->name('quotations.pd');
```

`app/Livewire/QuotationProjectPage.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Quotation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuotationProjectPage extends Component
{
    public Quotation $quotation;

    public function mount(Quotation $quotation): void
    {
        abort_unless($quotation->project()->exists(), 404);
        $this->quotation = $quotation;
    }

    public function render()
    {
        return view('livewire.quotation-project-page', ['project' => $this->quotation->project])
            ->title($this->quotation->number.' · Project');
    }
}
```

`resources/views/livewire/quotation-project-page.blade.php`:

```blade
<div class="space-y-4">
    <a href="{{ route('quotations.show', $quotation) }}" class="text-sm text-muted hover:text-ink">← Back to {{ $quotation->number }}</a>
    <header class="rounded-xl border border-line bg-surface p-4">
        <p class="text-xs uppercase text-muted">Project from quotation</p>
        <h1 class="text-lg font-semibold">{{ $quotation->number }} — {{ $quotation->subject }}</h1>
        <p class="text-sm text-muted">{{ $quotation->customer_name }}</p>
    </header>
    <livewire:project-pd :project="$project" wire:key="pd-q-{{ $quotation->id }}" />
</div>
```

Minimal `app/Livewire/QuotationPage.php` (Task 8 replaces it):

```php
<?php

namespace App\Livewire;

use App\Models\Quotation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuotationPage extends Component
{
    public Quotation $quotation;

    public function render()
    {
        return view('livewire.quotation-page')->title($this->quotation->number);
    }
}
```
with `resources/views/livewire/quotation-page.blade.php` containing `<div><h1 class="text-lg font-semibold">{{ $quotation->number }}</h1></div>`. Task 8 replaces both files in full.

- [ ] **Step 3: Implement the owner**

`Project.php` — add (imports `App\Enums\{QuotationStatus, TenderStatus}`):

```php
    public function owner(): Tender|Quotation
    {
        return $this->tender ?? $this->quotation;
    }

    /** Tender still Awarded / quotation still Accepted. */
    public function isActive(): bool
    {
        $owner = $this->owner();

        return $owner instanceof Tender ? $owner->status === TenderStatus::Awarded : $owner->status === QuotationStatus::Accepted;
    }

    public function logActivity(?User $user, string $event, string $description): void
    {
        ActivityLog::record($this->owner(), $user, $event, $description);
    }

    public function pdUrl(): string
    {
        return $this->tender ? route('tenders.show', $this->tender).'?tab=pd' : route('quotations.pd', $this->quotation);
    }
```

`ProjectLocked.php` — add:

```php
    public static function notActive(\App\Models\Project $project): self
    {
        return $project->tender ? self::notAwarded() : new self('This project is on hold because its quotation is no longer Accepted.');
    }
```

`GuardsProject.php`:
- `lockOpenProject`: `Gate::forUser($actor)->authorize('update', $p->owner());`
- replace `requireAwarded(Project $p)` body with:

```php
        if (! $p->isActive()) {
            throw ProjectLocked::notActive($p);
        }
```
  and drop the unused `TenderStatus` import.

PD actions — replace each `ActivityLog::record($p->tender, $actor, ` with `$p->logActivity($actor, `; each `ActivityLog::record($l->project->tender, $actor, ` with `$l->project->logActivity($actor, `; in `RemovePdLine` replace `$tender = $l->project->tender;` + `ActivityLog::record($tender, $actor, ` with `$project = $l->project;` + `$project->logActivity($actor, `. Remove `ActivityLog` from their `use` lines where now unused. (`CreateProjectFromCosting` keeps logging on the tender.)

Rename the PD component:

```bash
git mv app/Livewire/TenderPd.php app/Livewire/ProjectPd.php
git mv resources/views/livewire/tender-pd.blade.php resources/views/livewire/project-pd.blade.php
git mv tests/Feature/Livewire/TenderPdTest.php tests/Feature/Livewire/ProjectPdTest.php
```

In `ProjectPd.php`:
- class name `ProjectPd`; replace `public Tender $tender;` with `public Project $project;`;
- `private function project(): Project` body → `return Project::findOrFail($this->project->id);`
- `render()`: view `livewire.project-pd`; `'canEdit' => Gate::allows('update', $project->owner()) && $project->isOpen(),`; drop `Tender` import.

In `project-pd.blade.php`: the reload link `href="{{ route('tenders.show', $tender) }}?tab=pd"` → `href="{{ $project->pdUrl() }}"`.

In `tender-detail.blade.php`: `<livewire:tender-pd :tender="$tender" wire:key="pd-{{ $tender->id }}" />` → `<livewire:project-pd :project="$tender->project" wire:key="pd-{{ $tender->id }}" />`.

In `ProjectPdTest.php`: replace `TenderPd` with `ProjectPd` throughout, `TenderPd::class, ['tender' => $tender]` → `ProjectPd::class, ['project' => $tender->project]`, and `['tender' => $tender->fresh()]` → `['project' => $tender->fresh()->project]`.

- [ ] **Step 4: Run** `pest tests/Feature/Pd tests/Feature/Livewire` — Expected: PASS (Stage 4 PD tests unchanged in behaviour).
- [ ] **Step 5: Commit** — `refactor: PD works for projects owned by a tender or a quotation`

---

### Task 4: Create and edit quotations

**Files:**
- Create: `app/Actions/Quotations/Concerns/GuardsQuotation.php`, `app/Exceptions/{StaleQuotation,QuotationLocked,InvalidQuotationTransition}.php`, `app/Actions/Quotations/{CreateQuotation,UpdateQuotation,AddQuotationItem,UpdateQuotationItem,RemoveQuotationItem,MoveQuotationItem}.php`
- Test: `tests/Feature/Quotations/EditQuotationTest.php`

**Interfaces — Produces:**
- `GuardsQuotation::lockQuotation(User, Quotation, int $expectedVersion, string $ability = 'update'): Quotation`, `requireDraft(Quotation)`, `requireStatus(Quotation, array $statuses, string $action)`, `bump(Quotation, User)`.
- `CreateQuotation::handle(User $actor): Quotation`
- `UpdateQuotation::FIELDS`; `UpdateQuotation::handle(User, Quotation, int $expectedVersion, array $data): Quotation`
- `AddQuotationItem::handle(User, Quotation, int $expectedVersion): Quotation`
- `UpdateQuotationItem::handle(User, QuotationItem, int $expectedVersion, array{title:string, details:?string, quantity:int, unit:string, unit_price_sen:int}): Quotation`
- `RemoveQuotationItem::handle(User, QuotationItem, int $expectedVersion): Quotation`
- `MoveQuotationItem::handle(User, QuotationItem, int $expectedVersion, int $direction): Quotation`
- All return the fresh quotation (new `version`).

- [ ] **Step 1: Failing test** `tests/Feature/Quotations/EditQuotationTest.php`

```php
<?php

use App\Actions\Quotations\{AddQuotationItem, CreateQuotation, MoveQuotationItem, RemoveQuotationItem, UpdateQuotation, UpdateQuotationItem};
use App\Enums\QuotationStatus;
use App\Exceptions\{QuotationLocked, StaleQuotation};
use App\Models\{Quotation, QuotationItem, User};
use App\Support\MalaysiaTime;
use Illuminate\Auth\Access\AuthorizationException;

it('creates a draft filled with today, the defaults and a copy of the letterhead', function () {
    $siti = User::factory()->create(['name' => 'Siti Aisyah', 'email' => 'siti@cmt.test']);

    $q = app(CreateQuotation::class)->handle($siti);

    expect($q->number)->toBe('QTN-'.MalaysiaTime::today()->year.'-0001')
        ->and($q->status)->toBe(QuotationStatus::Draft)
        ->and($q->quote_date->format('Y-m-d'))->toBe(MalaysiaTime::today()->format('Y-m-d'))
        ->and($q->validity_days)->toBe(30)
        ->and($q->prepared_by)->toBe($siti->id)
        ->and($q->preparer_email)->toBe('siti@cmt.test')
        ->and($q->sst_bp)->toBe(800)
        ->and(substr_count($q->terms, "\n"))->toBe(5)
        ->and($q->letterhead['name'])->toBe('CMT Sdn. Bhd.')
        ->and($q->activity->first()->description)->toBe("Quotation {$q->number} created");
});

it('saves details while draft and bumps the version', function () {
    $q = Quotation::factory()->create();
    $preparer = $q->preparer;

    $q = app(UpdateQuotation::class)->handle($preparer, $q, 1, ['customer_name' => 'JPNIN', 'sst_bp' => 600, 'show_stamp' => false, 'number' => 'HACK']);

    expect($q->customer_name)->toBe('JPNIN')->and($q->sst_bp)->toBe(600)->and($q->show_stamp)->toBeFalse()
        ->and($q->number)->not->toBe('HACK')->and($q->version)->toBe(2);
});

it('refuses a preparer who is switched off or unknown', function () {
    $q = Quotation::factory()->create();
    $off = User::factory()->create(['is_active' => false]);

    app(UpdateQuotation::class)->handle($q->preparer, $q, 1, ['prepared_by' => $off->id]);
})->throws(InvalidArgumentException::class, 'Choose an active person as the preparer.');

it('adds, edits, moves and removes items', function () {
    $q = Quotation::factory()->create();
    $u = $q->preparer;

    $q = app(AddQuotationItem::class)->handle($u, $q, 1);
    $q = app(AddQuotationItem::class)->handle($u, $q, 2);
    [$a, $b] = $q->items->all();
    expect($a->title)->toBe('New item')->and($b->position)->toBe(2);

    $q = app(UpdateQuotationItem::class)->handle($u, $b, 3, ['title' => 'Switch', 'details' => "Ports: 24", 'quantity' => 6, 'unit' => 'Unit', 'unit_price_sen' => 485000]);
    $q = app(MoveQuotationItem::class)->handle($u, $b->fresh(), 4, -1);
    expect($q->items->pluck('title')->all())->toBe(['Switch', 'New item'])->and($q->totals()['subtotal_sen'])->toBe(2910000);

    $q = app(MoveQuotationItem::class)->handle($u, $b->fresh(), 5, -1); // already first: no change, still bumps nothing harmful
    $q = app(RemoveQuotationItem::class)->handle($u, $a->fresh(), $q->version);
    expect($q->items->pluck('title')->all())->toBe(['Switch']);
});

it('rejects bad item data', function () {
    $item = QuotationItem::factory()->create();

    app(UpdateQuotationItem::class)->handle($item->quotation->preparer, $item, 1, ['title' => ' ', 'details' => null, 'quantity' => 0, 'unit' => 'Unit', 'unit_price_sen' => 1]);
})->throws(InvalidArgumentException::class, 'An item needs a title, a quantity of at least 1 and a price of RM 0.00 or more.');

it('refuses any change once sent, even through a crafted request', function () {
    $q = Quotation::factory()->create(['status' => QuotationStatus::Sent]);
    $item = QuotationItem::factory()->for($q)->create();
    $u = $q->preparer;

    expect(fn () => app(UpdateQuotation::class)->handle($u, $q, 1, ['subject' => 'x']))->toThrow(QuotationLocked::class, 'This quotation has been sent. Revise it to make changes.')
        ->and(fn () => app(AddQuotationItem::class)->handle($u, $q, 1))->toThrow(QuotationLocked::class)
        ->and(fn () => app(UpdateQuotationItem::class)->handle($u, $item, 1, ['title' => 'x', 'details' => null, 'quantity' => 1, 'unit' => 'U', 'unit_price_sen' => 1]))->toThrow(QuotationLocked::class)
        ->and(fn () => app(RemoveQuotationItem::class)->handle($u, $item, 1))->toThrow(QuotationLocked::class);
});

it('refuses other staff and out-of-date pages', function () {
    $q = Quotation::factory()->create();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);

    expect(fn () => app(UpdateQuotation::class)->handle(User::factory()->create(), $q, 1, ['subject' => 'x']))->toThrow(AuthorizationException::class);

    app(UpdateQuotation::class)->handle($manager, $q, 1, ['subject' => 'Manager edit']);
    expect(fn () => app(UpdateQuotation::class)->handle($q->preparer, $q, 1, ['subject' => 'Mine']))
        ->toThrow(StaleQuotation::class, 'This quotation was changed by Ahmad Faizal — reload to see their changes.');
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Exceptions/StaleQuotation.php`:

```php
<?php

namespace App\Exceptions;

use App\Models\Quotation;
use RuntimeException;

class StaleQuotation extends RuntimeException
{
    public static function for(Quotation $fresh): self
    {
        return new self('This quotation was changed by '.($fresh->updatedBy?->name ?? 'someone else').' — reload to see their changes.');
    }
}
```

`app/Exceptions/QuotationLocked.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class QuotationLocked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This quotation has been sent. Revise it to make changes.');
    }
}
```

`app/Exceptions/InvalidQuotationTransition.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidQuotationTransition extends RuntimeException {}
```

`app/Actions/Quotations/Concerns/GuardsQuotation.php`:

```php
<?php

namespace App\Actions\Quotations\Concerns;

use App\Enums\QuotationStatus;
use App\Exceptions\{InvalidQuotationTransition, QuotationLocked, StaleQuotation};
use App\Models\{Quotation, User};
use Illuminate\Support\Facades\Gate;

trait GuardsQuotation
{
    /** Call inside DB::transaction. Locks the row, checks permission, then checks nobody saved in between. */
    private function lockQuotation(User $actor, Quotation $quotation, int $expectedVersion, string $ability = 'update'): Quotation
    {
        $q = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
        Gate::forUser($actor)->authorize($ability, $q);
        if ($q->version !== $expectedVersion) {
            throw StaleQuotation::for($q);
        }

        return $q;
    }

    private function requireDraft(Quotation $q): void
    {
        if ($q->status !== QuotationStatus::Draft) {
            throw new QuotationLocked;
        }
    }

    /** @param list<QuotationStatus> $statuses */
    private function requireStatus(Quotation $q, array $statuses, string $action): void
    {
        if (! in_array($q->status, $statuses, true)) {
            throw new InvalidQuotationTransition("A {$q->displayLabel()} quotation cannot be {$action}.");
        }
    }

    private function bump(Quotation $q, User $actor): void
    {
        $q->forceFill(['updated_by' => $actor->id, 'version' => $q->version + 1])->save();
    }
}
```

`app/Actions/Quotations/CreateQuotation.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, CompanyProfile, Quotation, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Facades\DB;

final class CreateQuotation
{
    public function __construct(private GenerateQuotationNumber $numbers) {}

    public function handle(User $actor): Quotation
    {
        return DB::transaction(function () use ($actor) {
            $company = CompanyProfile::current();
            $today = MalaysiaTime::today();
            $q = Quotation::create([
                'number' => $this->numbers->next($today->year),
                'status' => QuotationStatus::Draft,
                'quote_date' => $today->format('Y-m-d'),
                'validity_days' => 30,
                'prepared_by' => $actor->id,
                'preparer_email' => $actor->email,
                'show_signature' => true,
                'show_stamp' => true,
                'sst_bp' => $company->default_sst_bp,
                'terms' => $company->default_terms,
                'letterhead' => $company->letterhead(),
                'updated_by' => $actor->id,
                'version' => 1,
            ]);
            ActivityLog::record($q, $actor, 'quotation_created', "Quotation {$q->number} created");

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/UpdateQuotation.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Saves Details / Terms fields of a Draft. Fields not listed here (number, status, letterhead…) are ignored. */
final class UpdateQuotation
{
    use GuardsQuotation;

    public const FIELDS = [
        'quote_date', 'validity_days', 'customer_name', 'attention', 'attention_phone', 'attention_email', 'customer_address',
        'subject', 'prepared_by', 'preparer_position', 'preparer_phone', 'preparer_email', 'show_signature', 'show_stamp',
        'sst_bp', 'terms',
    ];

    public function handle(User $actor, Quotation $quotation, int $expectedVersion, array $data): Quotation
    {
        $values = Arr::only($data, self::FIELDS);
        if (array_key_exists('prepared_by', $values) && ! User::whereKey($values['prepared_by'])->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Choose an active person as the preparer.');
        }

        return DB::transaction(function () use ($actor, $quotation, $expectedVersion, $values) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireDraft($q);
            $q->fill($values);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/AddQuotationItem.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, User};
use Illuminate\Support\Facades\DB;

final class AddQuotationItem
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireDraft($q);
            $q->items()->create([
                'position' => (int) $q->items()->max('position') + 1,
                'title' => 'New item', 'quantity' => 1, 'unit' => 'Unit', 'unit_price_sen' => 0,
            ]);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/UpdateQuotationItem.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateQuotationItem
{
    use GuardsQuotation;

    /** @param array{title:string, details:?string, quantity:int, unit:string, unit_price_sen:int} $data */
    public function handle(User $actor, QuotationItem $item, int $expectedVersion, array $data): Quotation
    {
        $title = trim($data['title']);
        if ($title === '' || $data['quantity'] < 1 || $data['unit_price_sen'] < 0) {
            throw new InvalidArgumentException('An item needs a title, a quantity of at least 1 and a price of RM 0.00 or more.');
        }

        return DB::transaction(function () use ($actor, $item, $expectedVersion, $data, $title) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $item->update([
                'title' => mb_substr($title, 0, 255),
                'details' => trim((string) $data['details']) ?: null,
                'quantity' => $data['quantity'],
                'unit' => trim($data['unit']) ?: 'Unit',
                'unit_price_sen' => $data['unit_price_sen'],
            ]);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/RemoveQuotationItem.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;

final class RemoveQuotationItem
{
    use GuardsQuotation;

    public function handle(User $actor, QuotationItem $item, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $item, $expectedVersion) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $item->delete();
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/MoveQuotationItem.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;

final class MoveQuotationItem
{
    use GuardsQuotation;

    /** $direction: -1 up, +1 down. Moving past either end does nothing. */
    public function handle(User $actor, QuotationItem $item, int $expectedVersion, int $direction): Quotation
    {
        return DB::transaction(function () use ($actor, $item, $expectedVersion, $direction) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $ids = $q->items()->pluck('id')->all();
            $from = array_search($item->id, $ids, true);
            $to = $from + ($direction < 0 ? -1 : 1);
            if ($from !== false && isset($ids[$to])) {
                [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
                foreach ($ids as $i => $id) {
                    $q->items()->whereKey($id)->update(['position' => $i + 1]);
                }
                $this->bump($q, $actor);
            }

            return $q->fresh();
        });
    }
}
```

Note on the test's second `MoveQuotationItem` call (already first): it passes `5` as the version but the first move bumped to 5 only if a swap happened — the first move did swap (version 4 → 5), so 5 is current; the second call swaps nothing and does not bump, so `RemoveQuotationItem` uses `$q->version` (still 5). The test reads `$q->version` for exactly this reason.

- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: create and edit draft quotations and their items`

---

### Task 5: Sent, accepted, rejected, revise, duplicate, back to draft, project

**Files:**
- Create: `app/Exceptions/QuotationIncomplete.php`, `app/Actions/Quotations/{MarkQuotationSent,MarkQuotationAccepted,MarkQuotationRejected,MoveQuotationBackToDraft,ReviseQuotation,DuplicateQuotation,CreateProjectFromQuotation}.php`
- Test: `tests/Feature/Quotations/QuotationLifecycleTest.php`

**Interfaces — Produces:** each `handle(User $actor, Quotation $q, int $expectedVersion)`; `MarkQuotationSent`/`Accepted`/`Rejected`/`MoveQuotationBackToDraft` return the fresh quotation; `ReviseQuotation` returns the **new** draft; `DuplicateQuotation::handle(User, Quotation): Quotation` (no version — viewing is enough); `CreateProjectFromQuotation` returns `Project`.

- [ ] **Step 1: Failing test** `tests/Feature/Quotations/QuotationLifecycleTest.php`

```php
<?php

use App\Actions\Quotations\{CreateProjectFromQuotation, DuplicateQuotation, MarkQuotationAccepted, MarkQuotationRejected, MarkQuotationSent, MoveQuotationBackToDraft, ReviseQuotation};
use App\Enums\{PdGroup, QuotationStatus};
use App\Exceptions\{InvalidQuotationTransition, QuotationIncomplete};
use App\Models\{CompanyProfile, Quotation, QuotationItem, User};
use Illuminate\Auth\Access\AuthorizationException;

function readyQuotation(array $attrs = []): Quotation
{
    $q = Quotation::factory()->create(array_merge(['customer_name' => 'JPNIN', 'subject' => 'Switches'], $attrs));
    QuotationItem::factory()->for($q)->create(['quantity' => 6, 'unit_price_sen' => 485000, 'title' => 'Switch']);
    QuotationItem::factory()->for($q)->create(['position' => 2, 'quantity' => 1, 'unit_price_sen' => 650000, 'title' => 'Install']);

    return $q->fresh();
}

it('marks a complete draft Sent and logs the total', function () {
    $q = readyQuotation();

    $sent = app(MarkQuotationSent::class)->handle($q->preparer, $q, 1);

    expect($sent->status)->toBe(QuotationStatus::Sent)->and($sent->sent_by)->toBe($q->prepared_by)
        ->and($sent->activity->first()->description)->toBe('Marked Sent — total RM 38,448.00');
});

it('lists what is missing before it can be sent', function () {
    $q = Quotation::factory()->create(['customer_name' => null, 'subject' => '']);

    expect(fn () => app(MarkQuotationSent::class)->handle($q->preparer, $q, 1))
        ->toThrow(QuotationIncomplete::class, 'Add a customer name, a subject and at least one item before marking this quotation Sent.');

    $zero = Quotation::factory()->create();
    QuotationItem::factory()->for($zero)->create(['unit_price_sen' => 0]);
    expect(fn () => app(MarkQuotationSent::class)->handle($zero->preparer, $zero, 1))
        ->toThrow(QuotationIncomplete::class, 'Add a total above RM 0.00 before marking this quotation Sent.');
});

it('accepts or rejects a sent quotation, even after it expired', function () {
    $q = readyQuotation(['status' => QuotationStatus::Sent, 'quote_date' => '2020-01-01']);
    $accepted = app(MarkQuotationAccepted::class)->handle($q->preparer, $q, 1);
    expect($accepted->status)->toBe(QuotationStatus::Accepted)->and($accepted->activity->first()->description)->toBe('Marked Accepted');

    $r = readyQuotation(['status' => QuotationStatus::Sent]);
    expect(app(MarkQuotationRejected::class)->handle($r->preparer, $r, 1)->status)->toBe(QuotationStatus::Rejected);

    $draft = readyQuotation();
    expect(fn () => app(MarkQuotationAccepted::class)->handle($draft->preparer, $draft, 1))
        ->toThrow(InvalidQuotationTransition::class, 'A Draft quotation cannot be marked Accepted.');
});

it('revises a sent quotation into -R1, then -R2, keeping everything', function () {
    $q = readyQuotation(['status' => QuotationStatus::Sent, 'number' => 'QTN-2026-0012', 'preparer_position' => 'Sales Executive',
        'letterhead' => ['name' => 'Old Letterhead Sdn. Bhd.', 'stamp_path' => null]]);

    $r1 = app(ReviseQuotation::class)->handle($q->preparer, $q, 1);
    expect($r1->number)->toBe('QTN-2026-0012-R1')->and($r1->status)->toBe(QuotationStatus::Draft)
        ->and($r1->revision_of_id)->toBe($q->id)->and($r1->preparer_position)->toBe('Sales Executive')
        ->and($r1->letterhead['name'])->toBe('Old Letterhead Sdn. Bhd.')
        ->and($r1->items->pluck('title')->all())->toBe(['Switch', 'Install'])
        ->and($q->fresh()->status)->toBe(QuotationStatus::Revised)
        ->and($q->fresh()->activity->first()->description)->toBe('Revised as QTN-2026-0012-R1');

    $r1->update(['status' => QuotationStatus::Sent]);
    expect(app(ReviseQuotation::class)->handle($q->preparer, $r1->fresh(), 1)->number)->toBe('QTN-2026-0012-R2');
});

it('duplicates any quotation as a new draft for the person duplicating, with the current letterhead', function () {
    $q = readyQuotation(['status' => QuotationStatus::Rejected, 'preparer_position' => 'Sales Executive',
        'letterhead' => ['name' => 'Old Letterhead Sdn. Bhd.', 'stamp_path' => null]]);
    $hafiz = User::factory()->create(['email' => 'hafiz@cmt.test']);

    $copy = app(DuplicateQuotation::class)->handle($hafiz, $q);

    expect($copy->number)->not->toBe($q->number)->and($copy->status)->toBe(QuotationStatus::Draft)
        ->and($copy->prepared_by)->toBe($hafiz->id)->and($copy->preparer_position)->toBeNull()
        ->and($copy->preparer_email)->toBe('hafiz@cmt.test')
        ->and($copy->letterhead['name'])->toBe(CompanyProfile::current()->name)
        ->and($copy->customer_name)->toBe('JPNIN')->and($copy->items)->toHaveCount(2)
        ->and($copy->revision_of_id)->toBeNull()
        ->and($copy->activity->first()->description)->toBe("Duplicated from {$q->number}");
});

it('lets managers move a quotation back to draft, but not once it has a project', function () {
    $q = readyQuotation(['status' => QuotationStatus::Accepted]);
    $manager = User::factory()->manager()->create();

    expect(fn () => app(MoveQuotationBackToDraft::class)->handle($q->preparer, $q, 1))->toThrow(AuthorizationException::class);

    $project = app(CreateProjectFromQuotation::class)->handle($q->preparer, $q, 1);
    expect(fn () => app(MoveQuotationBackToDraft::class)->handle($manager, $q, 1))
        ->toThrow(InvalidQuotationTransition::class, 'This quotation has a project, so it cannot go back to Draft.');

    $s = readyQuotation(['status' => QuotationStatus::Sent, 'sent_at' => now()]);
    $back = app(MoveQuotationBackToDraft::class)->handle($manager, $s, 1);
    expect($back->status)->toBe(QuotationStatus::Draft)->and($back->sent_at)->toBeNull();
});

it('creates one project from an accepted quotation with the subtotal as contract value', function () {
    $q = readyQuotation(['status' => QuotationStatus::Accepted]);

    $project = app(CreateProjectFromQuotation::class)->handle($q->preparer, $q, 1);

    expect($project->quotation_id)->toBe($q->id)->and($project->tender_id)->toBeNull()
        ->and($project->project_charge_bp)->toBe(900)
        ->and($project->lines->map(fn ($l) => [$l->pd_group, $l->name, $l->budget_sen])->all())->toBe([[PdGroup::Collection, 'Contract value', 3560000]])
        ->and($q->activity()->first()->description)->toBe('Project created from the quotation')
        ->and(fn () => app(CreateProjectFromQuotation::class)->handle($q->preparer, $q, 1))->toThrow(DomainException::class, 'This quotation already has a project.');

    $sent = readyQuotation(['status' => QuotationStatus::Sent]);
    expect(fn () => app(CreateProjectFromQuotation::class)->handle($sent->preparer, $sent, 1))->toThrow(InvalidQuotationTransition::class);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Exceptions/QuotationIncomplete.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class QuotationIncomplete extends RuntimeException
{
    /** @param list<string> $missing e.g. ['a customer name', 'a subject'] */
    public function __construct(array $missing)
    {
        $list = count($missing) > 1 ? implode(', ', array_slice($missing, 0, -1)).' and '.end($missing) : $missing[0];
        parent::__construct("Add {$list} before marking this quotation Sent.");
    }
}
```

`app/Actions/Quotations/MarkQuotationSent.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Exceptions\QuotationIncomplete;
use App\Models\{ActivityLog, Quotation, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class MarkQuotationSent
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Draft], 'marked Sent');

            $missing = [];
            if (trim((string) $q->customer_name) === '') {
                $missing[] = 'a customer name';
            }
            if (trim((string) $q->subject) === '') {
                $missing[] = 'a subject';
            }
            if ($q->items->isEmpty()) {
                $missing[] = 'at least one item';
            } elseif ($q->totals()['total_sen'] <= 0) {
                $missing[] = 'a total above RM 0.00';
            }
            if ($missing !== []) {
                throw new QuotationIncomplete($missing);
            }

            $q->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now(), 'sent_by' => $actor->id]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_sent', 'Marked Sent — total '.Money::format($q->totals()['total_sen']));

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/MarkQuotationAccepted.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, Quotation, User};
use Illuminate\Support\Facades\DB;

final class MarkQuotationAccepted
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Sent], 'marked Accepted');
            $q->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now(), 'accepted_by' => $actor->id]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_accepted', 'Marked Accepted');

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/MarkQuotationRejected.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, Quotation, User};
use Illuminate\Support\Facades\DB;

final class MarkQuotationRejected
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Sent], 'marked Rejected');
            $q->forceFill(['status' => QuotationStatus::Rejected, 'rejected_at' => now(), 'rejected_by' => $actor->id]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_rejected', 'Marked Rejected');

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/MoveQuotationBackToDraft.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Exceptions\InvalidQuotationTransition;
use App\Models\{ActivityLog, Quotation, User};
use Illuminate\Support\Facades\DB;

final class MoveQuotationBackToDraft
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion, 'backToDraft');
            $this->requireStatus($q, [QuotationStatus::Sent, QuotationStatus::Rejected, QuotationStatus::Accepted], 'moved back to Draft');
            if ($q->project()->exists()) {
                throw new InvalidQuotationTransition('This quotation has a project, so it cannot go back to Draft.');
            }
            $was = $q->displayLabel();
            $q->forceFill(['status' => QuotationStatus::Draft, 'sent_at' => null, 'sent_by' => null, 'accepted_at' => null,
                'accepted_by' => null, 'rejected_at' => null, 'rejected_by' => null]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_reopened', "Moved back to Draft (was {$was})");

            return $q->fresh();
        });
    }
}
```

`app/Actions/Quotations/ReviseQuotation.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, Quotation, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Facades\DB;

final class ReviseQuotation
{
    use GuardsQuotation;

    /** Returns the new draft revision. */
    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Sent], 'revised');

            $base = preg_replace('/-R\d+$/', '', $q->number);
            $n = Quotation::where('number', 'like', $base.'-R%')->lockForUpdate()->count() + 1;

            $new = $q->replicate(['number', 'status', 'sent_at', 'sent_by', 'accepted_at', 'accepted_by', 'rejected_at', 'rejected_by']);
            $new->forceFill([
                'number' => "{$base}-R{$n}", 'status' => QuotationStatus::Draft, 'revision_of_id' => $q->id,
                'quote_date' => MalaysiaTime::today()->format('Y-m-d'), 'updated_by' => $actor->id, 'version' => 1,
            ])->save();
            foreach ($q->items as $item) {
                $new->items()->create($item->only(['position', 'title', 'details', 'quantity', 'unit', 'unit_price_sen']));
            }

            $q->forceFill(['status' => QuotationStatus::Revised]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_revised', "Revised as {$new->number}");
            ActivityLog::record($new, $actor, 'quotation_created', "Revision of {$q->number} created");

            return $new->fresh();
        });
    }
}
```

`app/Actions/Quotations/DuplicateQuotation.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, CompanyProfile, Quotation, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Facades\{DB, Gate};

/** A fresh draft for the person duplicating, with the current letterhead. Anyone who can view may duplicate. */
final class DuplicateQuotation
{
    public function __construct(private GenerateQuotationNumber $numbers) {}

    public function handle(User $actor, Quotation $source): Quotation
    {
        Gate::forUser($actor)->authorize('view', $source);

        return DB::transaction(function () use ($actor, $source) {
            $today = MalaysiaTime::today();
            $samePerson = $source->prepared_by === $actor->id;
            $copy = Quotation::create([
                ...$source->only(['validity_days', 'customer_name', 'attention', 'attention_phone', 'attention_email',
                    'customer_address', 'subject', 'show_signature', 'show_stamp', 'sst_bp', 'terms']),
                'number' => $this->numbers->next($today->year),
                'status' => QuotationStatus::Draft,
                'quote_date' => $today->format('Y-m-d'),
                'prepared_by' => $actor->id,
                'preparer_position' => $samePerson ? $source->preparer_position : null,
                'preparer_phone' => $samePerson ? $source->preparer_phone : null,
                'preparer_email' => $samePerson ? $source->preparer_email : $actor->email,
                'letterhead' => CompanyProfile::current()->letterhead(),
                'updated_by' => $actor->id,
                'version' => 1,
            ]);
            foreach ($source->items as $item) {
                $copy->items()->create($item->only(['position', 'title', 'details', 'quantity', 'unit', 'unit_price_sen']));
            }
            ActivityLog::record($copy, $actor, 'quotation_created', "Duplicated from {$source->number}");

            return $copy->fresh();
        });
    }
}
```

`app/Actions/Quotations/CreateProjectFromQuotation.php`:

```php
<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\{PdGroup, QuotationStatus};
use App\Models\{ActivityLog, FinanceSetting, Project, Quotation, User};
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateProjectFromQuotation
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Project
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Accepted], 'turned into a project');
            if ($q->project()->exists()) {
                throw new DomainException('This quotation already has a project.');
            }
            $defaults = FinanceSetting::current();
            $project = Project::create([
                'quotation_id' => $q->id, 'approved_margin_bp' => 0,
                'project_charge_bp' => $defaults->project_charge_bp, 'commission_share_bp' => $defaults->commission_share_bp,
                'updated_by' => $actor->id, 'version' => 1,
            ]);
            $project->lines()->create(['position' => 1, 'pd_group' => PdGroup::Collection, 'name' => 'Contract value',
                'budget_sen' => $q->totals()['subtotal_sen'], 'updated_by' => $actor->id]);
            ActivityLog::record($q, $actor, 'project_created', 'Project created from the quotation');

            return $project->fresh();
        });
    }
}
```

- [ ] **Step 4: Run** `pest tests/Feature/Quotations` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: quotation life cycle, revise, duplicate and create project`

---

### Task 6: The PDF

**Files:**
- Modify: `docker/php/Dockerfile` (GD), `composer.json`/`composer.lock` (`barryvdh/laravel-dompdf`)
- Create: `app/Pdf/QuotationPdf.php`, `app/Http/Controllers/QuotationPdfController.php`, `resources/views/quotations/pdf.blade.php`, `resources/views/quotations/pdf-failed.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Quotations/QuotationPdfTest.php`

**Interfaces — Produces:** `QuotationPdf::html(Quotation): string`, `QuotationPdf::bytes(Quotation): string`; route `quotations.pdf` (`/quotations/{quotation}/pdf`, `?inline=1` to show in the page).

- [ ] **Step 1: Add GD to the image and install Dompdf**

In `docker/php/Dockerfile`, change the first `RUN` block's packages and extensions to:

```dockerfile
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install pdo_mysql zip intl bcmath pcntl opcache gd \
 && pecl install pcov && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*
```

Then:

```bash
docker compose build app && docker compose up -d app worker scheduler
MSYS_NO_PATHCONV=1 docker compose exec -T app php -m | grep -i '^gd$'
MSYS_NO_PATHCONV=1 docker compose exec -T app composer require barryvdh/laravel-dompdf
```
Expected: `gd` printed; composer reports the package installed.

- [ ] **Step 2: Failing test** `tests/Feature/Quotations/QuotationPdfTest.php`

```php
<?php

use App\Enums\QuotationStatus;
use App\Models\{Quotation, QuotationItem, User};
use App\Pdf\QuotationPdf;
use Illuminate\Support\Facades\Storage;

const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function pdfQuotation(array $attrs = []): Quotation
{
    $preparer = User::factory()->create(['name' => 'Siti Aisyah']);
    $q = Quotation::factory()->create(array_merge(['number' => 'QTN-2026-0012', 'prepared_by' => $preparer->id,
        'customer_name' => 'Jabatan Perpaduan Negara dan Integrasi Nasional', 'attention' => 'Puan Rozita binti Hassan',
        'subject' => 'Supply of network switches', 'preparer_position' => 'Sales Executive',
        'terms' => "Prices quoted are in Ringgit Malaysia (RM).\n\nPayment terms: 30 days from the date of invoice."], $attrs));
    QuotationItem::factory()->for($q)->create(['title' => '24-port Gigabit PoE+ managed switch', 'quantity' => 6, 'unit_price_sen' => 485000,
        'details' => "Power Supply: 100–240 V AC\nRack mountable"]);
    QuotationItem::factory()->for($q)->create(['position' => 2, 'title' => 'Installation', 'unit' => 'Lot', 'unit_price_sen' => 650000]);

    return $q->fresh();
}

it('lays out the quotation like the prototype', function () {
    $html = app(QuotationPdf::class)->html(pdfQuotation());

    expect($html)->toContain('QUOTATION')->toContain('QTN-2026-0012')->toContain('Jabatan Perpaduan Negara dan Integrasi Nasional')
        ->toContain('Puan Rozita binti Hassan')->toContain('<strong>Power Supply:</strong> 100–240 V AC')->toContain('Rack mountable')
        ->toContain('35,600.00')->toContain('SST (8.0%)')->toContain('2,848.00')->toContain('38,448.00')
        ->toContain('Ringgit Malaysia Thirty Eight Thousand Four Hundred Forty Eight Only')
        ->toContain('1.</td>')->toContain('2.</td>')       // two numbered terms, blank line skipped
        ->toContain('Siti Aisyah')->toContain('Sales Executive')->toContain('This is a computer-generated quotation.')
        ->not->toContain('data:image');
});

it('shows the stamp only when switched on and present', function () {
    Storage::disk('local')->put('company-stamps/s.png', base64_decode(TINY_PNG));
    $letterhead = ['name' => 'CMT Sdn. Bhd.', 'stamp_path' => 'company-stamps/s.png'];

    expect(app(QuotationPdf::class)->html(pdfQuotation(['letterhead' => $letterhead])))->toContain('data:image/png;base64,')
        ->and(app(QuotationPdf::class)->html(pdfQuotation(['number' => 'QTN-2026-0013', 'letterhead' => $letterhead, 'show_stamp' => false])))->not->toContain('data:image')
        ->and(app(QuotationPdf::class)->html(pdfQuotation(['number' => 'QTN-2026-0014', 'letterhead' => ['name' => 'X', 'stamp_path' => 'gone.png']])))->not->toContain('data:image');
});

it('hides the typed signature when switched off', function () {
    expect(app(QuotationPdf::class)->html(pdfQuotation(['show_signature' => false])))->not->toContain('class="signature"');
});

it('downloads a real PDF named after the quotation, or shows it in the page', function () {
    $q = pdfQuotation(['status' => QuotationStatus::Sent]);
    $this->actingAs(User::factory()->create());

    $r = $this->get(route('quotations.pdf', $q));
    $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($r->getContent(), 0, 4))->toBe('%PDF')
        ->and($r->headers->get('Content-Disposition'))->toBe('attachment; filename="QTN-2026-0012.pdf"');

    expect($this->get(route('quotations.pdf', [$q, 'inline' => 1]))->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('explains when the PDF could not be made', function () {
    $this->actingAs(User::factory()->create());
    $this->mock(QuotationPdf::class, fn ($m) => $m->shouldReceive('bytes')->andThrow(new RuntimeException('boom')));

    $this->get(route('quotations.pdf', pdfQuotation()))->assertStatus(500)->assertSee('The PDF could not be made');
});

it('needs a signed-in user', function () {
    $this->get(route('quotations.pdf', pdfQuotation()))->assertRedirect(route('login'));
});
```

- [ ] **Step 3: Run** — Expected: FAIL (route / class missing).

- [ ] **Step 4: Implement**

`app/Pdf/QuotationPdf.php`:

```php
<?php

namespace App\Pdf;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** Builds the quotation PDF. One Blade layout is used for the Preview tab and the download. */
class QuotationPdf
{
    public function html(Quotation $quotation): string
    {
        $quotation->loadMissing('items', 'preparer');

        return view('quotations.pdf', [
            'q' => $quotation,
            'totals' => $quotation->totals(),
            'stamp' => $this->stamp($quotation),
            'terms' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $quotation->terms)))),
        ])->render();
    }

    public function bytes(Quotation $quotation): string
    {
        return Pdf::loadHTML($this->html($quotation))->setPaper('a4')->output();
    }

    /** The stamp as a data: URI, or null when switched off, not uploaded or missing. */
    private function stamp(Quotation $q): ?string
    {
        $path = $q->letterhead['stamp_path'] ?? null;
        $disk = Storage::disk('local');
        if (! $q->show_stamp || ! $path || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }
}
```

`app/Http/Controllers/QuotationPdfController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Pdf\QuotationPdf;
use Illuminate\Http\Request;
use Throwable;

class QuotationPdfController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation, QuotationPdf $pdf)
    {
        $this->authorize('view', $quotation);
        try {
            $bytes = $pdf->bytes($quotation);
        } catch (Throwable $e) {
            report($e);

            return response()->view('quotations.pdf-failed', ['quotation' => $quotation], 500);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$quotation->number.'.pdf"',
        ]);
    }
}
```
(If `app/Http/Controllers/Controller.php` lacks `AuthorizesRequests`, use `\Illuminate\Support\Facades\Gate::authorize('view', $quotation);` instead.)

`resources/views/quotations/pdf-failed.blade.php`:

```blade
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>PDF not available</title></head>
<body style="font-family: sans-serif; padding: 2rem">
    <h1 style="font-size: 1.1rem">The PDF could not be made</h1>
    <p>Something went wrong while building the PDF for {{ $quotation->number }}. Please try again in a minute; if it keeps happening, tell your administrator.</p>
    <p><a href="{{ route('quotations.show', $quotation) }}">Back to the quotation</a></p>
</body></html>
```

`resources/views/quotations/pdf.blade.php` (Dompdf understands plain tables and inline CSS — no Tailwind):

```blade
@php
    use App\Support\{Money, Percent};
    $l = $q->letterhead;
    $num = fn (int $sen) => ltrim(Money::format($sen), 'RM ');
    $detail = function (string $line) {
        return preg_match('/^([^:]{1,40}):\s*(.+)$/u', $line, $m)
            ? '<strong>'.e($m[1]).':</strong> '.e($m[2])
            : e($line);
    };
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $q->number }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2933; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b7280; }
    .items th { background: #f1f2f4; font-size: 8.5px; text-transform: uppercase; padding: 6px; text-align: left; }
    .items td { padding: 6px; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
    .items thead { display: table-header-group; }
    .right { text-align: right; }
    .signature { font-family: Times, serif; font-style: italic; font-size: 20px; color: #1d4ed8; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 65%">
            <div style="font-size: 13px; font-weight: bold">{{ $l['name'] ?? '' }}
                @if (! empty($l['registration_no'])) <span class="muted" style="font-size: 8px; font-weight: normal">({{ $l['registration_no'] }})</span> @endif</div>
            <div class="muted">{!! nl2br(e($l['address'] ?? '')) !!}</div>
            <div class="muted">{{ collect([$l['phone'] ?? null, $l['email'] ?? null, $l['website'] ?? null])->filter()->map(fn ($v, $k) => $k === 0 ? 'Tel: '.$v : $v)->implode(' · ') }}</div>
            @if (! empty($l['sst_no'])) <div class="muted">SST No.: {{ $l['sst_no'] }}</div> @endif
        </td>
        <td class="right" style="vertical-align: top">
            <div style="font-size: 18px; font-weight: bold; letter-spacing: 1px">QUOTATION</div>
            <table style="width: auto; margin-left: auto">
                <tr><td class="muted right">No.</td><td class="right"><strong>{{ $q->number }}</strong></td></tr>
                <tr><td class="muted right">Date</td><td class="right">{{ $q->quote_date->format('d M Y') }}</td></tr>
                <tr><td class="muted right">Valid until</td><td class="right">{{ $q->validUntil()->format('d M Y') }}</td></tr>
            </table>
        </td>
    </tr>
</table>
<div style="border-bottom: 1.5px solid #dc2626; margin: 10px 0 14px"></div>

<div class="muted" style="font-size: 8px; font-weight: bold">QUOTATION TO</div>
<div style="font-weight: bold">{{ $q->customer_name }}</div>
<div class="muted">{!! nl2br(e((string) $q->customer_address)) !!}</div>
@if ($q->attention)
    <div style="margin-top: 4px"><span class="muted">Attn:</span> <strong>{{ $q->attention }}</strong>
        {{ collect([$q->attention_phone, $q->attention_email])->filter()->implode(' · ') }}</div>
@endif
<div style="background: #f3f4f6; padding: 6px 8px; margin: 10px 0"><strong>Subject:</strong> {{ $q->subject }}</div>

<table class="items">
    <thead>
        <tr><th style="width: 24px">No</th><th>Description</th><th class="right" style="width: 40px">Qty</th><th style="width: 40px">Unit</th>
            <th class="right" style="width: 80px">Unit price (RM)</th><th class="right" style="width: 85px">Amount (RM)</th></tr>
    </thead>
    <tbody>
    @foreach ($q->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $item->title }}</strong>
                @foreach (array_filter(array_map('trim', preg_split('/\R/', (string) $item->details))) as $line)
                    <div class="muted" style="font-size: 8.5px">{!! $detail($line) !!}</div>
                @endforeach
            </td>
            <td class="right">{{ $item->quantity }}</td>
            <td>{{ $item->unit }}</td>
            <td class="right">{{ $num($item->unit_price_sen) }}</td>
            <td class="right">{{ $num($totals['lines'][$i]) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table style="width: 45%; margin-left: auto; margin-top: 8px">
    <tr><td>Subtotal</td><td class="right">{{ $num($totals['subtotal_sen']) }}</td></tr>
    <tr><td>SST ({{ Percent::format($q->sst_bp) }})</td><td class="right">{{ $num($totals['sst_sen']) }}</td></tr>
    <tr><td style="border-top: 1.5px solid #1f2933; font-weight: bold">Total (RM)</td>
        <td class="right" style="border-top: 1.5px solid #1f2933; font-weight: bold">{{ $num($totals['total_sen']) }}</td></tr>
</table>
<div class="muted" style="font-style: italic; margin: 8px 0 14px">{{ $totals['words'] }}</div>

@if ($terms !== [])
    <div style="font-weight: bold; font-size: 8.5px">TERMS &amp; CONDITIONS</div>
    <table>
        @foreach ($terms as $n => $term)
            <tr><td style="width: 18px; vertical-align: top">{{ $n + 1 }}.</td><td>{{ $term }}</td></tr>
        @endforeach
    </table>
@endif

<table style="margin-top: 24px">
    <tr>
        <td style="width: 50%; vertical-align: bottom">
            <div class="muted">Prepared by,</div>
            <div style="height: 46px; position: relative">
                @if ($q->show_signature) <div class="signature">{{ $q->preparer->name }}</div> @endif
                @if ($stamp) <img src="{{ $stamp }}" style="height: 60px; position: absolute; left: 140px; top: -8px" alt=""> @endif
            </div>
            <div style="border-top: 1px solid #9ca3af; width: 80%; padding-top: 3px"><strong>{{ $q->preparer->name }}</strong></div>
            @if ($q->preparer_position) <div class="muted">{{ $q->preparer_position }}</div> @endif
            <div class="muted">{{ $l['name'] ?? '' }}</div>
            <div class="muted">{{ collect([$q->preparer_phone, $q->preparer_email])->filter()->implode(' · ') }}</div>
        </td>
        <td style="vertical-align: bottom">
            <div class="muted">Accepted by,</div>
            <div style="height: 46px"></div>
            <div style="border-top: 1px solid #9ca3af; width: 85%; padding-top: 3px" class="muted">Name, signature &amp; company stamp<br>Date:</div>
        </td>
    </tr>
</table>
<div class="muted" style="text-align: center; border-top: 1px solid #e5e7eb; margin-top: 18px; padding-top: 6px">This is a computer-generated quotation.</div>
</body>
</html>
```

`routes/web.php` — in the auth group:

```php
    Route::get('/quotations/{quotation}/pdf', \App\Http\Controllers\QuotationPdfController::class)->whereNumber('quotation')->name('quotations.pdf');
```

- [ ] **Step 5: Run** `pest tests/Feature/Quotations/QuotationPdfTest.php` — Expected: PASS. (The PDF's look is checked by eye in the Task 8/9 browser walkthrough, where the Preview tab shows it.)
- [ ] **Step 6: Commit** — `feat: quotation PDF with Dompdf`

---

### Task 7: Company letterhead and stamp (Finance Settings)

**Files:**
- Create: `app/Actions/Company/{SaveCompanyProfile,SaveCompanyStamp,RemoveCompanyStamp}.php`, `app/Http/Controllers/CompanyStampController.php`
- Modify: `app/Livewire/FinanceSettings.php`, `resources/views/livewire/finance-settings.blade.php`, `routes/web.php`
- Test: `tests/Feature/Livewire/CompanyLetterheadTest.php`

**Interfaces — Produces:** `SaveCompanyProfile::handle(User, array $data): CompanyProfile` (fields `name, registration_no, sst_no, address, phone, email, website, default_terms, default_sst_bp`); `SaveCompanyStamp::handle(User, UploadedFile): CompanyProfile`; `RemoveCompanyStamp::handle(User): CompanyProfile`; route `company.stamp`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Actions\Company\SaveCompanyProfile;
use App\Livewire\FinanceSettings;
use App\Models\{CompanyProfile, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const STAMP_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

it('edits the letterhead, default terms and SST', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->assertSet('company.name', 'CMT Sdn. Bhd.')->assertSet('company.sst', '8')
        ->set('company.sst_no', 'W10-1808-32000001')->set('company.sst', '6')->set('company.default_terms', "One\nTwo")
        ->call('saveCompany')->assertHasNoErrors()->assertSee('Letterhead saved. New quotations will use it.');

    $c = CompanyProfile::current();
    expect($c->sst_no)->toBe('W10-1808-32000001')->and($c->default_sst_bp)->toBe(600)->and($c->default_terms)->toBe("One\nTwo");
});

it('validates the letterhead', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->set('company.name', '')->set('company.email', 'nope')->set('company.sst', '100')
        ->call('saveCompany')->assertHasErrors(['company.name', 'company.email', 'company.sst']);
});

it('uploads, shows and removes the stamp, keeping old files', function () {
    $admin = User::factory()->admin()->create();
    $c = Livewire::actingAs($admin)->test(FinanceSettings::class)
        ->set('stamp', UploadedFile::fake()->createWithContent('stamp.png', base64_decode(STAMP_PNG)))
        ->call('uploadStamp')->assertHasNoErrors();
    $path = CompanyProfile::current()->stamp_path;
    expect($path)->toStartWith('company-stamps/')->and(Storage::disk('local')->exists($path))->toBeTrue();

    $this->actingAs($admin)->get(route('company.stamp'))->assertOk()->assertHeader('Content-Type', 'image/png');

    $c->call('removeStamp');
    expect(CompanyProfile::current()->stamp_path)->toBeNull()->and(Storage::disk('local')->exists($path))->toBeTrue();
    $this->actingAs($admin)->get(route('company.stamp'))->assertNotFound();
});

it('refuses files that are not a small PNG or JPG', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(FinanceSettings::class)
        ->set('stamp', UploadedFile::fake()->create('stamp.pdf', 10, 'application/pdf'))
        ->call('uploadStamp')->assertHasErrors('stamp')->assertSee('Upload a PNG or JPG image of 1 MB or less.');
});

it('keeps the letterhead for admins only', function () {
    expect(fn () => app(SaveCompanyProfile::class)->handle(User::factory()->manager()->create(), ['name' => 'X']))
        ->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->manager()->create())->get(route('company.stamp'))->assertForbidden();
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Actions/Company/SaveCompanyProfile.php`:

```php
<?php

namespace App\Actions\Company;

use App\Models\{CompanyProfile, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

final class SaveCompanyProfile
{
    public const FIELDS = ['name', 'registration_no', 'sst_no', 'address', 'phone', 'email', 'website', 'default_terms', 'default_sst_bp'];

    public function handle(User $actor, array $data): CompanyProfile
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $c = CompanyProfile::current();
        $c->update(Arr::only($data, self::FIELDS));

        return $c;
    }
}
```

`app/Actions/Company/SaveCompanyStamp.php`:

```php
<?php

namespace App\Actions\Company;

use App\Models\{CompanyProfile, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/** Stores a new stamp file. Old files are kept: quotations created earlier still point at them. */
final class SaveCompanyStamp
{
    public function handle(User $actor, UploadedFile $file): CompanyProfile
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $c = CompanyProfile::current();
        $c->update(['stamp_path' => $file->store('company-stamps', 'local')]);

        return $c;
    }
}
```

`app/Actions/Company/RemoveCompanyStamp.php`:

```php
<?php

namespace App\Actions\Company;

use App\Models\{CompanyProfile, User};
use Illuminate\Support\Facades\Gate;

final class RemoveCompanyStamp
{
    public function handle(User $actor): CompanyProfile
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $c = CompanyProfile::current();
        $c->update(['stamp_path' => null]); // the file stays for quotations that already use it

        return $c;
    }
}
```

`app/Http/Controllers/CompanyStampController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;

class CompanyStampController extends Controller
{
    public function __invoke()
    {
        $path = CompanyProfile::current()->stamp_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
```

Route (auth group): `Route::get('/settings/finance/stamp', \App\Http\Controllers\CompanyStampController::class)->middleware('can:manage-finance')->name('company.stamp');`

`FinanceSettings.php` — add `use Livewire\WithFileUploads;` + `use WithFileUploads;`, imports `App\Actions\Company\{RemoveCompanyStamp, SaveCompanyProfile, SaveCompanyStamp}`, `App\Models\CompanyProfile`, and:

```php
    public array $company = [];
    public $stamp = null;
```
In `mount()` add `$this->loadCompany();` and:

```php
    private function loadCompany(): void
    {
        $c = CompanyProfile::current();
        $this->company = [
            'name' => $c->name, 'registration_no' => (string) $c->registration_no, 'sst_no' => (string) $c->sst_no,
            'address' => (string) $c->address, 'phone' => (string) $c->phone, 'email' => (string) $c->email,
            'website' => (string) $c->website, 'default_terms' => (string) $c->default_terms,
            'sst' => Percent::toInput($c->default_sst_bp),
        ];
    }

    public function saveCompany(): void
    {
        $this->validate([
            'company.name' => ['required', 'string', 'max:255'],
            'company.registration_no' => ['nullable', 'string', 'max:255'],
            'company.sst_no' => ['nullable', 'string', 'max:255'],
            'company.address' => ['nullable', 'string', 'max:1000'],
            'company.phone' => ['nullable', 'string', 'max:50'],
            'company.email' => ['nullable', 'email', 'max:255'],
            'company.website' => ['nullable', 'string', 'max:255'],
            'company.default_terms' => ['nullable', 'string', 'max:5000'],
            'company.sst' => ['required', new Percentage],
        ], [], ['company.name' => 'company name', 'company.email' => 'email', 'company.sst' => 'default SST']);
        app(SaveCompanyProfile::class)->handle(auth()->user(), [
            ...array_map(fn ($v) => $v === '' ? null : $v, array_diff_key($this->company, ['sst' => true])),
            'name' => $this->company['name'],
            'default_sst_bp' => Percent::parseBp($this->company['sst']),
        ]);
        $this->notice = 'Letterhead saved. New quotations will use it.';
    }

    public function uploadStamp(): void
    {
        $this->validate(['stamp' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:1024']],
            ['stamp.*' => 'Upload a PNG or JPG image of 1 MB or less.']);
        app(SaveCompanyStamp::class)->handle(auth()->user(), $this->stamp);
        $this->reset('stamp');
        $this->notice = 'Stamp saved. New quotations will use it.';
    }

    public function removeStamp(): void
    {
        app(RemoveCompanyStamp::class)->handle(auth()->user());
        $this->notice = 'Stamp removed. Quotations already created keep theirs.';
    }
```
and pass `'hasStamp' => CompanyProfile::current()->stamp_path !== null` to the view.

`finance-settings.blade.php` — add a section after "Company defaults":

```blade
    <section class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Company letterhead</h2>
        <p class="text-sm text-muted">Printed at the top of every quotation. Each new quotation keeps a copy, so changes here never alter quotations already created.</p>
        <div class="grid gap-3 text-sm md:grid-cols-2">
            @foreach (['name' => 'Company name', 'registration_no' => 'Registration no.', 'sst_no' => 'SST no.', 'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website'] as $k => $label)
                <label class="flex flex-col gap-1">{{ $label }}
                    <input wire:model="company.{{ $k }}" class="{{ $input }}">
                    @error("company.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                </label>
            @endforeach
            <label class="flex flex-col gap-1 md:col-span-2">Address
                <textarea wire:model="company.address" rows="2" class="{{ $input }}"></textarea></label>
            <label class="flex flex-col gap-1">Default SST %
                <input wire:model="company.sst" class="{{ $input }} w-24">
                @error('company.sst') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 md:col-span-2">Default terms &amp; conditions (one per line)
                <textarea wire:model="company.default_terms" rows="6" class="{{ $input }}"></textarea></label>
        </div>
        <button type="button" wire:click="saveCompany" class="rounded-lg bg-chip px-4 py-1.5 text-sm font-medium text-chip-ink hover:bg-chip-hover">Save letterhead</button>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-3 text-sm">
            <span class="font-medium">Company stamp</span>
            @if ($hasStamp)
                <img src="{{ route('company.stamp') }}?v={{ md5((string) \App\Models\CompanyProfile::current()->stamp_path) }}" alt="Company stamp" class="h-16 rounded border border-line bg-white p-1">
                <button type="button" wire:click="removeStamp" wire:confirm="Remove the stamp? Quotations already created keep theirs." class="text-bad-ink underline">Remove</button>
            @else
                <span class="text-muted">No stamp yet.</span>
            @endif
            <input type="file" wire:model="stamp" accept="image/png,image/jpeg" class="text-xs" aria-label="Stamp image">
            <button type="button" wire:click="uploadStamp" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Upload stamp</button>
            <span class="text-xs text-muted">PNG with a transparent background works best (1 MB or less).</span>
        </div>
        @error('stamp') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
    </section>
```

- [ ] **Step 4: Run** `pest tests/Feature/Livewire/CompanyLetterheadTest.php tests/Feature/Livewire/FinanceSettingsTest.php` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: company letterhead and stamp in Finance Settings`

---

### Task 8: Quotation screens

**Files:**
- Create: `app/Quotations/QuotationListQuery.php`, `app/Livewire/QuotationList.php`, `resources/views/livewire/quotation-list.blade.php`
- Replace: `app/Livewire/QuotationPage.php`, `resources/views/livewire/quotation-page.blade.php` (Task 3 stubs)
- Modify: `routes/web.php`, `resources/views/layouts/partials/sidebar.blade.php`
- Test: `tests/Feature/Livewire/QuotationScreensTest.php`

**Interfaces — Produces:** route `quotations.index`; `QuotationListQuery::build(string $status, string $search, bool $mine, User $viewer, CarbonImmutable $today): Builder`; `QuotationPage` public state `tab, version, form, items, problem, notice`; actions `addItem, removeItem(int), moveItem(int, int), resetTerms, markSent, markAccepted, markRejected, revise, duplicate, backToDraft, createProject`.

- [ ] **Step 1: Failing test** `tests/Feature/Livewire/QuotationScreensTest.php`

```php
<?php

use App\Enums\QuotationStatus;
use App\Livewire\{QuotationList, QuotationPage};
use App\Models\{Quotation, QuotationItem, User};
use Livewire\Livewire;

function myQuotation(array $attrs = []): array
{
    $u = User::factory()->create(['name' => 'Siti Aisyah']);
    $q = Quotation::factory()->create(array_merge(['prepared_by' => $u->id, 'customer_name' => 'JPNIN', 'subject' => 'Switches'], $attrs));

    return [$u, $q];
}

it('lists quotations with search, status and mine filters', function () {
    [$u] = myQuotation(['number' => 'QTN-2026-0012', 'status' => QuotationStatus::Sent, 'quote_date' => now()->toDateString()]);
    myQuotation(['number' => 'QTN-2026-0010', 'status' => QuotationStatus::Sent, 'quote_date' => '2020-01-01', 'customer_name' => 'Kuantan']);
    myQuotation(['number' => 'QTN-2026-0011', 'status' => QuotationStatus::Accepted, 'customer_name' => 'Klang']);

    Livewire::actingAs($u)->test(QuotationList::class)
        ->assertSee('QTN-2026-0012')->assertSee('QTN-2026-0010')->assertSee('Expired')
        ->set('status', 'expired')->assertSee('QTN-2026-0010')->assertDontSee('QTN-2026-0012')
        ->set('status', 'sent')->assertSee('QTN-2026-0012')->assertDontSee('QTN-2026-0010')
        ->set('status', 'all')->set('search', 'Klang')->assertSee('QTN-2026-0011')->assertDontSee('QTN-2026-0012')
        ->set('search', '')->set('mine', true)->assertSee('QTN-2026-0012')->assertDontSee('QTN-2026-0011');
});

it('creates a new draft and opens it', function () {
    $u = User::factory()->create();

    Livewire::actingAs($u)->test(QuotationList::class)->call('create')
        ->assertRedirect(route('quotations.show', Quotation::first()));
});

it('saves details when a field is left and shows live totals', function () {
    [$u, $q] = myQuotation();
    QuotationItem::factory()->for($q)->create(['quantity' => 6, 'unit_price_sen' => 485000]);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->assertSee('RM 31,428.00')                      // 29,100 + 8%
        ->set('form.customer_name', 'Jabatan Perpaduan')->assertHasNoErrors()
        ->set('form.sst', '6')->assertSee('RM 30,846.00')
        ->set('form.attention_email', 'not-an-email')->assertHasErrors('form.attention_email');

    expect($q->fresh()->customer_name)->toBe('Jabatan Perpaduan')->and($q->fresh()->sst_bp)->toBe(600);
});

it('adds, edits, moves and removes items', function () {
    [$u, $q] = myQuotation();

    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->set('tab', 'items')
        ->call('addItem')->call('addItem');
    [$a, $b] = $q->fresh()->items->all();
    $c->set("items.i{$b->id}.title", 'Switch')->set("items.i{$b->id}.unit_price", '4,850')->set("items.i{$b->id}.quantity", '6')
        ->assertSee('29,100.00')
        ->set("items.i{$b->id}.quantity", '0')->assertHasErrors("items.i{$b->id}.quantity")
        ->call('moveItem', $b->id, -1)
        ->call('removeItem', $a->id);

    expect($q->fresh()->items->pluck('title')->all())->toBe(['Switch'])->and($q->fresh()->items->first()->unit_price_sen)->toBe(485000);
});

it('goes Draft → Sent → Accepted → project, explaining what is missing first', function () {
    [$u, $q] = myQuotation(['customer_name' => null]);

    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->call('markSent')->assertSee('Add a customer name and at least one item before marking this quotation Sent.');

    $q->update(['customer_name' => 'JPNIN']);
    QuotationItem::factory()->for($q)->create();
    $c = Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q->fresh()])
        ->call('markSent')->assertSee('Sent')->assertSee('Mark Accepted')->assertDontSee('+ Add item')
        ->call('markAccepted')->assertSee('Create project')
        ->call('createProject');

    $c->assertRedirect(route('quotations.pd', $q));
    expect($q->fresh()->project)->not->toBeNull();
});

it('revises and duplicates into new drafts', function () {
    [$u, $q] = myQuotation(['status' => QuotationStatus::Sent, 'number' => 'QTN-2026-0012']);

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])->call('revise')
        ->assertRedirect(route('quotations.show', Quotation::where('number', 'QTN-2026-0012-R1')->first()));

    Livewire::actingAs(User::factory()->create())->test(QuotationPage::class, ['quotation' => $q->fresh()])->call('duplicate')
        ->assertRedirect(route('quotations.show', Quotation::latest('id')->first()));
});

it('is read-only for other staff, and offers Back to Draft to managers only', function () {
    [$preparer, $q] = myQuotation(['status' => QuotationStatus::Sent]);

    Livewire::actingAs(User::factory()->create())->test(QuotationPage::class, ['quotation' => $q])
        ->assertDontSee('Mark Accepted')->assertDontSee('Back to Draft')->assertSee('Duplicate')
        ->set('form.subject', 'hack')->assertForbidden();

    Livewire::actingAs($preparer)->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.subject', 'hack')->assertSee('This quotation has been sent');

    Livewire::actingAs(User::factory()->manager()->create())->test(QuotationPage::class, ['quotation' => $q])
        ->assertSee('Back to Draft')->call('backToDraft')->assertSee('Mark as Sent');

    expect($q->fresh()->subject)->toBe('Switches');
});

it('previews the real PDF and shows history', function () {
    [$u, $q] = myQuotation();
    App\Models\ActivityLog::record($q, $u, 'quotation_created', 'Quotation created');

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->set('tab', 'preview')->assertSeeHtml(route('quotations.pdf', [$q, 'inline' => 1]))
        ->set('tab', 'history')->assertSee('Quotation created');
});

it('links Quotations from the sidebar', function () {
    $this->actingAs(User::factory()->create())->get(route('settings'))->assertSee(route('quotations.index'));
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Quotations/QuotationListQuery.php`:

```php
<?php

namespace App\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{Quotation, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class QuotationListQuery
{
    public static function build(string $status, string $search, bool $mine, User $viewer, CarbonImmutable $today): Builder
    {
        $q = Quotation::query()->with(['items', 'preparer'])->orderByDesc('quote_date')->orderByDesc('id');
        $expiredSql = 'DATE_ADD(quote_date, INTERVAL validity_days DAY) < ?';

        match ($status) {
            'expired' => $q->where('status', QuotationStatus::Sent)->whereRaw($expiredSql, [$today->format('Y-m-d')]),
            'sent' => $q->where('status', QuotationStatus::Sent)->whereRaw('NOT ('.$expiredSql.')', [$today->format('Y-m-d')]),
            'draft', 'accepted', 'rejected', 'revised' => $q->where('status', $status),
            default => null,
        };

        $search = trim($search);
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $q->where(fn (Builder $w) => $w->where('number', 'like', $like)->orWhere('customer_name', 'like', $like)->orWhere('subject', 'like', $like));
        }
        if ($mine) {
            $q->where('prepared_by', $viewer->id);
        }

        return $q;
    }
}
```

`app/Livewire/QuotationList.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Quotations\CreateQuotation;
use App\Quotations\QuotationListQuery;
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Quotations')]
class QuotationList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = 'all';
    #[Url] public bool $mine = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $q = app(CreateQuotation::class)->handle(auth()->user());
        $this->redirectRoute('quotations.show', $q);
    }

    public function render()
    {
        return view('livewire.quotation-list', [
            'quotations' => QuotationListQuery::build($this->status, $this->search, $this->mine, auth()->user(), MalaysiaTime::today())->paginate(25),
        ]);
    }
}
```

`resources/views/livewire/quotation-list.blade.php`:

```blade
@php
    use App\Support\Money;
    $tone = ['draft' => 'bg-subtle text-muted', 'sent' => 'bg-info-bg text-info-ink', 'expired' => 'bg-warn-bg text-warn-ink',
        'accepted' => 'bg-good-bg text-good-ink', 'rejected' => 'bg-bad-bg text-bad-ink', 'revised' => 'bg-subtle text-muted'];
@endphp
<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Quotations</h1>
        <p class="text-sm text-muted">Quick quotations outside the formal tender process</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <input wire:model.live.debounce.300ms="search" placeholder="Search quotation no., customer or subject"
               class="min-w-64 flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm" aria-label="Search">
        @foreach (['all' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'expired' => 'Expired', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'revised' => 'Revised'] as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key }}')" @class([
                'rounded-full border px-3 py-1 text-xs',
                'border-chip bg-chip text-chip-ink' => $status === $key,
                'border-line hover:bg-hover' => $status !== $key,
            ])>{{ $label }}</button>
        @endforeach
        <label class="flex items-center gap-1 text-sm"><input type="checkbox" wire:model.live="mine"> Mine</label>
        <button type="button" wire:click="create" class="ml-auto rounded-lg bg-chip px-3 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">+ New Quotation</button>
    </div>

    <div class="relative overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase text-muted">
                <tr><th class="px-3 py-2">Quotation No.</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2">Subject</th>
                    <th class="px-3 py-2">Prepared by</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2">Valid until</th><th class="px-3 py-2">Status</th></tr>
            </thead>
            <tbody>
            @forelse ($quotations as $q)
                <tr wire:key="q-{{ $q->id }}" class="border-t border-line hover:bg-hover">
                    <td class="px-3 py-2 font-mono text-xs"><a href="{{ route('quotations.show', $q) }}" class="underline">{{ $q->number }}</a></td>
                    <td class="whitespace-nowrap px-3 py-2">{{ $q->quote_date->format('d M Y') }}</td>
                    <td class="px-3 py-2">{{ $q->customer_name ?: '—' }}</td>
                    <td class="px-3 py-2 text-muted">{{ $q->subject ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $q->preparer->name }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">{{ Money::format($q->totals()['total_sen']) }}</td>
                    <td @class(['whitespace-nowrap px-3 py-2', 'text-bad-ink' => $q->isExpired()])>{{ $q->validUntil()->format('d M Y') }}</td>
                    <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$q->displayStatus()] }}">{{ $q->displayLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-10 text-center text-muted">No quotations match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $quotations->links('pagination.pager') }}
</div>
```

`app/Livewire/QuotationPage.php` (replaces the Task 3 stub):

```php
<?php

namespace App\Livewire;

use App\Actions\Quotations\{AddQuotationItem, CreateProjectFromQuotation, DuplicateQuotation, MarkQuotationAccepted, MarkQuotationRejected, MarkQuotationSent, MoveQuotationBackToDraft, MoveQuotationItem, RemoveQuotationItem, ReviseQuotation, UpdateQuotation, UpdateQuotationItem};
use App\Exceptions\{InvalidQuotationTransition, QuotationIncomplete, QuotationLocked, StaleQuotation};
use App\Models\{CompanyProfile, Quotation, QuotationItem, User};
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\{Layout, Url};
use Livewire\Component;

#[Layout('layouts.app')]
class QuotationPage extends Component
{
    public Quotation $quotation;
    #[Url] public string $tab = 'details';
    public int $version = 1;
    public array $form = [];
    /** Item fields keyed "i{id}". */
    public array $items = [];
    public ?string $problem = null;

    public function mount(Quotation $quotation): void
    {
        $this->quotation = $quotation;
        $this->load();
    }

    private function load(): void
    {
        $q = $this->quotation = $this->quotation->fresh(['items']);
        $this->version = $q->version;
        $this->form = [
            'quote_date' => $q->quote_date->format('Y-m-d'), 'validity_days' => (string) $q->validity_days,
            'customer_name' => (string) $q->customer_name, 'attention' => (string) $q->attention,
            'attention_phone' => (string) $q->attention_phone, 'attention_email' => (string) $q->attention_email,
            'customer_address' => (string) $q->customer_address, 'subject' => (string) $q->subject,
            'prepared_by' => (string) $q->prepared_by, 'preparer_position' => (string) $q->preparer_position,
            'preparer_phone' => (string) $q->preparer_phone, 'preparer_email' => (string) $q->preparer_email,
            'show_signature' => $q->show_signature, 'show_stamp' => $q->show_stamp,
            'sst' => Percent::toInput($q->sst_bp), 'terms' => (string) $q->terms,
        ];
        $this->items = [];
        foreach ($q->items as $i) {
            $this->items["i{$i->id}"] = ['title' => $i->title, 'details' => (string) $i->details, 'quantity' => (string) $i->quantity,
                'unit' => $i->unit, 'unit_price' => Money::toInput($i->unit_price_sen)];
        }
    }

    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        if ($parts[0] === 'form') {
            $this->saveForm();
        } elseif ($parts[0] === 'items' && isset($parts[1])) {
            $this->saveItem((int) substr($parts[1], 1));
        }
    }

    private function saveForm(): void
    {
        $this->validate([
            'form.quote_date' => ['required', 'date_format:Y-m-d'],
            'form.validity_days' => ['required', 'integer', 'between:1,365'],
            'form.customer_name' => ['nullable', 'string', 'max:255'],
            'form.attention' => ['nullable', 'string', 'max:255'],
            'form.attention_phone' => ['nullable', 'string', 'max:50'],
            'form.attention_email' => ['nullable', 'email', 'max:255'],
            'form.customer_address' => ['nullable', 'string', 'max:1000'],
            'form.subject' => ['nullable', 'string', 'max:500'],
            'form.prepared_by' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'form.preparer_position' => ['nullable', 'string', 'max:255'],
            'form.preparer_phone' => ['nullable', 'string', 'max:50'],
            'form.preparer_email' => ['nullable', 'email', 'max:255'],
            'form.show_signature' => ['boolean'],
            'form.show_stamp' => ['boolean'],
            'form.sst' => ['required', new Percentage],
            'form.terms' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'form.quote_date' => 'date', 'form.validity_days' => 'validity', 'form.attention_email' => 'attention email',
            'form.prepared_by' => 'prepared by', 'form.preparer_email' => 'email', 'form.sst' => 'SST',
        ]);
        $f = $this->form;
        $blank = fn ($v) => trim((string) $v) === '' ? null : trim((string) $v);
        $this->run(fn () => $this->version = app(UpdateQuotation::class)->handle(auth()->user(), $this->quotation, $this->version, [
            'quote_date' => $f['quote_date'], 'validity_days' => (int) $f['validity_days'],
            'customer_name' => $blank($f['customer_name']), 'attention' => $blank($f['attention']),
            'attention_phone' => $blank($f['attention_phone']), 'attention_email' => $blank($f['attention_email']),
            'customer_address' => $blank($f['customer_address']), 'subject' => $blank($f['subject']),
            'prepared_by' => (int) $f['prepared_by'], 'preparer_position' => $blank($f['preparer_position']),
            'preparer_phone' => $blank($f['preparer_phone']), 'preparer_email' => $blank($f['preparer_email']),
            'show_signature' => (bool) $f['show_signature'], 'show_stamp' => (bool) $f['show_stamp'],
            'sst_bp' => Percent::parseBp($f['sst']), 'terms' => $f['terms'],
        ])->version);
    }

    private function saveItem(int $id): void
    {
        $k = "i{$id}";
        $this->validate([
            "items.$k.title" => ['required', 'string', 'max:255'],
            "items.$k.details" => ['nullable', 'string', 'max:2000'],
            "items.$k.quantity" => ['required', 'integer', 'between:1,1000000'],
            "items.$k.unit" => ['required', 'string', 'max:50'],
            "items.$k.unit_price" => ['required', new MoneyAmount],
        ], [], ["items.$k.title" => 'title', "items.$k.quantity" => 'quantity', "items.$k.unit" => 'unit', "items.$k.unit_price" => 'unit price']);
        $row = $this->items[$k];
        $this->run(fn () => $this->version = app(UpdateQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version, [
            'title' => $row['title'], 'details' => $row['details'], 'quantity' => (int) $row['quantity'],
            'unit' => $row['unit'], 'unit_price_sen' => Money::parse($row['unit_price']) ?? 0,
        ])->version);
    }

    public function addItem(): void
    {
        $this->run(fn () => app(AddQuotationItem::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function removeItem(int $id): void
    {
        $this->run(fn () => app(RemoveQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version), reload: true);
    }

    public function moveItem(int $id, int $direction): void
    {
        $this->run(fn () => app(MoveQuotationItem::class)->handle(auth()->user(), $this->item($id), $this->version, $direction), reload: true);
    }

    public function resetTerms(): void
    {
        $this->form['terms'] = (string) CompanyProfile::current()->default_terms;
        $this->saveForm();
    }

    public function markSent(): void
    {
        $this->run(fn () => app(MarkQuotationSent::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function markAccepted(): void
    {
        $this->run(fn () => app(MarkQuotationAccepted::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function markRejected(): void
    {
        $this->run(fn () => app(MarkQuotationRejected::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function backToDraft(): void
    {
        $this->run(fn () => app(MoveQuotationBackToDraft::class)->handle(auth()->user(), $this->quotation, $this->version), reload: true);
    }

    public function revise(): void
    {
        $this->run(fn () => $this->redirectRoute('quotations.show', app(ReviseQuotation::class)->handle(auth()->user(), $this->quotation, $this->version)));
    }

    public function duplicate(): void
    {
        $this->run(fn () => $this->redirectRoute('quotations.show', app(DuplicateQuotation::class)->handle(auth()->user(), $this->quotation)));
    }

    public function createProject(): void
    {
        $this->run(function () {
            app(CreateProjectFromQuotation::class)->handle(auth()->user(), $this->quotation, $this->version);
            $this->redirectRoute('quotations.pd', $this->quotation);
        });
    }

    private function item(int $id): QuotationItem
    {
        return QuotationItem::where('quotation_id', $this->quotation->id)->findOrFail($id);
    }

    /** Runs an action; refusals become a message and typed values stay on screen. */
    private function run(callable $action, bool $reload = false): void
    {
        $this->problem = null;
        try {
            $action();
            if ($reload) {
                $this->load();
            }
        } catch (ModelNotFoundException) {
            $this->problem = 'This item was removed by someone else — reload to see the latest.';
        } catch (StaleQuotation|QuotationLocked|InvalidQuotationTransition|QuotationIncomplete|DomainException|InvalidArgumentException $e) {
            $this->problem = $e->getMessage();
        }
    }

    public function render()
    {
        $q = $this->quotation->fresh(['items', 'preparer', 'revisionOf', 'project']);
        $canUpdate = Gate::allows('update', $q);

        return view('livewire.quotation-page', [
            'q' => $q,
            'totals' => $q->totals(),
            'editable' => $canUpdate && $q->isDraft(),
            'canUpdate' => $canUpdate,
            'canBackToDraft' => Gate::allows('backToDraft', $q) && in_array($q->status->value, ['sent', 'rejected', 'accepted'], true) && ! $q->project,
            'people' => User::where('is_active', true)->orWhere('id', $q->prepared_by)->orderBy('name')->get(['id', 'name']),
            'activity' => $this->tab === 'history' ? $q->activity()->with('user')->get() : collect(),
        ])->title($q->number);
    }
}
```

`resources/views/livewire/quotation-page.blade.php` (replaces the stub):

```blade
@php
    use App\Support\{Money, Percent};
    $in = 'w-full rounded border border-line bg-surface px-2 py-1.5 text-sm disabled:border-transparent disabled:bg-transparent';
    $btn = 'rounded-lg px-3 py-1.5 text-sm font-medium';
    $tone = ['draft' => 'bg-subtle text-muted', 'sent' => 'bg-info-bg text-info-ink', 'expired' => 'bg-warn-bg text-warn-ink',
        'accepted' => 'bg-good-bg text-good-ink', 'rejected' => 'bg-bad-bg text-bad-ink', 'revised' => 'bg-subtle text-muted'];
    $status = $q->status->value;
@endphp
<div class="space-y-4">
    <a href="{{ route('quotations.index') }}" class="text-sm text-muted hover:text-ink">← Back to quotations</a>

    @if ($problem)
        <div class="flex items-center justify-between gap-3 rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $problem }}</span>
            <a href="{{ route('quotations.show', $q) }}" class="shrink-0 font-medium underline">Reload</a>
        </div>
    @endif

    <header class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-4">
        <span class="font-mono font-semibold">{{ $q->number }}</span>
        <span class="rounded-full px-2 py-0.5 text-xs {{ $tone[$q->displayStatus()] }}">{{ $q->displayLabel() }}</span>
        @if ($q->revisionOf)
            <a href="{{ route('quotations.show', $q->revisionOf) }}" class="text-xs text-muted underline">Revision of {{ $q->revisionOf->number }}</a>
        @endif
        <div class="ml-auto flex flex-wrap gap-2">
            @if ($canUpdate && $status === 'draft')
                <button type="button" wire:click="markSent" wire:confirm="Mark as Sent? The quotation will be locked." class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Mark as Sent</button>
            @endif
            @if ($canUpdate && $status === 'sent')
                <button type="button" wire:click="markAccepted" class="{{ $btn }} bg-accent text-accent-ink">Mark Accepted</button>
                <button type="button" wire:click="markRejected" wire:confirm="Mark this quotation Rejected?" class="{{ $btn }} border border-line hover:bg-hover">Mark Rejected</button>
                <button type="button" wire:click="revise" class="{{ $btn }} border border-line hover:bg-hover">Revise</button>
            @endif
            @if ($status === 'accepted')
                @if ($q->project)
                    <a href="{{ route('quotations.pd', $q) }}" class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Open project</a>
                @elseif ($canUpdate)
                    <button type="button" wire:click="createProject" wire:confirm="Create a project (PD) from this quotation?" class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Create project</button>
                @endif
            @endif
            @if ($canBackToDraft)
                <button type="button" wire:click="backToDraft" wire:confirm="Move this quotation back to Draft?" class="{{ $btn }} border border-line hover:bg-hover">Back to Draft</button>
            @endif
            <button type="button" wire:click="duplicate" class="{{ $btn }} border border-line hover:bg-hover">Duplicate</button>
            <a href="{{ route('quotations.pdf', $q) }}" class="{{ $btn }} border border-line hover:bg-hover">Download PDF</a>
        </div>
    </header>

    <nav class="flex gap-1 border-b border-line text-sm">
        @foreach (['details' => 'Details', 'items' => 'Items', 'terms' => 'Terms & Conditions', 'preview' => 'Preview', 'history' => 'History'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'px-3 py-2 -mb-px border-b-2',
                'border-ink font-medium' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'details')
        <div class="grid gap-3 rounded-xl border border-line bg-surface p-4 text-sm md:grid-cols-2">
            <label class="flex flex-col gap-1">Date <input type="date" wire:model.live.blur="form.quote_date" @disabled(! $editable) class="{{ $in }}">
                @error('form.quote_date') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            <label class="flex flex-col gap-1">Validity (days) <input wire:model.live.blur="form.validity_days" @disabled(! $editable) class="{{ $in }}">
                <span class="text-xs text-muted">Valid until {{ $q->validUntil()->format('d M Y') }}</span>
                @error('form.validity_days') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            @foreach (['customer_name' => 'Customer', 'attention' => 'Attention', 'attention_phone' => 'Attention mobile no.', 'attention_email' => 'Attention email'] as $k => $label)
                <label @class(['flex flex-col gap-1', 'md:col-span-2' => in_array($k, ['customer_name', 'attention'], true)])>{{ $label }}
                    <input wire:model.live.blur="form.{{ $k }}" @disabled(! $editable) class="{{ $in }}">
                    @error("form.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            @endforeach
            <label class="flex flex-col gap-1 md:col-span-2">Customer address
                <textarea wire:model.live.blur="form.customer_address" rows="3" @disabled(! $editable) class="{{ $in }}"></textarea></label>
            <label class="flex flex-col gap-1 md:col-span-2">Subject <input wire:model.live.blur="form.subject" @disabled(! $editable) class="{{ $in }}">
                @error('form.subject') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            <label class="flex flex-col gap-1">Prepared by
                <select wire:model.live="form.prepared_by" @disabled(! $editable) class="{{ $in }}">
                    @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                </select>
                @error('form.prepared_by') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            @foreach (['preparer_position' => 'Position', 'preparer_phone' => 'Phone', 'preparer_email' => 'Email'] as $k => $label)
                <label class="flex flex-col gap-1">{{ $label }} <input wire:model.live.blur="form.{{ $k }}" @disabled(! $editable) class="{{ $in }}">
                    @error("form.$k") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            @endforeach
            <label class="flex flex-col gap-1">SST % <input wire:model.live.blur="form.sst" @disabled(! $editable) class="{{ $in }} w-24">
                @error('form.sst') <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</label>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.show_signature" @disabled(! $editable)> Typed signature</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.show_stamp" @disabled(! $editable)> Company stamp</label>
                @if (empty($q->letterhead['stamp_path'])) <span class="text-xs text-muted">No stamp on this quotation's letterhead.</span> @endif
            </div>
        </div>
    @elseif ($tab === 'items')
        <div class="space-y-3 rounded-xl border border-line bg-surface p-4">
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-subtle text-left text-xs uppercase text-muted">
                        <tr><th class="px-2 py-2">No</th><th class="px-2">Description</th><th class="px-2">Qty</th><th class="px-2">Unit</th>
                            <th class="px-2 text-right">Unit price (RM)</th><th class="px-2 text-right">Amount (RM)</th><th class="px-2"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($q->items as $n => $item)
                        @php $k = 'i'.$item->id; @endphp
                        <tr wire:key="item-{{ $item->id }}" class="border-t border-line align-top">
                            <td class="px-2 py-2">{{ $n + 1 }}</td>
                            <td class="px-2 py-1">
                                <input wire:model.live.blur="items.{{ $k }}.title" @disabled(! $editable) class="{{ $in }} min-w-72 font-medium" aria-label="Title">
                                @error("items.$k.title") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror
                                <textarea wire:model.live.blur="items.{{ $k }}.details" rows="2" @disabled(! $editable) placeholder="Details (optional) — e.g. Power Supply: 100–240 V" class="{{ $in }} mt-1 text-xs" aria-label="Details"></textarea>
                            </td>
                            <td class="px-2 py-1"><input wire:model.live.blur="items.{{ $k }}.quantity" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Quantity">
                                @error("items.$k.quantity") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</td>
                            <td class="px-2 py-1"><input wire:model.live.blur="items.{{ $k }}.unit" @disabled(! $editable) class="{{ $in }} w-20" aria-label="Unit"></td>
                            <td class="px-2 py-1 text-right"><input wire:model.live.blur="items.{{ $k }}.unit_price" @disabled(! $editable) class="{{ $in }} w-32 text-right" aria-label="Unit price">
                                @error("items.$k.unit_price") <span class="text-xs text-bad-ink">{{ $message }}</span> @enderror</td>
                            <td class="whitespace-nowrap px-2 py-2 text-right">{{ ltrim(Money::format($totals['lines'][$n]), 'RM ') }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-xs">
                                @if ($editable)
                                    <button type="button" wire:click="moveItem({{ $item->id }}, -1)" aria-label="Move up">↑</button>
                                    <button type="button" wire:click="moveItem({{ $item->id }}, 1)" aria-label="Move down">↓</button>
                                    <button type="button" wire:click="removeItem({{ $item->id }})" wire:confirm="Remove this item?" class="ml-1 text-bad-ink">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-muted">No items yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-start gap-4">
                @if ($editable)
                    <button type="button" wire:click="addItem" class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-hover">+ Add item</button>
                @endif
                <table class="ml-auto text-sm">
                    <tr><td class="pr-6 text-muted">Subtotal</td><td class="text-right">{{ Money::format($totals['subtotal_sen']) }}</td></tr>
                    <tr><td class="pr-6 text-muted">SST ({{ Percent::format($q->sst_bp) }})</td><td class="text-right">{{ Money::format($totals['sst_sen']) }}</td></tr>
                    <tr class="font-semibold"><td class="pr-6">Total</td><td class="text-right">{{ Money::format($totals['total_sen']) }}</td></tr>
                </table>
            </div>
            <p class="text-right text-xs italic text-muted">{{ $totals['words'] }}</p>
        </div>
    @elseif ($tab === 'terms')
        <div class="space-y-2 rounded-xl border border-line bg-surface p-4 text-sm">
            <p class="text-muted">One term per line. They are numbered automatically on the quotation.</p>
            <textarea wire:model.live.blur="form.terms" rows="10" @disabled(! $editable) class="{{ $in }}" aria-label="Terms"></textarea>
            @if ($editable)
                <button type="button" wire:click="resetTerms" wire:confirm="Replace these terms with the company default?" class="rounded-lg border border-line px-3 py-1 hover:bg-hover">Reset to company default</button>
            @endif
        </div>
    @elseif ($tab === 'preview')
        <div class="rounded-xl border border-line bg-surface p-2">
            <iframe src="{{ route('quotations.pdf', [$q, 'inline' => 1]) }}" title="Quotation preview" class="h-[80vh] w-full rounded"></iframe>
        </div>
    @else
        <ul class="space-y-2 rounded-xl border border-line bg-surface p-4 text-sm">
            @forelse ($activity as $a)
                <li class="flex gap-3"><span class="w-40 shrink-0 text-muted">{{ $a->created_at->timezone(App\Support\MalaysiaTime::TZ)->format('d M Y, g:i a') }}</span>
                    <span>{{ $a->description }} <span class="text-muted">— {{ $a->user?->name ?? 'System' }}</span></span></li>
            @empty
                <li class="text-muted">Nothing yet.</li>
            @endforelse
        </ul>
    @endif
</div>
```

Routes (auth group): `Route::get('/quotations', \App\Livewire\QuotationList::class)->name('quotations.index');` (the `quotations.show`, `quotations.pd` and `quotations.pdf` routes exist from Tasks 3 and 6).

Sidebar — add after the `'Pipeline'` group in `$groups`:

```php
        'Quotation' => [
            ['href' => route('quotations.index'), 'label' => 'Quotations', 'count' => null, 'active' => request()->routeIs('quotations.*')],
        ],
```

Note: a crafted save from someone without rights reaches `saveForm()`; `UpdateQuotation` checks permission first (403), and for the preparer of a sent quotation it refuses with the "has been sent" message — both pinned by the read-only test.

- [ ] **Step 4: Run** `pest tests/Feature/Livewire tests/Feature/Quotations` — Expected: PASS.
- [ ] **Step 5: Browser check** — create a quotation, fill details, add the switch item with spec details, totals and words, Preview shows the PDF, Download PDF, Mark as Sent (locked), Revise (-R1), Duplicate, Accept, Create project → PD page with back link, read-only as another staff member, Back to Draft as Manager, dark mode, 375px.
- [ ] **Step 6: Commit** — `feat: quotation list and quotation page`

---

### Task 9: Sample quotations, docs, walkthrough

**Files:** Modify `database/seeders/DatabaseSeeder.php`, `README.md`; Test `tests/Feature/Quotations/QuotationSeedTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Database\Seeders\DatabaseSeeder;

it('seeds the prototype quotations', function () {
    $this->seed(DatabaseSeeder::class);

    $q12 = Quotation::where('number', 'QTN-2026-0012')->first();
    expect($q12->status)->toBe(QuotationStatus::Sent)
        ->and($q12->totals()['total_sen'])->toBe(3844800)
        ->and($q12->items)->toHaveCount(2)
        ->and($q12->preparer->name)->toBe('Siti Aisyah')
        ->and(Quotation::where('number', 'QTN-2026-0011')->first()->totals()['total_sen'])->toBe(2052000)
        ->and(Quotation::where('number', 'QTN-2026-0010')->first()->totals()['total_sen'])->toBe(3150000)
        ->and(Quotation::where('number', 'QTN-2026-0010')->first()->isExpired(Carbon\CarbonImmutable::parse('2026-10-07')))->toBeTrue()
        ->and(DB::table('quotation_sequences')->where('year', 2026)->value('last_seq'))->toBe(12);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement** — `DatabaseSeeder::run()`: after `self::seedSamplePd();` add `self::seedSampleQuotations();` and:

```php
    /** The prototype's three quotations. */
    private static function seedSampleQuotations(): void
    {
        $letterhead = \App\Models\CompanyProfile::current()->letterhead();
        $terms = \App\Models\CompanyProfile::current()->default_terms;
        $make = function (string $number, string $date, string $email, string $customer, string $subject, string $status, int $sstBp, array $items, array $extra = []) use ($letterhead, $terms) {
            $u = User::where('email', $email)->firstOrFail();
            $q = \App\Models\Quotation::create(array_merge([
                'number' => $number, 'status' => $status, 'quote_date' => $date, 'validity_days' => 30,
                'customer_name' => $customer, 'subject' => $subject, 'prepared_by' => $u->id,
                'preparer_position' => 'Sales Executive', 'preparer_email' => $email, 'sst_bp' => $sstBp, 'terms' => $terms,
                'letterhead' => $letterhead, 'updated_by' => $u->id, 'version' => 1,
                'sent_at' => $status !== 'draft' ? $date.' 10:00:00' : null, 'sent_by' => $status !== 'draft' ? $u->id : null,
            ], $extra));
            foreach ($items as $i => [$title, $details, $qty, $unit, $priceSen]) {
                $q->items()->create(['position' => $i + 1, 'title' => $title, 'details' => $details, 'quantity' => $qty, 'unit' => $unit, 'unit_price_sen' => $priceSen]);
            }
            ActivityLog::record($q, $u, 'quotation_created', "Quotation {$number} created");
        };

        $make('QTN-2026-0010', '2026-08-04', 'ahmad.faizal@cmt.test', 'Pejabat Daerah Kuantan', 'Laptop rental for 18 months', 'sent', 500,
            [['Laptop rental (18 months)', null, 1, 'Lot', 3000000]]);
        $make('QTN-2026-0011', '2026-09-10', 'muhammad.hafiz@cmt.test', 'Majlis Perbandaran Klang', 'Annual maintenance for CCTV system (12 months)', 'accepted', 800,
            [['CCTV preventive maintenance (12 months)', null, 1, 'Lot', 1900000]], ['accepted_at' => '2026-09-20 10:00:00']);
        $make('QTN-2026-0012', '2026-09-18', 'siti.aisyah@cmt.test', 'Jabatan Perpaduan Negara dan Integrasi Nasional',
            'Supply of network switches and installation for JPNIN HQ', 'sent', 800, [
                ['24-port Gigabit PoE+ managed switch', implode("\n", [
                    'Interface: 24× 10/100/1000 Mbps RJ45 PoE+ Ports; 4× Gigabit SFP Slots; 1× RJ45 Console Port; 1× Micro-USB Console Port',
                    'Power Supply: 100–240 V AC, 50/60 Hz, Internal Power Supply',
                    'Dimensions (W x D x H): 17.3 x 13.0 x 1.7 in (440 x 330 x 44 mm)',
                    'Mounting: 19-inch Rack Mountable (1U)',
                    'Switching Capacity: 56 Gbps',
                ]), 6, 'Unit', 485000],
                ['Installation, configuration & testing', null, 1, 'Lot', 650000],
            ], ['attention' => 'Puan Rozita binti Hassan, Ketua Unit ICT', 'customer_address' => "Aras 5, Blok F8, Kompleks F,\nPresint 1, 62000 Putrajaya"]);

        DB::table('quotation_sequences')->updateOrInsert(['year' => 2026], ['last_seq' => 12]);
    }
```

README — add after "PD (project finance)":

```markdown
## Quotations

- Quick quotations outside the tender process: **Quotations** in the sidebar. New Quotation opens a draft;
  every field saves when you leave it. Items can carry spec lines ("Label: value" prints the label in bold).
- **Mark as Sent** locks it. Then Accepted / Rejected, or **Revise** (makes `…-R1`). It shows Expired once
  its valid-until date passes. Managers/Admins can move it back to Draft (not once it has a project).
- **Download PDF** (Dompdf, server-side); the Preview tab shows the same PDF. **Duplicate** copies any quotation.
- An Accepted quotation can **Create project** — a PD (see above) with the subtotal as contract value.
- Admins set the letterhead, stamp, default terms and default SST in **Finance Settings**. Each quotation
  keeps a copy of the letterhead it was created with; stamp files are never deleted.
```

- [ ] **Step 4: Run full suite with coverage** — Expected: PASS ≥ 80%.
- [ ] **Step 5: Dev database** — only `php artisan migrate --force` (the letterhead, terms and SST row arrive with the migration). Do not reseed.
- [ ] **Step 6: Full browser walkthrough** (Playwright MCP) as in Task 8 step 5, plus Finance Settings letterhead edit + stamp upload, a PDF with the stamp, and removing every test quotation/stamp created on the dev database afterwards.
- [ ] **Step 7: Commit** — `feat: sample quotations and docs`

---

## Self-review notes (spec coverage)

| Spec section | Task |
|---|---|
| §2 company_profile, quotations, items, sequences, letterhead copy | 1, 4 |
| §2 totals, words | 2 |
| §2 projects / activity owner change | 1, 3 |
| §3 list, page, tabs, buttons by status, Mark as Sent checks | 5, 8 |
| §3 PDF layout, preview = PDF | 6, 8 |
| §3 Duplicate, Revise, Create project | 5, 8 |
| §4 permissions | 1, 3, 4, 5, 7, 8 |
| §5 errors (validation, stamp upload, conflicts, locked, numbering, PDF failure) | 4, 5, 6, 7, 8 |
| §6 sample data + dev DB | 1, 9 |
| §7 testing | all |
