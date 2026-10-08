# CMT Tender Hub — Stage 3 Design (Costing)

Date: 2026-10-07
Status: Approved in conversation, awaiting written-spec review
Builds on: Stage 1 (pipeline) and Stage 2 (Find Tenders) — branch `stage-3` from `stage-2`.
References: prototype `TenderHub.html` Costing tab; PD's `docs/superpowers/specs/2026-08-11-sales-module-design.md` §7 (top-down costing, 18% target, target-cost guide, project year).

## 1. Purpose

Give every pipeline tender a costing: the cost lines behind the bid, the suggested selling
price they imply, the bid price actually used, and the resulting margin. The costing's bid
price becomes the tender's submitted price at Mark Done.

### Decisions made during brainstorming

| Question | Decision |
|---|---|
| Pricing model | **Both**: lines carry a margin %, their selling prices add up to a *suggested* bid price; the user may override it. Margin is always computed from the bid price actually used. 18% target warning and target-cost guide apply (from PD). |
| Recurring costs vs project year | **Both, simple**: each line has a frequency (one-off, or monthly × N months) **and** one project year (1–7) in which its whole cost counts. No automatic splitting across years. |
| Markup entry | As a **margin %** (e.g. 20%); costing has a default that pre-fills new lines; any line can override. Price = cost ÷ (1 − margin), identical to the spreadsheet's divisor. |
| Link to Mark Done | **Required**: Mark Done is blocked until the tender has a costing; the submitted price is always the costing's bid price (no typing). |
| Editing style | **Edit, then Save**: edits are held on screen; one Save writes the whole costing with one conflict check and one activity entry. All maths in one PHP calculator. |
| Visibility / editing | Everyone sees costings and margins. PIC, Managers, Admins edit while In Progress. Locked once Done (like the rest of the tender). |
| Vendor / quotation | Vendor free text with suggestions from previously used vendors; quotation link is an http(s) URL (e.g. shared drive). |

## 2. Calculation rules (the `CostingCalculator`)

All money in **sen** (integers). Margin percentages stored as **basis points** (1% = 100 bp; 20% = 2000).

**Line inputs:** description, unit, quantity (integer ≥ 1), frequency (`one_off` | `monthly`),
months (integer ≥ 1; forced to 1 for one-off), project year (1–7), unit cost (sen ≥ 0),
margin (bp, 0–9999), vendor (optional), quotation link (optional http/https).

**Sub-items (one level):** description, unit, quantity (≥ 1), unit cost (sen ≥ 0), vendor, link.
When a line has sub-items, its **effective unit cost = Σ(sub quantity × sub unit cost)** — the
cost of *one* unit of the line — and its own unit-cost input is ignored.

**Per line:**
- `months_factor` = months for monthly, 1 for one-off
- `line cost` = quantity × effective unit cost × months_factor
- `price per unit` = ⌈ effective unit cost ÷ (1 − margin) ⌉ rounded **up to the whole ringgit** (i.e. to a multiple of 100 sen)
- `line selling price` = price per unit × quantity × months_factor

Rounding is done in integer arithmetic: `ceil(unitCostSen × 10000 / (10000 − marginBp) / 100) × 100`
(exact; no floating point).

**Costing totals:**
- `total cost` = Σ line cost
- `suggested bid` = Σ line selling price
- `bid price` = override if set, else suggested
- `margin` = bid price − total cost (may be negative)
- `margin %` = margin ÷ bid price (0 when bid price is 0), shown to 1 decimal
- `below target` = margin % < 18% (constant `COMPANY_TARGET_MARGIN_BP = 1800`)
- `under budget %` = (estimated value − bid price) ÷ estimated value, only when the tender has an estimated value > 0 (negative = over budget)
- `target-cost guide`: for each margin 12%…21%, `max total cost = bid price × (1 − margin)` (rounded down to the sen)
- `cost by year`: Σ line cost grouped by project year (shown; used by Stage 4)

**Reference example (prototype JPNIN, `docs/superpowers/plans/assets/jpnin-costing.json`):**
12 one-off lines, quantity 1, margin 20%: total cost **RM 132,845.00**, suggested bid
**RM 166,059.00**, margin **RM 33,214.00**, **20.0%**. E.g. RM 18,599 → RM 23,249.

## 3. Data model

### tenders (Stage 1 table) — added columns

| Column | Notes |
|---|---|
| default_margin_bp | unsigned smallint, default 2000 |
| bid_price_override_sen | nullable unsigned bigint (null = use suggested) |

### costing_lines

