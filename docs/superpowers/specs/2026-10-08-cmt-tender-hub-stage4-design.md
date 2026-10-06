# CMT Tender Hub — Stage 4 Design (PD: project finance)

Date: 2026-10-08
Status: Approved in conversation, awaiting written-spec review
Builds on: Stages 1–3 — branch `stage-4` from `stage-3`.
References: prototype `TenderHub.html` PD tab; old PD system (`C:\Projects\ProjectDeliverable`)
`docs/superpowers/specs/2026-08-07-project-deliverable-design.md` §4 (P&L, commission formula) and
its project-type list (`backend/src/repos/refdata.ts`).

## 1. Purpose

Once a tender is **Awarded** it becomes a project. The **PD tab** tracks its money from then on:
the budget (what we planned to earn and spend), the actuals (what was really invoiced, paid and
received), and whether the project still makes the margin management approved. It replaces the
"Project Declaration" part of the old Excel/PD for day-to-day tracking.

### Decisions made during brainstorming

| Question | Decision |
|---|---|
| Scope | **The prototype's PD tab only**: budget vs actual P&L, cost lines with PR → PO → invoice → payment, collections with invoices and receipts, payment schedule, cash flow. Today's simple permissions. No approval chain, Finance/SVP/PM roles, per-person commission or claims. |
| Budget source | **Copied from the costing at Award**: each costing line gets a Group; at Award each becomes a PD budget line in its group, plus a Collection line "Contract value" = bid price. The PD budget is independent afterwards. |
| Approved margin / charges / commission | **Admin-managed project types** (name + approved margin %) and company defaults (project charge 9%, commission share 50%), **copied into the project** when the type is chosen / at Award. Managers/Admins may adjust a single project's numbers. |
| Detail per line | **A list of document entries per line** (type, number, date, amount, note). Actual cost/revenue = **invoiced**; cash flow uses **paid/received** dates. |
| Life cycle | PD created at Awarded; editable while open; **Close project / Reopen project** by Manager/Admin. Non-awarded tenders have no PD. |
| Architecture | **Separate project record; every change saved immediately** with a per-line conflict check and its own activity entry. One `PdCalculator` does all maths. |

## 2. Data model

All money in **sen** (integer). Percentages in **basis points** (1% = 100 bp).

### project_types (Settings → Admin)

| Column | Notes |
|---|---|
| id, name (unique, ≤ 100) | |
| approved_margin_bp | unsigned smallint 0–9999 |
| is_active | switched-off types are hidden from the picker but kept on existing projects |

Seeded with the old PD's 22 types: Audio Visual 15%, Consultancy (BIM) 30%, Consultancy (ICT) 30%,
DC Infrastructure 15%, Distributorship 0%, Enterprise Solution 15%, Installation Services 30%,
Leasing (Audio Visual) 6%, Leasing (Enterprise Solution) 11%, Leasing (ICT Peripherals) 4%,
Leasing (Networking) 10%, Leasing (Others) 10%, Managed Services 15%, Networking 20%,
Project Management 20%, Security Solution 10%, Trading - General 15%, Trading - ICT Peripherals 15%,
Trading - Medical (disposable) 15%, Trading - Medical (Drugs) 15%, Maintenance Services 30%,
Support (ASP) 30%. A type in use by a project cannot be deleted (only switched off).

### Company defaults (Settings → Admin)

`project_charge_bp` (default 900) and `commission_share_bp` (default 5000), stored in a one-row
`finance_settings` table.

### projects (one per awarded tender)

| Column | Notes |
|---|---|
| id, tender_id (unique, cascade) | created on the first Award; kept (hidden) if the tender is reopened |
| project_type_id | nullable (picked on the PD tab) |
| approved_margin_bp | copied from the type when picked; default 0 until then |
| project_charge_bp, commission_share_bp | copied from company defaults at creation |
| start_date, end_date | nullable dates; end ≥ start |
| closed_at, closed_by | null = open |
| version | for header changes (type, numbers, dates, close/reopen) |

### pd_lines

| Column | Notes |
|---|---|
| id, project_id (cascade), position | |
| group | `collection`, `principal`, `distributor`, `partner`, `finance_cost`, `tax`, `misc`, `internal` |
| name (required, ≤ 255), reference (≤ 100, optional) | |
| budget_sen | unsigned, may be 0 |
| scheduled_date | Collection lines only (optional) |
| version | per-line conflict check |

### pd_entries

| Column | Notes |
|---|---|
| id, pd_line_id (restrict delete of line while entries exist) | |
| type | cost lines: `pr`, `po`, `invoice`, `payment`; collection lines: `invoice`, `receipt` |
| number (≤ 100, optional), date (required), amount_sen (> 0), note (≤ 255, optional) | |
| created_by | |

Entry changes bump their line's `version`.

### Costing change

`costing_lines.group` (same values minus `collection`; default `principal`).

### Award hook

When a tender becomes Awarded (`MarkTenderAwarded`) and has no project yet:
- create the project with the company defaults;
- each costing line → a PD line in its group, name = description, budget = the line's **total cost**
  (`line_cost_sen`, so monthly lines carry their whole cost), reference = vendor;
- one Collection line "Contract value", budget = costing bid price, else `submitted_price_sen`, else 0;
- one activity entry "Project created from the costing".

If a project already exists (tender was reopened and re-awarded) nothing is copied again.

## 3. Calculations (`PdCalculator`, pure, integer)

