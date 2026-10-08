# CMT Tender Hub — Quotation costing, simpler tender costing, per-item SST

**Date:** 2026-10-08 · **Branch:** `quotation-costing` (built on `market-insights`)

## Goal

1. Simplify tender costing:
   - frequency becomes a whole number
   - drop Year
   - drop Group
   - let people type a selling price and see the margin worked backwards
2. Make quotation items work like tender costing:
   - each item has a cost and margin %, which work out the selling price
   - each item can have sub-items, a vendor and a quote link
   - an editable selling price, with the margin worked backwards
   - a profit summary
3. Charge SST per quotation item (a tick box), not on the whole quotation.

## User decisions (from brainstorming)

- **Frequency:** an open whole number, 1 or more. The line total = price × quantity × frequency.
- **Year:** removed, including the "Cost by year" table.
- **Group:** removed.
- **Quotations copy everything from costing (A):**
  - cost + margin give the selling price
  - sub-items, vendor and quote link
  - the summary box with the 18% target warning
- **SST (B):** a per-item **SST tick box**, ticked by default, with no Service/Hardware label.
- **Price override (A):** each line's selling unit price is editable. Typing a price works the margin out backwards, on both quotations and tender costing.
- **PDF and hand-off (Part 3):**
  - a Freq. column, shown only when it's needed
  - a \* on SST items, and "SST 8% (on items marked \*)"
  - when a quotation becomes a project, its cost lines go into the PD budget under Principal

## 1. Shared costing maths

`App\Costing\CostingCalculator` stays the single place for the maths. Everything is in integer sen and basis points (1% = 100 bp).

- `pricePerUnitSen(cost, marginBp)` is unchanged: cost ÷ (1 − margin), rounded up to the whole ringgit.
- **`line(array)`:**
  - `months` and `frequency` ('one_off'/'monthly') are replaced by `frequency` (int ≥ 1)
  - `line_cost_sen` = quantity × unit cost × frequency
  - `price_per_unit_sen` = the override when present, otherwise `pricePerUnitSen`
  - `selling_sen` = price × quantity × frequency
  - new `effective_margin_bp` = round((price − cost) × 10000 ÷ price), or 0 when the price is 0. It can be negative.
- **`summary()`:** `cost_by_year` is removed. Everything else is unchanged.
- **Margin from a typed price:**
  - the line's stored `margin_bp` is left as it was
  - the screen shows `effective_margin_bp` in the margin box while an override is set, with a small "from price" hint
  - editing the margin clears the override, so the price is worked out again
  - clearing the price box also clears the override

## 2. Tender costing changes

- **Migration** (`costing_lines`):
  - add `unit_price_override_sen` (unsigned big int, nullable)
  - change `frequency` from string to unsigned int, default 1, filled as `months` when it was 'monthly', otherwise 1
  - drop `months`, `project_year` and `pd_group`
  - real data today: 248 lines, all one-off, year 1, Principal, so no total changes
- **`CostingForm`:**
  - rules: `frequency` is a required integer from 1 to 1000
  - new `unit_price` is optional money
  - remove the months/year/group rules and fields
  - `toData` sets `unit_price_override_sen` when a price is typed
- **Screen (`tender-costing`):**
  - columns: Item · Qty · Unit · Frequency (a number box) · Unit cost · Margin % · **Unit price (editable)** · Line total
  - the Year and Group columns are removed, and so is the "Cost by year" card
  - the margin box shows the worked-back margin while a price is typed
- **Excel import (`CostingImport`):** a monthly row becomes frequency = its months. Year and group columns in the sheet are ignored.
- **PD hand-off (`CreateProjectFromCosting`):** each cost line becomes a PD line under **Principal**, with budget = `line_cost_sen`. The group can still be changed on the PD page.
- **Other places:**
  - `Tender::costingSummary` mapping
  - `RegisterImporter` (it writes `frequency => 1` and no longer writes months/year)
  - the costing factory and tests

## 3. Quotation items like costing

