# 2026 Register Import (+ Dropped list, Ministry field) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Load the user's real 2026 tender register (CSV, 393 tenders) through a previewable, all-or-nothing artisan command. Add the Dropped list and Ministry field it needs.

**Architecture:**
- **Dropped:** a fifth `TenderStatus` with a `DropTender` action that follows the same pattern as `MarkTenderLost`.
- **Ministry:** a nullable column threaded through the existing tender form and actions.
- **Import, part 1:** `App\Import\TenderRegister` turns the CSV into clean rows plus skip/warning lists, with no database access.
- **Import, part 2:** `App\Import\RegisterImporter` writes the rows (and optionally removes the samples) inside one transaction.
- **Import, part 3:** `tenders:import-register` previews by default and saves only with `--commit`.

**Tech Stack:** Laravel 13, Livewire 4.4, Pest 5, MySQL 8.4. PHP runs only in Docker: from `C:\Projects\cmt-tender-hub` run `MSYS_NO_PATHCONV=1 docker compose exec -T app …`.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-register-import-design.md`

## Global Constraints

- The real CSV (`C:\Users\CMT-Lynnda\Downloads\6(2026) (1).csv`) is **never** copied into the repository, fixtures, commits or test output. Tests use `tests/fixtures/register/register-sample.csv` (made up) or temp files.
- Money in integer sen; dates `Y-m-d`; Malaysia time via `App\Support\MalaysiaTime` where "today" matters.
- The user-facing copy is plain English.
- Tests first (RED → GREEN), commit after green, coverage ≥ 80% (the pre-commit hook runs the full suite with coverage).
- Write PHP/Blade with the Edit/Write tools, not sed.
- Find Tenders (collected tenders) is never touched by the import.
- Status mapping (verbatim from the spec):
  - Open, Assigned → In Progress
  - Submitted → Done
  - Won → Awarded
  - Lost → Lost
  - Cancelled → Lost + `was_cancelled`
  - Drop → Dropped
  - anything else → skipped
- New accounts: `is_active = false`, role Staff, email `<slug>@import.invalid`, random password. Blank PIC → account "Unassigned".
- `--replace-samples` keeps `admin@cmt.test` and `manager@cmt.test`.

## Review Focus

1. **A re-import after users edited tenders** (category changed, documents ticked, extra costing lines added): only the register fields and the "Imported cost (2026 register)" line change; category, documents and the user's own lines stay. Pinned in Task 6.
2. **A sample user who started a Find Tenders collection** (referenced by `collection_runs.started_by`): `--replace-samples` must switch them off instead of crashing on the foreign key. Pinned in Task 7.
3. **A tender with a submitted cost but no submission price** (or the reverse): no costing line and no bid override; Gross stays "—". Pinned in Task 6.
4. **A PIC written with different capitals or spaces in different rows** ("Fitri" vs "fitri "): one account, not two. Pinned in Task 6.
5. **Dropped tenders reopened by a manager:** they return to In Progress with `dropped_at`/`drop_reason` cleared, and the Dashboard stops counting them as dropped. Pinned in Task 1.

---

## File structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_10_11_000001_add_ministry_and_drop_to_tenders.php` (create) | `ministry`, `dropped_at`, `drop_reason` |
| `app/Enums/TenderStatus.php` (modify) | `Dropped` case |
| `app/Actions/Tenders/DropTender.php` (create), `ReopenTender.php` (modify) | the drop lifecycle |
| `app/Livewire/TenderDetail.php`, `resources/views/livewire/tender-detail.blade.php`, `tender-detail/modals.blade.php`, `tender-detail/overview.blade.php` (modify) | Drop button, dialog, details |
| `routes/web.php`, `layouts/partials/sidebar.blade.php`, `components/status-badge.blade.php`, `components/icon.blade.php`, `livewire/tender-list.blade.php` (modify) | Dropped list |
| `app/Reports/PipelineReport.php`, `livewire/dashboard.blade.php`, `app/Livewire/StatusReport.php`, `livewire/status-report.blade.php` (modify) | reports |
| `app/Actions/Tenders/RegisterTender.php`, `UpdateTender.php`, `app/Livewire/Forms/TenderForm.php`, `livewire/partials/tender-fields.blade.php` (modify) | Ministry |
| `app/Import/TenderRegister.php`, `app/Import/RegisterFormatException.php` (create) | CSV → clean rows |
| `app/Import/RegisterImporter.php` (create) | write rows, accounts, costing line, sample removal |
| `app/Console/Commands/ImportTenderRegister.php` (create) | preview / commit |
| `tests/fixtures/register/register-sample.csv` (create) | made-up register |

---

### Task 1: Dropped status, columns and the DropTender action

**Files:**
- Create: `database/migrations/2026_10_11_000001_add_ministry_and_drop_to_tenders.php`, `app/Actions/Tenders/DropTender.php`
- Modify: `app/Enums/TenderStatus.php`, `app/Models/Tender.php` (casts), `app/Actions/Tenders/ReopenTender.php`, `resources/views/components/status-badge.blade.php`
- Test: `tests/Feature/Actions/StatusChangesTest.php` (append)

**Interfaces:**
- Produces:
  - `TenderStatus::Dropped` (value `dropped`, slug `dropped`, label "Dropped")
  - columns `ministry` (string null), `dropped_at` (immutable datetime null), `drop_reason` (text null)
  - `DropTender::handle(User $actor, Tender $tender, int $expectedVersion, ?string $reason): Tender`
  - activity event `dropped`

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/Actions/StatusChangesTest.php`; it already imports the models/enums it uses. Add `use App\Actions\Tenders\DropTender;` at the top if missing)

```php
it('drops an In Progress tender with an optional reason and logs it', function () {
    $pic = \App\Models\User::factory()->create();
    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);

    $dropped = app(\App\Actions\Tenders\DropTender::class)->handle($pic, $t, $t->version, '  Not our field  ');

    expect($dropped->status)->toBe(\App\Enums\TenderStatus::Dropped)
        ->and($dropped->drop_reason)->toBe('Not our field')
        ->and($dropped->dropped_at)->not->toBeNull()
        ->and($dropped->version)->toBe($t->version + 1)
        ->and($dropped->activity()->first()->description)->toBe('Dropped — reason: Not our field');
});

