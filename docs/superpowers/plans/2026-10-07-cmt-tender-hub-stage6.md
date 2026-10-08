# CMT Tender Hub — Stage 6 Implementation Plan (Dashboard and Status)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Dashboard home page (overview cards, deadlines, portfolio mix, PIC summary, quotation and project cards) and a per-PIC Status page, both filtered by the period tenders were registered.

**Architecture:** `ReportPeriod` (pure) turns the page address into a date range. `PipelineReport` runs one grouped database query for all tender figures plus a few small ones (deadlines, due this week, users) and returns plain arrays; `ReportCards` does the quotation and project cards. Two read-only Livewire pages share a period-filter trait and Blade partial. The ring chart is plain SVG.

**Tech Stack:** Laravel 13, Livewire 4.4, Pest 5, MySQL 8.4.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-stage6-design.md` — read first.

## Global Constraints

- Branch `stage-6`. Commit per task; never commit red; the pre-commit hook runs `pest --coverage --min=80`.
- PHP runs only in Docker: `docker compose exec -T app …` (Git Bash: prefix `MSYS_NO_PATHCONV=1`). Write PHP with the editor tool.
- Money = integer sen; percentages = basis points. "Today" = `MalaysiaTime::today()`.
- Period = tenders whose `wo_date` is inside the range, inclusive. Kinds: `all` (default), `this_month`, `last_month`, `this_year`, `month` (`month=YYYY-MM`), `custom` (`from`, `to` as `YYYY-MM-DD`). Invalid → All time with "That period wasn't valid — showing all time."
- Win rate = Awarded ÷ (Awarded + Lost not cancelled); "—" when nothing decided.
- Bid value = submitted price (Done/Awarded/Lost, not cancelled) + estimated value (In Progress); Won value = submitted price of Awarded.
- Everyone signed in sees both pages; nothing on them changes data.
- Plain-English UI copy. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **A cancelled tender** — never in win rate or bid value, but counted under Lost "(1 cancelled)". Pinned in Task 2.
2. **Tenders with no submitted price or estimated value** — count as RM 0 and are reported as "n without a value", not silently hidden. Pinned in Task 2.
3. **Many tenders** — page work does not grow with the number of tenders (fixed query count). Pinned in Task 5.
4. **Odd addresses** (`?period=custom&from=2026-12-01&to=2026-01-01`, `?month=banana`) — fall back to All time with a note, never an error. Pinned in Tasks 1 and 4.
5. **Month edges** — a tender with WO date on the 1st or last day of the month is inside "this month"; the day before is not. Pinned in Task 1.

---

## File Structure

| Path | Responsibility |
|---|---|
| `app/Reports/ReportPeriod.php` | period from the page address |
| `app/Reports/PipelineReport.php` | tender figures, per PIC, deadlines |
| `app/Reports/ReportCards.php` | quotation and project cards |
| `app/Livewire/Concerns/HasReportPeriod.php`, `resources/views/reports/period-filter.blade.php` | shared period filter |
| `app/Livewire/{Dashboard,StatusReport}.php` + views | the two pages |
| Modified: `routes/web.php`, `app/Livewire/Auth/Login.php`, sidebar, `tests/Feature/Auth/LoginTest.php`, README | home page, navigation |

---

### Task 1: ReportPeriod

**Files:** Create `app/Reports/ReportPeriod.php`; Test `tests/Unit/Reports/ReportPeriodTest.php`

**Interfaces — Produces:** `ReportPeriod::fromInput(array $input, CarbonImmutable $today): ReportPeriod` (keys `period, month, from, to`); public readonly `kind` (string), `from` (?string `Y-m-d`), `to` (?string), `invalid` (bool); `label(): string`; `isAllTime(): bool`; `contains(string $date): bool`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Reports\ReportPeriod as P;
use Carbon\CarbonImmutable;

function period(array $in, string $today = '2026-10-15'): P
{
    return P::fromInput($in, CarbonImmutable::parse($today));
}

it('defaults to all time', function () {
    $p = period([]);

    expect($p->kind)->toBe('all')->and($p->from)->toBeNull()->and($p->to)->toBeNull()
        ->and($p->isAllTime())->toBeTrue()->and($p->invalid)->toBeFalse()->and($p->label())->toBe('All time')
        ->and($p->contains('1999-01-01'))->toBeTrue();
});

it('works out the named periods', function (array $in, string $from, string $to, string $label) {
    $p = period($in);

    expect([$p->from, $p->to, $p->label()])->toBe([$from, $to, $label]);
})->with([
    'this month' => [['period' => 'this_month'], '2026-10-01', '2026-10-31', 'This month (Oct 2026)'],
    'last month' => [['period' => 'last_month'], '2026-09-01', '2026-09-30', 'Last month (Sep 2026)'],
    'this year' => [['period' => 'this_year'], '2026-01-01', '2026-12-31', 'This year (2026)'],
    'a month' => [['period' => 'month', 'month' => '2026-02'], '2026-02-01', '2026-02-28', 'Feb 2026'],
    'custom' => [['period' => 'custom', 'from' => '2026-03-05', 'to' => '2026-04-10'], '2026-03-05', '2026-04-10', '05 Mar 2026 – 10 Apr 2026'],
]);

it('counts the first and last day of the month as inside it', function () {
    $p = period(['period' => 'this_month']);

    expect($p->contains('2026-10-01'))->toBeTrue()->and($p->contains('2026-10-31'))->toBeTrue()
        ->and($p->contains('2026-09-30'))->toBeFalse()->and($p->contains('2026-11-01'))->toBeFalse();
});

it('handles last month across a year end', function () {
    expect([period(['period' => 'last_month'], '2026-01-10')->from, period(['period' => 'last_month'], '2026-01-10')->to])
        ->toBe(['2025-12-01', '2025-12-31']);
});

it('falls back to all time for anything it cannot read', function (array $in) {
    $p = period($in);

    expect($p->isAllTime())->toBeTrue()->and($p->invalid)->toBeTrue();
})->with([
    'end before start' => [['period' => 'custom', 'from' => '2026-12-01', 'to' => '2026-01-01']],
    'bad month' => [['period' => 'month', 'month' => 'banana']],
    'bad date' => [['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01']],
    'missing date' => [['period' => 'custom', 'from' => '2026-02-01']],
    'unknown kind' => [['period' => 'forever']],
]);
```

- [ ] **Step 2: Run** `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest tests/Unit/Reports` — Expected: FAIL (class missing).

- [ ] **Step 3: Implement** `app/Reports/ReportPeriod.php`