- **Migration:**
  - `quotation_items` adds:
    - `frequency` (unsigned int, default 1)
    - `unit_cost_sen` (unsigned big int, default 0)
    - `margin_bp` (unsigned small int, default 2000)
    - `unit_price_override_sen` (nullable)
    - `vendor` (nullable)
    - `quote_url` (500, nullable)
    - `has_sst` (bool, default true)
  - the existing `unit_price_sen` is copied into `unit_price_override_sen`, so existing items keep their exact price, and then dropped
  - new table `quotation_item_sub_items`: id, quotation_item_id FK cascade, position, description(500), unit(50), quantity, unit_cost_sen, vendor, quote_url
  - `quotations` adds `default_margin_bp` (unsigned small int, default 2000)
- **Price of an item:** the same calculator as tenders. The unit price shown to the customer is the line's `price_per_unit_sen`, either worked out or overridden.
- **Editor (`quotation-page`, drafts only, as today):** each item row has:
  - **for the customer:** Title · Details · Qty · Unit · **Frequency** · **Unit price (editable)** · Amount
  - **internal (collapsible, like the costing sub-item area):** Unit cost (or sub-items) · Margin % · Vendor · Quote link · **SST ✓**
  - the quotation header gets a **Default margin %**, used for new items
- **Summary card** (internal, not on the PDF):
  - Total cost · Quotation subtotal · Margin RM and % · SST · Total
  - "Below the 18% company target" warning
- **Saving:** keeps today's save-as-you-type per field. Sub-items are added and removed with buttons, as on costing.
- **Totals (`QuotationTotals::of`):**
  - each line amount = price × qty × frequency
  - subtotal = the sum of the lines
  - **SST = the quotation's SST % of the sum of the ticked lines** (half up to the sen)
  - total = subtotal + SST
  - the amount in words follows the total
- **Sent, accepted, rejected and revised quotations** aren't editable (as today). Their totals come out the same as before, because existing items have SST ticked and frequency 1 and keep their price.

## 4. Customer PDF

- **Columns:** No · Description · Qty · Unit · [Freq.] · Unit price · Amount.
  - Freq. appears only when any item's frequency is above 1.
  - Amount = unit price × qty × frequency.
- **SST:**
  - ticked items show a **\*** after the amount
  - the totals read: Subtotal · **SST 8% (on items marked \*)** · Total
  - with no items ticked, the SST line reads RM 0.00
- **Never on the PDF:** cost, margin, vendor, quote link or sub-items.

## 5. Quotation → project

`CreateProjectFromQuotation`:
- keeps the Collection "Contract value" line (subtotal before SST, as today)
- adds one **Principal** line per item, named after the item title, with reference = vendor and budget = cost × qty × frequency
- items with a cost of 0 are skipped

## Error handling

- Frequency, quantity and money are validated like costing today, and the messages use plain names.
- A typed price below cost is allowed. The margin shows as negative in red, and the summary warning shows.
- While typing, live totals use lenient parsing, as costing does now.

## Testing

- **Calculator unit tests:**
  - frequency multiplies cost and selling
  - an override sets the price and the effective margin (including below cost and a price of 0)
  - `cost_by_year` is gone
- **Costing migration test:** monthly × 12 becomes frequency 12, and one-off becomes 1, with totals unchanged.
- **Tender costing Livewire:**
  - the frequency box
  - typing a price shows the worked-back margin
  - editing the margin clears the price
  - no Year/Group columns
  - Excel import with a monthly row
  - PD hand-off lands lines under Principal
- **Quotation:**
  - each item works out its price from cost and margin, or uses the override
  - sub-items add up into the cost
  - the default margin applies to new items
  - the summary and the 18% warning
  - SST is only on ticked items
  - the PDF shows Freq. only when needed, \* and "on items marked \*"
  - a migrated item keeps its price
  - CreateProjectFromQuotation adds Principal cost lines
- **Real data:** after migrating, every tender's costing total and every quotation total is unchanged (compare before and after).
- **Browser check** of both editors and the PDF.

## Out of scope

- Per-item SST rates (one rate per quotation).
- A total-price override on quotations.
- Changing the Find Tenders, PD or Market Insights screens.
