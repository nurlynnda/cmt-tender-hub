# UI Round 1 (match the Claude Design prototype) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the page frame, tender lists, Dashboard, Status and Find Tenders look and behave like the user's Claude Design prototype.

**Architecture:** Shared Blade components (`x-icon`, `x-page-heading`, `x-filter-bar`, `x-filter-field`, `x-data-table`, `x-initials`) plus one small browser script (`resizable-columns.js`) are built first; each page is then rebuilt from them. Livewire components keep their queries and actions; only `TenderList` gains two address settings (`agency`, `sort`) and both list components gain a filter count.

**Tech Stack:** Laravel 13, Livewire 4.4 (Alpine bundled), Tailwind v4, Pest 5. PHP runs only in Docker: prefix every PHP command with `MSYS_NO_PATHCONV=1 docker compose exec -T app` from `C:\Projects\cmt-tender-hub`.

**Spec:** `docs/superpowers/specs/2026-10-07-cmt-tender-hub-ui-round1-design.md`. The prototype reference is in `docs/superpowers/plans/assets/prototype-ui/markup.html` (screens and inline styles) and `logic.js` (icons, numbers, column resizing).

## Global Constraints

- The colour tokens in `resources/css/app.css` already equal the prototype's. Add:
  - `--ink-2` (#374151 / dark #C7C5BD)
  - `--muted-2` (#8B87A0 / #85847C)
  - `--accent-solid` (#6FB85E / #7CC46A)
  - `--line-2` (#E6E2F1 / #33322E)
  - the Quick Overview tokens: `--qo-1` #F4F0FF, `--qo-2` #EDF3FF, `--qo-3` #E6F5FD, `--qo-4` #DCF1FB, `--qo-card` rgba(255,255,255,.72), `--qo-card-border` rgba(255,255,255,.9), `--shadow-soft` rgba(87,83,107,.07)
  - dark values for these: `--qo-1` #1F1C29, `--qo-2` #1B2030, `--qo-3` #18242B, `--qo-4` #172629, `--qo-card` rgba(27,26,24,.72), `--qo-card-border` rgba(255,255,255,.06), `--shadow-soft` rgba(0,0,0,.25)
- The font stays the system stack already in use.
- No sideways page scroll from 360px to 1920px. Wide tables scroll inside their own box; grid/flex children that hold text get `min-w-0`.
- Sidebar breakpoint: drawer below 1024px (`lg`), fixed sidebar from 1024px. List cards below 768px (`md`).
- UI copy in plain English.
- Tests first (RED → GREEN), commit after green, coverage ≥ 80% (the pre-commit hook runs `pest --coverage --min=80`).
- Write PHP/Blade with the Edit/Write tools, not sed.
- Queries, permissions, URLs and their existing address settings, and report numbers stay unchanged.

## Review Focus

1. A list arrives from a Dashboard number with `wo_from/wo_to` set. The Filters count must not count those, and the "Registered … · show all dates" note must still show. Pinned in Task 4.
2. Livewire re-renders a resizable table (filter or page change). The saved widths must come back and there must be one drag handle per column, not two. Pinned in the Task 3 browser check and Task 8.
3. A folded sidebar on a narrow window (<1024px). The drawer must show the full labels, not the folded strip. Pinned in Task 2 (the drawer markup never gets the folded marker).
4. An agency name with an apostrophe or ampersand (e.g. `JABATAN KERJA RAYA & …`). The filter must still match exactly. Pinned in Task 4.
5. Find Tenders with only the default status ("open"). Its Filters count must be 0, and the panel must start closed. Pinned in Task 7.

---

## File structure

| File | Responsibility |
|---|---|
| `resources/css/app.css` (modify) | new tokens, `folded` variant |
| `app/Support/Initials.php` (create) | initials from a name (used by `User::initials()` and `x-initials`) |
| `resources/views/components/icon.blade.php` (create) | the fixed SVG set |
| `resources/views/components/initials.blade.php` (create) | coloured initials circle from a plain name |
| `resources/views/components/page-heading.blade.php` (create) | title / subtitle / actions |
| `resources/views/components/filter-bar.blade.php` (create) | search + My tenders + Filters button + fold-out panel |
| `resources/views/components/filter-field.blade.php` (create) | small label + control |
| `resources/views/components/data-table.blade.php` (create) | table card, marks the table resizable |
| `resources/js/resizable-columns.js` (create) | drag/save/restore/reset column widths |
| `resources/js/app.js` (modify) | import the script |
| `resources/views/pagination/pager.blade.php` (modify) | prototype-style pager buttons |
| `resources/views/layouts/app.blade.php`, `layouts/partials/sidebar.blade.php` (modify) | frame, icons, folding |
| `bootstrap/app.php` (modify) | leave the `sidebar` cookie unencrypted |
| `app/Livewire/TenderList.php`, `app/Queries/TenderListQuery.php`, `resources/views/livewire/tender-list.blade.php` (modify) | lists |
| `resources/views/livewire/dashboard.blade.php`, `reports/period-filter.blade.php` (modify) | Dashboard + picker |
| `resources/views/livewire/status-report.blade.php` (modify) | Status |
| `app/Livewire/FindTenders.php`, `resources/views/livewire/find-tenders.blade.php` (modify) | Find Tenders |
| `README.md` (modify) | short "Look and feel" note |

---

### Task 1: Tokens, initials helper, icon and initials components

**Files:**
- Modify: `resources/css/app.css`, `app/Models/User.php:35-43`
- Create: `app/Support/Initials.php`, `resources/views/components/icon.blade.php`, `resources/views/components/initials.blade.php`
- Test: `tests/Unit/Support/InitialsTest.php`, `tests/Feature/Components/IconTest.php`

**Interfaces:**
- Produces:
  - `App\Support\Initials::of(string $name): string`
  - `<x-icon name="…" class="h-4 w-4" />`, with names `dashboard, tenders, clock, award, check, staff, user, quotation, settings, search, plus, chevronLeft, chevronRight, chevronDown, x, filter, logout, bell, moon, panel, calendar, chart`; an unknown name throws `InvalidArgumentException`
  - `<x-initials name="Ahmad Faizal" />`
  - Tailwind colours `ink-2`, `muted-2`, `accent-solid`, `line-2`
  - the CSS variables `--qo-*` and `--shadow-soft`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Support/InitialsTest.php`:
```php
<?php

use App\Support\Initials;

it('takes the first letter of the first two words, upper-cased', function (string $name, string $expected) {
    expect(Initials::of($name))->toBe($expected);
})->with([
    ['Ahmad Faizal', 'AF'],
    ['siti aisyah binti ahmad', 'SA'],
    ['  Nurul   Ain ', 'NA'],
    ['Zara', 'Z'],
    ['', ''],
]);
```

`tests/Feature/Components/IconTest.php`:
```php
<?php

use Illuminate\Support\Facades\Blade;

it('draws every icon the screens use', function (string $name) {
    expect(Blade::render('<x-icon name="'.$name.'" class="h-4 w-4" />'))
        ->toContain('<svg')->toContain('viewBox="0 0 24 24"')->toContain('class="h-4 w-4')->toContain('aria-hidden="true"');
})->with(['dashboard', 'tenders', 'clock', 'award', 'check', 'staff', 'user', 'quotation', 'settings', 'search', 'plus',
    'chevronLeft', 'chevronRight', 'chevronDown', 'x', 'filter', 'logout', 'bell', 'moon', 'panel', 'calendar', 'chart']);

it('refuses an icon name it does not know, so a typo cannot ship silently', function () {
    Blade::render('<x-icon name="nope" />');
})->throws(Illuminate\View\ViewException::class, 'Unknown icon "nope"');

it('shows initials in a circle with the full name on hover', function () {
    expect(Blade::render('<x-initials name="Ahmad Faizal" />'))->toContain('>AF</span>')->toContain('title="Ahmad Faizal"');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest tests/Unit/Support/InitialsTest.php tests/Feature/Components/IconTest.php`
Expected: FAIL. The class `App\Support\Initials` is not found, and the component `icon` is unknown.

- [ ] **Step 3: Implement**

`app/Support/Initials.php`:
```php
<?php

namespace App\Support;

/** "Ahmad Faizal" → "AF": first letter of the first two words. */
final class Initials
{
    public static function of(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));
    }
}
```

In `app/Models/User.php`, replace the body of `initials()` with:
```php
    public function initials(): string
    {
        return \App\Support\Initials::of($this->name);
    }
```

`resources/views/components/icon.blade.php` (paths copied from `logic.js` `ICONS`, plus the extra icons):
```blade
@props(['name'])
@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'tenders' => '<path d="M6 3h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M8 12h8M8 16h5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'award' => '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 21l5-2.5 5 2.5-1.5-7.5"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
        'staff' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5c1.7.4 3 2 3 3.9s-1.3 3.5-3 3.9M19 14.5c1.8.5 3.2 2.1 3.2 4"/>',
        'user' => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'quotation' => '<path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5M9.5 13h5M9.5 17h3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a7.6 7.6 0 0 0 0-3l1.9-1.5-2-3.4-2.2.9a7.6 7.6 0 0 0-2.6-1.5L14 2.5h-4l-.5 2.5a7.6 7.6 0 0 0-2.6 1.5l-2.2-.9-2 3.4L4.6 10.5a7.6 7.6 0 0 0 0 3l-1.9 1.5 2 3.4 2.2-.9a7.6 7.6 0 0 0 2.6 1.5l.5 2.5h4l.5-2.5a7.6 7.6 0 0 0 2.6-1.5l2.2.9 2-3.4-1.9-1.5z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chevronLeft' => '<path d="M15 5l-7 7 7 7"/>',
        'chevronRight' => '<path d="M9 5l7 7-7 7"/>',
        'chevronDown' => '<path d="M6 9l6 6 6-6"/>',
        'x' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
        'filter' => '<path d="M4 7h16M7 12h10M10 17h4"/>',
        'logout' => '<path d="M10 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4"/><path d="M16 16l4-4-4-4M20 12H10"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9Z"/><path d="M10 18a2 2 0 0 0 4 0"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'panel' => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M10 4v16"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];
    if (! isset($paths[$name])) {
        throw new InvalidArgumentException('Unknown icon "'.$name.'"');
    }
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'shrink-0']) }}>{!! $paths[$name] !!}</svg>
```

`resources/views/components/initials.blade.php`:
```blade
@props(['name'])
<span {{ $attributes->merge(['class' => 'grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent text-[11px] font-bold text-accent-ink']) }}
      title="{{ $name }}">{{ \App\Support\Initials::of($name) }}</span>