```php
<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/** The period a report covers, by WO date. Built from the page address; anything unreadable means All time. */
final class ReportPeriod
{
    private function __construct(
        public readonly string $kind,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly bool $invalid = false,
    ) {}

    public static function fromInput(array $input, CarbonImmutable $today): self
    {
        $kind = (string) ($input['period'] ?? 'all');

        return match ($kind) {
            'all', '' => new self('all', null, null),
            'this_month' => self::range('this_month', $today->startOfMonth(), $today->endOfMonth()),
            'last_month' => self::range('last_month', $today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()),
            'this_year' => self::range('this_year', $today->startOfYear(), $today->endOfYear()),
            'month' => self::month((string) ($input['month'] ?? '')),
            'custom' => self::custom((string) ($input['from'] ?? ''), (string) ($input['to'] ?? '')),
            default => self::invalid(),
        };
    }

    private static function range(string $kind, CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($kind, $from->format('Y-m-d'), $to->format('Y-m-d'));
    }

    private static function month(string $month): self
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return self::invalid();
        }
        $start = CarbonImmutable::parse($month.'-01');

        return self::range('month', $start, $start->endOfMonth());
    }

    private static function custom(string $from, string $to): self
    {
        $valid = fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && CarbonImmutable::hasFormat($d, 'Y-m-d')
            && CarbonImmutable::createFromFormat('Y-m-d', $d)->format('Y-m-d') === $d;
        if (! $valid($from) || ! $valid($to) || $to < $from) {
            return self::invalid();
        }

        return new self('custom', $from, $to);
    }

    private static function invalid(): self
    {
        return new self('all', null, null, true);
    }

    public function isAllTime(): bool
    {
        return $this->from === null;
    }

    public function contains(string $date): bool
    {
        return $this->isAllTime() || ($date >= $this->from && $date <= $this->to);
    }

    public function label(): string
    {
        $from = $this->from ? CarbonImmutable::parse($this->from) : null;

        return match ($this->kind) {
            'this_month' => 'This month ('.$from->format('M Y').')',
            'last_month' => 'Last month ('.$from->format('M Y').')',
            'this_year' => 'This year ('.$from->format('Y').')',
            'month' => $from->format('M Y'),
            'custom' => $from->format('d M Y').' – '.CarbonImmutable::parse($this->to)->format('d M Y'),
            default => 'All time',
        };
    }
}
```

- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: report period (all time, months, year, custom)`

---

### Task 2: PipelineReport

**Files:** Create `app/Reports/PipelineReport.php`; Test `tests/Feature/Reports/PipelineReportTest.php`

**Interfaces:**
- Consumes: `ReportPeriod` (Task 1).
- Produces: `PipelineReport::build(ReportPeriod $period, CarbonImmutable $today): array` with keys
  - `counts` → `['in_progress', 'done', 'awarded', 'lost', 'cancelled', 'total']` (ints; `lost` includes cancelled)
  - `due_this_week` (int), `win_rate_bp` (?int), `won` (int), `decided` (int)
  - `bid_value_sen`, `won_value_sen`, `without_value` (ints)
  - `modes` → `['EP' => row, 'NON_EP' => row]` where row = `['in_progress','done','awarded','lost','total','bid_value_sen']`
  - `pics` → list of `['user_id','name','initials','total','in_progress','done','awarded','lost','won','decided','win_rate_bp','bid_value_sen','won_value_sen','share_bp']`, sorted by bid value desc then name
  - `totals` → the same keys summed (`user_id` null, `name` 'Total')
- `PipelineReport::deadlines(CarbonImmutable $today, int $limit = 8): Collection` of In Progress tenders with `closing_date >= today`, soonest first, with `pic`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Enums\{TenderMode, TenderStatus};
use App\Models\{Tender, User};
use App\Reports\{PipelineReport, ReportPeriod};
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

function report(array $period = [], string $today = '2026-10-15'): array
{
    $t = CarbonImmutable::parse($today);

    return app(PipelineReport::class)->build(ReportPeriod::fromInput($period, $t), $t);
}

function tenderFor(User $pic, TenderStatus $status, array $attrs = []): Tender
{
    return Tender::factory()->status($status)->create(array_merge(['pic_id' => $pic->id, 'wo_date' => '2026-10-05',
        'estimated_value_sen' => null, 'submitted_price_sen' => null, 'mode' => TenderMode::Ep], $attrs));
}

it('counts statuses, win rate and values, leaving cancelled out', function () {
    $siti = User::factory()->create(['name' => 'Siti Aisyah']);
    tenderFor($siti, TenderStatus::InProgress, ['estimated_value_sen' => 100000]);
    tenderFor($siti, TenderStatus::Done, ['submitted_price_sen' => 200000]);
    tenderFor($siti, TenderStatus::Awarded, ['submitted_price_sen' => 300000]);
    tenderFor($siti, TenderStatus::Lost, ['submitted_price_sen' => 400000]);
    tenderFor($siti, TenderStatus::Lost, ['submitted_price_sen' => 999999, 'was_cancelled' => true]);

    $r = report();

    expect($r['counts'])->toBe(['in_progress' => 1, 'done' => 1, 'awarded' => 1, 'lost' => 2, 'cancelled' => 1, 'total' => 5])
        ->and([$r['won'], $r['decided'], $r['win_rate_bp']])->toBe([1, 2, 5000])
        ->and($r['bid_value_sen'])->toBe(1000000)      // 1,000 + 2,000 + 3,000 + 4,000; cancelled excluded
        ->and($r['won_value_sen'])->toBe(300000)
        ->and($r['without_value'])->toBe(0);
});

it('shows no win rate before anything is decided, and reports tenders without a value', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::InProgress);
    tenderFor($u, TenderStatus::Done);
    tenderFor($u, TenderStatus::Lost, ['was_cancelled' => true]);

    $r = report();

    expect($r['win_rate_bp'])->toBeNull()->and($r['decided'])->toBe(0)
        ->and($r['without_value'])->toBe(2)->and($r['bid_value_sen'])->toBe(0);
});

it('only counts tenders registered in the period', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::Done, ['wo_date' => '2026-10-01']);
    tenderFor($u, TenderStatus::Done, ['wo_date' => '2026-10-31']);
    tenderFor($u, TenderStatus::Done, ['wo_date' => '2026-09-30']);

    expect(report(['period' => 'this_month'])['counts']['total'])->toBe(2)
        ->and(report(['period' => 'last_month'])['counts']['total'])->toBe(1)
        ->and(report()['counts']['total'])->toBe(3);
});

it('splits EP and non-EP', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::Done, ['submitted_price_sen' => 500, 'mode' => TenderMode::Ep]);
    tenderFor($u, TenderStatus::InProgress, ['estimated_value_sen' => 700, 'mode' => TenderMode::NonEp]);

    $r = report();

    expect($r['modes']['EP'])->toMatchArray(['done' => 1, 'total' => 1, 'bid_value_sen' => 500])
        ->and($r['modes']['NON_EP'])->toMatchArray(['in_progress' => 1, 'total' => 1, 'bid_value_sen' => 700]);
});

it('counts tenders due within the week', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-15']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-22']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-23']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-14']);
    tenderFor($u, TenderStatus::Done, ['closing_date' => '2026-10-16']);

    expect(report()['due_this_week'])->toBe(2);
});

it('lists every active person, zero rows included, and switched-off people only when they have tenders', function () {
    $ahmad = User::factory()->create(['name' => 'Ahmad Faizal']);
    $nurul = User::factory()->create(['name' => 'Nurul Ain']);
    $gone = User::factory()->create(['name' => 'Gone Person', 'is_active' => false]);
    $left = User::factory()->create(['name' => 'Left Early', 'is_active' => false]);
    tenderFor($ahmad, TenderStatus::Awarded, ['submitted_price_sen' => 300]);
    tenderFor($ahmad, TenderStatus::Lost, ['submitted_price_sen' => 100]);
    tenderFor($gone, TenderStatus::Done, ['submitted_price_sen' => 600]);

    $rows = collect(report()['pics'])->keyBy('name');

    expect($rows->keys()->all())->toContain('Ahmad Faizal', 'Nurul Ain', 'Gone Person')->not->toContain('Left Early')
        ->and($rows['Ahmad Faizal'])->toMatchArray(['total' => 2, 'awarded' => 1, 'lost' => 1, 'win_rate_bp' => 5000,
            'bid_value_sen' => 400, 'won_value_sen' => 300, 'share_bp' => 4000, 'initials' => 'AF'])
        ->and($rows['Nurul Ain'])->toMatchArray(['total' => 0, 'win_rate_bp' => null, 'share_bp' => 0])
        ->and(report()['pics'][0]['name'])->toBe('Gone Person')   // biggest bid value first
        ->and(report()['totals'])->toMatchArray(['name' => 'Total', 'total' => 3, 'bid_value_sen' => 1000, 'share_bp' => 10000]);
});

it('lists the soonest deadlines, ignoring the period and past dates', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-20', 'title' => 'Later', 'wo_date' => '2020-01-01']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-16', 'title' => 'Sooner']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-01', 'title' => 'Past']);
    tenderFor($u, TenderStatus::Done, ['closing_date' => '2026-10-17', 'title' => 'Submitted']);

    expect(app(PipelineReport::class)->deadlines(CarbonImmutable::parse('2026-10-15'))->pluck('title')->all())->toBe(['Sooner', 'Later']);
});

it('matches the prototype sample data', function () {
    $this->seed(DatabaseSeeder::class);

    $r = report();

    expect($r['counts'])->toMatchArray(['total' => 41, 'in_progress' => 13, 'done' => 20, 'awarded' => 3, 'lost' => 5])
        ->and([$r['modes']['EP']['total'], $r['modes']['NON_EP']['total']])->toBe([40, 1])
        ->and(collect($r['pics'])->whereIn('name', ['Ahmad Faizal', 'Nurul Ain', 'Siti Aisyah', 'Muhammad Hafiz'])->pluck('total', 'name')->all())
        ->toEqualCanonicalizing(['Ahmad Faizal' => 11, 'Nurul Ain' => 10, 'Siti Aisyah' => 10, 'Muhammad Hafiz' => 10]);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement** `app/Reports/PipelineReport.php`

```php
<?php

