# CMT Tender Hub — UI Round 1: match the Claude Design prototype

**Date:** 2026-10-07 · **Branch:** `ui-round-1` (built on `plain-in-progress-rows`)
**Reference:** the user's prototype `Downloads\TenderHub.html`, unpacked into
`docs/superpowers/plans/assets/prototype-ui/markup.html` (screens, inline styles) and
`logic.js` (icons, nav, dashboard numbers, column resizing). Section line numbers below refer to
`markup.html`.

## Goal

Make the screens people use all day look and behave like the prototype. Round 1 covers the page
frame (sidebar, top bar, page title), the four tender lists, the Dashboard, Status and Find Tenders.
Round 2 (separate spec, later) covers tender detail tabs, Quotations, settings pages and pop-ups.

**Nothing behind the screens changes:** queries, permissions, page addresses (URLs and their
`?period=…`, `?pic=…`, `wo_from/wo_to` settings), Livewire actions and report numbers stay as
they are. This is presentation plus three small behaviours: sidebar folding, resizable
columns, and the deadline sort and Agency filter on the lists.

## User decisions (from brainstorming)

- "Retractable table" = **resizable columns** (drag a column edge), as in the prototype.
- **Two rounds** (B); this is Round 1.
- **Approach 1:** shared Blade building blocks first, then each page rebuilt from them.
- Pages missing from the prototype (Find Tenders, Manage Users, Finance Settings) get icons in
  the prototype's style; the menu order stays as it is today.
- The Dashboard period picker keeps all current choices; it is restyled as the prototype's
  corner picker.
- The In Progress Category filter moves into the new Filters panel.
- Sidebar fold state and column widths are remembered per computer.

## Global constraints

- Colours: the existing tokens in `resources/css/app.css` already equal the prototype's. Add the
  prototype's missing tokens: `--ink-2` (#374151 / dark #C7C5BD), `--muted-2` (#8B87A0 /
  #85847C), `--accent-solid` (#6FB85E / #7CC46A). `--border` in the prototype = our `--line`.
- Font stays the system font stack already in use.
- Every page must fit the window from 360px to 1920px wide with no sideways page scroll (wide
  tables scroll inside their own box). Light and dark mode both supported.
- Sidebar breakpoint follows the prototype: drawer below 1024px (`lg`), fixed sidebar from 1024px.
- Explanations and labels in plain English (CLAUDE.md communication rule applies to UI copy).
- Tests first (RED → GREEN) for every behaviour; coverage stays ≥ 80%.

## 1. Shared building blocks

| Part | What it does |
|---|---|
| `<x-icon name="…" class="…"/>` | Prints one SVG from a fixed set copied from the prototype's `ICONS` (`logic.js` line 2): `dashboard, tenders, clock, award, check, staff, quotation, settings, search, plus, chevronLeft, chevronRight, chevronDown, x`, plus `filter` (`M4 7h16M7 12h10M10 17h4`), `user` (head + shoulders, `markup.html` "My tenders"), `logout`, `bell`, `moon`, `sun`, `panel` (the fold icon), `calendar`, `chart`. Unknown name → exception (caught by a test). |
| `<x-page-heading title subtitle>` + optional `actions` slot | 24px extra-bold title, 13.5px `muted-2` subtitle; actions on the right (`markup.html` 77–91). |
| `<x-filter-bar>` | White strip: search box with icon, optional **My tenders** toggle, **Filters** button with a count badge; a slot for the fold-out "Filter by column" panel with **Clear all** (`markup.html` 666–707). The panel's open state is local (Alpine); it starts open when the count is above 0. |
| `<x-data-table resizable="key">` | Card frame around a table; marks it for the column-resize script under a storage key (`list-in-progress`, `status`, …). |
| `<x-pager :paginator>` | "Showing 1–10 of 13 tenders" left, Prev / numbers / Next right (`markup.html` 841–853). Replaces `pagination/pager` for the restyled pages. |
| `resources/js/resizable-columns.js` | Imported by `app.js`. For each `table[data-resizable]`: adds a drag handle to each header's right edge; dragging sets the column width (min 60px) and switches the table to fixed layout; widths saved in `localStorage` under `tenderhub-cols:<key>`; restored on load and after Livewire re-renders (`livewire:navigated` / morph hook); double-clicking a handle clears that table's saved widths. Logic follows `logic.js` `_initColResize` (line 158). |

