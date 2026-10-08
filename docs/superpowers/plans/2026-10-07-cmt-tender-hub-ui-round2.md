# UI Round 2 (remaining screens in the prototype style) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the tender page and its tabs, Quotations, all dialogs, the settings pages, the Find Tenders detail page and the sign-in pages to match the prototype. Add Bulk Add Documents, and quotation status counts plus a date sort.

**Architecture:**
- New shared Blade components: `x-dialog`, `x-status-pill`, `x-fact`, `x-card`, `x-tabs`/`x-tab`, plus button classes. Each screen is then rebuilt from these and the Round 1 parts.
- Only two behaviours are new:
  - `AddDocuments` (bulk add)
  - `QuotationListQuery::counts()` and a `sort` setting
- `GenerateWoNumber` gains a read-only `peek()` for the Register preview.

**Tech Stack:** Laravel 13, Livewire 4.4 (Alpine), Tailwind v4, Pest 5. PHP runs only in Docker: from `C:\Projects\cmt-tender-hub` run `MSYS_NO_PATHCONV=1 docker compose exec -T app …`.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-ui-round2-design.md`. Prototype reference: `docs/superpowers/plans/assets/prototype-ui/markup.html`:
- tender detail 857–1519
- quotations 255–603
- dialogs 1519–1704

## Global Constraints

- Reuse the Round 1 tokens and components (`x-icon`, `x-page-heading`, `x-filter-bar`, `x-filter-field`, `x-data-table`, `x-initials`, `x-avatar`, pager, `$th`/`$td` table classes). Colours come only from the tokens.
- The Round 1 table classes are:
  - header cell `px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted` inside `<thead class="bg-subtle">`
  - body cell `px-3.5 py-3 align-top`
  - rows `border-t border-line hover:bg-subtle`
- The Round 1 input class is `w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]`.
- No sideways page scroll from 360px to 1920px; light and dark mode.
- UI copy in plain English.
- **Nothing behind the screens changes** apart from `AddDocuments`, quotation counts and sort, and `GenerateWoNumber::peek()`. Every existing behaviour test must pass unchanged. Wording-only test edits get a ledger ruling.
- Tests first (RED → GREEN), commit after green, coverage ≥ 80%.
- Write PHP/Blade with the Edit/Write tools, not sed.
- Restyling a view: keep every `wire:*`, `@can`, `@if` condition, form field name and route exactly. Change only classes and wrapper markup. Re-read the whole view before editing it.

## Review Focus

1. **Bulk Add on a tender locked by Mark Done in another tab:** the user gets the locked message, nothing is added, and the version is unchanged. Pinned in Task 2.
2. **The Register Tender WO preview while someone else registers:** the preview never consumes a number, and both people still get unique numbers. Pinned in Task 4.
3. **Quotation status counts while a search is typed:** the counts match the list you'd get by clicking that status. Pinned in Task 7.
4. **Dialogs on a phone (375px):** the dialog fits, the textarea and buttons are reachable, with no sideways scroll. Pinned in the Task 10 browser check.
5. **The tender page for a Dropped or cancelled tender:** the header pill, key facts and reason rows are right, and Done-only buttons are absent. Pinned in Task 3.

---

## File structure

| File | Responsibility |
|---|---|
| `resources/css/app.css` | `btn`, `btn-primary`, `btn-dark`, `btn-outline`, `btn-danger` in `@layer components` |
| `resources/views/components/{dialog,status-pill,fact,card,tabs,tab}.blade.php` (create) | shared parts |
| `resources/views/components/status-badge.blade.php` (modify) | thin wrapper over `status-pill` (kept for existing callers) |
| `app/Actions/Tenders/AddDocuments.php` (create) | bulk add |
| `app/Actions/Tenders/GenerateWoNumber.php` (modify) | `peek()` |
| `app/Quotations/QuotationListQuery.php` (modify) | `counts()`, sort |
| `app/Livewire/{TenderDetail,QuotationList,QuotationPage,RegisterTenderModal}.php` (modify) | bulk add, counts/sort, saved event, preview |
| views under `resources/views/livewire/…`, `layouts/guest.blade.php` (modify) | restyles |

---

### Task 1: Shared parts — buttons, status pill, fact, card, tabs, dialog

**Files:**
- Modify: `resources/css/app.css`, `resources/views/components/status-badge.blade.php`
- Create: `resources/views/components/status-pill.blade.php`, `fact.blade.php`, `card.blade.php`, `tabs.blade.php`, `tab.blade.php`, `dialog.blade.php`
- Test: `tests/Feature/Components/PagePartsTest.php` (append)

**Interfaces:**
- Produces:
  - `<x-status-pill :status="TenderStatus|string">` (a string is a quotation status: draft, sent, expired, accepted, rejected, revised); prints `data-status="<value>"`
  - `<x-fact label="…">value</x-fact>`
  - `<x-card title="…" subtitle="…" icon="…">` (all optional)
  - `<x-tabs>` containing `<x-tab :active="bool" wire:click="…">Label</x-tab>`
  - `<x-dialog title="…" subtitle="…" close="closeModal">body<x-slot:actions>…</x-slot:actions></x-dialog>`
  - CSS classes `btn btn-primary|btn-dark|btn-outline|btn-danger`

- [ ] **Step 1: Write the failing tests** (append):
```php
it('shows each tender and quotation status as a coloured pill with a dot', function ($status, string $label) {
    $html = Blade::render('<x-status-pill :status="$s" />', ['s' => $status]);
    expect($html)->toContain('data-status=')->toContain('rounded-full')->toContain('h-1.5 w-1.5 rounded-full')->toContain($label);
})->with([
    [\App\Enums\TenderStatus::InProgress, 'In Progress'], [\App\Enums\TenderStatus::Dropped, 'Dropped'],
    ['sent', 'Sent'], ['expired', 'Expired'], ['revised', 'Revised'],
]);