namespace App\Reports;

use App\Enums\TenderStatus;
use App\Models\{Tender, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Tender figures for the Dashboard and Status pages, from one grouped query. */
final class PipelineReport
{
    private const STATUSES = ['in_progress', 'done', 'awarded', 'lost'];

    public function build(ReportPeriod $period, CarbonImmutable $today): array
    {
        $rows = Tender::query()
            ->when(! $period->isAllTime(), fn ($q) => $q->whereBetween('wo_date', [$period->from, $period->to]))
            ->groupBy('pic_id', 'mode', 'status', 'was_cancelled')
            ->select('pic_id', 'mode', 'status', 'was_cancelled')
            ->selectRaw('COUNT(*) AS n')
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN COALESCE(estimated_value_sen, 0)
                WHEN was_cancelled = 1 THEN 0 ELSE COALESCE(submitted_price_sen, 0) END) AS bid")
            ->selectRaw("SUM(CASE WHEN status = 'awarded' THEN COALESCE(submitted_price_sen, 0) ELSE 0 END) AS won_value")
            ->selectRaw("SUM(CASE WHEN was_cancelled = 1 THEN 0
                WHEN status = 'in_progress' THEN estimated_value_sen IS NULL ELSE submitted_price_sen IS NULL END) AS no_value")
            ->toBase()->get();

        $blank = fn () => ['in_progress' => 0, 'done' => 0, 'awarded' => 0, 'lost' => 0, 'cancelled' => 0, 'total' => 0,
            'bid_value_sen' => 0, 'won_value_sen' => 0, 'without_value' => 0];
        $all = $blank();
        $modes = ['EP' => $blank(), 'NON_EP' => $blank()];
        $byPic = [];
        foreach ($rows as $r) {
            $status = $r->status instanceof TenderStatus ? $r->status->value : (string) $r->status;
            $mode = is_object($r->mode) ? $r->mode->value : (string) $r->mode;
            foreach ([&$all, &$modes[$mode], &$byPic[$r->pic_id]] as &$bucket) {
                $bucket ??= $blank();
                $bucket[$status] += (int) $r->n;
                $bucket['cancelled'] += $r->was_cancelled ? (int) $r->n : 0;
                $bucket['total'] += (int) $r->n;
                $bucket['bid_value_sen'] += (int) $r->bid;
                $bucket['won_value_sen'] += (int) $r->won_value;
                $bucket['without_value'] += (int) $r->no_value;
            }
            unset($bucket);
        }

        $pics = $this->picRows($byPic, $all['bid_value_sen']);
        $decided = $all['awarded'] + $all['lost'] - $all['cancelled'];

        return [
            'counts' => array_intersect_key($all, array_flip([...self::STATUSES, 'cancelled', 'total'])),
            'due_this_week' => Tender::where('status', TenderStatus::InProgress)
                ->whereBetween('closing_date', [$today->format('Y-m-d'), $today->addDays(7)->format('Y-m-d')])->count(),
            'won' => $all['awarded'],
            'decided' => $decided,
            'win_rate_bp' => self::rate($all['awarded'], $decided),
            'bid_value_sen' => $all['bid_value_sen'],
            'won_value_sen' => $all['won_value_sen'],
            'without_value' => $all['without_value'],
            'modes' => array_map(fn ($m) => array_intersect_key($m, array_flip([...self::STATUSES, 'total', 'bid_value_sen'])), $modes),
            'pics' => $pics,
            'totals' => $this->row(null, 'Total', '', $all, $all['bid_value_sen']),
        ];
    }

    public function deadlines(CarbonImmutable $today, int $limit = 8): Collection
    {
        return Tender::with('pic')->where('status', TenderStatus::InProgress)
            ->where('closing_date', '>=', $today->format('Y-m-d'))
            ->orderBy('closing_date')->orderBy('id')->limit($limit)->get();
    }

    private function picRows(array $byPic, int $grandBid): array
    {
        $users = User::query()->where('is_active', true)->orWhereIn('id', array_keys($byPic))->get(['id', 'name', 'is_active']);
        $rows = $users->map(fn (User $u) => $this->row($u->id, $u->name, $u->initials(), $byPic[$u->id] ?? null, $grandBid))->all();
        usort($rows, fn ($a, $b) => [$b['bid_value_sen'], $a['name']] <=> [$a['bid_value_sen'], $b['name']]);

        return $rows;
    }

    private function row(?int $id, string $name, string $initials, ?array $b, int $grandBid): array
    {
        $b ??= ['in_progress' => 0, 'done' => 0, 'awarded' => 0, 'lost' => 0, 'cancelled' => 0, 'total' => 0, 'bid_value_sen' => 0, 'won_value_sen' => 0];
        $decided = $b['awarded'] + $b['lost'] - $b['cancelled'];

        return [
            'user_id' => $id, 'name' => $name, 'initials' => $initials,
            'total' => $b['total'], 'in_progress' => $b['in_progress'], 'done' => $b['done'], 'awarded' => $b['awarded'], 'lost' => $b['lost'],
            'won' => $b['awarded'], 'decided' => $decided, 'win_rate_bp' => self::rate($b['awarded'], $decided),
            'bid_value_sen' => $b['bid_value_sen'], 'won_value_sen' => $b['won_value_sen'],
            'share_bp' => $grandBid > 0 ? (int) round($b['bid_value_sen'] * 10000 / $grandBid) : 0,
        ];
    }

    private static function rate(int $won, int $decided): ?int
    {
        return $decided > 0 ? (int) round($won * 10000 / $decided) : null;
    }
}
```

Note: `toBase()` returns raw columns, so `status`/`mode` arrive as strings; the `instanceof`/`is_object` guards are defensive. In `build()`, the `foreach (... as &$bucket)` over three references updates all three buckets for each row.

- [ ] **Step 4: Run** `pest tests/Feature/Reports` — Expected: PASS. If the sample check differs, compare against the seed JSON (do not change expected numbers without checking the data).
- [ ] **Step 5: Commit** — `feat: pipeline report (counts, win rate, values, EP split, per PIC, deadlines)`

---

### Task 3: Quotation and project cards

**Files:** Create `app/Reports/ReportCards.php`; Test `tests/Feature/Reports/ReportCardsTest.php`

**Interfaces — Produces:** `ReportCards::quotations(ReportPeriod $p, CarbonImmutable $today): array{open:int, open_total_sen:int, accepted:int, accepted_total_sen:int}`; `ReportCards::projects(): array{running:int, below_margin:int}`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Enums\{PdEntryType, PdGroup, QuotationStatus, TenderStatus};
use App\Models\{PdEntry, PdLine, Project, Quotation, QuotationItem, Tender};
use App\Reports\{ReportCards, ReportPeriod};
use Carbon\CarbonImmutable;

function quote(QuotationStatus $status, string $date, int $priceSen): Quotation
{
    $q = Quotation::factory()->create(['status' => $status, 'quote_date' => $date, 'validity_days' => 30, 'sst_bp' => 0]);
    QuotationItem::factory()->for($q)->create(['quantity' => 1, 'unit_price_sen' => $priceSen]);

    return $q;
}

it('sums open and accepted quotations', function () {
    $today = CarbonImmutable::parse('2026-10-15');
    quote(QuotationStatus::Sent, '2026-10-01', 1000);     // open
    quote(QuotationStatus::Sent, '2026-08-01', 2000);     // expired → not open
    quote(QuotationStatus::Accepted, '2026-10-02', 3000); // accepted in October
    quote(QuotationStatus::Accepted, '2026-09-02', 4000); // accepted in September
    quote(QuotationStatus::Draft, '2026-10-03', 5000);

    expect(app(ReportCards::class)->quotations(ReportPeriod::fromInput(['period' => 'this_month'], $today), $today))
        ->toBe(['open' => 1, 'open_total_sen' => 1000, 'accepted' => 1, 'accepted_total_sen' => 3000])
        ->and(app(ReportCards::class)->quotations(ReportPeriod::fromInput([], $today), $today)['accepted'])->toBe(2);
});

it('counts running projects and those below their approved margin', function () {
    $healthy = Project::factory()->create(['approved_margin_bp' => 1500]);
    $line = PdLine::factory()->for($healthy)->create(['pd_group' => PdGroup::Collection]);
    PdEntry::factory()->for($line, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 100000]);

    $poor = Project::factory()->create(['approved_margin_bp' => 1500]);
    $rev = PdLine::factory()->for($poor)->create(['pd_group' => PdGroup::Collection]);
    $cost = PdLine::factory()->for($poor)->create(['pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($rev, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 100000]);
    PdEntry::factory()->for($cost, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 90000]);

    Project::factory()->create(['closed_at' => now()]);                                                // closed
    Project::factory()->for(Tender::factory()->status(TenderStatus::InProgress))->create();             // tender reopened
    Project::factory()->create(['tender_id' => null, 'quotation_id' => Quotation::factory()->create(['status' => QuotationStatus::Accepted])->id]);

    expect(app(ReportCards::class)->projects())->toBe(['running' => 3, 'below_margin' => 1]);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement** `app/Reports/ReportCards.php`

```php
<?php

