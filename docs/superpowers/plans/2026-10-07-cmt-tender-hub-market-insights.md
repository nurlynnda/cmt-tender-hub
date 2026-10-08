# Awarded Tenders + Market Insights Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an "Awarded" view to Find Tenders (winners, prices, contractor search, our wins) and a Market Insights page (awards by year, spend by ministry, top contractors, our rank), fed by an indexed winners table.

**Architecture:**
- **Winners:** a `collected_tender_winners` table (one row per winner, with a normalised `name_key`) mirrors the `winners` JSON. It's filled by a backfill command and kept current by a `CollectedTender` saved hook.
- **Own company:** `ContractorName::key()` normalises names; `OwnCompany` reads the company's names from Finance Settings.
- **Reports:** `MarketReport` runs grouped SQL over awarded tenders. The Livewire pages `MarketInsights`, `MarketMinistries` and `MarketContractors` render it with the Round 1/2 components.

**Tech Stack:** Laravel 13, Livewire 4.4, Pest 5, MySQL 8.4. PHP runs only in Docker: from `C:\Projects\cmt-tender-hub` run `MSYS_NO_PATHCONV=1 docker compose exec -T app …`. The pre-commit hook runs the full suite (~10 min). Run commits in the background and wait for them.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-market-insights-design.md`

## Global Constraints

- Money in sen; Malaysia time via `App\Support\MalaysiaTime`; UI copy in plain English.
- **Name key:** upper-case; every character that isn't a letter or digit becomes a space; collapse spaces; trim.
- **Company names:** both data spellings ("10 CREATIVE SOLUTIONS SDN. BHD.", "10 CREATIVE SOLUTIONS SDN BHD") are the company. "DARKWHITE CREATIVE SOLUTIONS SDN. BHD.", "ND CREATIVE SOLUTION" and "WHATTHEFOO CREATIVE SOLUTIONS" are not.
- **"Awarded"** = `status = 'closed'` and at least one winner row. **"Year"** = `YEAR(closing_date)`. The value of an award = the sum of its winners' `price_sen`.
- The `collected_tenders.winners` JSON is never modified.
- **Reuse the Round 1/2 components:** `x-page-heading`, `x-card`, `x-fact`, `x-filter-bar`, `x-filter-field`, `x-data-table`, `x-status-pill`, `btn-*`, table classes, the input class `w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]`.
- Tests first (RED → GREEN); coverage ≥ 80%; write PHP/Blade with the Edit/Write tools.

## Review Focus

1. A contractor search typed with punctuation or wildcards (`%`, `_`, "Sdn. Bhd.") matches by name key, and `%`/`_` don't act as wildcards. Pinned in Task 4.
2. The year picker set to a year with no awards (e.g. 2030), or to text → the current year with a note, and no crash. Pinned in Task 6.
3. The company has no wins in the chosen year → the card says "No awards in {year}", with no rank and no "… #N" row. Pinned in Task 6.
4. A tender whose winners change between collections (a winner removed or the price changed) → its winner rows reflect the latest list, with no duplicates. Pinned in Task 1.
5. Two contractor spellings with the same key → one row in Top Contractors, with wins counted once per tender (a tender listing both spellings counts as 1 win). Pinned in Task 5.

---

### Task 1: Name key, winners table, sync and the saved hook

**Files:**
- Create:
  - `app/Collector/ContractorName.php`
  - `app/Collector/WinnerIndex.php`
  - `app/Models/CollectedTenderWinner.php`
  - `database/migrations/2026_10_12_000001_create_collected_tender_winners.php`
- Modify: `app/Models/CollectedTender.php`
- Test: `tests/Unit/Collector/ContractorNameTest.php`, `tests/Feature/Collector/WinnerIndexTest.php`

**Interfaces:**
- Produces:
  - `ContractorName::key(string): string`
  - `WinnerIndex::sync(CollectedTender): void`
  - `CollectedTender::winnerRows(): HasMany` (ordered by position)
  - table `collected_tender_winners(id, collected_tender_id, name, name_key, price_sen, position)`
  - `finance_settings.own_company_names` (text, nullable) seeded `"10 CREATIVE SOLUTIONS SDN BHD"`

- [ ] **Step 1: Failing tests**

`tests/Unit/Collector/ContractorNameTest.php`:
```php
<?php

use App\Collector\ContractorName;

it('matches names that differ only in capitals, dots, commas and spacing', function (string $in, string $key) {
    expect(ContractorName::key($in))->toBe($key);
})->with([
    ['10 CREATIVE SOLUTIONS SDN. BHD.', '10 CREATIVE SOLUTIONS SDN BHD'],
    ['10 Creative Solutions Sdn Bhd', '10 CREATIVE SOLUTIONS SDN BHD'],
    ['  10   creative solutions, sdn.bhd. ', '10 CREATIVE SOLUTIONS SDN BHD'],
    ['DARKWHITE CREATIVE SOLUTIONS SDN. BHD.', 'DARKWHITE CREATIVE SOLUTIONS SDN BHD'],
    ['…', ''],
]);
```
`tests/Feature/Collector/WinnerIndexTest.php`:
```php
<?php

use App\Models\{CollectedTender, CollectedTenderWinner};