it('only drops In Progress tenders, checks the version and the permission', function () {
    $pic = \App\Models\User::factory()->create();
    $done = \App\Models\Tender::factory()->status(\App\Enums\TenderStatus::Done)->create(['pic_id' => $pic->id]);
    expect(fn () => app(\App\Actions\Tenders\DropTender::class)->handle($pic, $done, $done->version, null))
        ->toThrow(\App\Exceptions\InvalidTenderTransition::class);

    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);
    expect(fn () => app(\App\Actions\Tenders\DropTender::class)->handle($pic, $t, $t->version + 5, null))
        ->toThrow(\App\Exceptions\StaleTenderException::class);

    $stranger = \App\Models\User::factory()->create();
    expect(fn () => app(\App\Actions\Tenders\DropTender::class)->handle($stranger, $t, $t->version, null))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('reopening a dropped tender clears the drop', function () {
    $manager = \App\Models\User::factory()->manager()->create();
    $t = \App\Models\Tender::factory()->create(['status' => \App\Enums\TenderStatus::Dropped, 'dropped_at' => now(), 'drop_reason' => 'x']);

    $back = app(\App\Actions\Tenders\ReopenTender::class)->handle($manager, $t, $t->version);

    expect($back->status)->toBe(\App\Enums\TenderStatus::InProgress)->and($back->dropped_at)->toBeNull()->and($back->drop_reason)->toBeNull();
});
```
(If the user factory has no `manager()` state, use `->create(['role' => \App\Enums\Role::Manager])`.)

- [ ] **Step 2: Run them and watch them fail**

Run: `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest tests/Feature/Actions/StatusChangesTest.php`
Expected: FAIL. The class `App\Actions\Tenders\DropTender` is not found, and/or the case `Dropped` is undefined.

- [ ] **Step 3: Implement**

Migration `database/migrations/2026_10_11_000001_add_ministry_and_drop_to_tenders.php`:
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
            $table->string('ministry')->nullable()->after('client');
            $table->timestamp('dropped_at')->nullable()->after('lost_at');
            $table->text('drop_reason')->nullable()->after('dropped_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->dropColumn(['ministry', 'dropped_at', 'drop_reason']));
    }
};
```

`app/Enums/TenderStatus.php`:
- add `case Dropped = 'dropped';` after `Lost`
- `label()`: `self::Dropped => 'Dropped',`
- `listSubtitle()`: `self::Dropped => 'Tenders the company decided not to bid for',`

`app/Models/Tender.php` casts: add `'dropped_at' => 'immutable_datetime',`.

`app/Actions/Tenders/DropTender.php`:
```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

/** The company decided not to bid: In Progress → Dropped (kept out of the win rate and bid values). */
final class DropTender
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, ?string $reason): Tender
    {
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $reason) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'drop');

            $t->forceFill([
                'status' => TenderStatus::Dropped,
                'drop_reason' => $reason,
                'dropped_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'dropped', 'Dropped'.($reason !== null ? " — reason: {$reason}" : ''));

            return $t->fresh();
        });
    }
}
```

`ReopenTender`: add `'dropped_at' => null, 'drop_reason' => null,` to the `forceFill` array.

`status-badge.blade.php`: add `\App\Enums\TenderStatus::Dropped => 'bg-hover text-muted',` to the match.

- [ ] **Step 4: Run them and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/Actions/StatusChangesTest.php tests/Unit/Enums`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add database/migrations app/Enums/TenderStatus.php app/Models/Tender.php app/Actions/Tenders/DropTender.php app/Actions/Tenders/ReopenTender.php resources/views/components/status-badge.blade.php tests/Feature/Actions/StatusChangesTest.php
git commit -m "feat: Dropped status and the Drop tender action"
```

---

### Task 2: Dropped in the screens: list, sidebar, tender page

**Files:**
- Modify:
  - `routes/web.php:18`
  - `resources/views/layouts/partials/sidebar.blade.php`
  - `resources/views/components/icon.blade.php`
  - `resources/views/livewire/tender-list.blade.php`
  - `app/Livewire/TenderDetail.php`
  - `resources/views/livewire/tender-detail.blade.php`
  - `resources/views/livewire/tender-detail/modals.blade.php`
  - `resources/views/livewire/tender-detail/overview.blade.php`
- Test: `tests/Feature/Livewire/TenderListTest.php`, `tests/Feature/Livewire/TenderDetailTest.php`, `tests/Feature/LayoutTest.php`, `tests/Feature/Components/IconTest.php`

**Interfaces:**
- Consumes:
  - `TenderStatus::Dropped`, `DropTender` (Task 1)
- Produces:
  - route `/tenders/dropped`
  - icon `minus`
  - Livewire `TenderDetail::dropTender()` with the property `$dropReason`
  - modal key `drop`

- [ ] **Step 1: Write the failing tests**

`TenderListTest.php` (append):
```php
it('lists dropped tenders with their reason', function () {
    Tender::factory()->create(['status' => TenderStatus::Dropped, 'dropped_at' => now(), 'drop_reason' => 'Outside our scope', 'title' => 'DROPPED ONE']);

    $this->get('/tenders/dropped')->assertOk()->assertSee('Dropped Tenders')->assertSee('DROPPED ONE')->assertSee('Outside our scope');
});
```
`LayoutTest.php` (append):
```php
it('shows the Dropped list in the sidebar with its count', function () {
    Tender::factory()->create(['status' => TenderStatus::Dropped]);

    $html = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();
    expect($html)->toContain('data-nav="Dropped" data-icon="minus"')->toContain(route('tenders.index', 'dropped'));
});
```
`IconTest.php`: add `'minus'` to the dataset list.

`TenderDetailTest.php` (append; follow that file's existing helpers/imports):
```php
it('drops a tender from its page with an optional reason', function () {
    $pic = \App\Models\User::factory()->create();
    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);

    \Livewire\Livewire::actingAs($pic)->test(\App\Livewire\TenderDetail::class, ['tender' => $t])
        ->assertSee('Drop tender')
        ->call('openModal', 'drop')->set('dropReason', 'No capacity')->call('dropTender')
        ->assertHasNoErrors();

    expect($t->fresh()->status)->toBe(\App\Enums\TenderStatus::Dropped)->and($t->fresh()->drop_reason)->toBe('No capacity');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/TenderListTest.php tests/Feature/Livewire/TenderDetailTest.php tests/Feature/LayoutTest.php tests/Feature/Components/IconTest.php`
Expected: FAIL. `/tenders/dropped` returns 404, the icon `minus` is unknown, and the method `dropTender` does not exist.

- [ ] **Step 3: Implement**
- `routes/web.php:18`: `->whereIn('list', ['in-progress', 'done', 'awarded', 'lost', 'dropped'])`.
- `icon.blade.php`: add `'minus' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',`.
- `sidebar.blade.php` Pipeline group: add `$list('dropped', 'Dropped', 'minus', $counts['dropped']),` after Lost.
- `tender-list.blade.php`:
  - in `$extra`'s match add `TenderStatus::Dropped => [['Reason', $t->drop_reason ?? '—']],`
  - in `$extraLabels` add `TenderStatus::Dropped => ['Reason'],`
  - Dropped's reason column is text, so in the table loop use `text-right` only when `$status !== TenderStatus::Dropped` for both the `<th>` and `<td>` of the extra columns, and drop `whitespace-nowrap` on the Dropped reason cell
- `TenderDetail.php`:
  - add `public string $dropReason = '';`
  - add `use App\Actions\Tenders\DropTender;` to the actions import
  - add the method:
```php
    public function dropTender(): void
    {
        $this->validate(['dropReason' => ['nullable', 'string', 'max:1000']]);
        $this->apply(fn () => app(DropTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->dropReason));
    }
```
- `tender-detail.blade.php`: inside the `@if ($canEdit && $status === TenderStatus::InProgress)` block, before Cancel Tender, add:
  `<button wire:click="openModal('drop')" class="{{ $btn }} border border-line hover:bg-hover">Drop tender</button>`
- `tender-detail/modals.blade.php`: add a case before `reopen`:
```blade
                @case('drop')
                    <h2 class="text-lg font-semibold">Drop tender</h2>
                    <p class="text-sm text-muted">Use this when the company decides not to bid. The tender moves to the Dropped list and is left out of the win rate. A manager can reopen it.</p>
                    <label class="block text-sm"><span class="text-muted">Reason, if any</span>
                        <textarea wire:model="dropReason" rows="2" class="{{ $input }}"></textarea></label>
                    @error('dropReason') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    @php $confirm = ['dropTender', 'Drop tender']; @endphp
                    @break
```
- `tender-detail/overview.blade.php`: after `'Lost / cancel reason' => …,` add `'Drop reason' => $tender->drop_reason ?? '—',`.

- [ ] **Step 4: Run them and watch them pass.** Same command as Step 2. Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add routes/web.php resources/views app/Livewire/TenderDetail.php tests/Feature
git commit -m "feat: Dropped list in the sidebar and lists, and a Drop tender button on the tender page"
```

---

### Task 3: Reports leave Dropped out of win rate and values; Dashboard ring and Status column

**Files:**
- Modify: `app/Reports/PipelineReport.php`, `resources/views/livewire/dashboard.blade.php`, `app/Livewire/StatusReport.php:18`, `resources/views/livewire/status-report.blade.php`
- Test: `tests/Feature/Reports/PipelineReportTest.php`, `tests/Feature/Livewire/StatusReportTest.php`, `tests/Feature/Livewire/DashboardTest.php`

**Interfaces:**
- Consumes:
  - `TenderStatus::Dropped` (Task 1)
- Produces:
  - `build()['counts']['dropped']`
  - per-PIC row key `dropped`
  - mode buckets key `dropped`
  - `StatusReport::COLUMNS` includes `dropped`

- [ ] **Step 1: Write the failing tests**

`PipelineReportTest.php` (append; it has the `pipeline()` helper returning `build()` output for all time, so use it the same way the other tests do):
```php
it('counts Dropped tenders but leaves them out of the win rate and the bid and won values', function () {
    $u = \App\Models\User::factory()->create();
    \App\Models\Tender::factory()->status(\App\Enums\TenderStatus::Awarded)->create(['pic_id' => $u->id, 'submitted_price_sen' => 100000]);
    \App\Models\Tender::factory()->create(['pic_id' => $u->id, 'status' => \App\Enums\TenderStatus::Dropped,
        'estimated_value_sen' => 999900, 'submitted_price_sen' => 888800]);

    $r = pipeline();

    expect($r['counts']['dropped'])->toBe(1)->and($r['counts']['total'])->toBe(2)
        ->and($r['bid_value_sen'])->toBe(100000)->and($r['without_value'])->toBe(0)
        ->and($r['win_rate_bp'])->toBe(10000);
    expect(collect($r['pics'])->firstWhere('user_id', $u->id)['dropped'])->toBe(1);
});
```
`StatusReportTest.php` (append):
```php
it('shows a Dropped column that links to that PIC\'s dropped tenders', function () {
    $this->actingAs($u = \App\Models\User::factory()->create());
    \App\Models\Tender::factory()->create(['pic_id' => $u->id, 'status' => \App\Enums\TenderStatus::Dropped]);

    \Livewire\Livewire::test(\App\Livewire\StatusReport::class)
        ->assertSee('Dropped')->assertSeeHtml(e(route('tenders.index', ['dropped', 'pic' => $u->id])));
});
```
`DashboardTest.php` (append):
```php
it('shows Dropped as its own slice in the status ring', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    \App\Models\Tender::factory()->create(['status' => \App\Enums\TenderStatus::Dropped]);

    \Livewire\Livewire::test(\App\Livewire\Dashboard::class)->assertSeeInOrder(['Lost', 'Dropped', '1 (100%)']);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/Reports/PipelineReportTest.php tests/Feature/Livewire/StatusReportTest.php tests/Feature/Livewire/DashboardTest.php`
Expected: FAIL. You get "Undefined array key 'dropped'" from the report, or the Dropped text and link are not found.

- [ ] **Step 3: Implement**

`PipelineReport.php`:
- `private const STATUSES = ['in_progress', 'done', 'awarded', 'lost', 'dropped'];`
- bid SQL: change it to:
```php
            ->selectRaw("SUM(CASE WHEN status = 'dropped' THEN 0 WHEN status = 'in_progress' THEN COALESCE(estimated_value_sen, 0)
                WHEN was_cancelled = 1 THEN 0 ELSE COALESCE(submitted_price_sen, 0) END) AS bid")
```
- update the comment above it to "Dropped and cancelled tenders count 0"
- no_value SQL: `SUM(CASE WHEN was_cancelled = 1 OR status = 'dropped' THEN 0 WHEN status = 'in_progress' THEN estimated_value_sen IS NULL ELSE submitted_price_sen IS NULL END) AS no_value`
- `blank()`: add `'dropped' => 0,`
- `row()`: add `'dropped' => $b['dropped'],` after `'lost' => $b['lost'],`

`dashboard.blade.php` `$segments`: add `['Dropped', $c['dropped'], 'var(--color-muted-2)'],` after Lost.

`StatusReport::COLUMNS`: insert `'dropped'` after `'lost'`.

`status-report.blade.php`: in `$cols` insert `'dropped' => 'Dropped'` after Lost; in `$lists` add `'dropped' => 'dropped'`.

- [ ] **Step 4: Run them and watch them pass.** Same command. Expected: PASS (all old report tests too).

- [ ] **Step 5: Commit**
```bash
git add app/Reports/PipelineReport.php app/Livewire/StatusReport.php resources/views/livewire/dashboard.blade.php resources/views/livewire/status-report.blade.php tests/Feature
git commit -m "feat: reports count Dropped separately and leave it out of win rate and values"
```

---

### Task 4: Ministry field

**Files:**
- Modify: `app/Actions/Tenders/RegisterTender.php:13-17`, `app/Actions/Tenders/UpdateTender.php:16-22`, `app/Livewire/Forms/TenderForm.php`, `resources/views/livewire/partials/tender-fields.blade.php`, `resources/views/livewire/tender-detail/overview.blade.php`, `resources/views/livewire/tender-list.blade.php`
- Test: `tests/Feature/Actions/RegisterTenderTest.php`, `tests/Feature/Livewire/TenderListTest.php`

**Interfaces:**
- Consumes:
  - the `tenders.ministry` column (Task 1)
- Produces:
  - `TenderForm::$ministry`
  - `'ministry'` in `RegisterTender::FIELDS` (so `UpdateTender` accepts it too)

- [ ] **Step 1: Write the failing tests**

`RegisterTenderTest.php` (append; reuse its data helper if it has one, otherwise build `$data` the way its first test does and add `'ministry' => 'KEMENTERIAN KESIHATAN'`):
```php
it('saves the ministry with the agency', function () {
    $admin = \App\Models\User::factory()->admin()->create();
    $data = validTenderData(['ministry' => 'KEMENTERIAN KESIHATAN', 'client' => 'PUSAT DARAH NEGARA']); // use the file's existing helper name

    $t = app(\App\Actions\Tenders\RegisterTender::class)->handle($admin, $data);

    expect($t->ministry)->toBe('KEMENTERIAN KESIHATAN')->and($t->client)->toBe('PUSAT DARAH NEGARA');
});
```
`TenderListTest.php` (append):
```php
it('shows the ministry under the agency', function () {
    Tender::factory()->create(['client' => 'PUSAT DARAH NEGARA', 'ministry' => 'KEMENTERIAN KESIHATAN']);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSeeInOrder(['PUSAT DARAH NEGARA', 'KEMENTERIAN KESIHATAN']);
});
```

- [ ] **Step 2: Run them and watch them fail.**

Run: `… ./vendor/bin/pest tests/Feature/Actions/RegisterTenderTest.php tests/Feature/Livewire/TenderListTest.php`
Expected: FAIL. The ministry comes back null (it's not in FIELDS) and the list doesn't show it.

- [ ] **Step 3: Implement**
- `RegisterTender::FIELDS`: add `'ministry'` after `'client'`.
- `UpdateTender::LABELS`: add `'ministry' => 'ministry',`.
- `TenderForm`:
  - add `public string $ministry = '';`
  - rules: `'ministry' => ['nullable', 'string', 'max:255'],`
  - in the fill-from-tender method: `$this->ministry = (string) $t->ministry;`
  - in `fillFromCollected`: `$this->ministry = (string) ($c->ministry ?? '');`
  - in `toData()`: `'ministry' => trim($this->ministry) === '' ? null : trim($this->ministry),`
- `tender-fields.blade.php`:
  - before the client label, add:
```blade
    <label class="text-sm sm:col-span-2"><span class="text-muted">Ministry</span>
        <input wire:model="form.ministry" list="ministry-list" class="{{ $input }}">
        @if ($e = $err('ministry')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
```
  - change the client label text to `Agency (PTJ) *`
- `overview.blade.php`: before `'Client' => …` add `'Ministry' => $tender->ministry ?? '—',`, and rename `'Client'` to `'Agency'`.
- `tender-list.blade.php`:
  - Agency cell: `<td class="{{ $td }}">{{ $t->client }}@if ($t->ministry) <div class="text-[11.5px] text-muted-2">{{ $t->ministry }}</div> @endif</td>`
  - phone card agency line: `{{ $t->client }}{{ $t->ministry ? ' · '.$t->ministry : '' }} · {{ $t->tender_code }}`

Existing tests that assert the word "Client" on the tender page: update them to "Agency" and record a ruling.

- [ ] **Step 4: Run them and watch them pass.** Run: `… ./vendor/bin/pest tests/Feature/Actions tests/Feature/Livewire` → PASS.

- [ ] **Step 5: Commit**
```bash
git add app/Actions/Tenders app/Livewire/Forms/TenderForm.php resources/views tests/Feature
git commit -m "feat: optional Ministry field on tenders, shown under the agency"
```

---

### Task 5: Read the register CSV into clean rows

**Files:**
- Create: `app/Import/TenderRegister.php`, `app/Import/RegisterFormatException.php`, `tests/fixtures/register/register-sample.csv`
- Test: `tests/Unit/Import/TenderRegisterTest.php`

**Interfaces:**
- Consumes:
  - `TenderStatus::Dropped` (Task 1)
  - `TenderMode`, `TenderType`, `App\Support\Money::parse`
- Produces:
  - `TenderRegister::read(string $path): array` returning `['rows' => list<array>, 'skipped' => list<array{line:int, wo:string, reason:string}>, 'warnings' => list<string>]`
  - Each row has: `line, wo_number, wo_date, mode (TenderMode), type (TenderType), tender_code, title, ministry (?string), client, publish_date (?string), closing_date, briefing_date (?string), estimated_value_sen, submitted_price_sen, winning_price_sen, submitted_cost_sen (?int), pic_name (?string), status (TenderStatus), was_cancelled (bool)`
  - Throws `RegisterFormatException` (unreadable file or missing columns)

- [ ] **Step 1: Create the made-up fixture** `tests/fixtures/register/register-sample.csv` (exact header from the real file; rows invented):
```csv
No,WO Number,WO DATE,Mode,PIC,Ministry,PTJ Code & Name,QT No,QT Title (Full),Publish Date,Closing Date,Status,Indicative Price,Submission Price,Win Price,Company Variant,Win Variant,Submitted Cost,Gross,Month,Briefing date,Type,,
1,200-01012026-001,1/2/2026,Ep,Aminah,KEMENTERIAN CONTOH,JABATAN SATU,QT1,"SAMPLE TENDER ONE, WITH COMMA",12/30/2025,1/20/2026,Open,"1,000,000.00",,,0%,,,#DIV/0!,January,1/10/2026,TENDER,,
2,200-01012026-002,1/2/2026,Non-Ep,aminah ,KEMENTERIAN CONTOH,JABATAN DUA,QT2,SAMPLE TWO,,1/21/2026,Assigned,-,,,,,,#VALUE!,January,,SH,,
3,200-01012026-003,1/3/2026,Ep,Badrul,KEMENTERIAN CONTOH,,QT3,SAMPLE THREE,1/1/2026,1/22/2026,Submitted,"500,000.00","400,000.00",,80%,,"320,000.00",20%,January,,TENDER,,
4,200-01012026-004,1/3/2026,Ep,Badrul,"KEMENTERIAN	CONTOH",JABATAN SATU,QT4,SAMPLE FOUR,1/1/2026,1/23/2026,Won,"500,000.00","450,000.00","450,000.00",90%,100%,,100%,January,,TENDER,,
5,200-01012026-005,1/4/2026,Ep,,KEMENTERIAN CONTOH,JABATAN DUA,QT5,SAMPLE FIVE,1/1/2026,1/24/2026,Lost,"700,000.00","650,000.00",`,93%,,,100%,January,,TENDER,,
6,200-01012026-006,1/4/2026,Non-Ep,Aminah,KEMENTERIAN CONTOH,JABATAN DUA,QT6,SAMPLE SIX,1/1/2026,1/25/2026,Cancelled,"300,000.00",,,,,,100%,January,,SH,,
7,200-01012026-007,1/5/2026,Ep,Badrul,KEMENTERIAN CONTOH,JABATAN SATU,QT7,SAMPLE SEVEN,1/1/2026,1/26/2026,Drop,"200,000.00",,,,,,100%,January,,TENDER,,
8,200-01012026-008,1/5/2026,Ep,Badrul,KEMENTERIAN CONTOH,JABATAN SATU,QT8,SAMPLE EIGHT,1/1/2026,1/27/2026,Pending,"200,000.00",,,,,,100%,January,,TENDER,,
9,200-01012026-001,1/6/2026,Ep,Badrul,KEMENTERIAN CONTOH,JABATAN SATU,QT9,DUPLICATE OF ONE,1/1/2026,1/28/2026,Open,,,,,,,,January,,TENDER,,
,,,,,,,,,,,,,,,,,,#DIV/0!,,,,,
```

- [ ] **Step 2: Write the failing test** `tests/Unit/Import/TenderRegisterTest.php`:
```php
<?php

use App\Enums\{TenderMode, TenderStatus, TenderType};
use App\Import\{RegisterFormatException, TenderRegister};

function sampleRegister(): array
{
    return (new TenderRegister)->read(base_path('tests/fixtures/register/register-sample.csv'));
}

it('reads the rows it can use and explains the ones it skips', function () {
    $r = sampleRegister();

    expect(collect($r['rows'])->pluck('wo_number')->all())->toBe([
        '200-01012026-001', '200-01012026-002', '200-01012026-003', '200-01012026-004',
        '200-01012026-005', '200-01012026-006', '200-01012026-007',
    ]);
    expect($r['skipped'])->toBe([
        ['line' => 9, 'wo' => '200-01012026-008', 'reason' => "unknown status 'Pending'"],
        ['line' => 10, 'wo' => '200-01012026-001', 'reason' => 'duplicate WO number in file'],
    ]); // the blank trailing row is ignored silently
});

it('maps statuses, modes and types like the register means them', function () {
    $rows = collect(sampleRegister()['rows'])->keyBy('wo_number');

    expect($rows['200-01012026-001']['status'])->toBe(TenderStatus::InProgress)
        ->and($rows['200-01012026-002']['status'])->toBe(TenderStatus::InProgress)
        ->and($rows['200-01012026-003']['status'])->toBe(TenderStatus::Done)
        ->and($rows['200-01012026-004']['status'])->toBe(TenderStatus::Awarded)
        ->and($rows['200-01012026-005']['status'])->toBe(TenderStatus::Lost)
        ->and($rows['200-01012026-005']['was_cancelled'])->toBeFalse()
        ->and($rows['200-01012026-006']['status'])->toBe(TenderStatus::Lost)
        ->and($rows['200-01012026-006']['was_cancelled'])->toBeTrue()
        ->and($rows['200-01012026-007']['status'])->toBe(TenderStatus::Dropped)
        ->and($rows['200-01012026-002']['mode'])->toBe(TenderMode::NonEp)
        ->and($rows['200-01012026-002']['type'])->toBe(TenderType::Quotation)
        ->and($rows['200-01012026-001']['type'])->toBe(TenderType::Tender);
});

it('cleans money, dates, names and blanks', function () {
    $rows = collect(sampleRegister()['rows'])->keyBy('wo_number');

    expect($rows['200-01012026-001']['estimated_value_sen'])->toBe(100000000)
        ->and($rows['200-01012026-001']['wo_date'])->toBe('2026-01-02')
        ->and($rows['200-01012026-001']['briefing_date'])->toBe('2026-01-10')
        ->and($rows['200-01012026-001']['title'])->toBe('SAMPLE TENDER ONE, WITH COMMA')
        ->and($rows['200-01012026-002']['estimated_value_sen'])->toBeNull()   // "-"
        ->and($rows['200-01012026-002']['publish_date'])->toBeNull()
        ->and($rows['200-01012026-002']['pic_name'])->toBe('aminah')
        ->and($rows['200-01012026-003']['client'])->toBe('KEMENTERIAN CONTOH') // blank PTJ → ministry
        ->and($rows['200-01012026-003']['submitted_cost_sen'])->toBe(32000000)
        ->and($rows['200-01012026-004']['ministry'])->toBe('KEMENTERIAN CONTOH') // tab collapsed
        ->and($rows['200-01012026-005']['winning_price_sen'])->toBeNull()    // "`"
        ->and($rows['200-01012026-005']['pic_name'])->toBeNull();
});

it('refuses a file without the register columns', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    file_put_contents($path, "Name,Amount\nX,1\n");

    expect(fn () => (new TenderRegister)->read($path))->toThrow(RegisterFormatException::class, 'missing columns: WO Number');
});

it('refuses a file it cannot open', function () {
    expect(fn () => (new TenderRegister)->read('/nope/missing.csv'))->toThrow(RegisterFormatException::class, 'Cannot open');
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `… ./vendor/bin/pest tests/Unit/Import/TenderRegisterTest.php`
Expected: FAIL. The class `App\Import\TenderRegister` is not found.

- [ ] **Step 4: Implement**

`app/Import/RegisterFormatException.php`:
```php
<?php

namespace App\Import;

use RuntimeException;

/** The file is not a tender register we can read (unreadable, or missing columns). */
final class RegisterFormatException extends RuntimeException {}
```

`app/Import/TenderRegister.php`:
```php
<?php

namespace App\Import;

use App\Enums\{TenderMode, TenderStatus, TenderType};
use App\Support\Money;
use InvalidArgumentException;

/**
 * Reads the team's tender register (CSV export of their spreadsheet) into clean rows.
 * No database access: the importer decides what to do with the rows.
 */
final class TenderRegister
{
    public const REQUIRED = ['WO Number', 'WO DATE', 'Mode', 'PIC', 'Ministry', 'PTJ Code & Name', 'QT No', 'QT Title (Full)', 'Closing Date', 'Status'];

    /** Register status → [app status, was cancelled] */
    private const STATUSES = [
        'open' => [TenderStatus::InProgress, false],
        'assigned' => [TenderStatus::InProgress, false],
        'submitted' => [TenderStatus::Done, false],
        'won' => [TenderStatus::Awarded, false],
        'lost' => [TenderStatus::Lost, false],
        'cancelled' => [TenderStatus::Lost, true],
        'drop' => [TenderStatus::Dropped, false],
    ];

    /** @return array{rows: list<array>, skipped: list<array{line:int, wo:string, reason:string}>, warnings: list<string>} */
    public function read(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RegisterFormatException("Cannot open {$path}");
        }

        try {
            $header = array_map(fn ($h) => self::clean((string) $h), fgetcsv($handle, escape: '') ?: []);
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0] ?? ''); // Excel byte-order mark
            $missing = array_values(array_diff(self::REQUIRED, $header));
            if ($missing !== []) {
                throw new RegisterFormatException('This does not look like the tender register — missing columns: '.implode(', ', $missing));
            }
            $col = array_flip($header);

            $rows = $skipped = $warnings = $seen = [];
            $line = 1;
            while (($cells = fgetcsv($handle, escape: '')) !== false) {
                $line++;
                $get = fn (string $name) => isset($col[$name]) ? self::clean((string) ($cells[$col[$name]] ?? '')) : '';
                $wo = $get('WO Number');
                if ($wo === '') {
                    continue; // blank / leftover formula rows at the bottom of the sheet
                }
                $reason = null;
                $row = $this->row($get, $line, $wo, $warnings, $reason);
                if ($reason === null && isset($seen[$wo])) {
                    $reason = 'duplicate WO number in file';
                }
                if ($reason !== null) {
                    $skipped[] = ['line' => $line, 'wo' => $wo, 'reason' => $reason];

                    continue;
                }
                $seen[$wo] = true;
                $rows[] = $row;
            }

            return ['rows' => $rows, 'skipped' => $skipped, 'warnings' => $warnings];
        } finally {
            fclose($handle);
        }
    }

    private function row(callable $get, int $line, string $wo, array &$warnings, ?string &$reason): ?array
    {
        $statusText = $get('Status');
        $mapped = self::STATUSES[strtolower($statusText)] ?? null;
        if ($mapped === null) {
            $reason = "unknown status '{$statusText}'";

            return null;
        }
        $mode = match (strtolower(str_replace([' ', '-'], '', $get('Mode')))) {
            'ep' => TenderMode::Ep,
            'nonep' => TenderMode::NonEp,
            default => null,
        };
        if ($mode === null) {
            $reason = 'no mode';

            return null;
        }
        $woDate = self::date($get('WO DATE'));
        $closing = self::date($get('Closing Date'));
        if ($woDate === null || $closing === null) {
            $reason = $woDate === null ? 'no WO date' : 'no closing date';

            return null;
        }
        $type = match (strtolower($get('Type'))) {
            'sh', 'quotation' => TenderType::Quotation,
            default => TenderType::Tender,
        };
        $ministry = $get('Ministry');
        $client = $get('PTJ Code & Name');
        $pic = strtolower($get('PIC')) === '' ? null : $get('PIC');
        $money = function (string $name) use ($get, $line, &$warnings): ?int {
            $value = $get($name);
            try {
                return Money::parse($value === '' ? null : $value);
            } catch (InvalidArgumentException) {
                if (! in_array($value, ['-', '`'], true) && ! str_starts_with($value, '#')) {
                    $warnings[] = "Line {$line}: {$name} '{$value}' is not an amount — left empty";
                }

                return null;
            }
        };

        return [
            'line' => $line,
            'wo_number' => $wo,
            'wo_date' => $woDate,
            'mode' => $mode,
            'type' => $type,
            'tender_code' => $get('QT No'),
            'title' => $get('QT Title (Full)'),
            'ministry' => $ministry === '' ? null : $ministry,
            'client' => $client !== '' ? $client : $ministry,
            'publish_date' => self::date($get('Publish Date')),
            'closing_date' => $closing,
            'briefing_date' => self::date($get('Briefing date')),
            'estimated_value_sen' => $money('Indicative Price'),
            'submitted_price_sen' => $money('Submission Price'),
            'winning_price_sen' => $money('Win Price'),
            'submitted_cost_sen' => $money('Submitted Cost'),
            'pic_name' => $pic,
            'status' => $mapped[0],
            'was_cancelled' => $mapped[1],
        ];
    }

    /** Collapse tabs and repeated spaces; trim. */
    private static function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    /** m/d/yyyy → Y-m-d, or null when blank or not a real date. */
    private static function date(string $value): ?string
    {
        if (! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) || ! checkdate((int) $m[1], (int) $m[2], (int) $m[3])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    }
}
```
(The fixture's lines: the header is line 1, so data rows are lines 2–11. Row "No 8" is file line 9, and the duplicate "No 9" is line 10. A tender name with more than two decimal places becomes a warning plus null. The `pic_name` keeps the person's own capitals; the trailing space is trimmed. Matching is case-insensitive in Task 6.)

- [ ] **Step 5: Run it and watch it pass.** Same command → PASS.

- [ ] **Step 6: Commit**
```bash
git add app/Import tests/Unit/Import tests/fixtures/register
git commit -m "feat: read the tender register CSV into clean rows, skipping what can't be used"
```

---

### Task 6: Import the rows (preview and commit command, accounts, costing line, re-runs)

**Files:**
- Create: `app/Import/RegisterImporter.php`, `app/Console/Commands/ImportTenderRegister.php`
- Test: `tests/Feature/Import/ImportTenderRegisterTest.php`

**Interfaces:**
- Consumes:
  - `TenderRegister::read()` (Task 5)
  - `TenderStatus::Dropped` and the `ministry` / `dropped_at` columns (Task 1)
- Produces:
  - `RegisterImporter::accountsNeeded(array $rows): list<string>`
  - `RegisterImporter::import(array $rows, User $actor, bool $replaceSamples = false): array{created:int, updated:int, accounts:list<string>, removed:array}`
  - `RegisterImporter::IMPORTED_COST = 'Imported cost (2026 register)'`
  - the command `tenders:import-register {file} {--commit} {--replace-samples} {--as=admin@cmt.test}`

- [ ] **Step 1: Write the failing tests** `tests/Feature/Import/ImportTenderRegisterTest.php`:
```php
<?php