namespace App\Reports;

use App\Enums\QuotationStatus;
use App\Models\{Project, Quotation};
use Carbon\CarbonImmutable;

final class ReportCards
{
    public function quotations(ReportPeriod $period, CarbonImmutable $today): array
    {
        $open = Quotation::with('items')->where('status', QuotationStatus::Sent)
            ->whereRaw('DATE_ADD(quote_date, INTERVAL validity_days DAY) >= ?', [$today->format('Y-m-d')])->get();
        $accepted = Quotation::with('items')->where('status', QuotationStatus::Accepted)
            ->when(! $period->isAllTime(), fn ($q) => $q->whereBetween('quote_date', [$period->from, $period->to]))->get();

        return [
            'open' => $open->count(),
            'open_total_sen' => $open->sum(fn (Quotation $q) => $q->totals()['total_sen']),
            'accepted' => $accepted->count(),
            'accepted_total_sen' => $accepted->sum(fn (Quotation $q) => $q->totals()['total_sen']),
        ];
    }

    /** Current state, whatever the period: projects run across months. */
    public function projects(): array
    {
        $running = Project::with(['lines', 'tender', 'quotation'])->whereNull('closed_at')->get()
            ->filter(fn (Project $p) => $p->isActive());

        return [
            'running' => $running->count(),
            'below_margin' => $running->filter(fn (Project $p) => $p->summary()['below_margin'])->count(),
        ];
    }
}
```

- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: quotation and project cards for the dashboard`

