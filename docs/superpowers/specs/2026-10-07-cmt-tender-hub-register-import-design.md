# CMT Tender Hub — Import the 2026 tender register (+ Dropped list, Ministry field)

**Date:** 2026-10-07 · **Branch:** `register-import` (built on `ui-round-1`)

## Goal

Load the user's real 2026 tender register (a CSV export of their spreadsheet, 393 tenders) into
the pipeline. Replace the sample records. Add the two things the register needs that the app lacks:
a **Dropped** list and a **Ministry** field.

The CSV holds real company data. It is **never** copied into the repository, fixtures or GitHub. Tests use
a small made-up CSV of the same shape.

## User decisions (from brainstorming)

- Status "Drop" → a new fifth list **Dropped** (C).
- PICs → one **switched-off** account per name in the file (9). Blank PIC → a switched-off
  placeholder account **"Unassigned"** (A).
- Sample data → **replaced**: sample tenders, quotations, projects and the four sample staff are removed;
  **System Admin** and **Sales Manager** are kept; Find Tenders' collected data is untouched (A).
- Ministry → a new optional **Ministry** field on tenders (A).

## The register file (as received)

- Columns:
  - No, WO Number, WO DATE, Mode, PIC, Ministry, PTJ Code & Name, QT No, QT Title (Full)
  - Publish Date, Closing Date, Status
  - Indicative Price, Submission Price, Win Price, Company Variant, Win Variant, Submitted Cost
  - Gross, Month, Briefing date, Type
  - two blank columns
- About 1,045,836 lines, of which 395 are not blank: 393 real rows plus 2 near-blank rows (no WO number).
- Dates are `m/d/yyyy`. Money is `"46,437,935.04"`. Odd values seen: `-`, `` ` ``, `#DIV/0!`, `#VALUE!`,
  and stray tab characters inside names.
- Status counts: Open 5, Assigned 46, Submitted 177, Won 24, Lost 101, Cancelled 12, Drop 28.
- Mode: Ep / Non-Ep. Type: TENDER / SH. PIC: Sharul, Kamarina, Fitri, Afiq, Nina, Syukri, Ridhwan,
  Fazleen, Muallim, or blank (15).

## 1. Dropped list

- `TenderStatus::Dropped = 'dropped'`:
  - label "Dropped", slug `dropped`, list title "Dropped Tenders"
  - subtitle "Tenders the company decided not to bid for"
- Migration: `tenders.dropped_at` (timestamp, nullable) and `tenders.drop_reason` (text, nullable).
- `DropTender` action (`app/Actions/Tenders/DropTender.php`):
  - follows the same pattern as `MarkTenderLost`: lock → authorise → version check
  - status must be **In Progress**
  - optional reason
  - sets `dropped_at`
  - records activity "Dropped — reason: …"
  - permission: the same ability Mark Lost uses
- `ReopenTender` also clears `dropped_at` and `drop_reason`. The existing rule "reopen anything that is not In
  Progress, managers only" already covers Dropped.
- Tender page: a **Drop tender** button on In Progress tenders, opening a confirm dialog with an optional
  reason. Dropped tenders show "Dropped on …" and the reason.
- Sidebar: **Dropped** under Pipeline, after Lost, with its count and an icon (`x`, already in the set).
  The sidebar counts already come from `TenderStatus::cases()`.
- Lists: `/tenders/dropped` works through the existing `TenderList`. Columns as for In Progress, minus
  Briefing and Documents, plus "Reason".
- Reports (`PipelineReport`):
  - counts gain `dropped`
  - win rate unchanged (Awarded ÷ (Awarded + Lost not cancelled))
  - **bid value and won value exclude Dropped**, so the bid-value SQL must not add a dropped tender's
    submitted price
  - Dashboard ring and legend gain a grey **Dropped** slice
  - the Quick Overview keeps its six boxes
  - Status gains a **Dropped** column, with a drill-down link like the others
  - `PipelineReport::STATUSES` gains `dropped`

## 2. Ministry field

- Migration: `tenders.ministry` (string, nullable).
- `RegisterTender` and `UpdateTender` accept an optional `ministry` (max 255).
- Register Tender and the tender edit form get a "Ministry" text box.
- Lists, table and phone cards show the agency (`client`) with the ministry under it in small grey text
  when set. The tender page shows both.

## 3. Import command

`php artisan tenders:import-register {file} {--commit} {--replace-samples}`

- **Without `--commit`: preview only.** It prints:
  - rows read
  - rows skipped, with reasons
  - tenders per list
  - accounts to create
  - samples to remove (when `--replace-samples` is given)

  Nothing is written.
- **With `--commit`:** does everything in **one database transaction**. Any exception rolls back and
  prints the row number and the reason.
- **Matching:** by WO number. Rows whose WO number already exists are **updated** (re-running a newer
  register is safe); new ones are created. The `version` is bumped on update.
- **Parsing:** a streaming CSV reader (PHP `fgetcsv`). Before use, trim spaces and tabs from every value. A row
  with an empty WO Number is skipped (reason "no WO number").
- Fields:

| CSV | App |
|---|---|
| WO Number | `wo_number` |
| WO DATE (m/d/yyyy) | `wo_date` |
| Mode `Ep` / `Non-Ep` (any case) | `TenderMode::Ep` / `NonEp`; blank → row skipped, reason "no mode" |
| Type `TENDER` / `SH` | `TenderType::Tender` / `Quotation`; blank → Tender |
| QT No | `tender_code` |
| QT Title (Full) | `title` |
| Ministry | `ministry` |
| PTJ Code & Name | `client`; blank → the Ministry value |
| Publish Date | `publish_date` (blank → null) |
| Closing Date | `closing_date`; blank → row skipped, reason "no closing date" |
| Briefing date | `briefing_date`; `has_briefing` = briefing date present |
| Indicative Price | `estimated_value_sen` |
| Submission Price | `submitted_price_sen` |
| Win Price | `winning_price_sen` |
| PIC | `pic_id`; see accounts below |
| Status | see below |
| (none) | `category` = General; `owner_id` = PIC |

- **Money:** strip commas and parse. Anything that isn't a number (`-`, `` ` ``, `#DIV/0!`, blank) becomes
  null. Negative values become null, with a warning line.
- **Dates:** `m/d/yyyy`, checked with `checkdate`. An invalid date in a required field skips the row with
  the reason. An invalid optional date becomes null, with a warning.
- **Status mapping:**
  - Open, Assigned → In Progress
  - Submitted → Done (`done_at` = closing date)
  - Won → Awarded (`awarded_at` = closing date)
  - Lost → Lost (`lost_at` = closing date)
  - Cancelled → Lost with `was_cancelled = true` (`lost_at` = closing date)
  - Drop → Dropped (`dropped_at` = closing date)
  - Any other value, or blank → row skipped, reason "unknown status 'X'"
- **Submitted Cost:** when both Submitted Cost and Submission Price are present, the tender gets **one**
  costing line:
  - description "Imported cost (2026 register)", quantity 1, one-off, year 1
  - `unit_cost_sen` = submitted cost, `margin_bp` 0
  - `bid_price_override_sen` = submission price

  The app's Gross then equals (price − cost) ÷ price, matching the sheet. On re-import the
  imported line is replaced; lines users added themselves are left alone.
- **Documents:** newly created **In Progress** tenders get `TenderDocument::STANDARD`, unticked. Other
  statuses get none. Updates never touch documents.
- **Activity:** each created tender gets one activity entry "Imported from the 2026 register", recorded as
  the admin running the import; updates get "Updated from the register".
- **Accounts:**
  - For each distinct PIC name (case-insensitive) without an existing user of that exact name, create
    one with `is_active = false`, role Staff, email `<slug>@import.invalid`, and a random password.
  - A blank PIC uses the "Unassigned" account (same rules, email `unassigned@import.invalid`).
  - An admin later sets the real email in Manage Users, switches the account on, and the person sets
    their password via "Forgot password".
- **`--replace-samples`** (with `--commit`), before importing, in the same transaction:
  - delete all tenders whose WO number is **not** in the file, and their documents, costing, activity
    and projects (cascade)
  - delete all quotations and their projects and activity
  - delete users other than the System Admin and Sales Manager accounts (the seeder emails
    `admin@cmt.test` and `manager@cmt.test`) and the PIC accounts the import needs
  - notifications for deleted records go with them
  - collected tenders (Find Tenders) are never touched

  The preview lists exactly what would be removed.
- **Never** reads a path from inside the repository by default. The file is given on the command line.

## Error handling

- Unreadable file or wrong header (missing any of WO Number, WO DATE, Mode, PIC, Ministry, PTJ Code & Name,
  QT No, QT Title (Full), Closing Date, Status) → stop before reading rows, with a plain message.
- Duplicate WO numbers inside the file → the second is skipped, with the reason "duplicate WO number in file".
- The exit code is non-zero when nothing was imported or when the transaction failed.

## Testing

- `tests/fixtures/register-sample.csv`: made-up rows, one per status (incl. Cancelled and Drop), plus:
  - a blank PIC
  - `-` and `` ` `` prices
  - a blank PTJ
  - a briefing date
  - a Submitted Cost
  - an SH type
  - a tab in a name
  - a duplicate WO
  - a blank trailing row
  - a row with an unknown status
- Feature tests:
  - the preview writes nothing
  - commit creates the right counts per list
  - the field mapping, including the costing line, so Gross matches
  - accounts are created switched off and Unassigned is used
  - a re-run updates instead of duplicating
  - `--replace-samples` removes samples but keeps admin, manager and collected tenders
  - a bad header stops the import
  - a failure mid-way rolls everything back
- `DropTender` action tests (In Progress only, version check, permission, activity), Reopen clears the
  drop, list and sidebar show Dropped, reports exclude Dropped from bid value, Status column, Ministry
  saves and shows.
- After the code is merged into the working copy: run the preview on the real file, show the user the
  numbers, then commit. Check the counts (393 = 51 In Progress, 177 Done, 24 Awarded, 113 Lost
  (12 cancelled), 28 Dropped) and look at the lists, Dashboard and Status in the browser.

## Out of scope

- Importing quotations, projects or PD data from spreadsheets.
- Editing the CSV, scheduled imports, or an upload page in the app.
- Real emails for the new accounts (an admin does this in Manage Users).