it('keeps one winner row per published winner, refreshed when the winners change', function () {
    $t = CollectedTender::factory()->create(['status' => 'closed', 'winners' => [
        ['name' => 'ACME SDN. BHD.', 'price_sen' => 1000], ['name' => '  ', 'price_sen' => 5], ['name' => 'Beta Sdn Bhd', 'price_sen' => null],
    ]]);

    expect($t->winnerRows()->get(['name', 'name_key', 'price_sen', 'position'])->toArray())->toBe([
        ['name' => 'ACME SDN. BHD.', 'name_key' => 'ACME SDN BHD', 'price_sen' => 1000, 'position' => 1],
        ['name' => 'Beta Sdn Bhd', 'name_key' => 'BETA SDN BHD', 'price_sen' => null, 'position' => 2],
    ]);

    $t->update(['winners' => [['name' => 'ACME SDN BHD', 'price_sen' => 2000]]]);
    expect($t->winnerRows()->pluck('price_sen')->all())->toBe([2000]);

    $t->update(['winners' => null]);
    expect(CollectedTenderWinner::count())->toBe(0);
});

it('does not touch winner rows when an unrelated field changes', function () {
    $t = CollectedTender::factory()->create(['status' => 'closed', 'winners' => [['name' => 'ACME', 'price_sen' => 1]]]);
    $id = $t->winnerRows()->value('id');

    $t->update(['title' => 'NEW TITLE']);
    expect($t->winnerRows()->value('id'))->toBe($id);
});
```

- [ ] **Step 2: Run → FAIL** (the class is not found).

- [ ] **Step 3: Implement**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collected_tender_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collected_tender_id')->constrained()->cascadeOnDelete();
            $table->string('name', 500);
            $table->string('name_key', 500);
            $table->unsignedBigInteger('price_sen')->nullable();
            $table->unsignedSmallInteger('position');
            $table->index(['name_key', 'collected_tender_id']);
            $table->index(['collected_tender_id', 'position']);
        });
        Schema::table('finance_settings', fn (Blueprint $table) => $table->text('own_company_names')->nullable());
        DB::table('finance_settings')->update(['own_company_names' => '10 CREATIVE SOLUTIONS SDN BHD']);
    }

    public function down(): void
    {
        Schema::dropIfExists('collected_tender_winners');
        Schema::table('finance_settings', fn (Blueprint $table) => $table->dropColumn('own_company_names'));
    }
};
```
(If `string(500)` plus the index exceeds MySQL's index length with utf8mb4, use `string('name_key', 191)` and `mb_substr(..., 0, 191)` in the key, and record a ruling.)

`app/Collector/ContractorName.php`:
```php
<?php

namespace App\Collector;

/** One comparable key per company: "10 Creative Solutions Sdn. Bhd." and "10 CREATIVE SOLUTIONS SDN BHD" match. */
final class ContractorName
{
    public static function key(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtoupper($name))));
    }
}
```

`app/Models/CollectedTenderWinner.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One published winner of a collected tender (a searchable copy of collected_tenders.winners). */
class CollectedTenderWinner extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_sen' => 'integer', 'position' => 'integer'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(CollectedTender::class, 'collected_tender_id');
    }
}
```

`app/Collector/WinnerIndex.php`:
```php
<?php

namespace App\Collector;

use App\Models\CollectedTender;
use Illuminate\Support\Facades\DB;

/** Keeps collected_tender_winners in step with a tender's published winners. */
final class WinnerIndex
{
    public static function sync(CollectedTender $t): void
    {
        DB::table('collected_tender_winners')->where('collected_tender_id', $t->id)->delete();
        $rows = self::rows($t->id, $t->winners);
        if ($rows !== []) {
            DB::table('collected_tender_winners')->insert($rows);
        }
    }

    /** @return list<array> insertable rows; blank names are skipped */
    public static function rows(int $tenderId, mixed $winners): array
    {
        $rows = [];
        foreach (is_array($winners) ? $winners : [] as $w) {
            $name = trim((string) ($w['name'] ?? ''));
            $key = ContractorName::key($name);
            if ($key === '') {
                continue;
            }
            $price = $w['price_sen'] ?? null;
            $rows[] = ['collected_tender_id' => $tenderId, 'name' => mb_substr($name, 0, 500), 'name_key' => mb_substr($key, 0, 500),
                'price_sen' => is_numeric($price) && $price >= 0 ? (int) $price : null, 'position' => count($rows) + 1];
        }

        return $rows;
    }
}
```

`CollectedTender`:
- add `use App\Collector\WinnerIndex;`
- add the relation `public function winnerRows(): HasMany { return $this->hasMany(CollectedTenderWinner::class)->orderBy('position'); }`
- add the hook:
```php
    protected static function booted(): void
    {
        // Keep the searchable winners list in step with the published winners.
        static::saved(function (self $t) {
            if ($t->wasRecentlyCreated || $t->wasChanged('winners')) {
                WinnerIndex::sync($t);
            }
        });
    }
```

- [ ] **Step 4: Run → PASS**, then run `tests/Feature/Collector` (the merger etc.) → PASS.
- [ ] **Step 5: Commit** `feat: searchable winners list kept in step with each collected tender`

---

### Task 2: Backfill command

**Files:** Create `app/Console/Commands/IndexWinners.php`. Test: `tests/Feature/Collector/IndexWinnersTest.php`.

**Interfaces:**
- Consumes:
  - `WinnerIndex::rows()` (Task 1)
- Produces:
  - `php artisan collector:index-winners`

- [ ] **Step 1: Failing test**
```php
<?php

use App\Models\{CollectedTender, CollectedTenderWinner};
use Illuminate\Support\Facades\DB;

it('rebuilds the winners list from every tender, safely re-runnable', function () {
    $a = CollectedTender::factory()->create(['status' => 'closed', 'winners' => [['name' => 'ACME', 'price_sen' => 1], ['name' => 'BETA', 'price_sen' => 2]]]);
    CollectedTender::factory()->create(['status' => 'closed', 'winners' => null]);
    $bad = CollectedTender::factory()->create(['status' => 'closed']);
    DB::table('collected_tenders')->where('id', $bad->id)->update(['winners' => '"not a list"']); // as a legacy bulk insert might leave it
    DB::table('collected_tender_winners')->delete(); // as after the legacy import (bulk insert skips the model)

    $this->artisan('collector:index-winners')->expectsOutputToContain('2 winners from 1 tenders')->assertSuccessful();
    $this->artisan('collector:index-winners')->assertSuccessful();

    expect(CollectedTenderWinner::where('collected_tender_id', $a->id)->count())->toBe(2)->and(CollectedTenderWinner::count())->toBe(2);
});
```

- [ ] **Step 2: Run → FAIL** (the command is not defined).

- [ ] **Step 3: Implement**
```php
<?php

namespace App\Console\Commands;

use App\Collector\WinnerIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IndexWinners extends Command
{
    protected $signature = 'collector:index-winners';
    protected $description = 'Rebuild the searchable winners list from every collected tender (safe to re-run)';

    public function handle(): int
    {
        DB::table('collected_tender_winners')->delete();
        $winners = $tenders = 0;
        DB::table('collected_tenders')->whereNotNull('winners')->select('id', 'winners')->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$winners, &$tenders) {
                $rows = [];
                foreach ($chunk as $t) {
                    $found = WinnerIndex::rows($t->id, json_decode((string) $t->winners, true));
                    if ($found !== []) {
                        $tenders++;
                        array_push($rows, ...$found);
                    }
                }
                foreach (array_chunk($rows, 1000) as $part) {
                    DB::table('collected_tender_winners')->insert($part);
                }
                $winners += count($rows);
            });
        $this->info("Indexed {$winners} winners from {$tenders} tenders.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat: collector:index-winners rebuilds the winners list`

---

### Task 3: Company names in Finance Settings

**Files:**
- Create: `app/Market/OwnCompany.php`
- Modify: `app/Models/FinanceSetting.php`, `app/Livewire/FinanceSettings.php`, `resources/views/livewire/finance-settings.blade.php`
- Test: `tests/Feature/Livewire/FinanceSettingsTest.php` (covers `OwnCompany` too, since it reads the database)

**Interfaces:**
- Produces:
  - `OwnCompany::keys(): list<string>`
  - `OwnCompany::label(): string` (the first line, title-cased; "Our company" if empty)
  - `FinanceSettings::$ownNames`, `FinanceSettings::saveOwnNames()`

- [ ] **Step 1: Failing tests** (append to `FinanceSettingsTest.php`, using its admin setup):
```php
it('saves the company names used to spot our wins, one per line', function () {
    $this->actingAs(\App\Models\User::factory()->admin()->create());

    \Livewire\Livewire::test(\App\Livewire\FinanceSettings::class)
        ->assertSet('ownNames', '10 CREATIVE SOLUTIONS SDN BHD')
        ->set('ownNames', "10 Creative Solutions Sdn. Bhd.\n\n10 CREATIVE SOLUTIONS SDN BHD\nCMT TECH")->call('saveOwnNames')
        ->assertSee('Saved');

    expect(\App\Market\OwnCompany::keys())->toBe(['10 CREATIVE SOLUTIONS SDN BHD', 'CMT TECH'])
        ->and(\App\Market\OwnCompany::label())->toBe('10 Creative Solutions Sdn. Bhd.');
});
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement**

`OwnCompany`:
```php
<?php

namespace App\Market;

use App\Collector\ContractorName;
use App\Models\FinanceSetting;

/** The names our company wins under in government results (Finance Settings → one per line). */
final class OwnCompany
{
    private static function lines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) FinanceSetting::current()->own_company_names))));
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_values(array_unique(array_filter(array_map([ContractorName::class, 'key'], self::lines()))));
    }

    public static function label(): string
    {
        $first = self::lines()[0] ?? null;

        return $first === null ? 'Our company' : ($first === mb_strtoupper($first) ? mb_convert_case(mb_strtolower($first), MB_CASE_TITLE) : $first);
    }
}
```
(Title-casing an all-caps name gives "10 Creative Solutions Sdn Bhd". A name typed with mixed case is kept as typed.)

`FinanceSettings`:
- add `public string $ownNames = '';`
- in `mount()`: `$this->ownNames = (string) $s->own_company_names;`
- add the method:
```php
    public function saveOwnNames(): void
    {
        $this->authorize('manage-finance');
        $this->validate(['ownNames' => ['nullable', 'string', 'max:2000']]);
        FinanceSetting::current()->forceFill(['own_company_names' => trim($this->ownNames)])->save();
        $this->notice = 'Saved. Market Insights and Find Tenders will mark these names as ours.';
    }