---

### Task 4: Dashboard page, period filter, home page

**Files:**
- Create: `app/Livewire/Concerns/HasReportPeriod.php`, `resources/views/reports/period-filter.blade.php`, `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php`
- Modify: `routes/web.php`, `app/Livewire/Auth/Login.php`, `resources/views/layouts/partials/sidebar.blade.php`, `tests/Feature/Auth/LoginTest.php`
- Test: `tests/Feature/Livewire/DashboardTest.php`

**Interfaces — Produces:** route `dashboard` (`/dashboard`); trait `HasReportPeriod` with `#[Url]` properties `period`, `month`, `from`, `to` and `reportPeriod(): ReportPeriod`; partial `reports.period-filter` (expects `$reportPeriod`).

- [ ] **Step 1: Failing test** `tests/Feature/Livewire/DashboardTest.php`

```php
<?php

use App\Enums\{TenderMode, TenderStatus};
use App\Livewire\Dashboard;
use App\Models\{Tender, User};
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow('2026-10-15 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

it('is the home page', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('Quick Overview');
});

it('shows the overview cards with links', function () {
    $u = User::factory()->create(['name' => 'Siti Aisyah']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2026-10-02', 'closing_date' => '2026-10-17', 'estimated_value_sen' => 50000000]);
    Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $u->id, 'wo_date' => '2026-10-03', 'submitted_price_sen' => 170000000]);
    Tender::factory()->status(TenderStatus::Lost)->create(['pic_id' => $u->id, 'wo_date' => '2026-10-04', 'submitted_price_sen' => 1, 'was_cancelled' => true]);

    Livewire::actingAs($u)->test(Dashboard::class)
        ->assertSee('1 due this week')
        ->assertSee('RM 1,700,000.00')          // won value
        ->assertSee('1 (1 cancelled)')
        ->assertSee('100%')->assertSee('1 of 1 decided')
        ->assertSee('RM 2,200,000.00')          // portfolio = 1,700,000 + 500,000
        ->assertSeeHtml(route('tenders.index', 'awarded'));
});

it('filters by the WO month and explains bad periods', function () {
    $u = User::factory()->create();
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2026-10-02', 'title' => 'October tender']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2026-09-02', 'title' => 'September tender']);

    Livewire::actingAs($u)->test(Dashboard::class)
        ->set('period', 'this_month')->assertSee('This month (Oct 2026)')->assertSeeInOrder(['In Progress', '1'])
        ->set('period', 'custom')->set('from', '2026-12-01')->set('to', '2026-01-01')
        ->assertSee("That period wasn't valid — showing all time.");
});

it('lists upcoming deadlines whatever the period', function () {
    $u = User::factory()->create(['name' => 'Ahmad Faizal']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2020-01-01', 'closing_date' => '2026-10-16', 'title' => 'CLOSING TOMORROW']);

    Livewire::actingAs($u)->test(Dashboard::class)->set('period', 'this_month')
        ->assertSee('Upcoming deadlines')->assertSee('CLOSING TOMORROW')->assertSee('AF');
});

it('shows the portfolio mix, PIC summary and the two cards', function () {
    $u = User::factory()->create(['name' => 'Nurul Ain']);
    Tender::factory()->status(TenderStatus::Done)->create(['pic_id' => $u->id, 'mode' => TenderMode::NonEp, 'submitted_price_sen' => 100000]);

    Livewire::actingAs($u)->test(Dashboard::class)
        ->assertSee('Portfolio mix')->assertSee('Non-EP')->assertSeeHtml('<svg')
        ->assertSee('PIC summary')->assertSee('Nurul Ain')->assertSee('Grand total')
        ->assertSee('Quotations')->assertSee('Projects')->assertSee('below approved margin');
});

it('says so when nothing was registered in the period', function () {
    Livewire::actingAs(User::factory()->create())->test(Dashboard::class)->set('period', 'last_month')
        ->assertSee('No tenders registered in this period.')->assertSee('no decided bids yet');
});
```

In `tests/Feature/Auth/LoginTest.php`, change the expected after-login destination `->assertRedirect(route('tenders.index', 'in-progress'));` to `->assertRedirect(route('dashboard'));`.

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Livewire/Concerns/HasReportPeriod.php`:

```php
<?php

namespace App\Livewire\Concerns;

use App\Reports\ReportPeriod;
use App\Support\MalaysiaTime;
use Livewire\Attributes\Url;

/** The period filter shared by the Dashboard and Status pages (kept in the page address). */
trait HasReportPeriod
{
    #[Url] public string $period = 'all';
    #[Url] public string $month = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';

    protected function reportPeriod(): ReportPeriod
    {
        return ReportPeriod::fromInput(['period' => $this->period, 'month' => $this->month, 'from' => $this->from, 'to' => $this->to], MalaysiaTime::today());
    }
}
```

`resources/views/reports/period-filter.blade.php`:

```blade
@php $in = 'rounded-lg border border-line bg-surface px-2 py-1 text-sm'; @endphp
<div class="flex flex-wrap items-center gap-2 text-sm">
    <label class="flex items-center gap-1">Period
        <select wire:model.live="period" class="{{ $in }}" aria-label="Period">
            @foreach (['all' => 'All time', 'this_month' => 'This month', 'last_month' => 'Last month', 'this_year' => 'This year', 'month' => 'A month…', 'custom' => 'Custom…'] as $k => $label)
                <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @if ($period === 'month')
        <input type="month" wire:model.live="month" class="{{ $in }}" aria-label="Month">
    @elseif ($period === 'custom')
        <input type="date" wire:model.live="from" class="{{ $in }}" aria-label="From">
        <span class="text-muted">to</span>
        <input type="date" wire:model.live="to" class="{{ $in }}" aria-label="To">
    @endif
    <span class="text-xs text-muted">{{ $reportPeriod->label() }} · by WO date</span>