## 2. Page frame (layout + sidebar + top bar)

- **Sidebar** (`markup.html` 16–55, `logic.js` 760–815):
  - Dark rounded brand mark (`chip` background, accent "T"), bold "TenderHub", fold button
    (`panel` icon) on the right.
  - Groups and order unchanged: Operations (Dashboard, Find Tenders, In Progress) · Pipeline (Done,
    Awarded, Lost) · Quotation · Insights (Status) · Account (Settings, Manage Users, Finance
    Settings, with permission checks unchanged).
  - Icons: Dashboard `dashboard` · Find Tenders `search` · In Progress `clock` · Done `check` ·
    Awarded `award` · Lost `x` · Quotations `quotation` · Status `staff` · Settings `settings` ·
    Manage Users `user` · Finance Settings `settings`.
  - Item: 17px icon + label + count badge; active = solid `accent` pill with `accent-ink` text, bold;
    hover = `line` background.
  - **Folded:** sidebar 72px wide, labels/headings/badges/user name hidden, items centred, every
    item has a `title` tooltip; clicking the brand mark unfolds. State stored in cookie
    `sidebar=folded` (1 year); the layout reads it on the server so the first paint is already
    folded. Toggle is Alpine + sets the cookie; no server round-trip.
  - Bottom: avatar circle, name, role, log-out button (red on hover), as today but restyled.
- **Phone/tablet (<1024px):** sidebar hidden; top bar shows ☰ + "TenderHub"; tapping opens the
  drawer (unchanged behaviour); the fold button is hidden in the drawer.
- **Top bar:** sticky at the top; theme toggle (moon/sun) and bell; the bell's red count badge
  shows unread notifications (existing `notification-bell` component, restyled).
- Content padding as the prototype (`contentPad`): 28px desktop, 16px phone.

## 3. Tender lists (`/tenders/{in-progress|done|awarded|lost}`)

- `<x-page-heading>` with the list's title/subtitle; In Progress gets the **+ Register Tender**
  button.
- `<x-filter-bar>`:
  - search
  - My tenders
  - Filters panel with **PIC, Agency, Tender mode, Category, Deadline from, Deadline to**
  - The count = number of these that are set, plus My tenders.
  - The Dashboard's "Registered … · show all dates" note stays, shown under the bar.
- **Agency filter (new):** `#[Url] public string $agency = ''`; options = distinct `client`
  values among tenders with this list's status, sorted A–Z; `TenderListQuery` adds
  `where('client', $agency)` when it matches an option. Included in `clearFilters()` and the
  page-reset list.
- **Deadline sort (new):** `#[Url] public string $sort = ''` (`''` = today's default order,
  `deadline_asc`, `deadline_desc`); clicking the Deadline header cycles the direction; arrow shows
  it. Invalid values fall back to default.
- Table restyled (`markup.html` 711–790):
  - Header row in `subtle` with small uppercase labels.
  - WO number bold with the date under it; code above the title.
  - Avatar + PIC name.
  - Deadline keeps today's amber/red date colouring.
  - Briefing Yes/No pill.
  - Documents column = thin progress bar + "4/10".
  - Rows clickable as today.
  - Resizable, key `list-<slug>`.
- **Phones (<768px):** the table is replaced by cards (`markup.html` 794–838): WO number + "Due
  date", title, agency · code, avatar + PIC, value, then the list's extra figures (docs bar,
  briefing, Done: submit price / variant / gross, Lost: submitted / win price / win variant,
  Awarded: submit price / actual GP).
- Empty state "No tenders match your search." and `<x-pager>`.

## 4. Dashboard (`/dashboard`)

Layout per `markup.html` 93–253:

1. **Quick Overview panel:**
   - Lavender gradient card with a soft curved background shape.
   - Title "Quick Overview", subtitle "Every card below follows the selected period of WO dates."
   - The period picker (all current choices, `reports.period-filter`) restyled into the top-right
     corner.
   - Six white boxes: big number, grey bracketed note, label, each linking where it links today:
     - **In Progress** ("N due this week")
     - **Awarded** ("RM x won")
     - **Done** ("awaiting result")
     - **Lost** ("incl. cancelled")
     - **Win rate** ("3 of 8 decided", "—" when nothing decided)
     - **Portfolio value** (bid value)
   - Box grid: 6 across ≥1280px, 3 across ≥768px, 2 across on phones.
   - The invalid-period note stays.
2. **Two columns** (≥1024px: left ~38%, right ~62%; stacked below):
   - **Left, Upcoming deadlines:** `calendar` icon tile, title/subtitle, rows of initials avatar,
     title (one line, ellipsis), agency, date in `bad-ink`; empty text unchanged.
   - **Right, Portfolio mix:** `chart` icon tile.
     - "By status": the existing ring and legend.
     - "By submission mode":
       - a stacked bar split EP / Non-EP by count. It shows nothing when the total is 0. This closes
         Stage 6's deferred minor.
       - two mode boxes with the count, share %, value and per-status counts.
   - **Right, PIC summary:** `staff` icon tile.
     - Columns: PIC (avatar + name), Tenders, Share of value (green bar), Bid value.
     - A **Grand total** row at the bottom.
     - Names link to Status with the same period (unchanged).
3. **Quotations** and **Projects** cards (`quotation` / `tenders` icon tiles) below, same content
   as today.

Numbers come from the existing `PipelineReport` / `ReportCards`; the report queries do not change.

## 5. Status (`/status`)

- `<x-page-heading>` + the period picker on the right.
- Table in `<x-data-table resizable="status">`:
  - Avatar + name in the PIC column.
  - Current columns, sorting, totals row and drill-down links unchanged.
  - The sort arrow is styled like the lists.
- **Phones:** one card per PIC (`markup.html` 637–660): avatar + name, amount, then Total /
  Awarded / Lost / In progress / Win rate tiles. Counts keep their drill-down links.

## 6. Find Tenders (`/find-tenders`)

- `<x-page-heading>` with **Collect now** (permission unchanged) in the actions slot; the
  "Last collected… / Collecting now…" line restyled as a slim info card.
- `<x-filter-bar>`:
  - search
  - Filters panel holding today's filters: status (Open/Closed/All), source, type, ministry,
    field codes, closing from/to
  - The count = number of non-default filters.
- Table restyled, resizable key `find-tenders`; phone cards (reference, title, ministry/agency,
  type, closing date, price). Pager → `<x-pager>`.

## Error handling and edge cases

- Saved column widths for a table whose column count changed (e.g. Done vs In Progress) are
  ignored and dropped (key includes the slug; count checked like the prototype).
- `localStorage` unavailable → resizing still works for the visit, nothing is saved, no error.
- Cookie missing or any other value → sidebar unfolded.
- Very long titles/agency names never widen the page (truncate in cards, wrap in table cells).
- Unknown icon name → exception in tests, so a typo can't ship silently.
- Unknown `sort` or `agency` in the address → ignored (default order / no agency filter).

## Testing

- **Unit/feature tests first** for:
  - `x-icon` renders each name and rejects unknown names
  - the sidebar shows each icon and is folded when the cookie says so
  - the filter count and the panel starting open
  - the Agency filter, including ignoring an unknown agency
  - deadline sort in both directions, and ignoring an invalid sort
  - Dashboard: six boxes, the "due this week" note and the EP/Non-EP bar widths
  - Status phone cards present
  - Find Tenders filter count
- Existing tests that assert old wording/markup are updated, each listed in the ledger as a
  ruling.
- **Browser check** (built-in browser, Playwright-style live check per project rule):
  - every Round 1 page at 1440px, 1024px and 375px in light and dark mode, with no sideways
    scroll
  - folding/unfolding the sidebar, then a reload keeps it
  - dragging a column, then a reload keeps it; double-clicking resets
  - opening Filters and setting Agency
  - sorting by Deadline
  - the Dashboard drill-down links still land on filtered lists

## Out of scope (Round 2 or later)

Tender detail tabs, Costing, PD, Documents, Activity, Quotations list and editor, Settings /
Manage Users / Finance Settings pages, Register Tender and other pop-ups, login screen.
