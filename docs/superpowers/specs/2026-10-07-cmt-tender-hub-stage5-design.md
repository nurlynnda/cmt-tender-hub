# CMT Tender Hub — Stage 5 Design (Quotation)

Date: 2026-10-07
Status: Approved in conversation, awaiting written-spec review
Builds on: Stages 1–4 — branch `stage-5` from `stage-4`.
References: prototype `TenderHub.html` → Simple Quotation (list, Details / Items / Header /
Terms & Conditions / Preview, Duplicate, Download PDF); Stage 4 spec (projects/PD).

## 1. Purpose

Quick quotations to customers who ask directly, outside the tender process: prepare, preview,
download as PDF, send, and record whether the customer accepted. An accepted quotation can become
a project tracked in the Stage 4 PD.

### Decisions made during brainstorming

| Question | Decision |
|---|---|
| Link to the rest of the app | **Stand-alone**, plus **Create project** on an Accepted quotation (PD with a "Contract value" line = subtotal before SST; cost lines start empty). |
| Visibility / editing | **Everyone sees all quotations**; the preparer, Managers and Admins edit. Anyone may Duplicate. |
| Letterhead and stamp | **One company letterhead managed by Admins** (incl. stamp PNG/JPG upload), **copied into each quotation when it is created**. No per-quotation header tab. |
| Life cycle | **Locked once Sent.** Draft → Sent → Accepted / Rejected; Expired is derived; **Revise** makes `…-R1`; Managers/Admins can move back to Draft. |
| PDF | **Built on the server with Dompdf** (`barryvdh/laravel-dompdf`); the Preview tab shows the same PDF. |
| Saving | Every field saves when you leave it (Draft only), like the PD tab. |

## 2. Data model

Money in **sen**, percentages in **basis points**.

### company_profile (one row; Admin, Finance Settings → Company letterhead)

name, registration_no, sst_no, address (multi-line), phone, email, website, stamp_path (nullable;
private disk), default_terms (text, one per line), default_sst_bp (default 800).

### quotations

| Column | Notes |
|---|---|
| number | unique; `QTN-YYYY-NNNN` (per-year sequence in `quotation_sequences`, never reused); revisions `QTN-YYYY-NNNN-R1`, `-R2` |
| revision_of_id | nullable → quotations (the quotation this revises) |
| status | `draft`, `sent`, `accepted`, `rejected`, `revised` (Expired is derived: sent and today > valid_until) |
| quote_date, validity_days (1–365) | valid_until = quote_date + validity_days (computed) |
| customer_name, attention, attention_phone, attention_email, customer_address, subject | |
| prepared_by (user), preparer_position, preparer_phone, preparer_email | |
| show_signature, show_stamp | booleans (default true) |
| sst_bp | 0–9999 |
| terms | text, one per line |
| letterhead | JSON copy of the company_profile fields at creation; `stamp_copy_path` copy of the stamp file |
| sent_at/by, accepted_at/by, rejected_at/by | |
| version | conflict check |

### quotation_items

quotation_id (cascade), position, title (required, ≤ 255), details (text, optional; lines
"Label: value" print the label bold), quantity (int ≥ 1), unit (≤ 50), unit_price_sen (≥ 0).

### Totals (never stored)

line amount = qty × unit price; subtotal = Σ; SST = subtotal × sst_bp rounded half up to the sen;
total = subtotal + SST; total in words, e.g. "Ringgit Malaysia Thirty Eight Thousand Four Hundred
Forty Eight Only", with "and Fifty Sen" when there are sen.

Reference (prototype QTN-2026-0012): 6 × RM 4,850 + 1 × RM 6,500 = RM 35,600; SST 8% = RM 2,848;
**total RM 38,448.00**.

### Stage 4 changes

- `projects.tender_id` becomes nullable; new nullable unique `projects.quotation_id`; exactly one of
  the two is set.
- `activity_logs.tender_id` becomes nullable; new nullable `activity_logs.quotation_id`; exactly one
  set. Quotation history and its project's PD activity are recorded against the quotation.

## 3. Behaviour

### Quotation list (sidebar "Quotation" section → "Quotations")

Search (number, customer, subject); status chips All / Draft / Sent / Expired / Accepted / Rejected /
Revised; "Mine"; columns No., Date, Customer, Subject, Prepared by, Amount (total), Valid until (red
when expired), Status; newest first; 25 per page. **New Quotation** creates a Draft at once
(prepared by you, today, 30 days, default SST and terms, letterhead copy) and opens it.

### Quotation page

Header strip: number, status badge, "Revision of …" link, buttons by status:

| Status | Buttons |
|---|---|
| Draft | Mark as Sent · Download PDF · Duplicate |
| Sent / Expired | Mark Accepted · Mark Rejected · Revise · Download PDF · Duplicate |
| Accepted | Create project / Open project · Download PDF · Duplicate |
| Rejected / Revised | Download PDF · Duplicate |
| Manager/Admin | Back to Draft on Sent, Rejected, Accepted — not once a project exists |