</div>
@if ($reportPeriod->invalid && $period !== 'all')
    <p class="mt-1 text-xs text-warn-ink">That period wasn't valid — showing all time.</p>
@endif
```

(When the user is half-way through a custom range — e.g. only `from` filled — the note shows until both dates are valid; that is intended.)

`app/Livewire/Dashboard.php`:

```php
<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasReportPeriod;
use App\Reports\{PipelineReport, ReportCards};
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use HasReportPeriod;

    public function render()
    {
        $today = MalaysiaTime::today();
        $period = $this->reportPeriod();
        $reports = app(PipelineReport::class);

        return view('livewire.dashboard', [
            'reportPeriod' => $period,
            'r' => $reports->build($period, $today),
            'deadlines' => $reports->deadlines($today),
            'quotes' => app(ReportCards::class)->quotations($period, $today),
            'projects' => app(ReportCards::class)->projects(),
            'today' => $today,
        ]);
    }
}
```

`resources/views/livewire/dashboard.blade.php`:

```blade
@php
    use App\Support\{Money, Percent};
    $card = 'rounded-xl border border-line bg-surface p-4';
    $c = $r['counts'];
    $rate = $r['win_rate_bp'] === null ? '—' : Percent::format($r['win_rate_bp'], 0);
    $segments = [
        ['In Progress', $c['in_progress'], 'var(--color-info-ink)'],
        ['Awarded', $c['awarded'], 'var(--color-good-ink)'],
        ['Done', $c['done'], 'var(--color-muted)'],
        ['Lost', $c['lost'], 'var(--color-bad-ink)'],
    ];
    $circumference = 2 * M_PI * 40;
@endphp
<div class="space-y-4">
    <section class="{{ $card }} space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h1 class="text-lg font-semibold">Quick Overview</h1>
                <p class="text-xs text-muted">Every card follows the selected period of WO dates.</p></div>
            <div>@include('reports.period-filter')</div>
        </div>
        @if ($c['total'] === 0)
            <p class="text-sm text-muted">No tenders registered in this period.</p>
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ([
                ['In Progress', $c['in_progress'], $r['due_this_week'].' due this week', route('tenders.index', 'in-progress')],
                ['Awarded', $c['awarded'], Money::format($r['won_value_sen']).' won', route('tenders.index', 'awarded')],
                ['Done', $c['done'], 'awaiting result', route('tenders.index', 'done')],
                ['Lost', $c['lost'].($c['cancelled'] ? ' ('.$c['cancelled'].' cancelled)' : ''), 'incl. cancelled', route('tenders.index', 'lost')],
                ['Win rate', $rate, $r['decided'] ? $r['won'].' of '.$r['decided'].' decided' : 'no decided bids yet', null],
                ['Portfolio value', Money::format($r['bid_value_sen']), $r['without_value'] ? $r['without_value'].' without a value' : 'bid value', null],
            ] as [$label, $value, $hint, $href])
                @php $tag = $href ? 'a' : 'div'; @endphp
                <{{ $tag }} @if ($href) href="{{ $href }}" @endif class="rounded-lg border border-line bg-canvas p-3 {{ $href ? 'hover:bg-hover' : '' }}">
                    <p class="text-xl font-semibold">{{ $value }}</p>
                    <p class="text-sm">{{ $label }}</p>
                    <p class="text-xs text-muted">{{ $hint }}</p>
                </{{ $tag }}>
            @endforeach
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="{{ $card }}">
            <h2 class="font-medium">Upcoming deadlines</h2>
            <p class="mb-2 text-xs text-muted">Live tenders, closing soonest first</p>
            <ul class="divide-y divide-line text-sm">
                @forelse ($deadlines as $t)
                    @php $days = (int) $today->diffInDays($t->closing_date, false); @endphp
                    <li class="flex items-center gap-3 py-2">
                        <x-avatar :user="$t->pic" />
                        <a href="{{ route('tenders.show', $t) }}" class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $t->title }}</p>
                            <p class="truncate text-xs text-muted">{{ $t->client }}</p>
                        </a>
                        <span @class(['whitespace-nowrap text-xs', 'font-medium text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>{{ $t->closing_date->format('d M Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-muted">Nothing closing soon.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $card }}">
            <h2 class="font-medium">Portfolio mix</h2>
            <p class="mb-3 text-xs text-muted">Where every tender sits, and how it is submitted</p>
            <div class="flex flex-wrap items-center gap-6">
                <svg viewBox="0 0 100 100" class="h-32 w-32 -rotate-90" role="img" aria-label="Tenders by status">
                    <circle cx="50" cy="50" r="40" fill="none" stroke="var(--color-subtle)" stroke-width="14" />
                    @php $offset = 0; @endphp
                    @foreach ($segments as [$label, $n, $colour])
                        @if ($n > 0 && $c['total'] > 0)
                            @php $len = $circumference * $n / $c['total']; @endphp
                            <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $colour }}" stroke-width="14"
                                    stroke-dasharray="{{ $len }} {{ $circumference - $len }}" stroke-dashoffset="{{ -$offset }}" />
                            @php $offset += $len; @endphp
                        @endif
                    @endforeach
                </svg>
                <div class="text-center"><p class="text-2xl font-semibold">{{ $c['total'] }}</p><p class="text-xs text-muted">tenders</p></div>
                <ul class="space-y-1 text-sm">
                    @foreach ($segments as [$label, $n, $colour])
                        <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $colour }}"></span>
                            {{ $label }} <span class="text-muted">{{ $n }} ({{ $c['total'] ? round($n * 100 / $c['total']) : 0 }}%)</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach (['EP' => 'EP', 'NON_EP' => 'Non-EP'] as $key => $label)
                    @php $m = $r['modes'][$key]; @endphp
                    <div class="rounded-lg border border-line p-3 text-sm">
                        <div class="flex items-baseline justify-between"><span class="font-medium">{{ $label }} <span class="text-lg">{{ $m['total'] }}</span></span>
                            <span>{{ Money::format($m['bid_value_sen']) }}</span></div>
                        <div class="mt-1 grid grid-cols-4 text-xs text-muted">
                            <span>In Progress<br><b class="text-ink">{{ $m['in_progress'] }}</b></span><span>Done<br><b class="text-ink">{{ $m['done'] }}</b></span>
                            <span>Awarded<br><b class="text-ink">{{ $m['awarded'] }}</b></span><span>Lost<br><b class="text-ink">{{ $m['lost'] }}</b></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="{{ $card }} lg:col-span-2">
            <h2 class="font-medium">PIC summary</h2>
            <p class="mb-2 text-xs text-muted">Tenders and bid value per person in charge</p>
            <div class="relative overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead class="bg-subtle text-left text-xs uppercase text-muted">
                        <tr><th class="px-3 py-2">PIC</th><th class="px-3 py-2 text-right">Tenders</th><th class="px-3 py-2">Share of value</th><th class="px-3 py-2 text-right">Bid value</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($r['pics'] as $p)
                        <tr class="border-t border-line">
                            <td class="px-3 py-2"><a href="{{ route('status') }}" class="hover:underline">{{ $p['name'] }}</a></td>
                            <td class="px-3 py-2 text-right">{{ $p['total'] }}</td>
                            <td class="px-3 py-2"><div class="h-2 w-40 overflow-hidden rounded-full bg-subtle"><div class="h-full bg-good-ink" style="width: {{ $p['share_bp'] / 100 }}%"></div></div></td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">{{ Money::format($p['bid_value_sen']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-line font-semibold">
                        <td class="px-3 py-2">Grand total</td><td class="px-3 py-2 text-right">{{ $r['totals']['total'] }}</td><td></td>
                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ Money::format($r['totals']['bid_value_sen']) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="space-y-4">
            <a href="{{ route('quotations.index') }}" class="{{ $card }} block hover:bg-hover">
                <h2 class="font-medium">Quotations</h2>
                <p class="mt-1 text-sm"><b>{{ $quotes['open'] }}</b> open · {{ Money::format($quotes['open_total_sen']) }}</p>
                <p class="text-sm"><b>{{ $quotes['accepted'] }}</b> accepted in the period · {{ Money::format($quotes['accepted_total_sen']) }}</p>
            </a>
            <a href="{{ route('tenders.index', 'awarded') }}" class="{{ $card }} block hover:bg-hover">
                <h2 class="font-medium">Projects</h2>
                <p class="mt-1 text-sm"><b>{{ $projects['running'] }}</b> running</p>
                <p @class(['text-sm', 'text-bad-ink' => $projects['below_margin'] > 0])><b>{{ $projects['below_margin'] }}</b> below approved margin</p>
                <p class="text-xs text-muted">Current state — not affected by the period.</p>
            </a>
        </div>
    </div>