it('prints a labelled fact, a titled card and tabs', function () {
    expect(Blade::render('<x-fact label="Tender Code">QT1</x-fact>'))->toContain('Tender Code')->toContain('QT1')
        ->and(Blade::render('<x-card title="Scope of Work" icon="tenders">Body</x-card>'))->toContain('Scope of Work')->toContain('<svg')->toContain('Body')
        ->and(Blade::render('<x-tabs><x-tab :active="true">Overview</x-tab><x-tab :active="false">Costing</x-tab></x-tabs>'))
            ->toContain('role="tablist"')->toContain('aria-selected="true"')->toContain('aria-selected="false"');
});

it('frames a dialog with a title, explanation, body and actions', function () {
    $html = Blade::render('<x-dialog title="Drop tender" subtitle="Why it matters" close="closeModal">BODY<x-slot:actions><button>Go</button></x-slot:actions></x-dialog>');
    expect($html)->toContain('role="dialog"')->toContain('Drop tender')->toContain('Why it matters')->toContain('BODY')
        ->toContain('<button>Go</button>')->toContain('wire:click="closeModal"');
});
```

- [ ] **Step 2: Run them and watch them fail.**

Run: `… ./vendor/bin/pest tests/Feature/Components/PagePartsTest.php`
Expected: FAIL. The component `status-pill` cannot be found.

- [ ] **Step 3: Implement**

`app.css`, after the `[x-cloak]` line:
```css
@layer components {
    .btn { @apply inline-flex items-center justify-center gap-1.5 rounded-[11px] px-4 py-2.5 text-[12.5px] font-bold whitespace-nowrap disabled:opacity-50; }
    .btn-primary { @apply bg-accent text-accent-ink hover:bg-accent-solid; }
    .btn-dark { @apply bg-chip text-chip-ink hover:bg-chip-hover; }
    .btn-outline { @apply border border-line-2 bg-surface text-ink-2 hover:bg-hover; }
    .btn-danger { @apply bg-bad-ink text-white hover:opacity-90; }
}
```

`status-pill.blade.php`:
```blade
@props(['status'])
@php
    $value = $status instanceof \App\Enums\TenderStatus ? $status->value : (string) $status;
    $label = $status instanceof \App\Enums\TenderStatus ? $status->label() : ucfirst($value);
    [$bg, $ink] = match ($value) {
        'in_progress', 'sent' => ['bg-info-bg', 'text-info-ink'],
        'awarded', 'accepted' => ['bg-good-bg', 'text-good-ink'],
        'lost', 'rejected', 'expired' => ['bg-bad-bg', 'text-bad-ink'],
        'done', 'draft' => ['bg-hover', 'text-muted'],
        default => ['bg-hover', 'text-muted-2'], // dropped, revised
    };
@endphp
<span data-status="{{ $value }}" {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold {$bg} {$ink}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $label }}
</span>
```

`status-badge.blade.php` (kept so existing callers and tests keep working):
```blade
@props(['status'])
<x-status-pill :status="$status" {{ $attributes }} />
```

`fact.blade.php`:
```blade
@props(['label'])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">{{ $label }}</div>
    <div class="mt-0.5 truncate text-[13px] font-semibold">{{ $slot }}</div>
</div>
```

`card.blade.php`:
```blade
@props(['title' => null, 'subtitle' => null, 'icon' => null])
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-[20px] border border-line bg-surface p-5']) }}>
    @if ($title)
        <div class="mb-4 flex items-center gap-3">
            @if ($icon) <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-accent-tint text-good-ink"><x-icon :name="$icon" class="h-[17px] w-[17px]" /></span> @endif
            <div class="min-w-0 flex-1"><h2 class="font-bold">{{ $title }}</h2>@if ($subtitle)<p class="text-xs text-muted">{{ $subtitle }}</p>@endif</div>
            @isset($actions) <div class="flex shrink-0 gap-2">{{ $actions }}</div> @endisset
        </div>
    @endif
    {{ $slot }}
</section>
```

`tabs.blade.php` and `tab.blade.php`:
```blade
<nav role="tablist" {{ $attributes->merge(['class' => 'flex gap-1 overflow-x-auto border-b border-line text-[13px]']) }}>{{ $slot }}</nav>
```
```blade
@props(['active' => false])
<button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    '-mb-px whitespace-nowrap border-b-2 px-3.5 py-2.5 font-semibold',
    'border-ink text-ink' => $active, 'border-transparent text-muted hover:text-ink' => ! $active,
]) }}>{{ $slot }}</button>
```

`dialog.blade.php`:
```blade
@props(['title', 'subtitle' => null, 'close' => 'closeModal', 'wide' => false])
<div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-3 sm:items-center sm:p-4" x-data @keydown.escape.window="$wire.{{ $close }}()">
    <div class="absolute inset-0" wire:click="{{ $close }}"></div>
    <div role="dialog" aria-modal="true" aria-label="{{ $title }}"
         @class(['relative max-h-[92vh] w-full space-y-4 overflow-y-auto rounded-[20px] bg-surface p-6 shadow-xl', 'max-w-lg' => ! $wide, 'max-w-3xl' => $wide])>
        <div>
            <h2 class="text-lg font-extrabold tracking-tight">{{ $title }}</h2>
            @if ($subtitle) <p class="mt-1 text-[13px] text-muted">{{ $subtitle }}</p> @endif
        </div>
        {{ $slot }}
        @isset($actions)
            <div class="flex flex-wrap justify-between gap-2 pt-1">
                <button type="button" wire:click="{{ $close }}" class="btn btn-outline">Cancel</button>
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            </div>
        @endisset
    </div>