use App\Enums\{TenderCategory, TenderStatus};
use App\Import\RegisterImporter;
use App\Models\{Tender, User};

beforeEach(fn () => User::factory()->admin()->create(['email' => 'admin@cmt.test']));

function registerFixture(): string
{
    return base_path('tests/fixtures/register/register-sample.csv');
}

it('previews without saving anything', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture()])
        ->expectsOutputToContain('7 tenders ready')
        ->expectsOutputToContain('In Progress: 2')
        ->expectsOutputToContain('Dropped: 1')
        ->expectsOutputToContain("line 9 (200-01012026-008): unknown status 'Pending'")
        ->expectsOutputToContain('New switched-off accounts: Aminah, Badrul, Unassigned')
        ->expectsOutputToContain('Preview only — nothing saved')
        ->assertSuccessful();

    expect(Tender::count())->toBe(0)->and(User::count())->toBe(1);
});

it('imports every usable row into the right list with its details', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    expect(Tender::where('status', TenderStatus::InProgress)->count())->toBe(2)
        ->and(Tender::where('status', TenderStatus::Done)->count())->toBe(1)
        ->and(Tender::where('status', TenderStatus::Awarded)->count())->toBe(1)
        ->and(Tender::where('status', TenderStatus::Lost)->count())->toBe(2)
        ->and(Tender::where('status', TenderStatus::Dropped)->count())->toBe(1)
        ->and(Tender::where('was_cancelled', true)->count())->toBe(1);

    $one = Tender::where('wo_number', '200-01012026-001')->first();
    expect($one->ministry)->toBe('KEMENTERIAN CONTOH')->and($one->client)->toBe('JABATAN SATU')
        ->and($one->category)->toBe(TenderCategory::General)
        ->and($one->has_briefing)->toBeTrue()->and($one->briefing_date->toDateString())->toBe('2026-01-10')
        ->and($one->documents()->count())->toBe(count(\App\Models\TenderDocument::STANDARD))
        ->and($one->activity()->first()->description)->toBe('Imported from the 2026 register');

    $won = Tender::where('wo_number', '200-01012026-004')->first();
    expect($won->awarded_at->toDateString())->toBe('2026-01-23')->and($won->documents()->count())->toBe(0);
    expect(Tender::where('wo_number', '200-01012026-007')->first()->dropped_at->toDateString())->toBe('2026-01-26');
});

