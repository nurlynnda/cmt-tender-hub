# CMT Tender Hub — UI Round 2: the remaining screens in the prototype style

**Date:** 2026-10-07 · **Branch:** `ui-round-2` (built on `register-import`)
**Reference:** `docs/superpowers/plans/assets/prototype-ui/markup.html` and `logic.js`:
- tender detail 857–1519
- quotations 255–603
- dialogs 1519–1704

Round 1 spec: `2026-10-07-cmt-tender-hub-ui-round1-design.md`. Its shared parts (`x-icon`, `x-page-heading`,
`x-filter-bar`, `x-filter-field`, `x-data-table`, `x-initials`, resizable columns, pager, tokens) are reused.

## Goal

Make every screen Round 1 didn't touch look and behave like the prototype:
- the tender page and its tabs
- Quotations
- all dialogs
- Settings, Manage Users and Finance Settings
- the Find Tenders detail page
- the sign-in pages

**Nothing behind the screens changes** (data, rules, permissions, addresses, report figures), except two
small additions: **Bulk Add Documents** and a **date sort plus status counts** on the Quotations list.

## User decisions (from brainstorming)

- Documents stay **tick-only** (Stage 1). Add the prototype's **Bulk Add** (one name per line). No file
  uploads or previews.
- The quotation letterhead stays **one company letterhead in Finance Settings** (Stage 5). The prototype's
  per-quotation "Header" tab is not added.
- The same approach as Round 1: shared building blocks first, then each screen rebuilt from them.

## Global constraints

- Reuse the Round 1 tokens and components; colours come only from the tokens in `resources/css/app.css`.
- No sideways page scroll from 360px to 1920px; light and dark mode.
- UI copy in plain English.
- Tests first (RED → GREEN), commit after green, coverage ≥ 80%.
- Existing tests that assert old wording are updated with a recorded ruling. Behaviour tests must keep
  passing unchanged.

## 1. New shared parts

| Part | Purpose |
|---|---|
| `<x-dialog title="…" subtitle="…">` with `actions` slot | Dimmed overlay plus a white rounded panel (max-w-lg, `rounded-[20px]`, p-6, shadow). Bold title, muted explanation, body slot, footer with Cancel left and the main button right. Escape and the overlay call the existing close action (`wire:click` passed in as `close`). |
| `<x-status-pill :status>` | A coloured dot plus label for `TenderStatus`. In Progress uses info, Done muted, Awarded good, Lost bad, Dropped `muted-2`. It replaces the plain badge on the tender page and in lists, and the old `x-status-badge` becomes a wrapper around it. A quotation variant takes a status string (draft, sent, expired, accepted, rejected, revised) and colours it. |
| `<x-fact label="…">` | A small uppercase muted label above a value (for the key-facts strip and registration details). |
| `<x-card title="…" subtitle="…" icon="…">` | A white `rounded-[20px]` bordered card with an optional icon tile and heading, plus a body slot. |
| `<x-tabs>` with `<x-tab :active wire:click>` | An underlined tab bar in the prototype style; horizontal scroll on phones. |
| Button classes `btn`, `btn-primary`, `btn-dark`, `btn-outline`, `btn-danger` | Defined once in `app.css` (`@layer components`) from the Round 1 values (`rounded-[11px] px-4 py-2.5 text-[12.5px] font-bold`). Every changed screen uses them. |

## 2. Tender page (`/tenders/{id}`)

- **Header card:**
  - "← Back to list", then the WO number in bold
  - `x-status-pill`, a mode pill, and "Docs done/total"
  - action buttons with the same conditions as today, styled with `btn-*`. Cancel, Drop and Lost use
    `btn-outline`, Mark Done `btn-dark`, Mark Awarded `btn-primary`, Reopen `btn-outline`
  - the title below in `text-xl font-extrabold`
- **Key-facts strip** (new, inside the header card):
  - row 1: Assigned PIC (avatar plus name), Opportunity Owner, Category, Tender Code
  - row 2: Agency (with the ministry in grey when it differs), Estimated value, Closing date (amber or
    red using the existing `closingState()`), WO date
  - uses `x-fact`; wraps on phones
- **Tabs:** `x-tabs` with Overview · Costing · PD (when there is a project) · Documents · Activity. The
  existing `tab` URL setting is unchanged.
- **Overview:**
  - `x-card` "Scope of Work" holding the scope text, or "No scope written yet."
  - `x-card` "Registration Details": WO number, WO date, mode, type, publish date, briefing (Yes —
    date / No), tender code, submitted price, winning price, and lost / cancel / drop reason. A reason
    row only shows when that reason exists.
  - "Edit details" uses `btn-outline`, and the edit form uses the Round 1 input style.
- **Costing:**
  - four summary boxes (Total Cost, Total Sell, Margin, Margin %) from the existing costing summary;
    Margin and Margin % turn red when below the company target (`below_target`)
  - the line-items table restyled (header row, row borders, inputs)
  - the existing unsaved-changes bar restyled as a warm amber bar with **Discard** and **Save changes**
  - Bulk Import, vendor picker and breakdowns behave exactly as now
- **PD tab** (`ProjectPd`, shared with quotation projects): the same sections and figures in `x-card`s,
  restyled tables, `btn-*` buttons and dialogs. No change to calculations.