Mark as Sent requires a customer name, a subject, at least one item and a total > RM 0; otherwise
it is refused with the list of what is missing.

Tabs: **Details** · **Items** (title, details, qty, unit, unit price, amount; add/remove/move; live
subtotal, SST, total) · **Terms & Conditions** (text box + "Reset to company default") · **Preview**
(the actual PDF embedded) · **History**. Editable only while Draft by the preparer/Manager/Admin.

### PDF

Letterhead (name, registration no., address, phone · email · website, SST no.) and title
"QUOTATION" with No., Date, Valid until; "Quotation to" block with attention; subject bar; item
table (bold "Label:" details); subtotal, SST (x%), total; total in words; numbered terms; "Prepared
by" with handwriting-style typed signature (if on), name, position, company, stamp (if on and
present); empty "Accepted by" block; footer "This is a computer-generated quotation." Multi-page with
repeated table header. File name `QTN-2026-0012.pdf`.

### Duplicate / Revise

- **Duplicate:** new Draft, new number, prepared by you, today; copies customer, subject, items, SST,
  terms; takes the **current** letterhead.
- **Revise** (Sent/Expired): new Draft `…-Rn` (n = 1 + revisions so far of the root), full copy
  including preparer and letterhead; original becomes **Revised** and links to it.

### Create project

Accepted quotations only, once. Creates a project with a Collection line "Contract value" = subtotal
(before SST), using company defaults for charges/commission. Opens on a PD page
(`/quotations/{id}/pd`) with the Stage 4 PD screen and a back link. PD editing follows the quotation:
preparer/Manager/Admin while the quotation is Accepted and the project open; rates and close/reopen
Manager/Admin.

## 4. Permissions

| Action | Who |
|---|---|
| View, preview, download, Duplicate | everyone |
| Edit Draft; Mark Sent / Accepted / Rejected; Revise; Create project | preparer, Manager, Admin |
| Back to Draft | Manager, Admin (not once a project exists) |
| Quotation project: lines and documents | preparer, Manager, Admin; quotation Accepted; project open |
| Quotation project: rates, close/reopen | Manager, Admin |
| Company letterhead, stamp, default terms, default SST | Admin |

All enforced on the server.

## 5. Error handling

- Validation beside fields: quantity ≥ 1; unit price ≥ 0; SST 0–99.99%; validity 1–365; valid emails;
  item title required.
- Stamp: PNG/JPG ≤ 1 MB, else "Upload a PNG or JPG image of 1 MB or less."; stored on the private disk,
  served only through the app to signed-in users.
- Conflicts: "This quotation was changed by X — reload to see their changes."; typed values stay.
- Locked: "This quotation has been sent. Revise it to make changes."
- Numbers generated inside a locked transaction (no duplicates).
- PDF failure: friendly error message, logged.

## 6. Sample data

Letterhead: the prototype's CMT Sdn. Bhd. (201901000000 (1234567-X)), Level 8, Menara Example, Jalan
Tun Razak, 50400 Kuala Lumpur, Malaysia; +603-0000 0000; sales@cmt.com.my; www.cmt.com.my; no stamp;
default SST 8%; the prototype's 6 default terms. Quotations:
- QTN-2026-0012, 18 Sep 2026, 30 days, JPNIN (attention Puan Rozita binti Hassan, Ketua Unit ICT),
  "Supply of network switches and installation for JPNIN HQ", Siti Aisyah, Sent; items 6 × RM 4,850
  switch (with spec details) and 1 Lot × RM 6,500 installation; SST 8% → RM 38,448.00.
- QTN-2026-0011, 10 Sep 2026, Majlis Perbandaran Klang, "Annual maintenance for CCTV system (12 months)",
  Muhammad Hafiz, Accepted; 1 Lot × RM 19,000; SST 8% → RM 20,520.00.
- QTN-2026-0010, 04 Aug 2026, 30 days, Pejabat Daerah Kuantan, "Laptop rental for 18 months",
  Ahmad Faizal, Sent (shows Expired); 1 Lot × RM 30,000; SST 5% → RM 31,500.00.
Dev database: letterhead, default terms and SST only.

## 7. Testing

Totals and words (reference example; rounding; 0% SST; sen); numbering (per year, revisions, never
reused); status rules incl. derived Expired and Back-to-Draft guard; permissions per §4; Duplicate /
Revise copies; PDF produced with number, total and words, stamp only when on and present; Create
project (subtotal, once, PD rules for quotation projects, tender projects unaffected); stamp upload
checks; browser walkthrough; coverage ≥ 80%.

## 8. Out of scope

Emailing from the app; customer address book; multiple letterheads; discounts; currencies other than
RM; linking quotations to tenders.