**Per cost line:** PR, PO, Invoiced, Paid = sums of entries by type. Still owed = Invoiced − Paid.
Variance = Budget − Invoiced. Status = Pending → PR Raised → PO Issued → Invoiced → Paid (Paid when
Invoiced > 0 and Paid ≥ Invoiced; "Paid more than invoiced" when Paid > Invoiced). Over budget when
Invoiced > Budget.

**Per Collection line:** Invoiced, Received; still owed = Invoiced − Received. Status = Pending,
Invoiced, Partly received, Received ("Received more than invoiced" when Received > Invoiced).

**P&L — two columns, Budget and Actual:**

| Row | Budget | Actual |
|---|---|---|
| Revenue | Σ Collection budgets | Σ Collection invoices |
| Cost of sales | Σ budgets: principal, distributor, partner | Σ their invoices |
| Other costs | Σ budgets: finance_cost, tax, misc, internal | Σ their invoices |
| Project charges | revenue × charge bp (rounded to sen) | actual revenue × charge bp |
| Gross profit (GP), GP % | revenue − the three above | same |
| Commission | max(0, GP − revenue × approved margin bp) × commission share bp | same |
| Net profit, % | GP − commission | same |

Percentages of revenue are 0 when revenue is 0. Rounding: each percentage multiplication rounds
half up to the sen. **Below approved margin** warning when actual revenue > 0 and actual GP % <
approved margin %.

Reference example (the prototype's budget inputs): revenue RM 1,250,000; cost of sales 651,000;
other costs 132,000; project charges 9% = 112,500 → **GP 354,500 (28.4%)**; approved margin 15% =
187,500 → **commission (354,500 − 187,500) × 50% = 83,500** → **net profit 271,000 (21.7%)**.
(The prototype's own P&L is not internally consistent — its "GP 599,000" is revenue − cost of sales
only and its net profit omits commission — so this spec follows the old PD/Excel order: GP after all
costs and charges, then commission, then net.)

**Cash flow:** months from the earliest to the latest of: start date, end date, Collection scheduled
dates, entry dates. Per month: Expected in (Collection budgets by scheduled date), Received (receipt
entries), Paid out (payment entries), Balance (running Σ received − paid out). Empty when no dates.

**Duration bar:** days from start to today ÷ days from start to end (clamped 0–100%); hidden unless
both dates are set.

## 4. Screens

### PD tab (Awarded tenders; between Costing and Documents)

1. **Header strip:** project type picker (fills approved margin); approved margin %, project charge %,
   commission share % (Manager/Admin edit; others read); start/end dates; Close project / Reopen
   project (Manager/Admin). Saved on change.
2. **Profit & Loss** card (§3) + "Approved margin RM X (Y% · Type)" box + below-margin warning.
3. **Cost lines:** group buttons (with line counts); table for the selected group; **+ Add line**;
   name/reference/budget/scheduled date edited in place, saved on blur; **Documents** opens a panel
   under the line listing entries with an add/edit form (type, number, date, amount, note);
   **Remove** on entries, and on lines that have no entries.
   - Cost columns: Name · Reference · Budget · PR · PO · Invoiced · Paid · Still owed · Variance · Status.
   - Collection columns: Name · Scheduled date · Scheduled amount · Invoiced · Received · Still owed · Status · totals row.
4. **Cash flow** table + duration bar.

Read-only for users who cannot edit and for everyone when the project is closed.

### Elsewhere

- **Costing tab:** Group column (dropdown) per line.
- **Awarded list:** Actual GP % column (red when below approved margin) and a "Closed" badge.
- **Settings (Admin):** Project types (add, rename, edit margin, switch off/on; delete only if unused)
  and Company defaults.
- **Reopen tender** (existing): the project is kept and hidden while the tender is not Awarded.

## 5. Permissions

| Action | Who |
|---|---|
| View PD tab | everyone |
| Lines, entries, project type, dates | tender `update` policy (PIC, Manager, Admin) while project open |
| Approved margin / charge / commission per project | Manager, Admin |
| Close / reopen project | Manager, Admin |
| Project types, company defaults | Admin |

Enforced in the actions, not only hidden in the UI.

## 6. Error handling

- Validation beside fields: entry amount > 0; budget ≥ 0; valid dates; end ≥ start; entry type must
  suit the line's group; percentages 0–99.99%; name required.
- Overpayment allowed, flagged amber.
- Same-line conflict: "This line was changed by X — reload to see their changes"; typed values stay.
  Header uses the project version the same way.
- Closed project: "This project is closed. A Manager can reopen it."
- Each change runs in one transaction; nothing is half-saved.

## 7. Sample data

Seeder: 22 project types + defaults; the sample awarded tender gets a PD with a RM 1,250,000 contract
in 3 payments (30% / 30% / 40%), a few cost lines with PR/PO/invoice/payment entries, start/end dates.
Dev database: add the project types and defaults and create empty PDs for already-awarded tenders —
no other data changed.

## 8. Testing

- `PdCalculator`: reference example; negative commission → 0; revenue 0; overpaid lines; every line
  status; cash flow months and running balance; duration bar.
- Award hook: groups, monthly line totals, Contract value line, no-costing fallback, reopen + re-award
  does not duplicate.
- Actions: permissions per row of §5, closed-project refusal, same-line conflict, entry-type rules,
  line with entries cannot be removed, project type in use cannot be deleted.
- Livewire PD tab and Settings; browser walkthrough; coverage ≥ 80%.

## 9. Out of scope

Approval chain; Finance/SVP/PM roles; per-person commission split and privacy; expense claims;
file uploads for invoices/receipts; 7-year budget per category; SST calculations; company-wide finance
dashboards (Stage 6).