- **Documents:**
  - "Assigned to {PIC} (PIC)", a progress bar, "done / total done", and prototype-style tick boxes
  - the add box and the new **Bulk Add** button, which opens an `x-dialog` with a textarea ("Type one
    document name per line")
  - the locked banner restyled
- **Bulk Add action:** `AddDocuments::handle(User $actor, Tender $tender, int $expectedVersion, string $text): int`.
  - Follows the same pattern as `AddDocument`: lock → authorise `update` → version check → tender must
    not be locked (same rule as `AddDocument`).
  - Splits on new lines, trims, and drops blanks and names already on the tender (case-insensitive) or
    repeated in the text. Names are capped at 255 characters (longer lines are cut, not refused).
  - Creates the documents at the end of the list and bumps the version once.
  - Logs one activity entry, "Added N documents: a, b, c".
  - Returns N. The dialog then shows "Added N documents" or "Nothing new to add".
- **Activity:** a vertical timeline with one dot per entry: description, then "by {name} · {date time}" in
  muted text, newest first. Same data as now.

## 3. Quotations

- **List (`/quotations`):**
  - `x-page-heading` "Quotations" with a **+ New Quotation** `btn-primary`
  - `x-filter-bar` (search, My quotations), and above the table a row of **status buttons with counts**:
    All · Draft · Sent · Expired · Accepted · Rejected · Revised. The counts come from one grouped query
    that follows the current search and My quotations filter. The active button is filled.
  - **Date sort:** `#[Url] sort` = `date_desc` (default, same order as today) | `date_asc`. Clicking the
    Date heading toggles it and an arrow shows the direction.
  - Table in `x-data-table resizable="quotations"`: No., Date, Customer, Subject, Prepared By (initials
    plus name), Amount, Valid Until, Status (`x-status-pill` quotation variant)
  - phone cards
  - "Showing a–b of N quotations" plus the pager
- **Quotation page:**
  - header: "← Back to quotations", the number, a status pill, and the existing buttons in `btn-*`
  - a **"Saved ✓"** note that shows for about two seconds after any field saves, via a browser event the
    component already has, or a new `saved` event dispatched from `saveField`/`saveItem`
  - `x-tabs` for Details · Items · Terms & Conditions · Preview · History
  - Details in two-column `x-card`s (Customer; Prepared by)
  - Items table restyled with a Subtotal / SST / Total block
  - History as the same timeline as Activity
- **Quotation project page:** the header plus the restyled `ProjectPd`.

## 4. Dialogs

- Every dialog moves to `x-dialog`:
  - tender: Mark Done, Cancel, Mark Awarded, Mark Lost, Drop, Reopen, Bulk Add Documents, the Costing Bulk
    Import
  - Register Tender
  - quotation dialogs (if any)
  - Manage Users add / edit
- **Register Tender layout:**
  - "WO Number (auto)" and "WO Date (auto)" read-only previews at the top. The preview number comes
    from a read-only peek at the next sequence: today's `wo_sequences.last_seq + 1`, formatted. The
    real number is still taken only on save, so the preview is labelled "(auto — final number given on
    save)".
  - then Mode, Type, PIC, Owner, Ministry, Agency, QT No, QT Title, Publish Date, Closing Date, Price,
    Briefing, Briefing Date, Category, Scope
  - two columns on desktop, one on phones
- **Mark Done text:** "Marking this tender as Done will lock its costing and documents from further edits."
  The missing-documents warning is kept.

## 5. Other pages

- **Settings, Manage Users, Finance Settings:** `x-page-heading`, sections in `x-card`, tables in the
  Round 1 table style, and `btn-*` buttons. The behaviour is unchanged.
- **Find Tenders detail:** a header card (reference, source labels, status), a key-facts strip (ministry,
  agency, type, advertised, closing, price), then the existing sections in `x-card`s. "Register this
  tender" becomes `btn-primary`.
- **Sign-in / Forgot / Reset password** (guest layout): a centred card (max-w-sm), the dark "T" logo plus
  "TenderHub", prototype inputs and a `btn-dark` full-width button. Messages are unchanged.

## Error handling

- Bulk Add on a locked tender, without permission, or with an old version → the same errors and messages
  as `AddDocument` (locked / not allowed / changed by someone else — reload).
- Bulk Add with only blanks or duplicates → no change and no version bump; the message says "Nothing
  new to add".
- An unknown quotation `sort` value → the default order.
- An unknown quotation `status` → All (as today).
- The WO preview never takes a number: two people opening Register at once may see the same preview,
  and each still gets a unique number on save.

## Testing

- **Feature tests first:**
  - `AddDocuments`: adds, skips blanks and duplicates (case-insensitive, both existing and repeated),
    caps at 255 characters, permission, locked tender, version, a single activity entry, the returned
    count
  - TenderDetail: Bulk Add dialog flow and message; the key-facts strip shows PIC, owner, category,
    code, agency, value, closing date and WO date; reason rows only when set
  - `x-status-pill` renders each tender status and quotation status
  - Quotation list: status counts follow search and My quotations; the date sort toggles; an unknown
    sort falls back
  - QuotationPage: a field save dispatches `saved`
  - Register Tender shows the WO preview without changing `wo_sequences`
  - each dialog still confirms its action (existing tests)
- **Existing wording tests** are updated with rulings.
- **Browser check:**
  - each changed page at 1440 / 1024 / 375, light and dark, with no sideways scroll
  - every dialog opens, cancels and confirms
  - Bulk Add on a tender
  - quotation status buttons and date sort
  - the Costing unsaved bar
  - sign-in page

## Out of scope

- File attachments and previews for documents.
- A per-quotation letterhead.
- Any change to calculations, permissions or workflows.