</div>
```

Routes — replace `Route::redirect('/', '/tenders/in-progress');` with:

```php
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
```

`Login.php` — `redirectIntended(route('tenders.index', 'in-progress'))` → `redirectIntended(route('dashboard'))`.

Sidebar — in `$groups['Operations']`, put first:

```php
            ['href' => route('dashboard'), 'label' => 'Dashboard', 'count' => null, 'active' => request()->routeIs('dashboard')],
```
and change the logo link `route('tenders.index', 'in-progress')` to `route('dashboard')`.

- [ ] **Step 4: Run** `pest tests/Feature/Livewire/DashboardTest.php tests/Feature/Auth tests/Feature/LayoutTest.php` — Expected: PASS. (`LayoutTest` asserts `assertDontSee('Dashboard')` — the sidebar now shows it; change that line to `->assertSee(route('dashboard'))` and note it in the ledger.)
- [ ] **Step 5: Commit** — `feat: dashboard home page with period filter`

---

### Task 5: Status page and fixed query count

**Files:** Create `app/Livewire/StatusReport.php`, `resources/views/livewire/status-report.blade.php`; Modify `routes/web.php`, sidebar; Test `tests/Feature/Livewire/StatusReportTest.php`

**Interfaces — Produces:** route `status` (`/status`); `StatusReport` with `#[Url] sort` (one of `name,total,in_progress,done,awarded,lost,win_rate_bp,bid_value_sen,won_value_sen`, default `bid_value_sen`) and `#[Url] dir` (`asc|desc`, default `desc`); `sortBy(string $column)`.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Enums\TenderStatus;
use App\Livewire\{Dashboard, StatusReport};
use App\Models\{Tender, User};
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('shows per-PIC performance with totals', function () {
    $ahmad = User::factory()->create(['name' => 'Ahmad Faizal']);
    $nurul = User::factory()->create(['name' => 'Nurul Ain']);
    Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $ahmad->id, 'submitted_price_sen' => 500000]);
    Tender::factory()->status(TenderStatus::Lost)->create(['pic_id' => $ahmad->id, 'submitted_price_sen' => 100000]);

    Livewire::actingAs($nurul)->test(StatusReport::class)
        ->assertSee('Per-PIC tender performance')
        ->assertSeeInOrder(['Ahmad Faizal', '2', '1', '1', '50%', 'RM 6,000.00', 'RM 5,000.00'])
        ->assertSee('Nurul Ain')->assertSee('Total')
        ->assertSeeHtml(e(route('tenders.index', ['awarded', 'pic' => $ahmad->id])));
});

it('sorts by any column and back', function () {
    User::factory()->create(['name' => 'Zara']);
    $a = User::factory()->create(['name' => 'Aminah']);
    Tender::factory()->status(TenderStatus::Done)->create(['pic_id' => $a->id, 'submitted_price_sen' => 100]);

    Livewire::actingAs($a)->test(StatusReport::class)
        ->assertSeeInOrder(['Aminah', 'Zara'])
        ->call('sortBy', 'name')->assertSet('dir', 'asc')->assertSeeInOrder(['Aminah', 'Zara'])
        ->call('sortBy', 'name')->assertSet('dir', 'desc')->assertSeeInOrder(['Zara', 'Aminah'])
        ->call('sortBy', 'nonsense')->assertSet('sort', 'bid_value_sen');
});

it('uses the period filter', function () {
    $u = User::factory()->create(['name' => 'Siti Aisyah']);
    Tender::factory()->create(['pic_id' => $u->id, 'wo_date' => '2001-01-01']);

    Livewire::actingAs($u)->test(StatusReport::class)->set('period', 'month')->set('month', '2001-01')
        ->assertSee('Jan 2001')->assertSeeInOrder(['Siti Aisyah', '1']);
});

it('is in the sidebar and reachable', function () {
    $this->actingAs(User::factory()->create())->get(route('status'))->assertOk()->assertSee(route('status'));
});