it('creates one switched-off account per person, whatever the capitals, and Unassigned for blanks', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    $aminah = User::where('name', 'Aminah')->sole();
    expect($aminah->is_active)->toBeFalse()->and($aminah->email)->toBe('aminah@import.invalid')
        ->and(Tender::where('pic_id', $aminah->id)->count())->toBe(3)   // "Aminah" and "aminah "
        ->and(Tender::where('wo_number', '200-01012026-005')->first()->pic->name)->toBe('Unassigned');
});

it('turns the submitted cost into one costing line so Gross matches the sheet', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    $t = Tender::where('wo_number', '200-01012026-003')->first();
    expect($t->costingLines)->toHaveCount(1)
        ->and($t->costingLines->first()->description)->toBe(RegisterImporter::IMPORTED_COST)
        ->and($t->costingSummary()['margin_bp'])->toBe(2000);                       // (400k - 320k) / 400k = 20%
    expect(Tender::where('wo_number', '200-01012026-004')->first()->costingLines)->toHaveCount(0); // price but no cost
});

it('updates on a re-run instead of duplicating, leaving what people changed', function () {
    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();
    $t = Tender::where('wo_number', '200-01012026-003')->first();
    $t->forceFill(['category' => TenderCategory::CivilWorks])->save();
    $t->costingLines()->create(['position' => 9, 'description' => 'Added by hand', 'unit_cost_sen' => 100]);
    $first = Tender::where('wo_number', '200-01012026-001')->first();
    $first->documents()->first()->forceFill(['is_done' => true])->save();

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true])->assertSuccessful();

    expect(Tender::count())->toBe(7)
        ->and($t->fresh()->category)->toBe(TenderCategory::CivilWorks)
        ->and($t->fresh()->costingLines->pluck('description')->all())->toContain('Added by hand')
        ->and($t->fresh()->costingLines->where('description', RegisterImporter::IMPORTED_COST))->toHaveCount(1)
        ->and($first->fresh()->documents()->where('is_done', true)->count())->toBe(1)
        ->and($t->fresh()->activity()->first()->description)->toBe('Updated from the register');
});