```

View: inside the "Company defaults" card, below the error lines:
```blade
        <div class="mt-4 border-t border-line pt-4">
            <label class="flex flex-col gap-1 {{ $lbl }}">Our company names in tender results (one per line)
                <textarea wire:model="ownNames" rows="3" class="{{ $input }}" placeholder="10 CREATIVE SOLUTIONS SDN BHD"></textarea>
            </label>
            <p class="mt-1 text-xs text-muted">Matched ignoring capitals, dots, commas and spaces. Used to mark our wins in Find Tenders and Market Insights.</p>
            @error('ownNames') <p class="mt-1 text-xs text-bad-ink">{{ $message }}</p> @enderror
            <button type="button" wire:click="saveOwnNames" class="btn btn-dark mt-2">Save names</button>
        </div>
```

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat: company names for spotting our wins, in Finance Settings`

---

### Task 4: Awarded in Find Tenders

**Files:**
- Modify:
  - `app/Queries/CollectedTenderQuery.php`
  - `app/Livewire/FindTenders.php`
  - `resources/views/livewire/find-tenders.blade.php`
  - `resources/views/livewire/collected-tender-detail.blade.php`
  - `app/Livewire/CollectedTenderDetail.php` (pass `ownKeys`)
- Test: `tests/Feature/Livewire/FindTendersTest.php`, `tests/Feature/Livewire/CollectedTenderDetailTest.php`