</div>
```

- [ ] **Step 4: Run them and watch them pass.** Run: `… ./vendor/bin/pest tests/Feature/Components tests/Feature/Livewire` → PASS.

- [ ] **Step 5: Commit**
```bash
git add resources/css/app.css resources/views/components tests/Feature/Components/PagePartsTest.php
git commit -m "feat(ui): shared dialog, status pill, fact, card, tabs and button styles"
```

---

### Task 2: Bulk Add Documents and the Documents tab

**Files:**
- Create: `app/Actions/Tenders/AddDocuments.php`
- Modify: `app/Livewire/TenderDetail.php`, `resources/views/livewire/tender-detail/documents.blade.php`, `resources/views/livewire/tender-detail/modals.blade.php`
- Test: `tests/Feature/Actions/DocumentActionsTest.php`, `tests/Feature/Livewire/TenderDetailTest.php` (append)

**Interfaces:**
- Consumes:
  - `x-dialog`, `x-card`, `btn` (Task 1)
- Produces:
  - `AddDocuments::handle(User $actor, Tender $tender, int $expectedVersion, string $text): array{0: Tender, 1: int}`
  - `TenderDetail::$bulkDocuments` (string), `TenderDetail::bulkAddDocuments()`, `TenderDetail::$notice` (?string)
  - modal key `bulk-docs`

- [ ] **Step 1: Write the failing tests**

`DocumentActionsTest.php` (append; use that file's existing setup style for a PIC and tender):
```php
it('adds several documents at once, skipping blanks and names already there', function () {
    $pic = \App\Models\User::factory()->create();
    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);
    $t->documents()->create(['name' => 'Company Profile', 'position' => 1]);

    [$fresh, $added] = app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $t, $t->version,
        "Site Visit Report\n\n  company profile \nInsurance Certificate\nSite visit report\n".str_repeat('x', 300));

    expect($added)->toBe(3)
        ->and($fresh->documents()->pluck('name')->all())->toBe(['Company Profile', 'Site Visit Report', 'Insurance Certificate', str_repeat('x', 255)])
        ->and($fresh->version)->toBe($t->version + 1)
        ->and($fresh->activity()->first()->description)->toStartWith('Added 3 documents: Site Visit Report, Insurance Certificate');
});

it('changes nothing when there is nothing new to add', function () {
    $pic = \App\Models\User::factory()->create();
    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);

    [$fresh, $added] = app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $t, $t->version, "\n  \n");

    expect($added)->toBe(0)->and($fresh->version)->toBe($t->version)->and($fresh->activity()->count())->toBe(0);
});

it('refuses bulk add on a locked tender, for strangers, and on an old version', function () {
    $pic = \App\Models\User::factory()->create();
    $done = \App\Models\Tender::factory()->status(\App\Enums\TenderStatus::Done)->create(['pic_id' => $pic->id]);
    expect(fn () => app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $done, $done->version, 'A'))
        ->toThrow(\App\Exceptions\InvalidTenderTransition::class);

    $t = \App\Models\Tender::factory()->create(['pic_id' => $pic->id]);
    expect(fn () => app(\App\Actions\Tenders\AddDocuments::class)->handle(\App\Models\User::factory()->create(), $t, $t->version, 'A'))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class)
        ->and(fn () => app(\App\Actions\Tenders\AddDocuments::class)->handle($pic, $t, $t->version + 3, 'A'))
        ->toThrow(\App\Exceptions\StaleTenderException::class);
});
```
`TenderDetailTest.php` (append):
```php
it('bulk-adds documents from the Documents tab and says how many', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender, 'tab' => 'documents'])
        ->assertSee('Bulk Add')
        ->call('openModal', 'bulk-docs')->set('bulkDocuments', "Warranty Letter\nInsurance Certificate")->call('bulkAddDocuments')
        ->assertSee('Added 2 documents')->assertSee('Warranty Letter');
});
```

- [ ] **Step 2: Run them and watch them fail.**

Run: `… ./vendor/bin/pest tests/Feature/Actions/DocumentActionsTest.php tests/Feature/Livewire/TenderDetailTest.php`
Expected: FAIL. The class `AddDocuments` does not exist.

- [ ] **Step 3: Implement** `app/Actions/Tenders/AddDocuments.php`:
```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

/** Bulk Add: one document name per line; blanks and names already on the checklist are skipped. */
final class AddDocuments
{
    use GuardsTender;

    /** @return array{0: Tender, 1: int} the fresh tender and how many documents were added */
    public function handle(User $actor, Tender $tender, int $expectedVersion, string $text): array
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $text) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $seen = $t->documents()->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip()->all();
            $names = [];
            foreach (preg_split('/\R/u', $text) as $line) {
                $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $line)), 0, 255);
                if ($name === '' || isset($seen[mb_strtolower($name)])) {
                    continue;
                }
                $seen[mb_strtolower($name)] = true;
                $names[] = $name;
            }
            if ($names === []) {
                return [$t, 0];
            }

            $position = (int) $t->documents()->max('position');
            foreach ($names as $name) {
                $t->documents()->create(['name' => $name, 'position' => ++$position]);
            }
            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'documents_added', 'Added '.count($names).' documents: '.implode(', ', $names));

            return [$t->fresh(), count($names)];
        });
    }
}
```

`TenderDetail.php`:
- add `public string $bulkDocuments = '';` and `public ?string $notice = null;`
- add `AddDocuments` to the actions import
- add the method:
```php
    public function bulkAddDocuments(): void
    {
        $this->validate(['bulkDocuments' => ['required', 'string', 'max:20000']]);
        $added = 0;
        if ($this->apply(function () use (&$added) {
            [$fresh, $added] = app(AddDocuments::class)->handle(auth()->user(), $this->tender, $this->version, $this->bulkDocuments);

            return $fresh;
        })) {
            $this->bulkDocuments = '';
            $this->notice = $added ? "Added {$added} documents" : 'Nothing new to add';
        }
    }
```
(`apply` closes the modal after success; check how it does so and follow it.)

`documents.blade.php`: rebuild as an `x-card`:
- header line "Assigned to {PIC} (PIC)"
- a progress bar (`h-1.5 rounded-full bg-hover` with a `bg-good-ink` fill at `documentPercent()`) and "done / total done"
- the `$notice` (`text-good-ink text-sm`) when set
- the list with prototype tick boxes (`h-4 w-4 accent-[var(--accent-solid)]`)
- when editable: the add form (Round 1 input and `btn btn-outline`) plus `<button wire:click="openModal('bulk-docs')" class="btn btn-outline">Bulk Add</button>`
- when locked: the banner "This tender is {status} — documents and costing are locked from further edits." in `bg-subtle text-muted rounded-xl px-3 py-2`

`modals.blade.php`: add the `bulk-docs` case. It's converted to `x-dialog` with the rest in Task 4; until then add it in the same style as the existing cases:
```blade
                @case('bulk-docs')
                    <h2 class="text-lg font-semibold">Bulk Add Documents</h2>
                    <p class="text-sm text-muted">Type one document name per line. Names already on the checklist are skipped.</p>
                    <textarea wire:model="bulkDocuments" rows="7" placeholder="Site Visit Report&#10;Insurance Certificate&#10;Warranty Letter" class="{{ $input }}"></textarea>
                    @error('bulkDocuments') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    @php $confirm = ['bulkAddDocuments', 'Add documents']; @endphp
                    @break
