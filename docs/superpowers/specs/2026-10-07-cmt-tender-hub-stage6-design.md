# CMT Tender Hub — Stage 6 Design (Dashboard and Status)

Date: 2026-10-07
Status: Approved in conversation, awaiting written-spec review
Builds on: Stages 1–5 — branch `stage-6` from `stage-5`.
References: prototype `TenderHub.html` → Dashboard (Quick Overview, Upcoming deadlines, Portfolio mix,
PIC summary) and Insights → Status (per-PIC table).

## 1. Purpose

An at-a-glance view of the tender pipeline for everyone (the new home page) and a per-PIC performance
report, both filterable by the period in which tenders were registered.

### Decisions made during brainstorming

| Question | Decision |
|---|---|
| Win rate | **Awarded ÷ (Awarded + Lost)**, cancelled tenders **left out**. "—" when nothing is decided. |
| Money | Two labelled figures: **Bid value** (submitted price for Done/Awarded/Lost; estimated value for In Progress; cancelled excluded) and **Won value** (submitted price of Awarded). Portfolio value = bid value. |
| Period | **One filter on both pages, by WO date**: All time (default), This month, Last month, This year, a specific month, custom range. |
| Scope | **Prototype Dashboard + two small cards** (Quotations, Projects). No finance-across-projects dashboard. |
| Architecture | **Live database totals on each page load**; one reporting class feeds both pages. |
| Visibility | Everyone signed in sees company-wide figures. Dashboard becomes the home page. |

## 2. Figures

All counts refer to tenders whose `wo_date` is inside the selected period (inclusive, Malaysia calendar
days). "Today" is `MalaysiaTime::today()`.

| Figure | Rule |
|---|---|
| In Progress / Done / Awarded / Lost | tenders in each status; Lost shown with "(n cancelled)" |
| Due this week | In Progress tenders with `closing_date` from today to today + 7 days |
| Win rate | Awarded ÷ (Awarded + Lost where `was_cancelled` is false), shown to 0 decimals with "a of b decided"; "—" when b = 0 |
| Bid value | Σ submitted price over Done/Awarded/Lost (not cancelled) + Σ estimated value over In Progress; tenders with neither count 0 and are reported as "n without a value" |
| Won value | Σ submitted price over Awarded |
| EP / Non-EP | the counts per status and the bid value, split by `mode` |
| Per PIC | Total, In Progress, Done, Awarded, Lost, Win rate, Bid value, Won value, Share of bid value; every **active** user listed (zeros allowed); inactive users only when they have tenders in the period; plus a totals row |
| Quotations card | Open = Sent and not expired (count + total); Accepted = accepted quotations with `quote_date` in the period (count + total) |
| Projects card | Running = open projects (tender Awarded or quotation Accepted); Below margin = running projects whose actual GP % is below the approved margin (Stage 4 rule). Ignores the period. |

Reference (prototype sample data, All time): 41 tenders — In Progress 13, Done 20, Awarded 3, Lost 5;
EP 40, Non-EP 1; PIC totals Ahmad Faizal 11, Nurul Ain 10, Siti Aisyah 10, Muhammad Hafiz 10.

## 3. Screens

### Dashboard (`/dashboard`, home page)

1. **Quick Overview:** period filter (right); six cards — In Progress (n due this week), Awarded (won
   value), Done, Lost (incl. cancelled), Win rate (of decided bids), Portfolio value (bid value); each
   card links to its tender list.
2. **Upcoming deadlines:** up to 8 In Progress tenders closing soonest (today onwards) with PIC
   initials, title, agency, closing date (red within 3 days); ignores the period; rows open the tender.
3. **Portfolio mix:** ring chart by status (plain SVG, no chart library) with the total in the centre
   and counts/percentages; EP vs Non-EP bar and two boxes (count, bid value, per-status counts).
4. **PIC summary:** PIC, number of tenders, share-of-value bar, bid value; grand total row; names link
   to Status.
5. **Quotations** and **Projects** cards (§2).

### Status (`/status`)

Period filter; table PIC · Total · In Progress · Done · Awarded · Lost · Win rate · Bid value · Won value,
totals row; sortable by any column (default bid value, highest first); numbers link to the tender list
for that status filtered by that PIC.

### Elsewhere

- Sidebar: **Dashboard** first under Operations; new **Insights** section with **Status**.
- `/` and the after-login destination go to the Dashboard.
- Empty states: "No tenders registered in this period."; win rate "—" with "no decided bids yet".
- The period is kept in the page address (`?period=…&month=…&from=…&to=…`).

## 4. Permissions

Both pages for every signed-in, active user; read-only.

## 5. Error handling

- Invalid custom range (end before start) or unreadable dates in the address → All time, with the note
  "That period wasn't valid — showing all time."
- Figures come from grouped database totals (a fixed small number of queries per page, tested), not a
  query per tender.

## 6. Testing

Win rate (formula, cancelled excluded, "—"); bid value (submitted vs estimated by status, cancelled
excluded, "without a value" count); periods (this month, last month, specific month, custom, all time,
Malaysia month boundary); due this week; deadlines list; EP/Non-EP; per PIC incl. zero rows and inactive
users; quotation and project cards; sorting; links; empty states; home-page redirect; prototype sample
check (§2 reference); query count stays fixed; browser walkthrough; coverage ≥ 80%.

## 7. Out of scope

Trend charts over time; Excel/PDF export; finance totals across projects; emailed reports; per-person
targets.
