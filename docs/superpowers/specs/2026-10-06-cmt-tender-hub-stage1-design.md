# CMT Tender Hub — Stage 1 Design (Foundation + Tender Pipeline)

Date: 2026-10-06
Status: Approved in conversation, awaiting written-spec review
Prototype: `TenderHub.html` (Claude Design bundle, React) — the visual and behavioural reference.

## 1. Purpose and context

CMT Tender Hub is an internal system for CMT's sales team to track the government
tenders they bid on: register a tender, prepare its documents, submit it, and record
whether it was won or lost.

It is a **new Laravel (PHP) application that will replace both existing Node systems**:

- **tms-v2** (this repo) — the MyProcurement tender collector. Moves into Tender Hub in Stage 2.
- **Project Deliverable (PD)** at `C:\Projects\ProjectDeliverable` — revenue/costing tracker
  with an unmerged Sales module. Its costing and project-finance ideas return in Stages 3–4.

Neither has real production data, so Tender Hub starts fresh (with sample data for development).
Both old systems stay untouched until Tender Hub covers their features, then they are retired.

The code will be maintained mainly by Claude, so clarity and test coverage matter more than
matching any particular team's PHP habits.

### Whole-system roadmap (each stage gets its own spec → plan → build)

| Stage | Scope |
|---|---|
| **1 (this spec)** | Login, users, layout, tender register, 4 pipeline lists, tender detail (Overview / Documents / Activity), status changes, in-app notifications |
| 2 | "Find Tenders" page: MyProcurement collector ported to Laravel + "Register this tender" button that pre-fills the Register form |
| 3 | Costing tab: line items, markup, sell price, margin, bulk import; submitted price comes from costing |
| 4 | PD tab: P&L budget vs actual, payment collection schedule, invoices/receipts, cash flow |
| 5 | Quotation: quick quotes outside the tender process, PDF output |
| 6 | Dashboard + Status (per-PIC performance) reports |

### Decisions made during brainstorming

| Question | Decision |
|---|---|
| Relationship to existing systems | Replace both PD and tms-v2 |
| Existing data | None to migrate — start fresh |
| Login | Email + password, admin-created accounts |
| Permissions | Simple: everyone sees everything; PIC + managers/admins edit; no approval chain |
| MyProcurement collector | "Find Tenders" page with "Register this tender" (Stage 2) |
| Hosting | Docker on a company server/PC |
| Lifecycle | In Progress → Done (submitted) → Awarded / Lost; Cancel sends In Progress → Lost with a reason |
| Documents | Tick-only checklist; files stay on the shared drive |
| Notifications | In-app bell only (no email) |
| Screen technology | Laravel + Livewire (+ Alpine.js), Tailwind CSS |

## 2. Project setup and structure

- **Location:** new git repository at `C:\Projects\cmt-tender-hub` (separate from tms-v2 so
  Node tooling — husky, vitest — does not interfere).
- **Stack:** PHP, Laravel, Livewire, Alpine.js, Tailwind CSS, MySQL 8, Pest — each at its
  latest stable version, pinned when scaffolding.
- **Docker Compose services:**
  - `app` — Laravel web app, served at `http://localhost:8080`
  - `mysql` — database (named volume for persistence)
  - `scheduler` — runs `php artisan schedule:work` (hourly notification checks; Stage 2 collector)
  - `vite` — development-only asset rebuilder
  - `mailpit` — development-only test mailbox that catches password-reset emails
- PHP is not installed on the host; **every PHP/Composer/Artisan command runs inside Docker.**

### Code layout