```

In `resources/css/app.css`:
- add to `:root`:
```css
    --ink-2: #374151; --muted-2: #8B87A0; --accent-solid: #6FB85E; --line-2: #E6E2F1;
    --qo-1: #F4F0FF; --qo-2: #EDF3FF; --qo-3: #E6F5FD; --qo-4: #DCF1FB;
    --qo-card: rgba(255,255,255,0.72); --qo-card-border: rgba(255,255,255,0.9); --shadow-soft: rgba(87,83,107,0.07);
```
- add to `.dark`:
```css
    --ink-2: #C7C5BD; --muted-2: #85847C; --accent-solid: #7CC46A; --line-2: #33322E;
    --qo-1: #1F1C29; --qo-2: #1B2030; --qo-3: #18242B; --qo-4: #172629;
    --qo-card: rgba(27,26,24,0.72); --qo-card-border: rgba(255,255,255,0.06); --shadow-soft: rgba(0,0,0,0.25);
```
- add to `@theme inline`:
```css
    --color-ink-2: var(--ink-2);
    --color-muted-2: var(--muted-2);
    --color-accent-solid: var(--accent-solid);
    --color-line-2: var(--line-2);
```
- after the `dark` variant line:
```css
/* Folded desktop sidebar: the <aside> carries .is-folded; children use folded:… */
@custom-variant folded (&:where(.is-folded, .is-folded *));
```

- [ ] **Step 4: Run the tests and watch them pass**

Run the same command as Step 2, then the existing user tests: `… ./vendor/bin/pest tests/Unit tests/Feature/Components`
Expected: PASS. In particular `User::initials()` still gives "SA" etc. (exercised by the existing avatar tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Initials.php app/Models/User.php resources/views/components/icon.blade.php resources/views/components/initials.blade.php resources/css/app.css tests/Unit/Support/InitialsTest.php tests/Feature/Components/IconTest.php
git commit -m "feat(ui): prototype icon set, initials helper and design tokens"
```

---

### Task 2: Page frame: sidebar with icons and folding, sticky top bar

**Files:**
- Modify: `resources/views/layouts/app.blade.php`, `resources/views/layouts/partials/sidebar.blade.php`, `bootstrap/app.php`, `resources/views/livewire/notification-bell.blade.php` (icon only)
- Test: `tests/Feature/LayoutTest.php`

**Interfaces:**
- Consumes:
  - `<x-icon>` and the `folded:` variant (Task 1)
- Produces:
  - the cookie `sidebar` = `folded` | `open`
  - `<aside data-sidebar="folded|open">` on the desktop sidebar
  - the content wrapper padding `p-4 lg:p-7`

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/LayoutTest.php`)

```php
it('puts the prototype icon beside every menu item', function () {
    $html = $this->actingAs(User::factory()->admin()->create())->get('/tenders/in-progress')->getContent();

    foreach (['Dashboard' => 'dashboard', 'Find Tenders' => 'search', 'In Progress' => 'clock', 'Done' => 'check', 'Awarded' => 'award',
        'Lost' => 'x', 'Quotations' => 'quotation', 'Status' => 'staff', 'Settings' => 'settings', 'Manage Users' => 'user',
        'Finance Settings' => 'settings'] as $label => $icon) {
        expect($html)->toContain('data-nav="'.$label.'" data-icon="'.$icon.'"');
    }
});

it('opens the sidebar folded when this computer folded it last time', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertSee('data-sidebar="open"', false);
    $this->actingAs($user)->withUnencryptedCookie('sidebar', 'folded')->get('/dashboard')->assertSee('data-sidebar="folded"', false);
    $this->actingAs($user)->withUnencryptedCookie('sidebar', 'nonsense')->get('/dashboard')->assertSee('data-sidebar="open"', false);
});