it('does not run more queries as tenders pile up', function () {
    $u = User::factory()->create();
    $count = function () use ($u) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($u)->test(Dashboard::class);
        Livewire::actingAs($u)->test(StatusReport::class);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };
    Tender::factory()->count(3)->create(['pic_id' => $u->id]);
    $few = $count();
    Tender::factory()->count(30)->create(['pic_id' => User::factory()->create()->id]);

    expect($count())->toBe($few);
});
```

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement**

`app/Livewire/StatusReport.php`:

```php
<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasReportPeriod;
use App\Reports\PipelineReport;
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Status')]
class StatusReport extends Component
{
    use HasReportPeriod;

    public const COLUMNS = ['name', 'total', 'in_progress', 'done', 'awarded', 'lost', 'win_rate_bp', 'bid_value_sen', 'won_value_sen'];

    #[Url] public string $sort = 'bid_value_sen';
    #[Url] public string $dir = 'desc';

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::COLUMNS, true)) {
            $this->reset('sort', 'dir');

            return;
        }
        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : ($this->sort === $column ? 'asc' : ($column === 'name' ? 'asc' : 'desc'));
        $this->sort = $column;
    }

    public function render()
    {
        $period = $this->reportPeriod();
        $report = app(PipelineReport::class)->build($period, MalaysiaTime::today());
        $sort = in_array($this->sort, self::COLUMNS, true) ? $this->sort : 'bid_value_sen';
        $rows = collect($report['pics'])->sortBy([[$sort, $this->dir === 'asc' ? 'asc' : 'desc'], ['name', 'asc']])->values();

        return view('livewire.status-report', ['reportPeriod' => $period, 'rows' => $rows, 'totals' => $report['totals']]);
    }
}
```

Note on `sortBy` toggling: first click on a new column sorts it (name ascending, numbers descending); clicking the same column again flips the direction. The test's first `sortBy('name')` from the default yields `asc`; the second flips to `desc`.

`resources/views/livewire/status-report.blade.php`:

```blade
@php
    use App\Support\{Money, Percent};
    $cols = ['name' => 'PIC', 'total' => 'Total', 'in_progress' => 'In Progress', 'done' => 'Done', 'awarded' => 'Awarded', 'lost' => 'Lost',
        'win_rate_bp' => 'Win rate', 'bid_value_sen' => 'Bid value', 'won_value_sen' => 'Won value'];
    $lists = ['in_progress' => 'in-progress', 'done' => 'done', 'awarded' => 'awarded', 'lost' => 'lost'];
    $cell = fn (array $row, string $k) => match ($k) {
        'win_rate_bp' => $row[$k] === null ? '—' : Percent::format($row[$k], 0),
        'bid_value_sen', 'won_value_sen' => Money::format($row[$k]),
        default => $row[$k],
    };
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-xl font-semibold">Status</h1><p class="text-sm text-muted">Per-PIC tender performance</p></div>
        <div>@include('reports.period-filter')</div>
    </div>
    <div class="relative overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[860px] text-sm">
            <thead class="bg-subtle text-xs uppercase text-muted">
                <tr>
                    @foreach ($cols as $k => $label)
                        <th @class(['px-3 py-2', 'text-left' => $k === 'name', 'text-right' => $k !== 'name'])>
                            <button type="button" wire:click="sortBy('{{ $k }}')" class="uppercase hover:text-ink">{{ $label }}
                                @if ($sort === $k) {{ $dir === 'asc' ? '↑' : '↓' }} @endif</button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr class="border-t border-line">
                    @foreach ($cols as $k => $label)
                        <td @class(['whitespace-nowrap px-3 py-2', 'text-right' => $k !== 'name'])>
                            @if (isset($lists[$k]) && $row[$k] > 0)
                                <a href="{{ route('tenders.index', [$lists[$k], 'pic' => $row['user_id']]) }}" class="underline">{{ $row[$k] }}</a>
                            @else
                                {{ $cell($row, $k) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="border-t border-line font-semibold">
                @foreach ($cols as $k => $label)
                    <td @class(['whitespace-nowrap px-3 py-2', 'text-right' => $k !== 'name'])>{{ $cell($totals, $k) }}</td>
                @endforeach
            </tr>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-muted">Win rate = Awarded ÷ (Awarded + Lost), cancelled tenders left out. Bid value uses the submitted price, or the estimated value for tenders still in progress.</p>
</div>
```

Routes (auth group): `Route::get('/status', \App\Livewire\StatusReport::class)->name('status');`

Sidebar — add a group after `'Quotation'`:

```php
        'Insights' => [
            ['href' => route('status'), 'label' => 'Status', 'count' => null, 'active' => request()->routeIs('status')],
        ],
```

- [ ] **Step 4: Run** `pest tests/Feature/Livewire tests/Feature/Reports` — Expected: PASS.
- [ ] **Step 5: Commit** — `feat: per-PIC Status page`

---

### Task 6: Docs and walkthrough

**Files:** Modify `README.md`

- [ ] **Step 1:** README — add after "Quotations":

```markdown
## Dashboard and Status

- The **Dashboard** is the home page: overview cards, upcoming deadlines, portfolio mix (by status and
  EP / Non-EP), PIC summary, and Quotations / Projects cards.
- **Status** (Insights) shows each PIC's tenders, win rate, bid value and won value; click a column to sort.
- Both follow a period filter by **WO date** (All time, this/last month, this year, a month, custom).
- Win rate = Awarded ÷ (Awarded + Lost), cancelled left out. Bid value = submitted price, or the estimated
  value while a tender is in progress. Figures live in `app/Reports/`.
```

- [ ] **Step 2: Run the full suite with coverage** — Expected: PASS ≥ 80%.
- [ ] **Step 3: Browser walkthrough** (Playwright MCP): sign-in lands on the Dashboard; each card links to its list; period filter (this month, a month, custom, invalid custom shows the note); deadlines red within 3 days; ring chart and EP/Non-EP; PIC summary; Quotations/Projects cards; Status sorting and number links into the filtered tender list; dark mode; 375px (no sideways page scroll).
- [ ] **Step 4: Commit** — `docs: dashboard and status`

---

## Self-review notes (spec coverage)

| Spec section | Task |
|---|---|
| §2 period kinds, invalid fallback, Malaysia days | 1, 4 |
| §2 counts, due this week, win rate, bid/won value, without value, EP split, per PIC | 2 |
| §2 quotation and project cards | 3 |
| §3 Dashboard (overview, deadlines, mix, PIC summary, cards), home page, sidebar, empty states | 4 |
| §3 Status (table, totals, sorting, links) | 5 |
| §4 permissions (everyone signed in) | 4, 5 (routes in the auth group) |
| §5 fixed query count | 5 |
| §6 testing incl. prototype sample check | 2–5 |