it('saves nothing if any row fails part-way', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    $csv = file_get_contents(registerFixture());
    // a WO number longer than the column allows makes the database refuse the last row
    file_put_contents($path, str_replace('200-01012026-007', '200-01012026-007-THIS-IS-FAR-TOO-LONG', $csv));

    $this->artisan('tenders:import-register', ['file' => $path, '--commit' => true])
        ->expectsOutputToContain('Nothing was saved')->assertFailed();

    expect(Tender::count())->toBe(0)->and(User::count())->toBe(1);
});

it('stops with a plain message for a file that is not the register', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    file_put_contents($path, "Name,Amount\nX,1\n");

    $this->artisan('tenders:import-register', ['file' => $path, '--commit' => true])
        ->expectsOutputToContain('missing columns')->assertFailed();
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/Import/ImportTenderRegisterTest.php`
Expected: FAIL. The command "tenders:import-register" is not defined.

- [ ] **Step 3: Implement** `app/Import/RegisterImporter.php`:
```php
<?php

namespace App\Import;

use App\Enums\{Role, TenderCategory, TenderStatus};
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

/** Writes register rows into the pipeline, all in one transaction. */
final class RegisterImporter
{
    public const IMPORTED_COST = 'Imported cost (2026 register)';
    public const UNASSIGNED = 'Unassigned';

    /** Names of people the rows need that have no account yet (as they will be created). */
    public function accountsNeeded(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $name = $row['pic_name'] ?? self::UNASSIGNED;
            $names[mb_strtolower($name)] ??= self::displayName($name);
        }
        $existing = User::query()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

        return array_values(array_diff_key($names, array_flip($existing)));
    }

    public function import(array $rows, User $actor, bool $replaceSamples = false): array
    {
        return DB::transaction(function () use ($rows, $actor, $replaceSamples) {
            $removed = $replaceSamples ? app(SampleData::class)->remove(array_column($rows, 'wo_number')) : [];
            $people = [];
            $created = $updated = 0;
            $accounts = $this->accountsNeeded($rows);

            foreach ($rows as $row) {
                $pic = $this->person($row['pic_name'] ?? self::UNASSIGNED, $people);
                $tender = Tender::query()->where('wo_number', $row['wo_number'])->lockForUpdate()->first();
                $isNew = $tender === null;
                $tender ??= new Tender(['wo_number' => $row['wo_number'], 'category' => TenderCategory::General, 'version' => 0]);

                $closing = $row['closing_date'].' 00:00:00';
                $status = $row['status'];
                $tender->forceFill([
                    'wo_date' => $row['wo_date'], 'mode' => $row['mode'], 'type' => $row['type'],
                    'tender_code' => $row['tender_code'], 'title' => $row['title'],
                    'ministry' => $row['ministry'], 'client' => $row['client'],
                    'pic_id' => $pic->id, 'owner_id' => $pic->id,
                    'publish_date' => $row['publish_date'], 'closing_date' => $row['closing_date'],
                    'has_briefing' => $row['briefing_date'] !== null, 'briefing_date' => $row['briefing_date'],
                    'estimated_value_sen' => $row['estimated_value_sen'],
                    'submitted_price_sen' => $row['submitted_price_sen'],
                    'winning_price_sen' => $row['winning_price_sen'],
                    'status' => $status,
                    'was_cancelled' => $row['was_cancelled'],
                    'done_at' => in_array($status, [TenderStatus::Done, TenderStatus::Awarded], true) ? $closing : null,
                    'awarded_at' => $status === TenderStatus::Awarded ? $closing : null,
                    'lost_at' => $status === TenderStatus::Lost ? $closing : null,
                    'dropped_at' => $status === TenderStatus::Dropped ? $closing : null,
                    'version' => $tender->version + 1,
                ])->save();

                $this->costing($tender, $row);
                if ($isNew && $status === TenderStatus::InProgress) {
                    foreach (TenderDocument::STANDARD as $i => $name) {
                        $tender->documents()->create(['name' => $name, 'position' => $i + 1]);
                    }
                }
                ActivityLog::record($tender, $actor, $isNew ? 'imported' : 'import_updated',
                    $isNew ? 'Imported from the 2026 register' : 'Updated from the register');
                $isNew ? $created++ : $updated++;
            }

            return ['created' => $created, 'updated' => $updated, 'accounts' => $accounts, 'removed' => $removed];
        });
    }

    /** One costing line holding the register's submitted cost, priced at the submitted price, so Gross matches. */
    private function costing(Tender $tender, array $row): void
    {
        $tender->costingLines()->where('description', self::IMPORTED_COST)->delete();
        $cost = $row['submitted_cost_sen'];
        $price = $row['submitted_price_sen'];
        if ($cost === null || $price === null) {
            return;
        }
        $tender->costingLines()->create([
            'position' => (int) $tender->costingLines()->max('position') + 1,
            'description' => self::IMPORTED_COST, 'unit' => 'lot', 'quantity' => 1,
            'frequency' => 'one_off', 'months' => 1, 'project_year' => 1,
            'unit_cost_sen' => $cost, 'margin_bp' => 0,
        ]);
        $tender->forceFill(['bid_price_override_sen' => $price])->save();
    }

    private function person(string $name, array &$people): User
    {
        $key = mb_strtolower($name);

        return $people[$key] ??= User::query()->whereRaw('LOWER(name) = ?', [$key])->first()
            ?? User::query()->forceCreate([
                'name' => self::displayName($name),
                'email' => Str::slug($name).'@import.invalid',
                'password' => Hash::make(Str::random(40)),
                'role' => Role::Staff,
                'is_active' => false,
            ]);
    }

    /** "aminah" → "Aminah"; names already written with capitals are kept as written. */
    private static function displayName(string $name): string
    {
        return $name === mb_strtolower($name) ? mb_convert_case($name, MB_CASE_TITLE) : $name;
    }
}
```
`SampleData` comes in Task 7. For this task, create the stub `app/Import/SampleData.php` with `public function remove(array $keepWoNumbers): array { return []; }` and `public function preview(array $keepWoNumbers): array { return []; }`, and record a ruling. Task 7 replaces it.

Note on `accountsNeeded`: the preview must list "Aminah, Badrul, Unassigned" in first-seen order.

`app/Console/Commands/ImportTenderRegister.php`:
```php
<?php