**Interfaces:**
- Consumes:
  - `winnerRows()` (Task 1)
  - `OwnCompany::keys()` (Task 3)
- Produces:
  - query filters `status=awarded`, `contractor`, `ours`
  - `FindTenders::$contractor`, `FindTenders::$ours`

- [ ] **Step 1: Failing tests** (append to `FindTendersTest.php`):
```php
function awarded(string $ref, array $winners, array $o = []): CollectedTender
{
    return CollectedTender::factory()->create(array_merge(['reference_no' => $ref, 'status' => 'closed', 'closing_date' => '2026-03-01', 'winners' => $winners], $o));
}

it('lists awarded tenders with their winners, and searches by contractor ignoring punctuation and wildcards', function () {
    awarded('OURS-1', [['name' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 500000]]);
    awarded('OURS-2', [['name' => '10 CREATIVE SOLUTIONS SDN BHD', 'price_sen' => 100]]);
    awarded('LOOKALIKE', [['name' => 'DARKWHITE CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 1]]);
    CollectedTender::factory()->create(['reference_no' => 'NO-WINNER', 'status' => 'closed']);

    expect(refs(['status' => 'awarded']))->toEqualCanonicalizing(['OURS-1', 'OURS-2', 'LOOKALIKE'])
        ->and(refs(['status' => 'awarded', 'contractor' => '10 creative solutions sdn.bhd']))->toEqualCanonicalizing(['OURS-1', 'OURS-2'])
        ->and(refs(['status' => 'awarded', 'contractor' => '%']))->toEqualCanonicalizing(['OURS-1', 'OURS-2', 'LOOKALIKE']) // too short → ignored
        ->and(refs(['status' => 'awarded', 'contractor' => 'X_Y']))->toBe([])
        ->and(refs(['status' => 'awarded', 'ours' => true]))->toEqualCanonicalizing(['OURS-1', 'OURS-2']);

    Livewire::test(FindTenders::class)->set('status', 'awarded')
        ->assertSee('Winner(s)')->assertSee('10 CREATIVE SOLUTIONS SDN. BHD.')->assertSee('RM 5,000.00')
        ->assertSeeHtml('data-ours')->assertSee('Our wins')
        ->toggle('ours')->assertDontSee('LOOKALIKE');
});

it('only offers Our wins while Awarded is chosen', function () {
    Livewire::test(FindTenders::class)->assertDontSee('Our wins');
});
```
`CollectedTenderDetailTest.php` (append):
```php
it('marks our company in the winners', function () {
    $c = collected(['winners' => [['name' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 1]]]);
    $this->get(route('find-tenders.show', $c))->assertSee('data-ours', false);
});
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement**

`CollectedTenderQuery::build`:
- status list: `['open', 'closed', 'awarded', 'all']`. For `awarded`: `$q->where('status', 'closed')->whereExists(fn ($s) => $s->from('collected_tender_winners as w')->whereColumn('w.collected_tender_id', 'collected_tenders.id'))`. The existing `if ($status !== 'all') where('status', $status)` must not run for `awarded`, so restructure it as a `match`.
- `usualOrder`: `awarded` behaves like `closed`.
- eager-load: `$q->with(['sources', 'pipelineTenders:…', 'winnerRows'])` (the winner rows are needed by the awarded columns, and are cheap for 25 rows).
- filters:
```php
        $key = ContractorName::key((string) ($f['contractor'] ?? ''));
        if (mb_strlen($key) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%';
            $q->whereExists(fn ($s) => $s->from('collected_tender_winners as w')
                ->whereColumn('w.collected_tender_id', 'collected_tenders.id')->where('w.name_key', 'like', $like));
        }
        if (! empty($f['ours']) && ($own = OwnCompany::keys()) !== []) {
            $q->whereExists(fn ($s) => $s->from('collected_tender_winners as w')
                ->whereColumn('w.collected_tender_id', 'collected_tenders.id')->whereIn('w.name_key', $own));
        }
```
  (A key of `%` is the empty string → ignored. "X_Y" → "X Y" → LIKE '%X Y%' matches nothing.)

`FindTenders`:
- `#[Url] public string $contractor = '';` and `#[Url] public bool $ours = false;`
- add both to `clearFilters`, and `contractor` to `filterCount()`
- pass `'contractor', 'ours'` in `$this->only([...])`
- pass `'ownKeys' => OwnCompany::keys()` to the view

