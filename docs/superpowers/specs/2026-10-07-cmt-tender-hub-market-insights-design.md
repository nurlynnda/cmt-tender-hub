# CMT Tender Hub — Awarded tenders and Market Insights (from tms-v2)

**Date:** 2026-10-07 · **Branch:** `market-insights` (built on `find-tenders-closing-sort`)

## Goal

Bring back two things from the old tender aggregator (tms-v2):
1. the **Awarded Tenders** list: government tenders with a published winner, the contractor and the price won, plus a contractor search
2. the **market dashboard**: awards by year, spend by ministry, top contractors

Both are built on the collected tenders the new app already holds: 152,331 awards with winners, almost all from MyProcurement. The app also recognises the user's own company, **10 Creative Solutions Sdn Bhd**, in the results.

## User decisions (from brainstorming)

- **Placement (A):**
  - "Awarded" becomes a fourth status in Find Tenders (Open · Closed · Awarded · All)
  - a new **Market Insights** page under Insights
  - the existing Dashboard stays about the company's own pipeline
- **Period (A):** a year picker (All years, or a single year), defaulting to the current year. The "by year" charts always show every year.
- **Own company (A):** both data spellings belong to the company, and only those two:
  - "10 CREATIVE SOLUTIONS SDN. BHD." (118 awards)
  - "10 CREATIVE SOLUTIONS SDN BHD" (1 award)

  Today that's 119 awards: 23 in 2026, 22 in 2025, 9 in 2024, 20 in 2023, and 48 with no closing date.
- **Name matching:** contractor names that differ only in capitals, dots, commas or spacing are the same company everywhere.
- **Approach 1:** a winners table kept beside the collected tenders, filled once and kept current by the collection.

## 1. Winners data

- **Table `collected_tender_winners`:**
  - `id`
  - `collected_tender_id` (FK, cascade on delete)
  - `name` (string 500, as published)
  - `name_key` (string 500, indexed)
  - `price_sen` (unsigned big int, nullable)
  - `position` (small int)
  - index (`name_key`, `collected_tender_id`)
- **Name key** (`App\Collector\ContractorName::key(string): string`):
  - upper-case
  - replace every character that is not a letter or digit with a space
  - collapse spaces and trim

  "10 Creative Solutions Sdn. Bhd." → "10 CREATIVE SOLUTIONS SDN BHD"; "SDN.BHD." → "SDN BHD". A blank result means no winner row.
- **Sync** (`App\Collector\WinnerIndex::sync(CollectedTender $t): void`): delete the tender's winner rows and insert one per entry in `$t->winners` with a non-blank name. Idempotent.
- **Kept current:** a `CollectedTender` model `saved` hook calls `sync` when `winners` changed (`wasChanged('winners')`) or the tender was just created. Everything that saves through the model (the `Merger`, used by the daily collection) is covered.
- **Backfill:** `php artisan collector:index-winners` rebuilds the whole table in chunks (by id, 2,000 at a time), skipping tenders without winners, and prints counts. It's safe to re-run. The legacy import bulk-inserts without the model, so it needs this command once after it runs. The README says so.
- **The JSON column `collected_tenders.winners` is unchanged** and stays the published record.

## 2. Own company names

- Migration: `finance_settings.own_company_names` (text, nullable), seeded with `10 CREATIVE SOLUTIONS SDN BHD`.
- Finance Settings gets a field, "Our company names in tender results (one per line)", inside the Company defaults card, saved with Save. Admin only, as the page already is.
- `App\Market\OwnCompany::keys(): array` returns the distinct name keys of those lines (`ContractorName::key`), cached per request.

## 3. Awarded in Find Tenders

- `CollectedTenderQuery`:
  - status `awarded` = `status = 'closed'` AND a winner row exists (`whereExists` on `collected_tender_winners`). Default order: latest closing date first (the same rule as "closed"); the Closing heading sort still applies.
  - `contractor` filter (any status): a winner row exists whose `name_key LIKE %key(term)%`, with the LIKE wildcards in the term escaped
  - `ours` filter (`'1'`): a winner row exists whose `name_key` is in `OwnCompany::keys()`
- `FindTenders`:
  - `#[Url] contractor`, `#[Url] ours` (bool)
  - the status select gains "Awarded"
  - the Filters panel gains a "Contractor" box
  - an "Our wins" toggle button appears beside the search **only when status = awarded** (it reuses the Round 1 filter-bar `mine` slot styling, labelled "Our wins")
  - `filterCount()` counts `contractor`
  - `ours` shows as the toggle's pressed state
  - `clearFilters` resets both
- **Awarded table columns:** Reference · Title · Ministry / Agency · Closing · Winner(s) · Price won · Indicative price.
  - Winners come from the winner rows (eager-loaded), one per line, each with its price.
  - A green "Ours" pill appears next to a winner whose key is the company's.
  - Other statuses keep today's columns.