it('never folds the phone drawer, so its labels always show', function () {
    $html = $this->actingAs(User::factory()->create())->withUnencryptedCookie('sidebar', 'folded')->get('/dashboard')->getContent();

    $drawer = Illuminate\Support\Str::between($html, 'data-drawer', '<main');
    expect($html)->toContain('data-drawer')->and($drawer)->not->toContain('is-folded')->toContain('Find Tenders');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/LayoutTest.php`
Expected: FAIL. `data-nav` and `data-sidebar` are not found.

- [ ] **Step 3: Implement**

In `bootstrap/app.php`, inside `withMiddleware`, add:
```php
        $middleware->encryptCookies(except: ['sidebar']); // set by the browser when the sidebar is folded
```

Replace `resources/views/layouts/partials/sidebar.blade.php` with:
```blade
@php
    $desktop ??= false;
    $item = fn (string $href, string $label, string $icon, bool $active, ?int $count = null) => compact('href', 'label', 'icon', 'active', 'count');
    $list = fn (string $slug, string $label, string $icon, ?int $count) => $item(route('tenders.index', $slug), $label, $icon,
        request()->routeIs('tenders.index') && request()->route('list') === $slug, $count);
    $groups = [
        'Operations' => [
            $item(route('dashboard'), 'Dashboard', 'dashboard', request()->routeIs('dashboard')),
            $item(route('find-tenders.index'), 'Find Tenders', 'search', request()->routeIs('find-tenders.*')),
            $list('in-progress', 'In Progress', 'clock', $counts['in_progress']),
        ],
        'Pipeline' => [
            $list('done', 'Done', 'check', $counts['done']),
            $list('awarded', 'Awarded', 'award', $counts['awarded']),
            $list('lost', 'Lost', 'x', $counts['lost']),
        ],
        'Quotation' => [$item(route('quotations.index'), 'Quotations', 'quotation', request()->routeIs('quotations.*'))],
        'Insights' => [$item(route('status'), 'Status', 'staff', request()->routeIs('status'))],
        'Account' => array_values(array_filter([
            $item(route('settings'), 'Settings', 'settings', request()->routeIs('settings')),
            auth()->user()->can('manage-users') ? $item(route('users.index'), 'Manage Users', 'user', request()->routeIs('users.*')) : null,
            auth()->user()->can('manage-finance') ? $item(route('finance.settings'), 'Finance Settings', 'settings', request()->routeIs('finance.*')) : null,
        ])),
    ];
@endphp
<div class="flex h-full flex-col gap-0.5 p-3.5">
    <div class="flex items-center gap-2 px-1.5 pb-4 pt-2 folded:justify-center">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5" @if ($desktop) @click.prevent="if (folded) { toggleSidebar() } else { window.location = $el.href }" @endif title="TenderHub">
            <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px] bg-chip text-sm font-extrabold text-accent">T</span>
            <span class="whitespace-nowrap text-[17px] font-extrabold tracking-tight folded:hidden">TenderHub</span>
        </a>
        @if ($desktop)
            <button type="button" @click="toggleSidebar()" title="Fold the menu" aria-label="Fold the menu"
                    class="ml-auto grid h-[30px] w-[30px] place-items-center rounded-[9px] text-muted hover:bg-hover hover:text-ink folded:hidden">
                <x-icon name="panel" class="h-[17px] w-[17px]" />
            </button>
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto">
        @foreach ($groups as $heading => $items)
            <div class="mb-3">
                <p class="px-2.5 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.6px] text-muted folded:h-2 folded:overflow-hidden folded:p-0 folded:text-transparent">{{ $heading }}</p>
                @foreach ($items as $i)
                    <a href="{{ $i['href'] }}" title="{{ $i['label'] }}" data-nav="{{ $i['label'] }}" data-icon="{{ $i['icon'] }}" @class([
                        'mb-px flex items-center gap-2.5 rounded-[11px] px-2.5 py-2 text-[13.5px] folded:justify-center folded:px-2',
                        'bg-accent font-bold text-accent-ink' => $i['active'],
                        'font-semibold text-muted hover:bg-line hover:text-ink' => ! $i['active'],
                    ])>
                        <x-icon :name="$i['icon']" class="h-[17px] w-[17px]" />
                        <span class="min-w-0 flex-1 truncate folded:hidden">{{ $i['label'] }}</span>
                        @if (! is_null($i['count']))
                            <span @class(['rounded-md px-1.5 text-[10.5px] font-bold folded:hidden',
                                'bg-accent-ink/15 text-accent-ink' => $i['active'], 'bg-hover text-muted' => ! $i['active']])>{{ $i['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="mt-auto flex items-center gap-2.5 border-t border-line px-2.5 pt-3 folded:justify-center folded:px-0">
        <x-avatar :user="auth()->user()" class="h-8 w-8 text-[13px]" />
        <div class="min-w-0 flex-1 folded:hidden">
            <p class="truncate text-[12.5px] font-semibold">{{ auth()->user()->name }}</p>
            <p class="text-[10.5px] text-muted">{{ auth()->user()->role->label() }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="folded:hidden">
            @csrf
            <button type="submit" title="Log out" aria-label="Log out" class="grid h-7 w-7 place-items-center rounded-lg text-muted-2 hover:bg-bad-bg hover:text-bad-ink">
                <x-icon name="logout" class="h-4 w-4" />
            </button>
        </form>
    </div>
</div>
```

In `resources/views/layouts/app.blade.php` replace the `<body …>` up to the start of `{{ $slot }}`'s wrapper with:
```blade
@php $folded = request()->cookie('sidebar') === 'folded'; @endphp
<body class="min-h-screen bg-body font-sans text-ink antialiased"
      x-data="{ drawer: false, folded: @js($folded),
                toggleSidebar() { this.folded = ! this.folded; document.cookie = 'sidebar=' + (this.folded ? 'folded' : 'open') + ';path=/;max-age=31536000;samesite=lax' } }">
<div class="flex min-h-screen">
    {{-- Desktop sidebar (folds to icons; remembered in the "sidebar" cookie) --}}
    <aside data-sidebar="{{ $folded ? 'folded' : 'open' }}" :data-sidebar="folded ? 'folded' : 'open'"
           class="sticky top-0 hidden h-screen shrink-0 border-r border-line bg-surface transition-[width] duration-200 lg:block {{ $folded ? 'is-folded w-[72px]' : 'w-60' }}"
           :class="{ 'is-folded': folded, 'w-[72px]': folded, 'w-60': ! folded }">
        @include('layouts.partials.sidebar', ['desktop' => true])
    </aside>

    {{-- Phone/tablet drawer: never folded --}}
    <div x-show="drawer" x-cloak data-drawer class="fixed inset-0 z-40 lg:hidden">
        <div class="absolute inset-0 bg-black/40" @click="drawer = false"></div>
        <aside class="absolute inset-y-0 left-0 w-64 bg-surface shadow-xl">
            @include('layouts.partials.sidebar')
        </aside>
    </div>

    <main class="flex min-w-0 flex-1 flex-col bg-canvas">
        <nav class="sticky top-0 z-30 flex items-center gap-3.5 border-b border-line bg-surface px-5 py-2.5">
            <button type="button" class="flex items-center gap-2 lg:hidden" @click="drawer = true" aria-label="Open menu">
                <span class="grid h-10 w-10 place-items-center rounded-[11px] text-ink-2 hover:bg-hover">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </span>
                <span class="text-base font-extrabold tracking-tight">TenderHub</span>
            </button>
            <div class="ml-auto flex items-center gap-3">
                <button type="button" aria-label="Toggle theme" title="Toggle theme" class="grid h-9 w-9 place-items-center rounded-[11px] text-ink-2 hover:bg-hover"
                        @click="const d = document.documentElement.classList.toggle('dark'); localStorage.theme = d ? 'dark' : 'light'">
                    <x-icon name="moon" class="h-[19px] w-[19px]" />
                </button>
                <livewire:notification-bell />
            </div>
        </nav>
        <div class="p-4 lg:p-7">
            {{ $slot }}
        </div>
    </main>
</div>
```
(keep the closing `</body></html>` as they are).

In `resources/views/livewire/notification-bell.blade.php`, replace the bell `<svg …>…</svg>` with `<x-icon name="bell" class="h-[19px] w-[19px]" />`. Change the button classes to `relative grid h-9 w-9 place-items-center rounded-[11px] text-ink-2 hover:bg-hover`. Change the badge classes to `absolute right-px top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full border-2 border-surface bg-[#E0483B] px-1 text-[10px] font-bold text-white`.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/LayoutTest.php tests/Feature/Livewire/NotificationBellTest.php`
Expected: PASS. The old LayoutTest cases still pass because the counts, the name, the role, Manage Users visibility and the route links are unchanged.

- [ ] **Step 5: Commit**

```bash
git add bootstrap/app.php resources/views/layouts resources/views/livewire/notification-bell.blade.php tests/Feature/LayoutTest.php
git commit -m "feat(ui): sidebar with prototype icons that folds to an icon strip; sticky top bar"
```

---

### Task 3: Shared page parts: heading, filter bar, table card, pager, resizable columns

**Files:**
- Create: `resources/views/components/page-heading.blade.php`, `filter-bar.blade.php`, `filter-field.blade.php`, `data-table.blade.php`, `resources/js/resizable-columns.js`
- Modify: `resources/js/app.js`, `resources/views/pagination/pager.blade.php`
- Test: `tests/Feature/Components/PagePartsTest.php`

**Interfaces:**
- Consumes:
  - `<x-icon>` (Task 1)
- Produces:
  - `<x-page-heading title="…" subtitle="…">` with an optional `<x-slot:actions>`
  - `<x-filter-bar :count="int" placeholder="…" debounce="300" :mine="?bool">`; the default slot is the panel's fields
  - `<x-filter-field label="…">`; the slot is one control
  - `<x-data-table resizable="key" min-width="900px">`; the slot is `<thead>…</thead><tbody>…</tbody>`
  - the JS behaviour on `table[data-resizable]`
  - the pager view `pagination.pager`, unchanged in name

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Components/PagePartsTest.php`:
```php
<?php

use Illuminate\Support\Facades\Blade;

it('prints a page title with its subtitle and action buttons', function () {
    $html = Blade::render('<x-page-heading title="In Progress Tenders" subtitle="Tenders being worked on"><x-slot:actions><button>Go</button></x-slot:actions></x-page-heading>');

    expect($html)->toContain('<h1')->toContain('In Progress Tenders')->toContain('Tenders being worked on')->toContain('<button>Go</button>');
});

it('shows how many filters are on and opens the panel when any are', function () {
    $closed = Blade::render('<x-filter-bar :count="0" placeholder="Search tenders">PANEL</x-filter-bar>');
    $open = Blade::render('<x-filter-bar :count="2" placeholder="Search tenders">PANEL</x-filter-bar>');

    expect($closed)->toContain('x-data="{ open: false }"')->not->toContain('data-filter-count')->toContain('Filter by column')->toContain('PANEL')
        ->and($open)->toContain('x-data="{ open: true }"')->toContain('data-filter-count="2"');
});

it('offers My tenders only where the list supports it, pressed when on', function () {
    expect(Blade::render('<x-filter-bar :count="0">x</x-filter-bar>'))->not->toContain('My tenders')
        ->and(Blade::render('<x-filter-bar :count="1" :mine="true">x</x-filter-bar>'))->toContain('My tenders')->toContain('aria-pressed="true"');
});

it('wraps a table in a card and marks it resizable', function () {
    expect(Blade::render('<x-data-table resizable="list-done"><tbody></tbody></x-data-table>'))
        ->toContain('data-resizable="list-done"')->toContain('overflow-x-auto');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/Components/PagePartsTest.php`
Expected: FAIL. The component `page-heading` is unknown.

- [ ] **Step 3: Implement**

`resources/views/components/page-heading.blade.php`:
```blade
@props(['title', 'subtitle' => null])
<header class="flex flex-wrap items-center justify-between gap-4">
    <div class="min-w-0 flex-[1_1_240px]">
        <h1 class="text-2xl font-extrabold leading-tight tracking-tight">{{ $title }}</h1>
        @if ($subtitle) <p class="mt-1 text-[13.5px] text-muted-2">{{ $subtitle }}</p> @endif
    </div>
    @isset($actions) <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div> @endisset
</header>
```

`resources/views/components/filter-bar.blade.php`:
```blade
@props(['count' => 0, 'placeholder' => 'Search', 'debounce' => 300, 'mine' => null])
<div x-data="{ open: {{ $count > 0 ? 'true' : 'false' }} }" class="space-y-2">
    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-line bg-surface p-2">
        <label class="flex min-w-[200px] flex-1 items-center gap-2 px-2">
            <x-icon name="search" class="h-4 w-4 text-muted-2" />
            <input type="search" wire:model.live.debounce.{{ $debounce }}ms="search" placeholder="{{ $placeholder }}"
                   class="min-w-0 flex-1 bg-transparent py-1.5 text-sm outline-none placeholder:text-muted-2">
        </label>
        @if (! is_null($mine))
            <button type="button" wire:click="$toggle('mine')" aria-pressed="{{ $mine ? 'true' : 'false' }}" @class([
                'flex items-center gap-1.5 rounded-[10px] border px-3 py-1.5 text-[12.5px] font-semibold',
                'border-accent bg-accent text-accent-ink' => $mine,
                'border-line-2 text-ink-2 hover:bg-hover' => ! $mine,
            ])><x-icon name="user" class="h-[15px] w-[15px]" /> My tenders</button>
        @endif
        <button type="button" @click="open = ! open" :aria-expanded="open"
                class="flex items-center gap-1.5 rounded-[10px] border border-line-2 px-3 py-1.5 text-[12.5px] font-semibold text-ink-2 hover:bg-hover">
            <x-icon name="filter" class="h-[15px] w-[15px]" /> Filters
            @if ($count > 0) <span data-filter-count="{{ $count }}" class="rounded-md bg-accent px-1.5 text-[10.5px] font-bold text-accent-ink">{{ $count }}</span> @endif
        </button>
    </div>
    <div x-show="open" x-cloak class="rounded-2xl border border-line bg-surface p-4">
        <div class="mb-3 flex items-center justify-between">
            <p class="text-[11px] font-bold uppercase tracking-[0.5px] text-muted">Filter by column</p>
            <button type="button" wire:click="clearFilters" class="text-xs font-semibold text-muted hover:text-bad-ink">Clear all</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">{{ $slot }}</div>
    </div>
</div>
```

`resources/views/components/filter-field.blade.php`:
```blade
@props(['label'])
<label class="flex min-w-0 flex-col gap-1">
    <span class="text-[11px] font-semibold text-muted">{{ $label }}</span>
    {{ $slot }}
</label>
```
(Controls placed inside use the class string `w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]`. Each page defines it once as `$field`.)

`resources/views/components/data-table.blade.php`:
```blade
@props(['resizable' => null, 'minWidth' => '900px'])
<div {{ $attributes->merge(['class' => 'relative overflow-x-auto rounded-2xl border border-line bg-surface']) }}>
    <table @if ($resizable) data-resizable="{{ $resizable }}" @endif class="w-full text-[13px]" style="min-width: {{ $minWidth }}">
        {{ $slot }}
    </table>
</div>
```
Header cells on every page use `px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted` inside `<thead class="bg-subtle">`. Body rows use `border-t border-line hover:bg-subtle`, and cells use `px-3.5 py-3 align-top`.

`resources/js/resizable-columns.js`:
```js
// Drag a column's right edge to resize it; widths are remembered per table on this computer.
// Double-click an edge to go back to normal widths. Works across Livewire re-renders.
const PREFIX = 'tenderhub-cols:';
const MIN = 60;

const read = (key) => { try { return JSON.parse(localStorage.getItem(PREFIX + key) || 'null'); } catch { return null; } };
const write = (key, widths) => {
    try { widths ? localStorage.setItem(PREFIX + key, JSON.stringify(widths)) : localStorage.removeItem(PREFIX + key); } catch { /* storage off: resize still works for this visit */ }
};
const headers = (table) => Array.from(table.querySelectorAll('thead th'));

function fix(table, widths) {
    headers(table).forEach((th, i) => { th.style.width = widths[i] + 'px'; });
    table.style.tableLayout = 'fixed';
    table.style.width = widths.reduce((a, b) => a + b, 0) + 'px';
    table.style.minWidth = '0';
}

function unfix(table) {
    headers(table).forEach((th) => { th.style.width = ''; });
    table.style.tableLayout = table.style.width = table.style.minWidth = '';
}

function startDrag(event, table, index) {
    event.preventDefault();
    event.stopPropagation();
    const widths = headers(table).map((th) => th.getBoundingClientRect().width);
    fix(table, widths);
    const startX = event.clientX;
    const startWidth = widths[index];
    const move = (e) => { widths[index] = Math.max(MIN, startWidth + e.clientX - startX); fix(table, widths); };
    const up = () => {
        document.removeEventListener('pointermove', move);
        document.removeEventListener('pointerup', up);
        write(table.dataset.resizable, widths.map(Math.round));
    };
    document.addEventListener('pointermove', move);
    document.addEventListener('pointerup', up);
}

function setUp(table) {
    const key = table.dataset.resizable;
    const ths = headers(table);
    const saved = read(key);
    if (saved && saved.length === ths.length) fix(table, saved);
    else if (saved) write(key, null); // the table changed shape: forget the old widths

    ths.forEach((th, i) => {
        if (th.querySelector('[data-col-handle]')) return;
        th.style.position = 'relative';
        const handle = document.createElement('span');
        handle.dataset.colHandle = '';
        handle.title = 'Drag to resize · double-click to reset';
        handle.style.cssText = 'position:absolute;top:0;bottom:0;right:-4px;width:8px;cursor:col-resize;z-index:5;touch-action:none';
        handle.addEventListener('pointerdown', (e) => startDrag(e, table, i));
        handle.addEventListener('click', (e) => e.stopPropagation()); // never trigger the header's sort
        handle.addEventListener('dblclick', (e) => { e.stopPropagation(); write(key, null); unfix(table); });
        th.appendChild(handle);
    });
}

let queued = false;
function setUpAll() {
    queued = false;
    document.querySelectorAll('table[data-resizable]').forEach(setUp);
}
function queue() {
    if (!queued) { queued = true; requestAnimationFrame(setUpAll); }
}

// Livewire re-renders strip the handles and inline widths; put them back whenever the page changes.
new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
document.addEventListener('DOMContentLoaded', setUpAll);
```

`resources/js/app.js`:
```js
import './resizable-columns';
```

Replace `resources/views/pagination/pager.blade.php` (same logic, prototype look):
```blade
@if ($paginator->hasPages())
    @php
        $page = $paginator->getPageName();
        $btn = 'grid h-8 min-w-8 place-items-center rounded-[9px] border px-2.5 text-[12.5px] font-semibold disabled:opacity-40';
    @endphp
    <nav class="flex items-center gap-1" aria-label="Pages">
        <button type="button" wire:click="previousPage('{{ $page }}')" @disabled($paginator->onFirstPage()) class="{{ $btn }} border-line-2 text-ink-2 hover:bg-hover">Prev</button>
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-muted">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $number => $url)
                    <button type="button" wire:key="page-{{ $number }}" wire:click="gotoPage({{ $number }}, '{{ $page }}')"
                            @if ($number === $paginator->currentPage()) aria-current="page" @endif
                            @class([$btn, 'border-chip bg-chip text-chip-ink' => $number === $paginator->currentPage(), 'border-line-2 text-ink-2 hover:bg-hover' => $number !== $paginator->currentPage()])>{{ $number }}</button>
                @endforeach
            @endif
        @endforeach
        <button type="button" wire:click="nextPage('{{ $page }}')" @disabled(! $paginator->hasMorePages()) class="{{ $btn }} border-line-2 text-ink-2 hover:bg-hover">Next</button>
    </nav>
@endif
```

- [ ] **Step 4: Run the tests and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/Components tests/Feature/Livewire/TenderListTest.php`
Expected: PASS. The pager tests still pass because the Prev/numbers/Next order is unchanged.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components resources/js resources/views/pagination/pager.blade.php tests/Feature/Components/PagePartsTest.php
git commit -m "feat(ui): shared page heading, filter bar, table card, pager and resizable columns"
```

---

### Task 4: Tender lists: Agency filter, deadline sort, filter count, new look and phone cards

**Files:**
- Modify: `app/Queries/TenderListQuery.php`, `app/Livewire/TenderList.php`, `resources/views/livewire/tender-list.blade.php`
- Test: `tests/Feature/Livewire/TenderListTest.php`, `tests/Feature/Queries/TenderListQueryTest.php` (append; create if missing with the `use` lines shown)

**Interfaces:**
- Consumes:
  - `x-page-heading`, `x-filter-bar`, `x-filter-field`, `x-data-table`, `x-icon`, `x-avatar` (Tasks 1–3)
- Produces:
  - `TenderList` address settings `agency` (string) and `sort` (`''|'deadline_asc'|'deadline_desc'`)
  - `TenderList::toggleDeadlineSort(): void`
  - `TenderList::filterCount(): int`
  - `TenderListQuery` filter keys `agency` and `sort`

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/Livewire/TenderListTest.php`)

```php
it('filters by agency, including names with & and apostrophes, and ignores an agency not in the list', function () {
    Tender::factory()->create(['client' => "JABATAN KERJA RAYA & D'SERVIS", 'title' => 'JKR JOB']);
    Tender::factory()->create(['client' => 'KEMENTERIAN KESIHATAN', 'title' => 'KKM JOB']);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertSeeHtml('<option value="JABATAN KERJA RAYA &amp; D&#039;SERVIS">')
        ->set('agency', "JABATAN KERJA RAYA & D'SERVIS")->assertSee('JKR JOB')->assertDontSee('KKM JOB')
        ->set('agency', 'NOT AN AGENCY')->assertSee('JKR JOB')->assertSee('KKM JOB');
});

it('sorts by deadline when the Deadline heading is clicked, flipping on each click', function () {
    Tender::factory()->create(['title' => 'EARLY ONE', 'closing_date' => '2026-11-01']);
    Tender::factory()->create(['title' => 'LATE ONE', 'closing_date' => '2026-12-01']);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->call('toggleDeadlineSort')->assertSet('sort', 'deadline_asc')->assertSeeInOrder(['EARLY ONE', 'LATE ONE'])
        ->call('toggleDeadlineSort')->assertSet('sort', 'deadline_desc')->assertSeeInOrder(['LATE ONE', 'EARLY ONE'])
        ->set('sort', 'bogus')->assertSeeInOrder(['EARLY ONE', 'LATE ONE']); // In Progress default: closing soonest first
});

it('counts the filters that are on, but not the Dashboard date range', function () {
    $c = Livewire::test(TenderList::class, ['list' => 'in-progress']);
    expect($c->instance()->filterCount())->toBe(0);

    $c->set('mine', true)->set('mode', 'EP')->set('wo_from', '2026-10-01')->set('wo_to', '2026-10-31');
    expect($c->instance()->filterCount())->toBe(2);
    $c->assertSeeHtml('data-filter-count="2"')->assertSee('Registered 01 Oct 2026 – 31 Oct 2026');
});

it('shows each tender as a card on phones with the list’s own figures', function () {
    Tender::factory()->status(TenderStatus::Lost)->create(['wo_number' => 'CARD-1', 'winning_price_sen' => 86617900]);

    Livewire::test(TenderList::class, ['list' => 'lost'])
        ->assertSeeHtml('data-card="CARD-1"')->assertSee('Win price')->assertSee('RM 866,179.00');
});

it('shows document progress as done out of total', function () {
    $t = Tender::factory()->create();
    $t->documents()->delete(); // start from a known checklist
    $t->documents()->createMany([['name' => 'A', 'position' => 1, 'is_done' => true], ['name' => 'B', 'position' => 2, 'is_done' => false]]);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSee('1/2');
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/TenderListTest.php`
Expected: FAIL. The property `agency` does not exist, and the method `toggleDeadlineSort` does not exist.

- [ ] **Step 3: Implement**

`app/Queries/TenderListQuery.php`: after the `category` filter, add:
```php
        if (($agency = (string) ($filters['agency'] ?? '')) !== '') {
            $query->where('client', $agency);
        }
```
and replace the last two lines (`$direction = …` and the `return`) with:
```php
        $direction = match ($filters['sort'] ?? '') {
            'deadline_asc' => 'asc',
            'deadline_desc' => 'desc',
            default => $status === TenderStatus::InProgress ? 'asc' : 'desc',
        };

        return $query->orderBy('closing_date', $direction)->orderBy('id', $direction);
```

`app/Livewire/TenderList.php`:
- add the properties after `$category`:
```php
    #[Url] public string $agency = '';
    /** '' = the list's usual order; or deadline_asc / deadline_desc from the Deadline heading. */
    #[Url] public string $sort = '';
```
- add `'agency'` to the `updated()` list and to the `clearFilters()` reset list
- add the methods:
```php
    public function toggleDeadlineSort(): void
    {
        $this->sort = $this->sort === 'deadline_asc' ? 'deadline_desc' : 'deadline_asc';
        $this->resetPage();
    }

    /** Filters switched on in the Filters panel, plus My tenders. The Dashboard's date range shows separately. */
    public function filterCount(): int
    {
        return count(array_filter([$this->mine, $this->pic, $this->agency, $this->mode, $this->category, $this->from, $this->to]));
    }
```
- in `render()`:
```php
        $status = TenderStatus::fromSlug($this->list);
        $agencies = Tender::where('status', $status)->distinct()->orderBy('client')->pluck('client');
        $filters = ['search' => $this->search, 'mine' => $this->mine, 'mode' => $this->mode,
            'pic' => $this->pic, 'category' => $this->category, 'from' => $this->from, 'to' => $this->to,
            'wo_from' => $this->wo_from, 'wo_to' => $this->wo_to,
            'agency' => $agencies->contains($this->agency) ? $this->agency : '',
            'sort' => in_array($this->sort, ['deadline_asc', 'deadline_desc'], true) ? $this->sort : ''];
```
and pass `'agencies' => $agencies` to the view (add `use App\Models\Tender;` to the imports, changing `use App\Models\User;` to `use App\Models\{Tender, User};`).

Replace `resources/views/livewire/tender-list.blade.php` with:
```blade
@php
    use App\Enums\TenderStatus;
    use App\Support\{Money, Percent};
    $isOpen = $status === TenderStatus::InProgress;
    $field = 'w-full rounded-[9px] border border-line-2 bg-surface px-2.5 py-1.5 text-[13px]';
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
    $td = 'px-3.5 py-3 align-top';
    $arrow = ['deadline_asc' => '↑', 'deadline_desc' => '↓'][$sort] ?? '';
    $docs = fn ($t) => [(int) ($t->documents_done_count ?? 0), (int) ($t->documents_count ?? 0)];
    $deadlineClass = fn (?string $closing) => match ($closing) { 'soon' => 'font-semibold text-warn-ink', 'overdue' => 'font-semibold text-bad-ink', default => '' };
    // The list's own money columns: [label, value] pairs, used by the table and the phone cards
    $extra = function ($t) use ($status) {
        return match ($status) {
            TenderStatus::Done => [['Submit price', Money::format($t->submitted_price_sen)], ['Company variant', $t->companyVariant() ?? '—'],
                ['Gross', ($g = $t->costingSummary()) ? Percent::format($g['margin_bp']) : '—']],
            TenderStatus::Lost => [['Submitted price', Money::format($t->submitted_price_sen)], ['Win price', Money::format($t->winning_price_sen)],
                ['Win variant', $t->winVariant() ?? '—']],
            TenderStatus::Awarded => [['Submit price', Money::format($t->submitted_price_sen)]],
            default => [],
        };
    };
    // Header labels for $extra: must match its pairs, in order (headings need no model calls)
    $extraLabels = match ($status) {
        TenderStatus::Done => ['Submit price', 'Company variant', 'Gross'],
        TenderStatus::Lost => ['Submitted price', 'Win price', 'Win variant'],
        TenderStatus::Awarded => ['Submit price'],
        default => [],
    };
    $actualGp = function ($t) {
        $pd = $t->project?->summary();
        if (! $pd) return ['—', false, null];
        if ($pd['pnl']['actual']['revenue'] <= 0) return ['—', false, $t->project->isOpen() ? null : 'Closed'];
        return [Percent::format($pd['pnl']['actual']['gp_bp']), $pd['below_margin'], $t->project->isOpen() ? null : 'Closed'];
    };
@endphp
<div class="space-y-4">
    <x-page-heading :title="$status->listTitle()" :subtitle="$status->listSubtitle()">
        @if ($isOpen)
            <x-slot:actions>
                <button type="button" wire:click="$dispatch('open-register-tender')"
                        class="flex items-center gap-1.5 rounded-[11px] bg-accent px-4 py-2.5 text-[12.5px] font-bold text-accent-ink hover:bg-accent-solid">
                    <x-icon name="plus" class="h-[15px] w-[15px]" /> Register Tender
                </button>
            </x-slot:actions>
        @endif
    </x-page-heading>

    <x-filter-bar :count="$this->filterCount()" placeholder="Search WO, code, title, agency" :mine="$mine">
        <x-filter-field label="PIC">
            <select wire:model.live="pic" class="{{ $field }}"><option value="">All PICs</option>
                @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Agency">
            <select wire:model.live="agency" class="{{ $field }}"><option value="">All agencies</option>
                @foreach ($agencies as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Tender mode">
            <select wire:model.live="mode" class="{{ $field }}"><option value="">All modes</option>
                @foreach ($modes as $m) <option value="{{ $m->value }}">{{ $m->label() }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Category">
            <select wire:model.live="category" class="{{ $field }}"><option value="">All categories</option>
                @foreach ($categories as $c) <option value="{{ $c->value }}">{{ $c->value }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Deadline from"><input type="date" wire:model.live="from" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Deadline to"><input type="date" wire:model.live="to" class="{{ $field }}"></x-filter-field>
    </x-filter-bar>

    @if ($wo_from !== '' && $wo_to !== '')
        <p class="text-sm text-muted">
            Registered {{ \Carbon\CarbonImmutable::parse($wo_from)->format('d M Y') }} – {{ \Carbon\CarbonImmutable::parse($wo_to)->format('d M Y') }}
            (from the Dashboard / Status) · <button type="button" wire:click="$set('wo_from', ''); $set('wo_to', '')" class="underline">show all dates</button>
        </p>
    @endif

    {{-- Desktop / tablet table --}}
    <x-data-table :resizable="'list-'.$list" class="hidden md:block">
        <thead class="bg-subtle">
            <tr>
                <th class="{{ $th }}">WO Number</th>
                <th class="{{ $th }}">Tender</th>
                <th class="{{ $th }}">Agency</th>
                <th class="{{ $th }}">Assigned To</th>
                <th class="{{ $th }} cursor-pointer select-none hover:text-ink" wire:click="toggleDeadlineSort" data-col-label="Deadline">Deadline <span class="text-ink">{{ $arrow }}</span></th>
                @if ($isOpen) <th class="{{ $th }}">Briefing</th> @endif
                <th class="{{ $th }} text-right">Est. Value</th>
                @if ($isOpen) <th class="{{ $th }}">Documents</th> @endif
                @foreach ($extraLabels as $label) <th class="{{ $th }} text-right">{{ $label }}</th> @endforeach
                @if ($status === TenderStatus::Awarded) <th class="{{ $th }} text-right" title="Actual gross profit from the PD">Actual GP</th> @endif
            </tr>
        </thead>
        <tbody>
        @forelse ($tenders as $t)
            @php $closing = $t->closingState(); [$done, $total] = $docs($t); @endphp
            <tr wire:key="tender-{{ $t->id }}" data-closing="{{ $closing }}" onclick="window.location='{{ route('tenders.show', $t) }}'"
                class="cursor-pointer border-t border-line hover:bg-subtle">
                <td class="{{ $td }} whitespace-nowrap">
                    <a href="{{ route('tenders.show', $t) }}" class="font-bold hover:underline">{{ $t->wo_number }}</a>
                    <div class="text-[11.5px] text-muted-2">{{ $t->wo_date->format('d M Y') }}</div>
                </td>
                <td class="{{ $td }}">
                    <div class="text-[11px] font-semibold text-muted-2">{{ $t->tender_code }}</div>
                    <div class="line-clamp-2" title="{{ $t->title }}">{{ $t->title }}</div>
                    @if ($t->was_cancelled) <span class="mt-1 inline-block rounded bg-bad-bg px-1.5 text-xs text-bad-ink">Cancelled</span> @endif
                </td>
                <td class="{{ $td }}">{{ $t->client }}</td>
                <td class="{{ $td }}"><div class="flex items-center gap-2"><x-avatar :user="$t->pic" /> <span>{{ $t->pic->name }}</span></div></td>
                <td @if ($closing) data-deadline="{{ $closing }}" @endif class="{{ $td }} whitespace-nowrap {{ $deadlineClass($closing) }}">{{ $t->closing_date->format('d M Y') }}</td>
                @if ($isOpen)
                    <td class="{{ $td }}"><span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-good-bg text-good-ink' => $t->has_briefing, 'bg-bad-bg text-bad-ink' => ! $t->has_briefing])>{{ $t->has_briefing ? 'Yes' : 'No' }}</span></td>
                @endif
                <td class="{{ $td }} whitespace-nowrap text-right">{{ Money::format($t->estimated_value_sen) }}</td>
                @if ($isOpen)
                    <td class="{{ $td }}"><div class="flex items-center gap-2">
                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $t->documentPercent() }}%"></div></div>
                        <span class="text-[11.5px] text-muted">{{ $done }}/{{ $total }}</span></div></td>
                @endif
                @foreach ($extra($t) as [, $value]) <td class="{{ $td }} whitespace-nowrap text-right">{{ $value }}</td> @endforeach
                @if ($status === TenderStatus::Awarded)
                    @php [$gp, $below, $closed] = $actualGp($t); @endphp
                    <td class="{{ $td }} whitespace-nowrap text-right"><span @class(['text-bad-ink' => $below])>{{ $gp }}</span>
                        @if ($closed) <span class="ml-1 rounded-full bg-subtle px-2 py-0.5 text-xs">{{ $closed }}</span> @endif</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="12" class="px-3 py-10 text-center text-muted">No tenders match your search.</td></tr>
        @endforelse
        </tbody>
    </x-data-table>

    {{-- Phone cards --}}
    <div class="space-y-2.5 md:hidden">
        @forelse ($tenders as $t)
            @php $closing = $t->closingState(); [$done, $total] = $docs($t); @endphp
            <a href="{{ route('tenders.show', $t) }}" wire:key="card-{{ $t->id }}" data-card="{{ $t->wo_number }}" class="block min-w-0 rounded-2xl border border-line bg-surface p-4 active:bg-subtle">
                <div class="flex items-center justify-between gap-2 text-[12px]">
                    <span class="font-bold">{{ $t->wo_number }}</span>
                    <span class="{{ $deadlineClass($closing) ?: 'text-muted' }}">Due {{ $t->closing_date->format('d M Y') }}</span>
                </div>
                <p class="mt-1.5 line-clamp-2 text-[13.5px] font-semibold">{{ $t->title }}</p>
                <p class="mt-0.5 truncate text-[11.5px] text-muted">{{ $t->client }} · {{ $t->tender_code }}</p>
                <div class="mt-2.5 flex items-center justify-between gap-2 text-[12.5px]">
                    <span class="flex min-w-0 items-center gap-2"><x-avatar :user="$t->pic" /> <span class="truncate">{{ $t->pic->name }}</span></span>
                    <span class="whitespace-nowrap font-semibold">{{ Money::format($t->estimated_value_sen) }}</span>
                </div>
                @if ($isOpen)
                    <div class="mt-2.5 flex items-center gap-2 text-[11.5px] text-muted">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $t->documentPercent() }}%"></div></div>
                        Docs {{ $done }}/{{ $total }} · Briefing {{ $t->has_briefing ? 'Yes' : 'No' }}
                    </div>
                @endif
                @if ($extra($t))
                    <div class="mt-2.5 grid grid-cols-3 gap-2 rounded-xl bg-subtle p-2.5 text-[11px]">
                        @foreach ($extra($t) as [$label, $value]) <div class="min-w-0"><div class="text-muted">{{ $label }}</div><div class="truncate font-semibold">{{ $value }}</div></div> @endforeach
                        @if ($status === TenderStatus::Awarded) <div><div class="text-muted">Actual GP</div><div class="font-semibold">{{ $actualGp($t)[0] }}</div></div> @endif
                    </div>
                @endif
            </a>
        @empty
            <p class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">No tenders match your search.</p>
        @endforelse
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-2 text-[12.5px] text-muted">
        <span>@if ($tenders->total()) Showing {{ $tenders->firstItem() }}–{{ $tenders->lastItem() }} of {{ $tenders->total() }} tenders @endif</span>
        {{ $tenders->links('pagination.pager') }}
    </footer>

    @if ($isOpen) <livewire:register-tender-modal /> @endif
</div>
```

Existing tests to re-check, with rulings if they need edits:
- "No tenders match." → "No tenders match your search."
- "Showing 1–10 of 12" is still a prefix of the new text.
- The plain-rows test asserts `font-semibold text-warn-ink`: still true.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/TenderListTest.php tests/Feature/Queries`
Expected: PASS (all old + 5 new).

- [ ] **Step 5: Commit**

```bash
git add app/Queries/TenderListQuery.php app/Livewire/TenderList.php resources/views/livewire/tender-list.blade.php tests/Feature/Livewire/TenderListTest.php
git commit -m "feat(ui): tender lists in the prototype style — Filters panel with Agency, deadline sort, resizable columns, phone cards"
```

---

### Task 5: Dashboard in the prototype layout

**Files:**
- Modify: `resources/views/livewire/dashboard.blade.php`, `resources/views/reports/period-filter.blade.php`
- Test: `tests/Feature/Livewire/DashboardTest.php`

**Interfaces:**
- Consumes:
  - `x-icon`, `x-avatar`, `x-initials`, `x-data-table` (Tasks 1–3)
  - the existing view data `$r`, `$deadlines`, `$quotes`, `$projects`, `$reportPeriod`, `$today`
- Produces:
  - the markers `data-kpi="…"` (×6) and `data-mode-bar` with `data-ep-pct="NN"`

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/Livewire/DashboardTest.php`; reuse its existing imports and helpers)

```php
it('lays out the six Quick Overview boxes like the prototype', function () {
    $this->actingAs(App\Models\User::factory()->create());

    $html = Livewire\Livewire::test(App\Livewire\Dashboard::class)->html();
    foreach (['In Progress', 'Awarded', 'Done', 'Lost', 'Win rate', 'Portfolio value'] as $k) {
        expect($html)->toContain('data-kpi="'.$k.'"');
    }
});

it('splits the submission-mode bar by EP and Non-EP count, and draws nothing without tenders', function () {
    $this->actingAs(App\Models\User::factory()->create());
    Livewire\Livewire::test(App\Livewire\Dashboard::class)->assertDontSeeHtml('data-mode-bar');

    App\Models\Tender::factory()->count(3)->create(['mode' => App\Enums\TenderMode::Ep]);
    App\Models\Tender::factory()->create(['mode' => App\Enums\TenderMode::NonEp]);
    Livewire\Livewire::test(App\Livewire\Dashboard::class)->assertSeeHtml('data-mode-bar')->assertSeeHtml('data-ep-pct="75"');
});
```
(Enum cases: `TenderMode::Ep` = EP, `TenderMode::NonEp` = NON_EP.)

- [ ] **Step 2: Run them and watch them fail**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/DashboardTest.php`
Expected: FAIL. `data-kpi` and `data-mode-bar` are not found.

- [ ] **Step 3: Implement**

Replace `resources/views/reports/period-filter.blade.php` with the prototype's corner picker (same settings and same text):
```blade
@php $in = 'rounded-lg border border-line-2 bg-surface px-2.5 py-1.5 text-xs'; @endphp
<div class="flex flex-wrap items-center gap-2 rounded-[11px] border border-[var(--qo-card-border)] bg-[var(--qo-card)] py-1.5 pl-3 pr-1.5 text-sm">
    <label class="flex items-center gap-2 text-[11.5px] font-bold text-ink-2">Period
        <select wire:model.live="period" class="{{ $in }} font-normal" aria-label="Period">
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
    <span class="pr-1.5 text-[11px] text-muted">{{ $reportPeriod->label() }} · by WO date</span>
</div>
@if ($reportPeriod->invalid && $period !== 'all')
    <p class="mt-1 text-xs text-warn-ink">That period wasn't valid — showing all time.</p>
@endif
```

Replace `resources/views/livewire/dashboard.blade.php` with the code below. Keep the `@php` header and add:
```php
    $card = 'min-w-0 rounded-[20px] border border-line bg-surface p-5'; // min-w-0: long titles must not stretch the grid on phones
    $tile = fn (string $icon) => '<span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-accent-tint text-good-ink">'.\Illuminate\Support\Facades\Blade::render('<x-icon name="'.$icon.'" class="h-[17px] w-[17px]" />').'</span>';
    $epTotal = $r['modes']['EP']['total'] + $r['modes']['NON_EP']['total'];
    $epPct = $epTotal ? (int) round($r['modes']['EP']['total'] * 100 / $epTotal) : 0;
```
Keep `$c`, `$rate`, `$segments`, `$circumference` and `$overview` as they are. The `$overview` hints become the bracketed notes.

The body:
```blade
<div class="space-y-5">
    {{-- Quick Overview --}}
    <section class="relative overflow-hidden rounded-[20px] border border-line px-6 pb-6 pt-5"
             style="background: linear-gradient(115deg, var(--qo-1) 0%, var(--qo-2) 40%, var(--qo-3) 72%, var(--qo-4) 100%)">
        <div class="pointer-events-none absolute -right-[110px] -top-[130px] h-[360px] w-[360px] rounded-full" style="background: radial-gradient(circle at 35% 35%, rgba(140,196,246,.35), rgba(140,196,246,0) 68%)"></div>
        <div class="pointer-events-none absolute -bottom-[150px] -left-[120px] h-[340px] w-[340px] rounded-full" style="background: radial-gradient(circle at 60% 40%, rgba(186,164,255,.35), rgba(186,164,255,0) 70%)"></div>
        <div class="relative">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-base font-bold">Quick Overview</h1>
                    <p class="mt-0.5 text-xs text-muted">Every card below follows the selected period of WO dates.</p>
                </div>
                <div>@include('reports.period-filter')</div>
            </div>
            @if ($c['total'] === 0) <p class="mt-3 text-sm text-muted">No tenders registered in this period.</p> @endif
            <div class="mt-[18px] grid grid-cols-2 gap-[13px] md:grid-cols-3 xl:grid-cols-6">
                @foreach ($overview as [$label, $value, $hint, $href])
                    <{{ $href ? 'a href='.$href : 'div' }} data-kpi="{{ $label }}"
                        class="flex min-w-0 flex-col justify-between rounded-[14px] border border-[var(--qo-card-border)] bg-[var(--qo-card)] px-[18px] py-4 shadow-[0_6px_18px_var(--shadow-soft)] backdrop-blur-[6px] {{ $href ? 'hover:bg-surface' : '' }}">
                        <div class="flex flex-wrap items-baseline gap-x-[7px]">
                            <span class="text-2xl font-extrabold tracking-tight">{{ $value }}</span>
                            <span class="text-[12px] text-muted-2">({{ $hint }})</span>
                        </div>
                        <div class="mt-1.5 text-[13px] font-semibold text-ink-2">{{ $label }}</div>
                    </{{ $href ? 'a' : 'div' }}>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,38fr)_minmax(0,62fr)]">
        {{-- Upcoming deadlines --}}
        <section class="{{ $card }} lg:self-start">
            <div class="mb-3 flex items-center gap-3">{!! $tile('calendar') !!}
                <div><h2 class="font-bold">Upcoming deadlines</h2><p class="text-xs text-muted">Live tenders, closing soonest first</p></div></div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($deadlines as $t)
                    @php $days = (int) $today->diffInDays($t->closing_date, false); @endphp
                    <li class="flex items-center gap-3 py-2.5">
                        <x-avatar :user="$t->pic" />
                        <a href="{{ route('tenders.show', $t) }}" class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $t->title }}</p>
                            <p class="truncate text-xs text-muted">{{ $t->client }}</p>
                        </a>
                        <span @class(['whitespace-nowrap text-xs font-semibold', 'text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>{{ $t->closing_date->format('d M Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-muted">Nothing closing soon.</li>
                @endforelse
            </ul>
        </section>

        <div class="min-w-0 space-y-5">
            {{-- Portfolio mix --}}
            <section class="{{ $card }}">
                <div class="mb-4 flex items-center gap-3">{!! $tile('chart') !!}
                    <div><h2 class="font-bold">Portfolio mix</h2><p class="text-xs text-muted">Where every tender sits, and how it is submitted</p></div></div>
                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="min-w-0">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.5px] text-muted">By status · {{ $reportPeriod->label() }}</p>
                        {{-- keep the existing ring <div class="relative h-32 w-32">…</div> and legend <ul>…</ul> markup here, unchanged, inside a "flex flex-wrap items-center gap-6" div --}}
                    </div>
                    <div class="min-w-0">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.5px] text-muted">By submission mode · {{ $reportPeriod->label() }}</p>
                        @if ($epTotal > 0)
                            <div data-mode-bar data-ep-pct="{{ $epPct }}" class="mb-3 flex h-2.5 overflow-hidden rounded-full bg-hover" title="EP {{ $epPct }}% · Non-EP {{ 100 - $epPct }}%">
                                <div class="h-full bg-good-ink" style="width: {{ $epPct }}%"></div>
                                <div class="h-full bg-muted-2" style="width: {{ 100 - $epPct }}%"></div>
                            </div>
                        @endif
                        <div class="space-y-2.5">
                            @foreach (['EP' => ['EP', 'bg-good-ink'], 'NON_EP' => ['Non-EP', 'bg-muted-2']] as $key => [$label, $dot])
                                @php $m = $r['modes'][$key]; @endphp
                                <div class="rounded-xl border border-line p-3 text-sm">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <span class="flex items-center gap-2 font-bold"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $label }} <span class="text-lg">{{ $m['total'] }}</span>
                                            <span class="text-xs font-normal text-muted">{{ $epTotal ? round($m['total'] * 100 / $epTotal) : 0 }}%</span></span>
                                        <span class="whitespace-nowrap font-semibold">{{ Money::format($m['bid_value_sen']) }}</span>
                                    </div>
                                    <div class="mt-1.5 grid grid-cols-4 gap-1 text-xs text-muted">
                                        <span>In Progress<br><b class="text-ink">{{ $m['in_progress'] }}</b></span>
                                        <span>Done<br><b class="text-ink">{{ $m['done'] }}</b></span>
                                        <span>Awarded<br><b class="text-ink">{{ $m['awarded'] }}</b></span>
                                        <span>Lost<br><b class="text-ink">{{ $m['lost'] }}</b></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- PIC summary --}}
            <section class="{{ $card }}">
                <div class="mb-3 flex items-center gap-3">{!! $tile('staff') !!}
                    <div><h2 class="font-bold">PIC summary</h2><p class="text-xs text-muted">Tenders and bid value per person in charge</p></div></div>
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[520px] text-[13px]">
                        <thead class="bg-subtle"><tr>
                            <th class="px-3.5 py-2.5 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">PIC</th>
                            <th class="px-3.5 py-2.5 text-right text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">Tenders</th>
                            <th class="px-3.5 py-2.5 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">Share of value</th>
                            <th class="px-3.5 py-2.5 text-right text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted">Bid value</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($r['pics'] as $p)
                            <tr class="border-t border-line">
                                <td class="px-3.5 py-2.5"><a href="{{ route('status', $reportPeriod->addressParams()) }}" class="flex items-center gap-2 hover:underline"><x-initials :name="$p['name']" /> {{ $p['name'] }}</a></td>
                                <td class="px-3.5 py-2.5 text-right">{{ $p['total'] }}</td>
                                <td class="px-3.5 py-2.5"><div class="h-2 w-40 overflow-hidden rounded-full bg-hover"><div class="h-full bg-good-ink" style="width: {{ $p['share_bp'] / 100 }}%"></div></div></td>
                                <td class="whitespace-nowrap px-3.5 py-2.5 text-right">{{ Money::format($p['bid_value_sen']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-line bg-subtle font-bold">
                            <td class="px-3.5 py-2.5">Grand total</td><td class="px-3.5 py-2.5 text-right">{{ $r['totals']['total'] }}</td><td></td>
                            <td class="whitespace-nowrap px-3.5 py-2.5 text-right">{{ Money::format($r['totals']['bid_value_sen']) }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <a href="{{ route('quotations.index') }}" class="{{ $card }} flex gap-3 hover:bg-hover">{!! $tile('quotation') !!}
            <div><h2 class="font-bold">Quotations</h2>
                <p class="mt-1 text-sm"><b>{{ $quotes['open'] }}</b> open · {{ Money::format($quotes['open_total_sen']) }}</p>
                <p class="text-sm"><b>{{ $quotes['accepted'] }}</b> accepted in the period · {{ Money::format($quotes['accepted_total_sen']) }}</p></div>
        </a>
        <a href="{{ route('tenders.index', 'awarded') }}" class="{{ $card }} flex gap-3 hover:bg-hover">{!! $tile('tenders') !!}
            <div><h2 class="font-bold">Projects</h2>
                <p class="mt-1 text-sm"><b>{{ $projects['running'] }}</b> running</p>
                <p @class(['text-sm', 'text-bad-ink' => $projects['below_margin'] > 0])><b>{{ $projects['below_margin'] }}</b> below approved margin</p>
                <p class="text-xs text-muted">Current state — not affected by the period.</p></div>
        </a>
    </div>
</div>
```
The ring and legend markup moves unchanged from the old file into the "By status" column. In the ring, change the background ring stroke from `var(--color-subtle)` to `var(--color-hover)` so it shows on white.

The dynamic `<{{ … }}>` tag: if Blade escaping of `$href` matters, write the two branches out instead (`@if ($href) <a href="{{ $href }}" …> @else <div …> @endif`), the same as the old file did. **Use the explicit two-branch form.** It's safer, and the old tests assert the escaped URLs.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/DashboardTest.php tests/Feature/Livewire/StatusReportTest.php`
Expected: PASS. All old Dashboard assertions still pass: the same words, numbers, links and "Grand total".

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard.blade.php resources/views/reports/period-filter.blade.php tests/Feature/Livewire/DashboardTest.php
git commit -m "feat(ui): Dashboard in the prototype layout — Quick Overview panel, EP/Non-EP bar, icon tiles"
```

---

### Task 6: Status in the prototype style with phone cards

**Files:**
- Modify: `resources/views/livewire/status-report.blade.php`
- Test: `tests/Feature/Livewire/StatusReportTest.php`

**Interfaces:**
- Consumes:
  - `x-page-heading`, `x-data-table`, `x-initials` (Tasks 1–3)
  - the view data `$rows`, `$totals`, `$sort`, `$dir`, `$reportPeriod`
- Produces:
  - the marker `data-pic-card="<user_id>"`

- [ ] **Step 1: Write the failing test** (append)

```php
it('shows each PIC as a card on phones, with initials and the same drill-down links', function () {
    $this->actingAs($u = App\Models\User::factory()->create(['name' => 'Nurul Ain']));
    App\Models\Tender::factory()->status(App\Enums\TenderStatus::Done)->create(['pic_id' => $u->id]);

    Livewire\Livewire::test(App\Livewire\StatusReport::class)
        ->assertSeeHtml('data-pic-card="'.$u->id.'"')->assertSeeHtml('>NA</span>')
        ->assertSeeHtml('data-resizable="status"');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/StatusReportTest.php`
Expected: FAIL. `data-pic-card` is not found.

- [ ] **Step 3: Implement.** Replace the view's body (keep the `@php` header with `$cols`, `$lists` and `$cell`), adding `$th` and `$td` from Task 4:
```blade
<div class="space-y-4">
    <x-page-heading title="Status" subtitle="Per-PIC tender performance">
        <x-slot:actions>@include('reports.period-filter')</x-slot:actions>
    </x-page-heading>

    <x-data-table resizable="status" min-width="860px" class="hidden md:block">
        <thead class="bg-subtle"><tr>
            @foreach ($cols as $k => $label)
                <th @class([$th, 'text-right' => $k !== 'name'])>
                    <button type="button" wire:click="sortBy('{{ $k }}')" class="uppercase hover:text-ink">{{ $label }}
                        @if ($sort === $k) <span class="text-ink">{{ $dir === 'asc' ? '↑' : '↓' }}</span> @endif</button>
                </th>
            @endforeach
        </tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr wire:key="pic-{{ $row['user_id'] }}" class="border-t border-line hover:bg-subtle">
                @foreach ($cols as $k => $label)
                    <td @class([$td, 'whitespace-nowrap', 'text-right' => $k !== 'name'])>
                        @if ($k === 'name')
                            <span class="flex items-center gap-2"><x-initials :name="$row['name']" /> {{ $row['name'] }}</span>
                        @elseif (isset($lists[$k]) && $row[$k] > 0)
                            <a href="{{ route('tenders.index', [$lists[$k], 'pic' => $row['user_id'], ...$reportPeriod->listFilters()]) }}" class="underline">{{ $row[$k] }}</a>
                        @else
                            {{ $cell($row, $k) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        <tr class="border-t border-line bg-subtle font-bold">
            @foreach ($cols as $k => $label)
                <td @class([$td, 'whitespace-nowrap', 'text-right' => $k !== 'name'])>{{ $cell($totals, $k) }}</td>
            @endforeach
        </tr>
        </tbody>
    </x-data-table>

    <div class="space-y-2.5 md:hidden">
        @foreach ($rows as $row)
            <div data-pic-card="{{ $row['user_id'] }}" wire:key="pic-card-{{ $row['user_id'] }}" class="min-w-0 rounded-2xl border border-line bg-surface p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2 font-semibold"><x-initials :name="$row['name']" /> <span class="truncate">{{ $row['name'] }}</span></span>
                    <span class="whitespace-nowrap text-sm font-semibold">{{ $cell($row, 'bid_value_sen') }}</span>
                </div>
                <div class="mt-3 grid grid-cols-5 gap-1.5 text-center text-[11px]">
                    @foreach (['total' => 'Total', 'awarded' => 'Won', 'lost' => 'Lost', 'in_progress' => 'Active', 'win_rate_bp' => 'Win rate'] as $k => $label)
                        <div class="rounded-lg bg-subtle p-1.5"><div class="text-muted">{{ $label }}</div>
                            @if (isset($lists[$k]) && $row[$k] > 0)
                                <a href="{{ route('tenders.index', [$lists[$k], 'pic' => $row['user_id'], ...$reportPeriod->listFilters()]) }}" class="font-bold underline">{{ $row[$k] }}</a>
                            @else
                                <div class="font-bold">{{ $cell($row, $k) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-muted">Win rate = Awarded ÷ (Awarded + Lost), cancelled tenders left out. Bid value uses the submitted price, or the estimated value for tenders still in progress.</p>
</div>
```

- [ ] **Step 4: Run the tests and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/StatusReportTest.php`
Expected: PASS. Old in-order assertions still hold because the table comes before the cards.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/status-report.blade.php tests/Feature/Livewire/StatusReportTest.php
git commit -m "feat(ui): Status page in the prototype style with initials, resizable columns and phone cards"
```

---

### Task 7: Find Tenders in the same style

**Files:**
- Modify: `app/Livewire/FindTenders.php`, `resources/views/livewire/find-tenders.blade.php`
- Test: `tests/Feature/Livewire/FindTendersTest.php`

**Interfaces:**
- Consumes:
  - `x-page-heading`, `x-filter-bar`, `x-filter-field`, `x-data-table`, `x-icon` (Tasks 1–3)
- Produces:
  - `FindTenders::filterCount(): int`

- [ ] **Step 1: Write the failing test** (append)

```php
it('counts only the filters changed from their defaults, and shows phone cards', function () {
    $this->actingAs(App\Models\User::factory()->create());
    $c = Livewire\Livewire::test(App\Livewire\FindTenders::class);
    expect($c->instance()->filterCount())->toBe(0);
    $c->assertSeeHtml('x-data="{ open: false }"');

    $c->set('status', 'all')->set('source', 'span')->set('codes', '210103');
    expect($c->instance()->filterCount())->toBe(3);
    $c->assertSeeHtml('data-filter-count="3"')->assertSeeHtml('data-resizable="find-tenders"');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/FindTendersTest.php`
Expected: FAIL. The method `filterCount` does not exist.

- [ ] **Step 3: Implement**

In `app/Livewire/FindTenders.php` add:
```php
    /** Filters changed from their starting values (status starts as "open"). */
    public function filterCount(): int
    {
        return count(array_filter([$this->status !== 'open', $this->source, $this->type, $this->ministry, $this->codes, $this->from, $this->to]));
    }
```

In `resources/views/livewire/find-tenders.blade.php`:
- set `$field` to the Task 4 control class
- add `$th` and `$td` from Task 4
- replace the `<header>` with:
```blade
    <x-page-heading title="Find Tenders" subtitle="Government tenders collected from MyProcurement, SPAN and LLM">
        @can('collect-now')
            <x-slot:actions>
                <button type="button" wire:click="collectNow" class="rounded-[11px] bg-chip px-4 py-2.5 text-[12.5px] font-bold text-chip-ink hover:bg-chip-hover">Collect now</button>
            </x-slot:actions>
        @endcan
    </x-page-heading>
```
- give the status `<section>` the classes `rounded-2xl border border-line bg-surface px-4 py-2.5 text-[13px]`, keeping its contents
- replace the filter `<section>…</section>` with:
```blade
    <x-filter-bar :count="$this->filterCount()" placeholder="Search title, reference, agency" debounce="400">
        <x-filter-field label="Status">
            <select wire:model.live="status" class="{{ $field }}"><option value="open">Open</option><option value="closed">Closed</option><option value="all">All</option></select>
        </x-filter-field>
        <x-filter-field label="Source">
            <select wire:model.live="source" class="{{ $field }}"><option value="">All sources</option>
                @foreach ($sources as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Type">
            <select wire:model.live="type" class="{{ $field }}"><option value="">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
        </x-filter-field>
        <x-filter-field label="Ministry">
            <input wire:model.live.debounce.400ms="ministry" list="ministry-options" placeholder="Any ministry" class="{{ $field }}">
            <datalist id="ministry-options">@foreach ($ministries as $m) <option value="{{ $m }}"></option> @endforeach</datalist>
        </x-filter-field>
        <x-filter-field label="Field codes"><input wire:model.live.debounce.400ms="codes" placeholder="e.g. 210103, E05" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Closing from"><input type="date" wire:model.live="from" class="{{ $field }}"></x-filter-field>
        <x-filter-field label="Closing to"><input type="date" wire:model.live="to" class="{{ $field }}"></x-filter-field>
    </x-filter-bar>
```
- replace the table `<div class="overflow-x-auto …"><table …>` wrapper with `<x-data-table resizable="find-tenders" min-width="960px" class="hidden md:block">` (closing `</x-data-table>`). Use `<thead class="bg-subtle">` with `$th` header cells and `$td` body cells. Rows are `border-t border-line hover:bg-subtle`; the cell contents are unchanged.
- add phone cards after the table:
```blade
    <div class="space-y-2.5 md:hidden">
        @forelse ($tenders as $t)
            @php $days = $t->status === 'open' ? $t->daysLeft() : null; @endphp
            <a href="{{ route('find-tenders.show', $t) }}" wire:key="ct-card-{{ $t->id }}" class="block min-w-0 rounded-2xl border border-line bg-surface p-4">
                <div class="flex items-center justify-between gap-2 text-[12px]">
                    <span class="truncate font-bold">{{ $t->reference_no ?: '—' }}</span>
                    <span @class(['whitespace-nowrap', 'text-bad-ink' => $days !== null && $days <= 3, 'text-muted' => $days === null || $days > 3])>{{ $t->closing_date?->format('d M Y') ?? '—' }}</span>
                </div>
                <p class="mt-1.5 line-clamp-3 text-[13.5px] font-semibold">{{ $t->title }}</p>
                <p class="mt-0.5 truncate text-[11.5px] text-muted">{{ $t->ministry ?? '—' }} · {{ $types[$t->procurement_type] ?? '—' }}</p>
                <p class="mt-2 text-right text-[12.5px] font-semibold">{{ Money::format($t->indicative_price_sen) }}</p>
            </a>
        @empty
            <p class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">No tenders match.</p>
        @endforelse
    </div>
```
- footer text: `text-[12.5px]`; the content is unchanged.

- [ ] **Step 4: Run the tests and watch them pass**

Run: `… ./vendor/bin/pest tests/Feature/Livewire/FindTendersTest.php`
Expected: PASS (old + new).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/FindTenders.php resources/views/livewire/find-tenders.blade.php tests/Feature/Livewire/FindTendersTest.php
git commit -m "feat(ui): Find Tenders in the prototype style — Filters panel, resizable columns, phone cards"
```

---

### Task 8: README note, full suite, and live browser check

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add a README section** after "Dashboard and Status":
```markdown
## Look and feel

The screens follow the Claude Design prototype (copy in `docs/superpowers/plans/assets/prototype-ui/`).
- The sidebar folds to an icon strip (button beside the logo); the choice is kept in a `sidebar` cookie.
- Drag the edge of a table heading to resize a column; widths are kept in this browser
  (`localStorage`, key `tenderhub-cols:<table>`). Double-click the edge to reset.
- Lists show a **Filters** panel; the number on the button is how many filters are on.
```

- [ ] **Step 2: Run the full suite with coverage**

Run: `MSYS_NO_PATHCONV=1 docker compose exec -T app ./vendor/bin/pest --coverage --min=80`
Expected: all tests pass, coverage ≥ 80%.

- [ ] **Step 3: Live browser check** (built-in browser at `http://localhost:8080`, signed in with the seed admin from `config/tenderhub.php`). For each check, record what you saw in the ledger:
  1. `/dashboard`, `/tenders/in-progress`, `/tenders/done`, `/status`, `/find-tenders` at 1440, 1024 and 375 px wide: `document.documentElement.scrollWidth === clientWidth` on each; screenshot each at 1440.
  2. Fold the sidebar, reload: it is still folded with no flash. At 375 px the ☰ drawer shows full labels.
  3. On In Progress, drag the Tender column wider, change a filter (Livewire re-render), then reload: the width is kept and each heading has exactly one handle (`document.querySelectorAll('thead th [data-col-handle]').length === th count`). Double-click resets it.
  4. Open Filters, pick an Agency: the list narrows and the count shows 1. Click the Deadline heading twice: the arrow flips.
  5. Dashboard: click the "In Progress" box with Period = This month: it lands on a filtered list with the "Registered …" note.
  6. Toggle dark mode and repeat a screenshot of the Dashboard and a list.
  7. No queue-worker restart is needed (no background-job code changed).

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: look-and-feel notes for the prototype-style screens"
```