View:
- status select gains `<option value="awarded">Awarded</option>`
- the Filters panel gains `<x-filter-field label="Contractor"><input wire:model.live.debounce.400ms="contractor" placeholder="Any contractor" class="{{ $field }}"></x-filter-field>`
- the filter bar: pass `:mine="$status === 'awarded' ? $ours : null"`. Make `x-filter-bar` accept `mineLabel` (default "My tenders") and `mineModel` (default "mine"), and pass `mine-label="Our wins" mine-model="ours"`. Update `filter-bar.blade.php` to use `wire:click="$toggle('{{ $mineModel }}')"` and `{{ $mineLabel }}`, keeping the defaults so the tender lists are unchanged.
- when `$status === 'awarded'`: the headers become Reference · Title · Ministry / Agency · Closing · Winner(s) · Price won · Indicative price, where Advertised and Type are replaced by Winner(s) and Price won. In body cells, for each `$t->winnerRows`, a line with the name plus `@if (in_array($w->name_key, $ownKeys, true)) <span data-ours class="ml-1 rounded-full bg-good-bg px-1.5 text-[10.5px] font-bold text-good-ink">Ours</span> @endif`; the Price won cell lists each `Money::format($w->price_sen)` on matching lines
- phone cards: when awarded, a line per winner, "Name · RM x"

Detail page: in the Winners card, add the same `data-ours` pill when `ContractorName::key($w['name'])` is in `OwnCompany::keys()`. Compute `$ownKeys` in the view's `@php` from `\App\Market\OwnCompany::keys()`.

- [ ] **Step 4: Run** the Find Tenders, detail, tender list and component tests → PASS.
- [ ] **Step 5: Commit** `feat: Awarded status in Find Tenders with winners, contractor search and our wins`

---

### Task 5: MarketReport

**Files:** Create `app/Market/MarketReport.php`. Test: `tests/Feature/Market/MarketReportTest.php`.

**Interfaces:**
- Produces (all for awarded tenders; `$year` is `int|null`, with null = all years):
  - `now(): array{open:int, closing_today:int, closing_week:int}`
  - `summary(?int $year): array{tenders:int, value_sen:int, contractors:int, unpriced:int}`
  - `byYear(): list<array{year:int, tenders:int, value_sen:int}>` (newest first)
  - `years(): list<int>`
  - `byMinistry(?int $year, ?int $limit = null): list<array{ministry:string, tenders:int, value_sen:int}>`
  - `contractors(?int $year, string $search = '', ?int $limit = null): \Illuminate\Database\Query\Builder`, a query ordered value desc, wins desc, name asc, with columns `name_key, name, wins, value_sen`; callers `->limit()->get()` or `->paginate(50)`
  - `ownRank(?int $year): ?array{rank:int, of:int, wins:int, value_sen:int}`