namespace App\Console\Commands;

use App\Enums\TenderStatus;
use App\Import\{RegisterFormatException, RegisterImporter, SampleData, TenderRegister};
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class ImportTenderRegister extends Command
{
    protected $signature = 'tenders:import-register
        {file : Path to the register CSV (kept outside the project)}
        {--commit : Save the import (without it, this only previews)}
        {--replace-samples : Remove the sample tenders, quotations and staff first}
        {--as=admin@cmt.test : Email of the account recorded as doing the import}';

    protected $description = 'Import the tender register spreadsheet (CSV) into the pipeline';

    public function handle(TenderRegister $register, RegisterImporter $importer): int
    {
        try {
            $read = $register->read((string) $this->argument('file'));
        } catch (RegisterFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $rows = $read['rows'];

        $this->info(count($rows).' tenders ready, '.count($read['skipped']).' rows skipped.');
        foreach (TenderStatus::cases() as $s) {
            $this->line('  '.$s->label().': '.collect($rows)->where('status', $s)->count());
        }
        $this->line('  (of which cancelled: '.collect($rows)->where('was_cancelled', true)->count().')');
        foreach ($read['skipped'] as $s) {
            $this->warn("  Skipped line {$s['line']} ({$s['wo']}): {$s['reason']}");
        }
        foreach ($read['warnings'] as $w) {
            $this->warn('  '.$w);
        }
        $accounts = $importer->accountsNeeded($rows);
        $this->line('New switched-off accounts: '.($accounts ? implode(', ', $accounts) : 'none'));
        if ($this->option('replace-samples')) {
            foreach (app(SampleData::class)->preview(array_column($rows, 'wo_number')) as $label => $count) {
                $this->line("Will remove {$label}: {$count}");
            }
        }

        if (! $this->option('commit')) {
            $this->info('Preview only — nothing saved. Run again with --commit to save.');

            return self::SUCCESS;
        }
        if ($rows === []) {
            $this->error('There is nothing to import.');

            return self::FAILURE;
        }
        $actor = User::where('email', $this->option('as'))->first();
        if (! $actor) {
            $this->error("No account with the email {$this->option('as')} to record the import against.");

            return self::FAILURE;
        }

        try {
            $result = $importer->import($rows, $actor, (bool) $this->option('replace-samples'));
        } catch (Throwable $e) {
            $this->error('Nothing was saved — '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Saved: {$result['created']} new, {$result['updated']} updated, ".count($result['accounts']).' accounts created.');
        foreach ($result['removed'] as $label => $count) {
            $this->line("Removed {$label}: {$count}");
        }

        return self::SUCCESS;
    }
}
```
Adjust the skipped-line message to match the test, `line 9 (200-01012026-008): unknown status 'Pending'`. The test uses `expectsOutputToContain`, so the "Skipped " prefix is fine.

- [ ] **Step 4: Run them and watch them pass.** Same command → PASS. If MySQL truncates instead of rejecting the too-long WO number (non-strict mode), the rollback test fails. In that case check `config/database.php` `strict` (Laravel's default is `true`) and use a different guaranteed failure (for example a `title` of 70,000 characters, which exceeds TEXT). Record a ruling either way.

- [ ] **Step 5: Commit**
```bash
git add app/Import app/Console/Commands/ImportTenderRegister.php tests/Feature/Import
git commit -m "feat: tenders:import-register previews, then imports the register in one transaction"
```

---

### Task 7: Replace the sample data (`--replace-samples`)

**Files:**
- Modify: `app/Import/SampleData.php` (replace the stub)
- Test: `tests/Feature/Import/ImportTenderRegisterTest.php` (append)

**Interfaces:**
- Consumes:
  - `RegisterImporter::import(..., replaceSamples: true)` and the command option (Task 6)
- Produces:
  - `SampleData::preview(array $keepWoNumbers): array<string,int>`
  - `SampleData::remove(array $keepWoNumbers): array<string,int>`
  - the labels `sample tenders`, `quotations`, `sample staff (deleted)`, `sample staff (switched off)`, `notifications`

- [ ] **Step 1: Write the failing tests** (append)
```php
it('replaces the samples but keeps the admin, the manager and Find Tenders data', function () {
    $manager = User::factory()->create(['email' => 'manager@cmt.test', 'role' => \App\Enums\Role::Manager]);
    $sampleStaff = User::factory()->create(['name' => 'Sample Person']);
    $sample = Tender::factory()->create(['pic_id' => $sampleStaff->id]);
    \App\Models\Quotation::factory()->create(['prepared_by' => $sampleStaff->id]);
    $collected = \App\Models\CollectedTender::factory()->create();
    $runner = User::factory()->create(['name' => 'Ran A Collection']);
    \App\Models\CollectionRun::factory()->create(['started_by' => $runner->id]);

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--replace-samples' => true])
        ->expectsOutputToContain('Will remove sample tenders: 1')->assertSuccessful();
    expect(Tender::count())->toBe(1); // preview removed nothing

    $this->artisan('tenders:import-register', ['file' => registerFixture(), '--commit' => true, '--replace-samples' => true])
        ->expectsOutputToContain('Removed quotations: 1')->assertSuccessful();

    expect(Tender::find($sample->id))->toBeNull()
        ->and(\App\Models\Quotation::count())->toBe(0)
        ->and(User::find($sampleStaff->id))->toBeNull()
        ->and(User::find($runner->id)->is_active)->toBeFalse()           // still referenced by a collection run
        ->and(User::where('email', 'admin@cmt.test')->exists())->toBeTrue()
        ->and($manager->fresh())->not->toBeNull()
        ->and(\App\Models\CollectedTender::find($collected->id))->not->toBeNull()
        ->and(Tender::count())->toBe(7);
});
```
(If `CollectionRun` has no factory, create the row with `CollectionRun::create([...])`, copying the fields from an existing collector test.)

- [ ] **Step 2: Run it and watch it fail.** Expected: FAIL. With the Task 6 stub, "Will remove sample tenders" is never printed.

- [ ] **Step 3: Implement** `app/Import/SampleData.php`:
```php
<?php

namespace App\Import;

use App\Models\{Quotation, Tender, User};
use Illuminate\Support\Facades\DB;

/**
 * The made-up records the app shipped with. Removing them before the first real import keeps
 * the Dashboard honest. Find Tenders' collected tenders are never touched.
 */
final class SampleData
{
    public const KEEP_EMAILS = ['admin@cmt.test', 'manager@cmt.test'];

    public function preview(array $keepWoNumbers): array
    {
        return [
            'sample tenders' => $this->tenders($keepWoNumbers)->count(),
            'quotations' => Quotation::count(),
            'sample staff' => $this->staff()->count(),
        ];
    }

    /** Call inside a transaction. */
    public function remove(array $keepWoNumbers): array
    {
        $tenders = $this->tenders($keepWoNumbers)->count();
        $this->tenders($keepWoNumbers)->delete();      // documents, costing, activity, projects cascade
        $quotations = Quotation::count();
        Quotation::query()->delete();                  // items, activity, projects cascade
        $notifications = DB::table('notifications')->delete();

        $deleted = $switchedOff = 0;
        foreach ($this->staff()->get() as $user) {
            // Still referenced elsewhere (e.g. started a Find Tenders collection): keep the record, switch it off.
            if (DB::table('collection_runs')->where('started_by', $user->id)->exists()
                || DB::table('activity_logs')->where('user_id', $user->id)->exists()) {
                $user->forceFill(['is_active' => false])->save();
                $switchedOff++;
            } else {
                $user->delete();
                $deleted++;
            }
        }

        return ['sample tenders' => $tenders, 'quotations' => $quotations, 'notifications' => $notifications,
            'sample staff (deleted)' => $deleted, 'sample staff (switched off)' => $switchedOff];
    }

    private function tenders(array $keepWoNumbers)
    {
        return Tender::query()->whereNotIn('wo_number', $keepWoNumbers);
    }

    /** Everyone except the kept admin/manager and accounts the import itself created. */
    private function staff()
    {
        return User::query()->whereNotIn('email', self::KEEP_EMAILS)->where('email', 'not like', '%@import.invalid');
    }
}
```
Before relying on the cascades, check that `projects.quotation_id` and `activity_logs.quotation_id` cascade on delete (see migration `2026_10_10_000001_create_quotation_tables.php` lines 99–104). If a foreign key doesn't cascade, delete those child rows explicitly first and record a ruling. Also check `tender_documents.done_by`: it points at users without cascade, but those rows are already gone with their tenders.

- [ ] **Step 4: Run all import tests → PASS.** Then run the full suite, `… ./vendor/bin/pest`. Expected: all green.

- [ ] **Step 5: Commit**
```bash
git add app/Import/SampleData.php tests/Feature/Import
git commit -m "feat: --replace-samples removes the sample tenders, quotations and staff in the same transaction"
```

---

### Task 8: README, full suite, preview on the real file

**Files:**
- Modify: `README.md`

- [ ] **Step 1: README section** after "Look and feel":
```markdown
## Importing the tender register

The team's register spreadsheet (saved as CSV) is imported with a command — the file stays outside the project:

    docker compose exec app php artisan tenders:import-register "/path/to/register.csv"             # preview only
    docker compose exec app php artisan tenders:import-register "/path/to/register.csv" --commit    # save

- Statuses: Open/Assigned → In Progress, Submitted → Done, Won → Awarded, Lost → Lost,
  Cancelled → Lost (cancelled), Drop → **Dropped** (left out of win rate and bid values).
- Matching is by WO number, so re-running with a newer register updates tenders instead of duplicating.
- New PICs become **switched-off** accounts (`name@import.invalid`); an admin sets the real email and
  switches them on in Manage Users. Blank PIC → "Unassigned".
- Submitted Cost becomes one costing line, "Imported cost (2026 register)", so Gross matches the sheet.
- `--replace-samples` (first import only) removes the sample tenders, quotations and staff — keeps
  admin@cmt.test, manager@cmt.test and Find Tenders data. Everything runs in one transaction.
```

- [ ] **Step 2: Full suite with coverage**: `… ./vendor/bin/pest --coverage --min=80` → all pass, ≥ 80%.

- [ ] **Step 3: Copy the real file into the container's temp folder (not the project) and preview it**:
```bash
docker compose cp "C:/Users/CMT-Lynnda/Downloads/6(2026) (1).csv" app:/tmp/register-2026.csv
MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan migrate --force
MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan tenders:import-register /tmp/register-2026.csv --replace-samples
```
Expected:
- 393 ready, 0 skipped
- In Progress 51, Done 177, Awarded 24, Lost 113 (cancelled 12), Dropped 28
- accounts: Sharul, Kamarina, Fitri, Afiq, Nina, Fazleen, Ridhwan, Syukri, Muallim, Unassigned
- sample counts to remove

Record the output summary (counts only, no tender details) in the ledger.

- [ ] **Step 4: Commit** the README:
```bash
git add README.md
git commit -m "docs: how to import the tender register"
```

**Stop here.** The real `--commit --replace-samples` run deletes the sample data. It runs only after the user has seen the preview numbers and says yes (handled after the final review, before the finishing menu).