| Column | Notes |
|---|---|
| id, tender_id (cascade) | |
| position | display order |
| description | string(500), required |
| unit | string(50), default 'unit' |
| quantity | unsigned int ≥ 1 |
| frequency | `one_off` / `monthly` |
| months | unsigned smallint ≥ 1 |
| project_year | unsigned tinyint 1–7 |
| unit_cost_sen | unsigned bigint (ignored when the line has sub-items) |
| margin_bp | unsigned smallint 0–9999 |
| vendor | nullable string(255) |
| quote_url | nullable string(500), http/https only |

### costing_sub_items

`id`, `costing_line_id` (cascade), `position`, `description`, `unit`, `quantity`, `unit_cost_sen`, `vendor`, `quote_url` — same rules as lines.

Totals are never stored; always computed by the calculator.

**"Has a costing"** = at least one line **and** bid price > 0.

## 4. Behaviour

### Costing tab (tender detail, between Overview and Documents)

- **Summary:** Total cost · Bid price (suggested, or "your price" + **Reset to suggested**) · Margin RM · Margin % (red + ⚠ "Below the 18% target" when below) · Under budget % · cost/margin bar.
- **Default margin %** input + **Apply to all lines** (overwrites every line's margin with the default).
- **Cost table:** Item · Qty · Unit · Frequency (+ months) · Year · Unit cost · Line cost · Margin % · Price/unit · Selling price · Vendor · Quotation link · actions. Sub-items indented under their line; a line with sub-items shows its computed unit cost read-only.
- Actions: **+ Add line**, **+ Add sub-item** (per line), **Remove** (line or sub-item), move up/down.
- **Bulk import:** a text box; each pasted row is `description <TAB or comma> quantity <sep> unit <sep> unit cost` (Excel copy gives tabs). Valid rows are appended with the default margin, one-off, year 1. Invalid rows are skipped and listed ("Row 3: unit cost is not a number").
- **Target-cost guide** table (12–21%) and **Cost by year** table.
- **Save:** validates everything, then writes the whole costing in one transaction with the Stage 1 version check (`tenders.version`) and one activity entry: `Costing saved — bid price RM X, margin Y%`. "Unsaved changes" indicator while dirty; browser `beforeunload` warning when leaving with unsaved changes.
- Totals refresh when a field loses focus (Livewire round trip to the calculator).
- **Read-only** for users who can't edit, and when the tender is not In Progress.

### Mark Done (changes Stage 1 behaviour)

- Blocked with "Add a costing before marking this tender Done" when the tender has no costing.
- Blocked with "Save your costing changes first" when the Costing tab has unsaved edits.
- Otherwise the confirm dialog shows the costing's bid price as the submitted price (read-only); confirming stores it in `submitted_price_sen` (existing document-checklist rule still applies).
- `MarkTenderDone` computes the price itself from the saved costing (the submitted price is no longer an input).

### Done list

Adds the prototype's **Gross** column = costing margin % for tenders with a costing (blank otherwise).

### Permissions

Viewing: everyone. Saving: `update` policy (PIC / Manager / Admin) and status In Progress — enforced in the `SaveCosting` action, not just hidden in the UI.

## 5. Error handling

- Field validation beside each field; Save refuses until fixed (quantity/months ≥ 1, year 1–7, unit cost ≥ 0, margin 0–99.99%, valid link, description required, override ≥ 0).
- Edit conflict → "This tender was changed by X — reload to see their changes"; on-screen edits are kept.
- Bulk import never aborts on a bad row.
- Any server error → friendly message; nothing partially saved (single transaction).

## 6. Sample data

Seeder gives WO `200-10092026-001` (JPNIN) its 12-line costing at 20% (bid RM 166,059.00).

## 7. Testing

- `CostingCalculator` unit tests: JPNIN exact totals; ringgit round-up; sub-items; monthly; override; margin %/below-target; under-budget (incl. over budget, no estimate); target-cost guide; cost by year; zero lines; 0% margin; bid 0.
- `SaveCosting` action: saves lines + sub-items, replaces previous, version check, permission, locked when not In Progress, activity entry, validation.
- `MarkTenderDone`: blocked without costing; uses bid price.
- Livewire Costing tab: add/remove/reorder, sub-items, apply default, override + reset, bulk import (good + bad rows), unsaved indicator, read-only states, Mark Done blocked when dirty.
- Browser walkthrough; coverage ≥ 80%.

## 8. Out of scope

Carrying costs into project finances (Stage 4), linking Stage 5 quotations, uploading quotation files, costing approval/revisions history beyond the activity log, SST/tax (costing is tax-exclusive).