- [ ] **Step 1: Failing tests**
```php
<?php

use App\Market\MarketReport;
use App\Models\CollectedTender;

function award(?string $closing, ?string $ministry, array $winners): void
{
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => $closing, 'ministry' => $ministry, 'winners' => $winners]);
}

beforeEach(function () {
    award('2026-02-01', 'KEMENTERIAN A', [['name' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 300]]);
    award('2026-03-01', 'KEMENTERIAN A', [['name' => 'ACME SDN BHD', 'price_sen' => 1000], ['name' => 'Acme Sdn. Bhd.', 'price_sen' => 50]]); // same company twice on one tender
    award('2026-04-01', null, [['name' => 'BETA', 'price_sen' => null]]);
    award('2025-05-01', 'KEMENTERIAN B', [['name' => '10 CREATIVE SOLUTIONS SDN BHD', 'price_sen' => 700]]);
    award(null, 'KEMENTERIAN B', [['name' => 'GAMMA', 'price_sen' => 9]]);
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => '2026-01-01', 'winners' => null]); // closed, no winner
});

it('summarises a year and all years', function () {
    $r = app(MarketReport::class);

    expect($r->summary(2026))->toBe(['tenders' => 3, 'value_sen' => 1350, 'contractors' => 3, 'unpriced' => 1])
        ->and($r->summary(null))->toBe(['tenders' => 5, 'value_sen' => 2059, 'contractors' => 4, 'unpriced' => 1])
        ->and($r->byYear())->toBe([['year' => 2026, 'tenders' => 3, 'value_sen' => 1350], ['year' => 2025, 'tenders' => 1, 'value_sen' => 700]])
        ->and($r->years())->toBe([2026, 2025]);
});

it('ranks ministries and contractors, merging spellings and counting a tender once per contractor', function () {
    $r = app(MarketReport::class);

    expect($r->byMinistry(2026))->toBe([
        ['ministry' => 'KEMENTERIAN A', 'tenders' => 2, 'value_sen' => 1350], ['ministry' => 'Not stated', 'tenders' => 1, 'value_sen' => 0],
    ]);
    $top = $r->contractors(2026)->get()->map(fn ($c) => [$c->name_key, (int) $c->wins, (int) $c->value_sen])->all();
    expect($top)->toBe([['ACME SDN BHD', 1, 1050], ['10 CREATIVE SOLUTIONS SDN BHD', 1, 300], ['BETA', 1, 0]])
        ->and($r->contractors(2026, 'acme')->count())->toBe(1);
});

it('finds our rank, or none when we won nothing that year', function () {
    $r = app(MarketReport::class);

    expect($r->ownRank(2026))->toBe(['rank' => 2, 'of' => 3, 'wins' => 1, 'value_sen' => 300])
        ->and($r->ownRank(null))->toBe(['rank' => 2, 'of' => 4, 'wins' => 2, 'value_sen' => 1000])   // ACME 1050 first
        ->and($r->ownRank(2024))->toBeNull();
});
```
(The `finance_settings` row with the company name comes from the migration. If tests use a fresh migration per test (`RefreshDatabase`), it's present; otherwise set it in `beforeEach`.)

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** `app/Market/MarketReport.php`:
```php
<?php

namespace App\Market;

use App\Support\MalaysiaTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Government-market figures from awarded collected tenders (closed, with at least one winner). */
final class MarketReport
{
    /** Winner rows of awarded tenders, optionally in one closing-date year. */
    private function wins(?int $year): Builder
    {
        return DB::table('collected_tender_winners as w')
            ->join('collected_tenders as t', 't.id', '=', 'w.collected_tender_id')
            ->where('t.status', 'closed')
            ->when($year, fn ($q) => $q->whereBetween('t.closing_date', ["{$year}-01-01", "{$year}-12-31"]));
    }

    public function now(): array
    {
        $now = MalaysiaTime::now();
        $today = $now->toDateString();
        $open = DB::table('collected_tenders')->where('status', 'open')
            ->where(fn ($w) => $w->whereNull('closing_date')->orWhere('closing_date', '>', $today)
                ->when($now->format('H:i') < '12:01', fn ($w) => $w->orWhere('closing_date', $today)));

        return [
            'open' => (clone $open)->count(),
            'closing_today' => (clone $open)->where('closing_date', $today)->count(),
            'closing_week' => (clone $open)->whereBetween('closing_date', [$today, $now->addDays(7)->toDateString()])->count(),
        ];
    }

    public function summary(?int $year): array
    {
        $row = $this->wins($year)->selectRaw('COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen,
            COUNT(DISTINCT w.name_key) AS contractors, SUM(w.price_sen IS NULL) AS unpriced')->first();

        return ['tenders' => (int) $row->tenders, 'value_sen' => (int) $row->value_sen, 'contractors' => (int) $row->contractors, 'unpriced' => (int) $row->unpriced];
    }

    public function byYear(): array
    {
        return $this->wins(null)->whereNotNull('t.closing_date')
            ->selectRaw('YEAR(t.closing_date) AS year, COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupByRaw('YEAR(t.closing_date)')->orderByDesc('year')->get()
            ->map(fn ($r) => ['year' => (int) $r->year, 'tenders' => (int) $r->tenders, 'value_sen' => (int) $r->value_sen])->all();
    }

    public function years(): array
    {
        return array_column($this->byYear(), 'year');
    }

    public function byMinistry(?int $year, ?int $limit = null): array
    {
        return $this->wins($year)
            ->selectRaw("COALESCE(NULLIF(t.ministry, ''), 'Not stated') AS ministry, COUNT(DISTINCT w.collected_tender_id) AS tenders, COALESCE(SUM(w.price_sen), 0) AS value_sen")
            ->groupBy('ministry')->orderByDesc('value_sen')->orderBy('ministry')
            ->when($limit, fn ($q) => $q->limit($limit))->get()
            ->map(fn ($r) => ['ministry' => $r->ministry, 'tenders' => (int) $r->tenders, 'value_sen' => (int) $r->value_sen])->all();
    }

    public function contractors(?int $year, string $search = '', ?int $limit = null): Builder
    {
        $key = \App\Collector\ContractorName::key($search);

        return $this->wins($year)
            ->when(mb_strlen($key) >= 2, fn ($q) => $q->where('w.name_key', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%'))
            ->selectRaw('w.name_key, MAX(w.name) AS name, COUNT(DISTINCT w.collected_tender_id) AS wins, COALESCE(SUM(w.price_sen), 0) AS value_sen')
            ->groupBy('w.name_key')->orderByDesc('value_sen')->orderByDesc('wins')->orderBy('w.name_key')
            ->when($limit, fn ($q) => $q->limit($limit));
    }

    public function ownRank(?int $year): ?array
    {
        $own = OwnCompany::keys();
        if ($own === []) {
            return null;
        }
        $mine = $this->wins($year)->whereIn('w.name_key', $own)
            ->selectRaw('COUNT(DISTINCT w.collected_tender_id) AS wins, COALESCE(SUM(w.price_sen), 0) AS value_sen')->first();
        if ((int) $mine->wins === 0) {
            return null;
        }
        $totals = DB::query()->fromSub($this->wins($year)->whereNotIn('w.name_key', $own)
            ->selectRaw('COALESCE(SUM(w.price_sen), 0) AS v')->groupBy('w.name_key'), 'x');
        $ahead = (clone $totals)->where('v', '>', (int) $mine->value_sen)->count();

        return ['rank' => $ahead + 1, 'of' => (clone $totals)->count() + 1, 'wins' => (int) $mine->wins, 'value_sen' => (int) $mine->value_sen];
    }
}
```
Note: the company may own several keys. `ownRank` treats them as one company, and `of` counts the other contractors + 1.

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat: MarketReport — awards by year, ministry and contractor, and our rank`

---

### Task 6: Market Insights page and sidebar

**Files:**
- Create: `app/Livewire/MarketInsights.php`, `resources/views/livewire/market-insights.blade.php`, `app/Livewire/Concerns/HasMarketYear.php`
- Modify: `routes/web.php`, `resources/views/layouts/partials/sidebar.blade.php`
- Test: `tests/Feature/Livewire/MarketInsightsTest.php`, `tests/Feature/LayoutTest.php`

**Interfaces:**
- Consumes:
  - `MarketReport` (Task 5)
  - `OwnCompany` (Task 3)
- Produces:
  - route `market.index` (`/market-insights`)
  - `HasMarketYear` with `#[Url] public string $year`, `marketYear(): ?int`, `$yearNote`
  - the view markers `data-own-rank` and `data-own-extra`

- [ ] **Step 1: Failing tests** (`MarketInsightsTest.php`):
```php
<?php

use App\Livewire\MarketInsights;
use App\Models\{CollectedTender, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function win(string $closing, string $name, int $price): void
{
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => $closing, 'ministry' => 'KEMENTERIAN A', 'winners' => [['name' => $name, 'price_sen' => $price]]]);
}

it('shows the year figures, the trend and our rank, defaulting to the current year', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 04:00:00', 'UTC'));
    win('2026-02-01', '10 CREATIVE SOLUTIONS SDN. BHD.', 420000000);
    win('2025-02-01', 'ACME', 100);
    foreach (range(1, 11) as $i) { win('2026-03-01', "BIG CONTRACTOR {$i}", 900000000 + $i); }

    Livewire::test(MarketInsights::class)
        ->assertSet('year', '2026')
        ->assertSee('Market Insights')->assertSee('Awarded value by year')->assertSee('2025')
        ->assertSeeHtml('data-own-rank')->assertSee('#12 of 12')
        ->assertSeeHtml('data-own-extra')                                   // we're outside the top 10
        ->assertSee(route('find-tenders.index', ['status' => 'awarded', 'ours' => 1, 'from' => '2026-01-01', 'to' => '2026-12-31']));
});

it('falls back to the current year for a year with no awards, and says so', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 04:00:00', 'UTC'));
    win('2026-02-01', 'ACME', 100);

    Livewire::withQueryParams(['year' => '2030'])->test(MarketInsights::class)
        ->assertSet('year', '2026')->assertSee('That year has no awards — showing 2026')
        ->assertSee('No awards in 2026');                                    // our company won nothing
});

it('shows all years when asked', function () {
    win('2025-02-01', 'ACME', 100);
    Livewire::withQueryParams(['year' => 'all'])->test(MarketInsights::class)->assertSee('All years')->assertSee('RM 1.00');
});
```
`LayoutTest.php` (append):
```php
it('lists Market Insights under Insights', function () {
    expect($this->actingAs(User::factory()->create())->get('/dashboard')->getContent())
        ->toContain('data-nav="Market Insights" data-icon="chart"')->toContain(route('market.index'));
});
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement**

`HasMarketYear`:
```php
<?php

namespace App\Livewire\Concerns;

use App\Market\MarketReport;
use App\Support\MalaysiaTime;
use Livewire\Attributes\Url;

/** ?year=all|YYYY for the Market pages; an unknown year falls back to the current one with a note. */
trait HasMarketYear
{
    #[Url] public string $year = '';
    public ?string $yearNote = null;

    protected function marketYear(): ?int
    {
        $current = (int) MalaysiaTime::today()->format('Y');
        if ($this->year === 'all') {
            return null;
        }
        if ($this->year === '') {
            $this->year = (string) $current;
        }
        $years = app(MarketReport::class)->years();
        if (! ctype_digit($this->year) || ! in_array((int) $this->year, $years, true)) {
            $this->yearNote = "That year has no awards — showing {$current}";
            $this->year = (string) $current;
        } else {
            $this->yearNote = null;
        }

        return (int) $this->year;
    }
}
```
(When the current year itself has no awards, the note still shows and the page renders zeros. Record a ruling if behaviour differs.)

`MarketInsights` (`#[Layout('layouts.app')]`, `#[Title('Market Insights')]`, `use HasMarketYear`). `render()` builds:
- `$y = $this->marketYear()`
- `$r = app(MarketReport::class)`
- `'now' => $r->now()`
- `'summary' => $r->summary($y)`
- `'byYear' => $r->byYear()`
- `'ministries' => $r->byMinistry($y, 10)`
- `'top' => $r->contractors($y, '', 10)->get()`
- `'own' => $r->ownRank($y)`
- `'ownKeys' => OwnCompany::keys()`
- `'ownLabel' => OwnCompany::label()`
- `'years' => $r->years()`
- `'range' => $y ? ['from' => "{$y}-01-01", 'to' => "{$y}-12-31"] : []`

View `market-insights.blade.php`:
- `x-page-heading` "Market Insights", subtitle "Government tenders awarded — from the tenders collected in Find Tenders". In actions, `<select wire:model.live="year" class="…" aria-label="Year"><option value="all">All years</option>@foreach ($years as $yy)<option value="{{ $yy }}">{{ $yy }}</option>@endforeach</select>`
- `@if ($yearNote) <p class="text-[13px] text-warn-ink">{{ $yearNote }}</p> @endif`
- "Right now" row: 3 boxes in the Round 2 costing-box style, each an `<a>` to `find-tenders.index` with: (none) / `from=today&to=today` / `from=today&to=today+7`
- "{year label}" row: 3 boxes (Awarded tenders · Awarded value `Money::short` · Contractors), then `@if ($summary['unpriced']) <p class="text-xs text-muted">Excludes {{ number_format($summary['unpriced']) }} awards with no published price</p> @endif`
- `x-card title="{{ $ownLabel }}" icon="award"` with `data-own-rank` when `$own`: "#{{ $own['rank'] }} of {{ number_format($own['of']) }} contractors · {{ $own['wins'] }} wins · {{ Money::short($own['value_sen']) }}", plus a link `btn btn-outline` "See our wins" to `route('find-tenders.index', ['status' => 'awarded', 'ours' => 1, ...$range])`. Otherwise "No awards in {{ $year === 'all' ? 'any year' : $year }}"
- grid of 2 `x-card`s: "Awarded value by year" and "Tenders awarded by year". Each row: label, value, bar `h-2 rounded-full bg-hover` with fill `bg-good-ink` at `value/max*100%`
- `x-card` "Spend by ministry" (icon `chart`) with the top 10 rows (name, value, count, bar); `actions`: `<a href="{{ route('market.ministries', ['year' => $year]) }}" class="btn btn-outline">See all</a>`
- `x-card` "Top contractors" (icon `staff`) with the top 10 rows:
  - "{{ $loop->iteration }}. {{ $c->name }}" linking to `route('find-tenders.index', ['status' => 'awarded', 'contractor' => $c->name, ...$range])`, plus "{{ $c->wins }} wins · {{ Money::short($c->value_sen) }}" and a bar
  - a row whose `name_key` is in `$ownKeys` gets `bg-accent-tint` and an "Ours" pill
  - after the list, `@if ($own && $own['rank'] > 10)` a `data-own-extra` row "… #{{ $own['rank'] }} {{ $ownLabel }}" with wins and value, tinted
  - "See all" → `market.contractors`

Routes (inside the auth group): `Route::get('/market-insights', \App\Livewire\MarketInsights::class)->name('market.index');` plus the two See-all routes in Task 7 (stub them here only if the view needs them; add them in this task pointing at classes created in Task 7, or put the See-all links in Task 7). **Ruling allowed:** add the See-all routes and minimal components in this task, filled out in Task 7.

Sidebar Insights: `[$item(route('status'), 'Status', 'staff', request()->routeIs('status')), $item(route('market.index'), 'Market Insights', 'chart', request()->routeIs('market.*'))]`.

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat: Market Insights page — awards by year, ministry, top contractors and our rank`

---

### Task 7: "See all" pages

**Files:**
- Create: `app/Livewire/MarketMinistries.php`, `app/Livewire/MarketContractors.php`, and their views
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/MarketInsightsTest.php` (append)

**Interfaces:**
- Consumes:
  - `HasMarketYear` (Task 6)
  - `MarketReport` (Task 5)
- Produces:
  - routes `market.ministries` (`/market-insights/ministries`) and `market.contractors` (`/market-insights/contractors`)

- [ ] **Step 1: Failing tests**
```php
it('lists every ministry for the year', function () {
    win('2026-02-01', 'ACME', 100);
    Livewire::withQueryParams(['year' => '2026'])->test(\App\Livewire\MarketMinistries::class)
        ->assertSee('KEMENTERIAN A')->assertSee(route('market.index', ['year' => '2026']));
});

it('lists every contractor with a search and 50 per page, our rows marked', function () {
    foreach (range(1, 55) as $i) { win('2026-02-01', "CONTRACTOR {$i}", $i); }
    win('2026-02-01', '10 CREATIVE SOLUTIONS SDN. BHD.', 1);

    Livewire::withQueryParams(['year' => '2026'])->test(\App\Livewire\MarketContractors::class)
        ->assertSee('Showing 1–50 of 56 contractors')
        ->set('search', 'creative')->assertSee('10 CREATIVE SOLUTIONS SDN. BHD.')->assertSeeHtml('data-ours')->assertDontSee('CONTRACTOR 7');
});
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement:**
  - **`MarketMinistries`** (`use HasMarketYear`): `render()` passes `ministries = byMinistry($y)` (all) plus `years`. The view has a back link `route('market.index', ['year' => $year])`, the year select, and an `x-data-table` of Ministry · Tenders · Value · bar.
  - **`MarketContractors`** (`use HasMarketYear, WithPagination`): add `#[Url] public string $search = ''` and `updated()` → `resetPage()`. `render()` passes:
    - `contractors = $r->contractors($y, $this->search)->paginate(50)`
    - `ownKeys`
    - `range`

    The view has:
    - a back link and the year select
    - a search input, `wire:model.live.debounce.300ms="search"` with the placeholder "Search contractors"
    - an `x-data-table` of # (`$contractors->firstItem() + $loop->index`) · Contractor (linking to the Awarded filter as in Task 6, plus the `data-ours` pill and tint for own rows) · Wins · Value
    - phone cards
    - a footer "Showing a–b of N contractors" plus the pager
  - **Routes:** `/market-insights/ministries` → `MarketMinistries` (`market.ministries`), `/market-insights/contractors` → `MarketContractors` (`market.contractors`).

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat: Market Insights see-all pages for ministries and contractors`

---

### Task 8: README, full suite, backfill on real data, browser and speed check

- [ ] **Step 1: README section** "Market Insights and awarded tenders":
  - what it shows
  - that winners are indexed in `collected_tender_winners`, kept current by the collection
  - **run `php artisan collector:index-winners` once after `collector:import-legacy`** (bulk inserts skip the index)
  - where to set "Our company names" (Finance Settings)
- [ ] **Step 2: Full suite with coverage** → pass, ≥ 80%.
- [ ] **Step 3: On the real local data:**
  - `MSYS_NO_PATHCONV=1 docker compose exec -T app php artisan migrate --force`
  - then `… php artisan collector:index-winners`. Expect about 152k tenders and about 153k+ winners. Record the counts.
  - in tinker: `App\Market\MarketReport` `ownRank(null)['wins']` → 119, and `ownRank(2026)['wins']` → 23 (2025 → 22, 2024 → 9, 2023 → 20)
- [ ] **Step 4: Browser (built-in, signed in as the local test admin):**
  - `/market-insights` (2026, All years, 2023), the See-all pages, and Find Tenders → Awarded, Our wins, a contractor search
  - 1440 / 1024 / 375 widths, no sideways scroll; dark mode screenshot
  - speed: `fetch()` timing of each page < 1000 ms. If a query is slower, add the index that fixes it and record a ruling.
- [ ] **Step 5: Commit** `docs: Market Insights and awarded tenders`