- **Phone cards:** add winner(s) plus price won when awarded.
- **Detail page:** the Winners card shows the "Ours" pill beside the company's rows.

## 4. Market Insights

- **Route and sidebar:**
  - route `/market-insights` (`market.index`), Livewire `MarketInsights`, layout app, title "Market Insights"
  - sidebar: Insights group, after Status, icon `chart`, label "Market Insights"
  - visible to every signed-in user (the data is public government data)
- **Year:**
  - `#[Url] year`: `'all'` or a 4-digit year, defaulting to the current Malaysia year
  - options = "All years" plus the distinct closing-date years of awarded tenders, newest first
  - an unknown value falls back to the current year, with a note "That year has no awards — showing {year}"
- **Report:** `App\Market\MarketReport` (one class, no N+1). Every query works on awarded tenders (closed, with winners), joined to winner rows. "In year Y" means `YEAR(closing_date) = Y`; "all" applies no date condition.
  - `now()`: open tenders count, closing today, closing in the next 7 days. These use the same rules as the Find Tenders open list, and today is Malaysia time.
  - `summary(year)`:
    - awarded tenders (distinct)
    - total value (the sum of winner `price_sen`)
    - distinct contractors (`name_key`)
    - awards with no price (winner rows whose `price_sen` is null)
  - `byYear()`: for each year, awarded tenders and value, newest first, excluding a null closing date
  - `byMinistry(year, ?limit)`: ministry (null → "Not stated"), awarded tenders and value, sorted by value descending
  - `contractors(year, ?search, page)`:
    - grouped by `name_key`
    - display name = the most common published spelling (`MAX(name)` is acceptable)
    - wins (distinct tenders) and value, sorted by value descending, then wins, then name
    - paginated 50 at a time, with an optional search on `name_key`
  - `ownRank(year)`: the company's position in that ordering, plus its wins, value and the total contractor count. Null when the company has no wins that year. The rank comes from counting the contractors with a larger value (a single query).
- **Page** (Round 1 and 2 style: `x-page-heading` plus a year select in actions, `x-card`, bar rows like the Dashboard PIC share bars):
  - **"Right now" boxes:** Open · Closing today · Closing this week, each linking to Find Tenders with the matching filters (closing from/to)
  - **"{year}" boxes:** Awarded tenders · Awarded value · Contractors, with a muted note "Excludes N awards with no published price" when N > 0
  - **"10 Creative Solutions" card:** "#14 of 2,310 contractors · 23 wins · RM 4.2M" in the year, linking to Find Tenders Awarded plus Our wins plus closing from/to of the year. If there are no wins that year: "No awards in {year}".
  - **Two cards with all years:** Awarded value by year · Tenders awarded by year (horizontal bars, largest = 100%)
  - **Spend by ministry:** top 10 for the year; "See all" opens `/market-insights/ministries?year=`
  - **Top contractors:**
    - top 10 for the year
    - each name links to Find Tenders Awarded plus contractor = name plus that year's closing dates
    - the company's rows are highlighted (accent tint) and shown as an extra "… #14" row under the 10 when outside them
    - "See all" opens `/market-insights/contractors?year=` (a search box, 50 per page, same row style)
- **"See all" pages:** Livewire `MarketMinistries` and `MarketContractors`, with the same year handling and a "← Back to Market Insights" link that keeps the year.

## Error handling

- Winner entries with a blank name or a missing price are stored without a price (a blank name is skipped) and never break a sync.
- A non-numeric year → the current year, with a note.
- A contractor search shorter than 2 characters (after keying) is ignored.
- The backfill reports and skips (does not crash on) a tender whose winners JSON isn't an array.

## Testing

- **Unit:** `ContractorName::key` with every punctuation and spacing variant, including both company spellings and "SDN.BHD.".
- **Feature:**
  - `WinnerIndex::sync` and the saved hook: create, update winners, clear winners
  - the backfill command: counts, re-run doesn't duplicate, bad JSON skipped
  - the Awarded status, contractor filter and Our wins (only the two company spellings, not "DARKWHITE CREATIVE SOLUTIONS")
  - each `MarketReport` figure on a small fixture (two years, a null closing date, a null price, two spellings of one contractor, a ministry-less tender)
  - `ownRank` ordering and ties
  - the Market Insights page: year picker, links, extra own row
  - the See-all pages: search and pagination
  - Finance Settings saves the company names
  - the sidebar item
- **Real data:**
  - run the backfill
  - check 10 Creative Solutions shows 119 awards in All years (23 / 22 / 9 / 20 by year)
  - browser check of the pages at three widths and in dark mode
  - each page loads in under 1 second

## Out of scope

- An award announcement date (not in the source data).
- Charts beyond bars, export to Excel, and contractor profile pages beyond the filtered Awarded list.
- Changing how the collector gathers winners.