```
`openModal` authorises `update` for any name other than `reopen`, so `bulk-docs` is covered.

- [ ] **Step 4: Run them and watch them pass.** Same command → PASS.

- [ ] **Step 5: Commit**
```bash
git add app/Actions/Tenders/AddDocuments.php app/Livewire/TenderDetail.php resources/views/livewire/tender-detail tests/Feature
git commit -m "feat: Bulk Add documents (one per line) and the Documents tab in the prototype style"
```

---

### Task 3: Tender page — header card, key facts, tabs, Overview, Activity

**Files:**
- Modify: `resources/views/livewire/tender-detail.blade.php`, `tender-detail/overview.blade.php`, `tender-detail/activity.blade.php`
- Test: `tests/Feature/Livewire/TenderDetailTest.php` (append)

**Interfaces:**
- Consumes:
  - `x-status-pill`, `x-fact`, `x-card`, `x-tabs`/`x-tab`, `btn` (Task 1)
- Produces:
  - the markers `data-facts` (the key-facts strip) and `data-reason="lost|drop"` on reason rows

- [ ] **Step 1: Write the failing tests** (append):
```php
it('shows the key facts under the title', function () {
    [$pic, $tender] = detailFixture(['client' => 'PUSAT DARAH NEGARA', 'ministry' => 'KEMENTERIAN KESIHATAN',
        'tender_code' => 'QT-77', 'estimated_value_sen' => 31640000]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSeeHtml('data-facts')
        ->assertSeeInOrder(['Assigned PIC', 'Siti Aisyah', 'Opportunity Owner', 'Category', 'Tender Code', 'QT-77',
            'Agency', 'PUSAT DARAH NEGARA', 'KEMENTERIAN KESIHATAN', 'Estimated value', 'RM 316,400.00', 'Closing date', 'WO date']);
});

it('shows reason rows only when there is a reason, with the right pill for dropped tenders', function () {
    [$pic, $tender] = detailFixture(['status' => TenderStatus::Dropped, 'drop_reason' => 'No capacity', 'dropped_at' => now()]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSeeHtml('data-status="dropped"')->assertSeeHtml('data-reason="drop"')->assertSee('No capacity')
        ->assertDontSeeHtml('data-reason="lost"')->assertDontSee('Mark Done');
});
```

- [ ] **Step 2: Run them and watch them fail.** Expected: `data-facts` is not found.

- [ ] **Step 3: Implement** (re-read each view first; keep all `wire:*` and conditions)

`tender-detail.blade.php`:
- the header becomes an `x-card`-style section: `rounded-[20px] border border-line bg-surface p-5 space-y-4`
- "← Back to list" link above it (`text-[13px] font-semibold text-muted hover:text-ink`)
- inside: a top row with the WO number in `font-extrabold`, `<x-status-pill :status="$status" />`, a mode pill (`rounded-full bg-hover px-2.5 py-0.5 text-[11.5px] font-semibold`), and "Docs {done}/{total}" (same pill style)
- buttons on the right (`ml-auto flex flex-wrap gap-2`):
  - Drop tender, Cancel Tender, Mark Lost, Reopen → `btn btn-outline`
  - Mark Done → `btn btn-dark`
  - Mark Awarded → `btn btn-primary`
- the title: `<h1 class="text-xl font-extrabold leading-snug tracking-tight">`
- the key-facts strip: `<div data-facts class="grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4 sm:grid-cols-4">`
  - `<x-fact label="Assigned PIC"><span class="flex items-center gap-2"><x-avatar :user="$tender->pic" /> {{ $tender->pic->name }}</span></x-fact>`
  - Opportunity Owner (`owner?->name ?? '—'`), Category, Tender Code
  - Agency (`client`, plus `<span class="block text-[11.5px] font-normal text-muted-2">{{ $tender->ministry }}</span>` when the ministry is set and differs)
  - Estimated value (`Money::format`)
  - Closing date, coloured by `closingState()`: soon → `text-warn-ink`, overdue → `text-bad-ink`
  - WO date
  - `x-fact`'s value is `truncate`; for Agency pass `class="col-span-2"` and wrap the value in `whitespace-normal`
- the tabs: replace the existing tab `<nav>` with `<x-tabs>` and an `<x-tab :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">` per tab. Keep the existing `$tabs` array.

`overview.blade.php`:
- two `x-card`s in `grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]`:
  1. `<x-card title="Scope of Work" icon="tenders">`: the scope text (`whitespace-pre-line text-[13.5px]`) or "No scope written yet." (muted)
  2. `<x-card title="Registration Details" icon="quotation">`: a `grid grid-cols-2 gap-x-6 gap-y-3` of `x-fact`s: WO Number, WO Date, Mode, Type, Publish Date, Briefing ("Yes — d M Y" / "No"), Tender Code, Submitted price, Winning price. Then the reason rows, only when set:
     - `@if ($tender->lost_reason) <x-fact label="Lost / cancel reason" data-reason="lost" class="col-span-2">…</x-fact> @endif`
     - the same for `drop_reason` with `data-reason="drop"` and label "Drop reason"
- keep the existing "Edit details" button (now `btn btn-outline`) and the edit form include. Apply the Round 1 input class in `partials/tender-fields.blade.php` (replace `$input`'s value).
- the fact facts already in the header (PIC, owner, category, agency, value, closing) are no longer repeated here.

`activity.blade.php`: inside `<x-card title="Activity" icon="clock">`, an `<ol class="relative space-y-4 border-l-2 border-line pl-5">`. Each `<li class="relative">` has:
- `<span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-surface bg-accent-solid"></span>`
- the description (`text-[13.5px]`)
- "by {name} · {date}" (`text-xs text-muted`)

The empty state is unchanged.

Existing tests that assert wording now moved (e.g. "Client", the overview layout, "Lost / cancel reason" with "—"): update them and record rulings.

- [ ] **Step 4: Run** `… ./vendor/bin/pest tests/Feature/Livewire/TenderDetailTest.php tests/Feature/Livewire` → PASS.

- [ ] **Step 5: Commit**
```bash
git add resources/views/livewire/tender-detail.blade.php resources/views/livewire/tender-detail resources/views/livewire/partials tests/Feature
git commit -m "feat(ui): tender page header card, key facts, tabs, Overview cards and Activity timeline"
```

---

### Task 4: Dialogs — every pop-up in `x-dialog`; Register Tender with WO preview

**Files:**
- Modify:
  - `app/Actions/Tenders/GenerateWoNumber.php`
  - `app/Livewire/RegisterTenderModal.php`
  - `resources/views/livewire/register-tender-modal.blade.php`
  - `resources/views/livewire/tender-detail/modals.blade.php`
  - `resources/views/livewire/tender-costing.blade.php` (the Bulk Import dialog only)
  - `resources/views/livewire/manage-users.blade.php` (its dialog, if any)
  - `resources/views/livewire/project-pd.blade.php` (its dialogs, if any)
  - `resources/views/livewire/quotation-page.blade.php` (its dialogs, if any)
- Test: `tests/Feature/Actions/GenerateWoNumberTest.php`, `tests/Feature/Livewire/RegisterTenderModalTest.php`

**Interfaces:**
- Consumes:
  - `x-dialog`, `btn` (Task 1)
- Produces:
  - `GenerateWoNumber::peek(CarbonImmutable $malaysiaDay): string` (read-only)

- [ ] **Step 1: Write the failing tests**

`GenerateWoNumberTest.php` (append):
```php
it('previews the next WO number without using it', function () {
    $day = \Carbon\CarbonImmutable::parse('2026-10-07');
    $gen = app(\App\Actions\Tenders\GenerateWoNumber::class);

    expect($gen->peek($day))->toBe('200-07102026-001')->and($gen->peek($day))->toBe('200-07102026-001')
        ->and(\Illuminate\Support\Facades\DB::table('wo_sequences')->count())->toBe(0)
        ->and($gen->next($day))->toBe('200-07102026-001')
        ->and($gen->peek($day))->toBe('200-07102026-002');
});
```
`RegisterTenderModalTest.php` (append; reuse that file's sign-in setup):
```php
it('shows the WO number and date that registering will use, labelled as automatic', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 02:00:00', 'UTC'));
    $this->actingAs(\App\Models\User::factory()->create());

    \Livewire\Livewire::test(\App\Livewire\RegisterTenderModal::class)->call('open')
        ->assertSee('WO Number (auto')->assertSee('200-07102026-001')->assertSee('07 Oct 2026');
    expect(\Illuminate\Support\Facades\DB::table('wo_sequences')->count())->toBe(0);
});
```
(Check the modal's method that opens it, e.g. an `#[On('open-register-tender')]` handler, and call that.)

- [ ] **Step 2: Run them and watch them fail.** Expected: the method `peek` does not exist, and "WO Number (auto" is not found.

- [ ] **Step 3: Implement**

`GenerateWoNumber::peek`:
```php
    /** The number "Register" would give right now — read only, never reserves it. */
    public function peek(CarbonImmutable $malaysiaDay): string
    {
        $last = (int) DB::table('wo_sequences')->where('date', $malaysiaDay->toDateString())->value('last_seq');

        return sprintf('200-%s-%03d', $malaysiaDay->format('dmY'), $last + 1);
    }
```

`RegisterTenderModal`: pass `'woPreview' => app(GenerateWoNumber::class)->peek(MalaysiaTime::today())` and `'woDate' => MalaysiaTime::today()` to the view (only computed while open).

`register-tender-modal.blade.php`: wrap in `<x-dialog title="Register Tender" subtitle="Add a tender to In Progress. The WO number is given when you register." close="close" wide>`, using the modal's actual close method name. Then:
- a top strip `grid grid-cols-2 gap-3 rounded-xl bg-subtle p-3`:
  - `<x-fact label="WO Number (auto — final number given on save)">{{ $woPreview }}</x-fact>`
  - `<x-fact label="WO Date (auto)">{{ $woDate->format('d M Y') }}</x-fact>`
- the existing `partials/tender-fields` include (now prototype inputs), laid out `grid gap-3 sm:grid-cols-2`
- `<x-slot:actions><button type="button" wire:click="save" class="btn btn-primary">Register</button></x-slot:actions>`, using the existing submit action name

`tender-detail/modals.blade.php`: replace the hand-made overlay with one `<x-dialog>` per case, with `close="closeModal"`. Titles and subtitles:
- done: "Mark tender as Done?" / "Marking this tender as Done will lock its costing and documents from further edits." Keep the submitted-price line and the pending-documents warning. Action `btn btn-dark` "Yes, mark Done".
- cancel: "Cancel tender" / "The tender moves to Lost and is marked as cancelled." Action `btn btn-danger` "Cancel tender".
- awarded: "Mark as Awarded" / "Confirm CMT won this tender." Action `btn btn-primary`.
- lost: "Mark as Lost" (fields as now). Action `btn btn-danger`.
- drop: as now, action `btn btn-danger` "Drop tender".
- reopen: as now, action `btn btn-outline` "Reopen".
- bulk-docs: as in Task 2, action `btn btn-primary` "Add documents".

Inputs use the Round 1 input class. The modal still renders only `@if ($modal)`.

`tender-costing.blade.php` Bulk Import, and the dialogs in `project-pd.blade.php`, `quotation-page.blade.php` and `manage-users.blade.php`: wrap each existing dialog's body in `x-dialog` with its current title and close action, and move the confirm button into `actions` with the matching `btn-*`. Find them with `grep -n "fixed inset-0" resources/views/livewire`. Every place that grep finds must use `x-dialog` when done, except the phone drawer in `layouts/app.blade.php`.

- [ ] **Step 4: Run** `… ./vendor/bin/pest tests/Feature` → PASS (the dialog behaviour tests are unchanged).

- [ ] **Step 5: Commit**
```bash
git add app/Actions/Tenders/GenerateWoNumber.php app/Livewire/RegisterTenderModal.php resources/views tests/Feature
git commit -m "feat(ui): every dialog in one prototype style; Register Tender previews the WO number"
```

---

### Task 5: Costing tab — summary boxes, table and unsaved bar

**Files:**
- Modify: `resources/views/livewire/tender-costing.blade.php`
- Test: `tests/Feature/Livewire/TenderCostingTest.php` (append)

**Interfaces:**
- Consumes:
  - the existing `$summary` (or equivalent) view data from `TenderCosting`, with `total_cost_sen`, `bid_price_sen`, `margin_sen`, `margin_bp`, `below_target`
- Produces:
  - the markers `data-costing-box="cost|sell|margin|margin-pct"`

- [ ] **Step 1: Write the failing test** (append; reuse the file's helper for a tender with costing lines):
```php
it('summarises the costing in four boxes and turns the margin red below target', function () {
    [$pic, $tender] = costingFixture();

    $html = costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '100,000')->set('lines.0.margin', '10')
        ->html();   // 10% is below the 18% company target

    foreach (['cost', 'sell', 'margin', 'margin-pct'] as $box) {
        expect($html)->toContain('data-costing-box="'.$box.'"');
    }
    expect($html)->toMatch('/data-costing-box="margin-pct"[^>]*text-bad-ink/');
});
```
(The box's `data-costing-box` attribute must come before its `class`, so the regex can see the colour on the same tag.)

- [ ] **Step 2: Run → FAIL** (the markers are missing).

- [ ] **Step 3: Implement**
- re-read the view
- the top of the tab: `grid grid-cols-2 gap-3 md:grid-cols-4` of boxes (`rounded-[14px] border border-line bg-surface px-4 py-3`), each with a muted label and a `text-xl font-extrabold` value:
  - Total Cost
  - Total Sell (the bid price)
  - Margin (RM)
  - Margin % (`Percent::format`)
  - Margin and Margin % add `text-bad-ink` when `below_target`
- the unsaved-changes bar: `flex items-center gap-3 rounded-xl border border-warn-ink/30 bg-warn-bg px-4 py-2.5 text-[13px] text-warn-ink`, with "You have unsaved costing changes", then Discard (`btn btn-outline`) and Save changes (`btn btn-dark`). Keep their `wire:click`s.
- the table: Round 1 header and row classes; inputs use the Round 1 input class (compact: `py-1`)
- "+ Add line item" and "Bulk Import" become `btn btn-outline`; Save becomes `btn btn-dark`
- keep every `wire:model`, `wire:click` and conditional

- [ ] **Step 4: Run** `… ./vendor/bin/pest tests/Feature/Livewire/TenderCostingTest.php tests/Feature/Costing` → PASS.

- [ ] **Step 5: Commit** `feat(ui): Costing tab with summary boxes, prototype table and unsaved-changes bar`

---

### Task 6: PD tab (tender and quotation projects)

**Files:**
- Modify: `resources/views/livewire/project-pd.blade.php`, `resources/views/livewire/quotation-project-page.blade.php`
- Test: `tests/Feature/Livewire/ProjectPdTest.php` (append)

- [ ] **Step 1: Write the failing test** (append; reuse the file's project setup):
```php
it('lays the PD out in titled cards', function () {
    [$pic, $tender] = pdTab();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->assertSeeHtml('data-pd-card="pnl"')->assertSeeHtml('data-pd-card="lines"')->assertSeeHtml('data-pd-card="cashflow"');
});
```
(The view's sections today are "Profit & Loss", "Cost lines" and "Cash flow". The cards are named after them: icons `chart`, `tenders`, `calendar`.)

- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement:**
  - wrap each existing PD section in `<x-card title="…" icon="…" data-pd-card="…">`: Profit & Loss (`pnl`, `chart`), Cost lines (`lines`, `tenders`), Cash flow (`cashflow`, `calendar`)
  - tables use the Round 1 classes; inputs use the Round 1 input class; buttons use `btn-*`
  - no change to figures or `wire:*`
  - `quotation-project-page.blade.php`: an `x-page-heading` with the quotation number and customer, then the PD
- [ ] **Step 4: Run** `… ./vendor/bin/pest tests/Feature/Livewire/ProjectPdTest.php tests/Feature/Pd tests/Unit/Pd` → PASS.
- [ ] **Step 5: Commit** `feat(ui): PD in titled cards with the prototype tables`

---

### Task 7: Quotations list — status buttons with counts, date sort, table, cards

**Files:**
- Modify: `app/Quotations/QuotationListQuery.php`, `app/Livewire/QuotationList.php`, `resources/views/livewire/quotation-list.blade.php`
- Test: `tests/Feature/Livewire/QuotationScreensTest.php` (append)

**Interfaces:**
- Produces:
  - `QuotationListQuery::counts(string $search, bool $mine, User $viewer, CarbonImmutable $today): array<string,int>` with keys `all, draft, sent, expired, accepted, rejected, revised`
  - `QuotationListQuery::build(..., string $sort = 'date_desc')`
  - `QuotationList::$sort` (`#[Url]`, `date_desc|date_asc`), `QuotationList::toggleDateSort()`

- [ ] **Step 1: Write the failing tests** (append; reuse the file's quotation helpers):
```php
it('counts quotations per status for the current search, matching what each status button shows', function () {
    $this->actingAs($u = \App\Models\User::factory()->create());
    \App\Models\Quotation::factory()->create(['customer_name' => 'ALPHA', 'status' => \App\Enums\QuotationStatus::Draft]);
    \App\Models\Quotation::factory()->create(['customer_name' => 'ALPHA', 'status' => \App\Enums\QuotationStatus::Sent,
        'quote_date' => now()->subDays(60), 'validity_days' => 30]);                         // expired
    \App\Models\Quotation::factory()->create(['customer_name' => 'BETA', 'status' => \App\Enums\QuotationStatus::Draft]);

    $c = \Livewire\Livewire::test(\App\Livewire\QuotationList::class)->set('search', 'ALPHA');
    $c->assertSeeHtml('data-status-count="all">2<')->assertSeeHtml('data-status-count="draft">1<')
      ->assertSeeHtml('data-status-count="expired">1<')->assertSeeHtml('data-status-count="sent">0<');
    $c->set('status', 'draft')->assertSee('ALPHA')->assertDontSee('BETA');
});

it('sorts by date when the Date heading is clicked', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    \App\Models\Quotation::factory()->create(['subject' => 'OLDER ONE', 'quote_date' => '2026-01-01']);
    \App\Models\Quotation::factory()->create(['subject' => 'NEWER ONE', 'quote_date' => '2026-06-01']);

    \Livewire\Livewire::test(\App\Livewire\QuotationList::class)
        ->assertSeeInOrder(['NEWER ONE', 'OLDER ONE'])
        ->call('toggleDateSort')->assertSet('sort', 'date_asc')->assertSeeInOrder(['OLDER ONE', 'NEWER ONE'])
        ->set('sort', 'bogus')->assertSeeInOrder(['NEWER ONE', 'OLDER ONE']);
});
```

- [ ] **Step 2: Run → FAIL** (the method `toggleDateSort` and the counts markup are missing).

- [ ] **Step 3: Implement**

`QuotationListQuery`:
- `build()` gains `string $sort = 'date_desc'`; the ordering becomes:
  `$dir = $sort === 'date_asc' ? 'asc' : 'desc'; ->orderBy('quote_date', $dir)->orderBy('id', $dir)`. Move the ordering after the filters and keep `with(...)`.
- extract the search / mine filtering into `private static function scope(Builder $q, string $search, bool $mine, User $viewer): Builder`, used by both `build()` and `counts()`.
- add:
```php
    /** Quotations per status button, for the same search / "mine" filter as the list. */
    public static function counts(string $search, bool $mine, User $viewer, CarbonImmutable $today): array
    {
        $row = self::scope(Quotation::query()->toBase(), $search, $mine, $viewer)
            ->selectRaw('COUNT(*) AS all_n')
            ->selectRaw("SUM(status = 'draft') AS draft, SUM(status = 'accepted') AS accepted, SUM(status = 'rejected') AS rejected, SUM(status = 'revised') AS revised")
            ->selectRaw("SUM(status = 'sent' AND DATE_ADD(quote_date, INTERVAL validity_days DAY) < ?) AS expired", [$today->format('Y-m-d')])
            ->selectRaw("SUM(status = 'sent' AND DATE_ADD(quote_date, INTERVAL validity_days DAY) >= ?) AS sent", [$today->format('Y-m-d')])
            ->first();

        return ['all' => (int) $row->all_n] + array_map('intval', array_intersect_key((array) $row,
            array_flip(['draft', 'sent', 'expired', 'accepted', 'rejected', 'revised'])));
    }
```
(`scope` must accept a `Builder` or query builder. Type-hint as `$q` without a class, or make two overloads. Keep it simple and record a ruling.)

`QuotationList`:
- add `#[Url] public string $sort = 'date_desc';` and `public function toggleDateSort(): void { $this->sort = $this->sort === 'date_asc' ? 'date_desc' : 'date_asc'; $this->resetPage(); }`
- `render()` passes `$sort` (validated to `date_asc|date_desc`) into `build()`, and `'counts' => QuotationListQuery::counts(...)`

`quotation-list.blade.php` (re-read first):
- `x-page-heading` "Quotations" (subtitle "Quotes prepared for customers") with `<button wire:click="create" class="btn btn-primary"><x-icon name="plus" class="h-[15px] w-[15px]" /> New Quotation</button>`, keeping the existing `@can`
- `x-filter-bar :count="0" placeholder="Search number, customer, subject" :mine="$mine"` (the panel is empty, so pass it nothing; record a ruling)
- a status button row `flex flex-wrap gap-2`; for each key in `['all' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'expired' => 'Expired', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'revised' => 'Revised']`:
```blade
<button type="button" wire:click="$set('status', '{{ $k }}')" @class(['flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-[12.5px] font-semibold',
    'border-ink bg-ink text-surface' => $status === $k, 'border-line-2 text-ink-2 hover:bg-hover' => $status !== $k])>
    {{ $label }} <span data-status-count="{{ $k }}" class="rounded-md bg-hover px-1.5 text-[10.5px] text-muted">{{ $counts[$k] }}</span></button>
```
  The test expects `data-status-count="all">2<`, so keep the span's attributes in exactly that order, with no class between them. Put `class` before `data-status-count`.
- the table in `<x-data-table resizable="quotations" min-width="900px" class="hidden md:block">`:
  - headers No., Date (a button with `wire:click="toggleDateSort"`, the arrow ↑/↓ and `aria-sort`), Customer, Subject, Prepared By, Amount, Valid Until, Status
  - rows link to the quotation as now
  - Prepared By uses `<x-avatar :user="$q->preparer" />` plus the name
  - Status uses `<x-status-pill :status="$q->displayStatus()" />`, using whatever the model calls its "expired-aware status value" (there is one returning `'expired'` or the status value)
- phone cards `md:hidden`: number plus status pill, customer, subject, amount, valid until
- the footer "Showing a–b of N quotations" plus the pager

- [ ] **Step 4: Run** `… ./vendor/bin/pest tests/Feature/Livewire/QuotationScreensTest.php tests/Feature/Quotations` → PASS.

- [ ] **Step 5: Commit** `feat(ui): Quotations list with status counts, date sort, resizable table and phone cards`

---

### Task 8: Quotation page — header, Saved note, tabs, cards

**Files:**
- Modify: `app/Livewire/QuotationPage.php`, `resources/views/livewire/quotation-page.blade.php`
- Test: `tests/Feature/Livewire/QuotationScreensTest.php` (append)

**Interfaces:**
- Produces:
  - the browser event `saved`, dispatched after a successful `saveField`/`saveItem`

- [ ] **Step 1: Write the failing test:**
```php
it('tells the page a field was saved, so it can show Saved', function () {
    [$u, $q] = myQuotation();

    Livewire::actingAs($u)->test(QuotationPage::class, ['quotation' => $q])
        ->set('form.customer_name', 'Jabatan Perpaduan')->assertDispatched('saved')
        ->set('form.attention_email', 'not-an-email')->assertHasErrors('form.attention_email');
});
```

- [ ] **Step 2: Run → FAIL** (no event dispatched).

- [ ] **Step 3: Implement:**
  - `QuotationPage`: after a successful save in `saveField` and `saveItem` (inside the success path of `run`), call `$this->dispatch('saved');`
  - the view (re-read first):
    - the header row: "← Back to quotations", the number in `font-extrabold`, `<x-status-pill :status="…expired-aware value…" />`, a `<span x-data="{ s: false }" x-on:saved.window="s = true; setTimeout(() => s = false, 2000)" x-show="s" x-cloak class="text-[12px] font-semibold text-good-ink">Saved ✓</span>`, and the buttons as `btn-*` (Duplicate and Download PDF `btn-outline`, Mark Sent `btn-dark`, Accepted / Create project `btn-primary`, Rejected `btn-danger`, Back to Draft / Revise `btn-outline`) with their existing conditions
    - the tabs become `x-tabs`
    - Details: two `x-card`s ("Customer": customer, attention, mobile, email, address, subject; "Prepared by": preparer, position, phone, email, signature / stamp ticks) in `grid gap-4 lg:grid-cols-2`, with Round 1 inputs
    - Items: a Round 1 table, with the Subtotal / SST / Total block `ml-auto w-full max-w-xs space-y-1 text-[13px]` and Total in bold
    - Terms: an `x-card` with the textarea
    - Preview: unchanged inside an `x-card`
    - History: the Task 3 timeline markup
- [ ] **Step 4: Run** the quotation tests → PASS.
- [ ] **Step 5: Commit** `feat(ui): quotation page header with Saved note, tabs and cards`

---

### Task 9: Settings, Manage Users, Finance Settings, Find Tenders detail, sign-in pages

**Files:**
- Modify:
  - `resources/views/livewire/settings.blade.php`, `manage-users.blade.php`, `finance-settings.blade.php`, `collected-tender-detail.blade.php`
  - `resources/views/livewire/auth/login.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`
  - `resources/views/layouts/guest.blade.php`
- Test: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Livewire/CollectedTenderDetailTest.php` (append)

- [ ] **Step 1: Write the failing tests:**
```php
// LoginTest.php
it('shows the TenderHub sign-in card', function () {
    $this->get('/login')->assertOk()->assertSeeHtml('data-auth-card')->assertSee('TenderHub');
});
// CollectedTenderDetailTest.php (use the file's setup)
it('shows the collected tender\'s key facts', function () {
    // ...->assertSeeHtml('data-facts')->assertSee('Closing');
});
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement:**
  - **Settings, Manage Users, Finance Settings:** `x-page-heading`, each section in `x-card`, tables in Round 1 classes, inputs in the Round 1 input class, buttons `btn-*`
  - **`collected-tender-detail.blade.php`:**
    - a header card (reference in `font-extrabold`, source labels, open/closed pill, "Register this tender" `btn-primary`)
    - `<div data-facts …>` of `x-fact`s: Ministry, Agency, Type, Advertised, Closing (with days left), Indicative price
    - the remaining sections in `x-card`s
  - **`layouts/guest.blade.php`:** the body `min-h-screen bg-canvas grid place-items-center p-4`, a `<div data-auth-card class="w-full max-w-sm rounded-[20px] border border-line bg-surface p-7 shadow-[0_6px_18px_var(--shadow-soft)]">` containing the logo row (dark `T` mark plus "TenderHub") and the slot
  - **auth views:** Round 1 inputs, full-width `btn btn-dark`, links `text-[13px] font-semibold text-muted hover:text-ink`. Messages unchanged.
- [ ] **Step 4: Run** `… ./vendor/bin/pest tests/Feature` → PASS.
- [ ] **Step 5: Commit** `feat(ui): settings pages, Find Tenders detail and sign-in in the prototype style`

---

### Task 10: README, full suite, live browser check

- [ ] **Step 1: README** "Look and feel" section: add the line
  "Round 2: tender page, Quotations, dialogs, settings and sign-in follow the prototype too; **Bulk Add** on a tender's Documents tab adds one document per line."
- [ ] **Step 2: Full suite with coverage** → all pass, ≥ 80%.
- [ ] **Step 3: Browser check** (built-in browser, `localhost:8080`, signed in as the seed admin; record each result in the ledger):
  1. A tender page (In Progress, Done, Dropped), Costing, PD (if any), Documents, Activity, Quotations list, a quotation page, Settings, Manage Users, Finance Settings, a Find Tenders detail page, `/login` (signed out): no sideways scroll at 1440, 1024 and 375; screenshots at 1440.
  2. Open, cancel and confirm each tender dialog on a test tender (use one of the imported tenders only for a dialog's **Cancel** path, never confirm destructive actions on real data). For confirms, register a new test tender through Register Tender, then drop / reopen / cancel it, and delete nothing real.
  3. Bulk Add on the test tender ("A\nB\nA") → "Added 2 documents".
  4. Quotations: create a test quotation, check the status buttons' counts change and the date sort flips.
  5. Costing: edit a cell on the test tender, see the unsaved bar, then Discard.
  6. Dark mode screenshot of the tender page and the Quotations list.
  7. Afterwards: remove the test tender and test quotation **only if** the app offers a delete. Otherwise leave them clearly named "TEST — UI check" and report them to the user.
- [ ] **Step 4: Commit** `docs: Round 2 look-and-feel note`