| Path | Contents |
|---|---|
| `app/Models/` | `User`, `Tender`, `TenderDocument`, `ActivityLog` (+ Laravel's database notifications) |
| `app/Enums/` | `Role` (staff/manager/admin), `TenderStatus`, `TenderMode` (EP/Non-EP), `TenderType` (Tender/Quotation) |
| `app/Actions/` | One class per business action: `RegisterTender`, `UpdateTender`, `MarkTenderDone`, `MarkTenderAwarded`, `MarkTenderLost`, `CancelTender`, `ReopenTender`, `ToggleDocument`, `GenerateWoNumber` |
| `app/Policies/` | `TenderPolicy`, `UserPolicy` — the single source of truth for who may do what |
| `app/Livewire/` | Screens: tender list, register modal, tender detail (tabs), notification bell, settings, manage users |
| `app/Notifications/` | `TenderAssigned`, `TenderClosingSoon`, `BriefingTomorrow` |
| `app/Console/` | Scheduled command `tenders:send-reminders` |
| `resources/views/` | Blade templates styled with Tailwind to match the prototype, light + dark mode |
| `database/seeders/` | Dev-only sample data from the prototype |
| `tests/` | Pest unit + feature tests; Playwright browser checks |

Livewire components stay thin: they validate input and call an Action. Business rules live in
Actions and Policies so they can be tested without a browser.

### Quality rules

- Test-first (TDD): failing test → minimal code → passing test → commit. Never commit red.
- Pre-commit hook runs the full test suite.
- Coverage threshold 80% (lines), enforced in CI-style local run.
- Tests run against **real MySQL** in Docker (a separate test database), not SQLite.
- Every screen is clicked through in a real browser (Playwright) before being reported done.

## 3. Data model

All money is stored as **integer sen** (RM 1.00 = 100). All dates/times are stored in UTC and
displayed/computed in **Asia/Kuala_Lumpur**; "today" for WO numbers and reminders is Malaysia time.

### users

| Column | Notes |
|---|---|
| name, email (unique), password (hashed) | |
| role | `staff` / `manager` / `admin` |
| is_active | Deactivated users cannot log in; never deleted |
| timestamps | |

### tenders

| Column | Notes |
|---|---|
| wo_number (unique) | `200-DDMMYYYY-NNN`; NNN restarts at 001 each Malaysia calendar day |
| wo_date | Registration date (Malaysia) |
| mode | `EP` / `NON_EP` |
| type | `TENDER` / `QUOTATION` (sebut harga) |
| category | Fixed list taken from the prototype: IT Infrastructure, Software Development, Civil Works, General (default). Changing the list is a code change for now. |
| tender_code | Required; not unique (duplicates warn, see §4) |
| title | Required |
| client | Required; chosen from searchable ministry list or typed freely |
| scope | Optional long text |
| pic_id → users | Required |
| owner_id → users | Opportunity Owner; optional |
| publish_date, closing_date | closing_date required, must be ≥ publish_date when both given |
| has_briefing, briefing_date | briefing_date required when has_briefing |
| estimated_value_sen | Optional, ≥ 0 |
| status | `in_progress` / `done` / `awarded` / `lost` |
| submitted_price_sen | Set by Mark Done |
| winning_price_sen | Set by Mark Lost (optional when cancelled) |
| lost_reason | Set by Mark Lost (optional) or Cancel (required) |
| was_cancelled | true when it reached Lost via Cancel |
| done_at, awarded_at, lost_at | Status change timestamps |
| closing_soon_notified_at, briefing_notified_at | Prevent duplicate reminders; cleared when the relevant date is changed |
| version | Integer, incremented on every save — used for edit-conflict detection |
| timestamps | |

**WO number generation** runs inside a database transaction with a row lock on a
`wo_sequences(date, last_seq)` table so two simultaneous registrations never get the same number.

### tender_documents

| Column | Notes |
|---|---|
| tender_id | |
| name | |
| position | Display order |
| is_done, done_by → users, done_at | |

New tenders are created with the 5 standard items: Borang ISI (Tender Form), Pricing Schedule,
Company Profile / SSM Registration, Technical Proposal, Bid Bond / Bank Guarantee.
Items can be added or removed while the tender is In Progress.

### activity_logs

| Column | Notes |
|---|---|
| tender_id, user_id | |
| event | e.g. `registered`, `updated`, `pic_changed`, `document_ticked`, `marked_done`, `marked_awarded`, `marked_lost`, `cancelled`, `reopened` |
| description | Human-readable line, e.g. "PIC changed from Nurul Ain to Siti Aisyah" |
| created_at | Append-only; no update/delete paths exist |

### notifications

Laravel's built-in database notifications table (user, type, data with message + tender link, read_at).

## 4. Screens and behaviour

### Login and account

- Email + password login; "Forgot password?" sends a reset link. In development emails go to
  a local test mailbox (Mailpit container) — no real email is sent until a company SMTP account
  is provided.
- Deactivated users are refused at login and logged out on their next request.

### Layout

- Matches the prototype: collapsible sidebar with **Operations** (In Progress) and **Pipeline**
  (Done, Awarded, Lost) groups showing live counts; user name, role and Log out at the bottom.
- Top bar: light/dark toggle (remembered per browser), notification bell.
- Phone widths: sidebar becomes a slide-out drawer.
- Dashboard, Quotation and Status nav items are hidden until their stages ship.
- **Landing page after login: In Progress.**

### Tender lists (In Progress / Done / Awarded / Lost)

Columns per list follow the prototype:

| List | Columns |
|---|---|
| In Progress | WO Number (+date), Tender (code + title), Agency, Assigned To, Deadline, Briefing, Est. Value, Documents % |
| Done | WO, Tender, Agency, Assigned To, Deadline, Est. Value, Submit Price, Company Variant |
| Awarded | WO, Tender, Agency, Assigned To, Deadline, Est. Value, Documents % |
| Lost | WO, Tender, Agency, Assigned To, Deadline, Est. Value, Submitted Price, Win Price, Win Variant, Cancelled badge |

- Company Variant = submitted price ÷ estimated value, as a percentage.
- Win Variant = winning price ÷ estimated value, as a percentage.
- Both are blank if either number is missing (verified against prototype figures, e.g. 866,179 ÷ 402,754 = 215.1%).
- The prototype's Done "Gross" column depends on costing and arrives in Stage 3.

- Search across WO number, tender code, title, client.
- "My tenders" toggle (I am PIC or Opportunity Owner).
- Filters: mode, PIC, category, closing-date range.
- Sort by closing date (default ascending on In Progress, descending elsewhere); 10 per page.
- Closing within 7 days → highlighted; past closing date while In Progress → red.
- Clicking a row opens the tender detail.

### Register Tender (In Progress page, modal)

- Fields per §3. Required: tender code, title, client, PIC, closing date.
- If the tender code already exists on another tender, show a warning listing the matching WO
  number(s) with a "Register anyway" confirmation.
- On save: WO number generated, 5 standard documents created, activity logged, assignment
  notification sent, user taken to the new tender's detail page.

### Tender detail

- **Header:** WO number, status badge, mode, document progress %, action buttons.
- **Overview tab:** all fields. Editable inline by permitted users while In Progress; read-only otherwise.
- **Documents tab:** checklist with tick/untick, add item, remove item, "x / y done" summary.
- **Activity tab:** log entries newest first, with who and when.

**Actions and rules:**

| Action | Allowed from | Rules |
|---|---|---|
| Mark Done | In Progress | Refused with the list of unticked documents if any remain. Otherwise asks for submitted price (required, > 0). |
| Cancel Tender | In Progress | Requires a reason. Moves to Lost with `was_cancelled = true`. |
| Mark Awarded | Done | Confirmation only. |
| Mark Lost | Done | Asks for winning price (optional, ≥ 0) and reason (optional). |
| Reopen | Done / Awarded / Lost | Manager/Admin only. Returns to In Progress, clears outcome fields, logged. |

Any other transition is refused by the Action (not just hidden in the UI).

### Settings

- Everyone: change own name and password (current password required).
- Admin only — **Manage Users:** list, add user (name, email, role, temporary password),
  change role, deactivate / reactivate. An admin cannot deactivate or demote themselves
  (prevents locking everyone out).

## 5. Permissions

| Ability | Staff | Manager | Admin |
|---|---|---|---|
| View all tenders, lists, activity | ✓ | ✓ | ✓ |
| Register tender | ✓ | ✓ | ✓ |
| Edit tender / tick documents / Mark Done / Cancel / Mark Awarded / Mark Lost | Only where they are PIC | ✓ | ✓ |
| Reopen tender | ✗ | ✓ | ✓ |
| Manage users | ✗ | ✗ | ✓ |

Enforced in Policies on every server request; UI hides buttons as a convenience only.

## 6. Notifications (in-app bell)

| Notification | Recipients | Trigger |
|---|---|---|
| You were assigned | New PIC and Opportunity Owner (if different) — never the person making the change | Tender registered, or PIC/OO changed |
| Closes in 3 days | PIC + OO of In Progress tenders | Hourly scheduler, when closing date is within 3 days; once per tender (re-armed if closing date changes) |
| Briefing tomorrow | PIC + OO of In Progress tenders | Hourly scheduler, when briefing date is tomorrow (Malaysia); once per tender (re-armed if briefing date changes) |

Bell shows unread count; clicking an item opens the tender and marks it read; "Mark all read".

## 7. Error handling

- **Validation errors** shown beside each field; nothing saved.
- **Edit conflicts:** saves include the tender's `version`; if it changed since the page loaded,
  the save is refused with "This tender was changed by <name> — reload to see their changes".
  Applies to Overview edits, document changes and status actions.
- **Permission failures** return a 403 page (or an inline message inside Livewire).
- **Unexpected errors:** friendly error page; details written to Laravel's log.

## 8. Sample data (development only)

`php artisan db:seed` loads: 4 staff (Ahmad Faizal, Nurul Ain, Siti Aisyah, Muhammad Hafiz),
1 manager, 1 admin, and the prototype's 41 tenders across all four statuses with realistic
document progress and activity. Seeder refuses to run when `APP_ENV=production`.
Dev passwords are recorded in `.env.example` / README, not in chat.

## 9. Testing

- **Unit/feature (Pest, real MySQL test database):**
  - WO numbering incl. daily reset, Malaysia-midnight boundary, and concurrent registrations
  - Every allowed and refused status transition
  - Mark Done document gate
  - Permission matrix for every role × action
  - Reminder timing with a frozen clock; no duplicate reminders; re-arming on date change
  - Edit-conflict refusal
  - Deactivated-user login refusal; admin self-lockout prevention
  - Money stored as sen, displayed as `RM 1,234.56`
- **Livewire component tests** for each screen's validation and wiring.
- **Browser (Playwright):** login, register a tender, tick documents, Mark Done → Awarded,
  Cancel → Lost, Reopen, notification bell, Manage Users, dark mode, phone-width drawer.
- Coverage ≥ 80% lines.

## 10. Out of scope for Stage 1

Costing, PD finance, Quotation, Dashboard, Status reports, MyProcurement collector, file uploads,
email notifications, Microsoft 365 login, approval workflows, data import from PD/tms-v2.
