# CMT Tender Hub — Stage 2 Design (Find Tenders + Collector)

Date: 2026-10-06
Status: Approved in conversation, awaiting written-spec review
Builds on: `2026-10-06-cmt-tender-hub-stage1-design.md` (branch `stage-1`, PR open)
Replaces: the Node collector in `C:\Projects\tms-v2` (GitHub `nurlynnda/tenderaggregator`)

## 1. Purpose

Bring the government tender collector into Tender Hub so staff can browse every
collected tender ("Find Tenders") and register one into the pipeline in a click,
pre-filled. After Stage 2 is verified, the old `tms-v2` app is switched off.

### Decisions made during brainstorming

| Question | Decision |
|---|---|
| Existing 206,645 collected tenders | Copy once from tms-v2's MongoDB into MySQL (one-time import command) |
| KWSP (Cloudflare-protected, needs a real browser) | Dropped. Its 126 imported tenders stay as history, never updated |
| Pages in Stage 2 | Find Tenders list + collected-tender detail + "Register this tender". Dashboard, Ministry and Contractor pages move to Stage 6 |
| Schedule | Daily 12:01pm Malaysia time (catch-up if missed) + "Collect now" (Managers/Admins, open tenders only) + status line |
| Link to pipeline | Connected: pipeline tender stores `collected_tender_id`; Find Tenders shows "Registered as WO …" |
| Approach | Rewrite the 3 collectors in PHP inside Tender Hub; background jobs via a `worker` container |
| Requisition type | Maps to pipeline type **Quotation** |
| Visibility | Everyone can see Find Tenders (Stage 1 rule) |

## 2. Sources

| Source | Site | What is collected daily | Notes |
|---|---|---|---|
| `myprocurement` | myprocurement.treasury.gov.my (`/procurements/fetch`, JSON with HTML fragments, 100/page) | Open quotation, tender, requisition advertisements; daily results ("keputusan") for quotation and tender | Archive jobs exist in the old collector but are not needed after the import; the PHP adapter implements only open + daily-results jobs |
| `span` | span.gov.my/tender | Current-year listing (open) | Needs the DigiCert intermediate certificate (copied from tms-v2) trusted **for SPAN requests only** |
| `llm` | llm.gov.my/swasta/tender_tawaran (open, paginated by 6) and tender_keputusan (results, first page only) | Open listings + latest results with winners; detail pages for open tenders | Results pagination past page 1 is broken on their server (documented in tms-v2) |

Behaviour of each parser must match the tms-v2 parser on the same saved pages
(fixtures copied from `tms-v2/backend/test/fixtures/`, excluding KWSP).

## 3. Architecture

| Unit | Responsibility |
|---|---|
| `App\Collector\PoliteFetcher` | Serial downloads; 300 ms + up to 200 ms random pause before each; up to 3 attempts with 1 s / 4 s / 16 s back-off; on 429/503 waits `Retry-After` (or 60 s) and the first such wait does not use up an attempt; User-Agent `CMTTenderHub/1.0`; JSON or text mode; optional per-source extra CA certificate. Sleep and HTTP are injectable for tests |
| `App\Collector\Sources\{MyProcurement,Span,Llm}Source` | Implement `CollectorSource`: `name()`, `collect(CollectScope $scope, callable $onBatch): SourceResult`. Build URLs, page through, parse, emit `TenderPatch` batches |
| `App\Collector\Parsers\*` | Pure functions: page HTML/JSON in → list of `TenderPatch`. Tested against fixtures |
| `App\Collector\TenderPatch` | Value object mirroring tms-v2's `TenderPatch` (dedupKey, referenceNo, title, status, procurementType, scrapedAt, source + optional fields). Validated on construction; invalid → skipped and logged |
| `App\Collector\Merger` | Saves patches with tms-v2's rules: one row per `dedup_key`; field-level "newest observation wins" using per-field timestamps; `null` never overwrites a known value; sources list updated per source |
| `App\Collector\StaleOpenCloser` | Daily: marks `open` tenders `closed` once closing date has passed in Malaysia time (or, with no closing date, one month after advertised date) |
| `App\Jobs\RunCollection` | Queued job: creates a `collection_runs` row, runs each source in turn (a failing source is recorded, others continue), runs `StaleOpenCloser`, finishes the row |
| `App\Console\Commands\ImportLegacyTenders` | `collector:import-legacy` — one-time copy from MongoDB, batched, idempotent, prints per-source counts |
| Scheduler | `RunCollection` daily at 12:01 MYT; on scheduler start, run immediately if today's 12:01 run was missed |
| `worker` container | `php artisan queue:work` (database queue) |

`dedup_key` = reference number upper-cased with whitespace removed; if empty, `<source>:<sourceId>` (same as tms-v2 `computeDedupKey`).

## 4. Data model

All money in **sen** (ringgit × 100, rounded). Calendar dates are `DATE` (Malaysia days, `Y-m-d`).

### collected_tenders

| Column | Notes |
|---|---|
| id | |
| dedup_key (unique) | |
| reference_no, title (text) | |
| status | `open` / `closed` |
| procurement_type | `quotation` / `tender` / `requisition` / null |
| ministry, agency, category | nullable |
| advertised_date, closing_date | nullable DATE |
| indicative_price_sen | nullable unsigned big int |
| events | JSON list of `{label, date, address}` |
| winners | JSON list of `{name, price_sen}` or null |
| raw | JSON object of original field → value |
| field_updated_at | JSON object field → ISO timestamp (merge provenance) |
| scraped_at | timestamp of latest observation |
| timestamps | |

Indexes: `status`, `closing_date`, `procurement_type`, `ministry`; FULLTEXT on (`title`, `reference_no`, `agency`).

### collected_tender_sources

`collected_tender_id`, `source`, `source_id`, `source_url`; unique (`collected_tender_id`, `source`); index `source`.

### collected_tender_field_codes

`collected_tender_id`, `code`; unique pair; index `code`.

### collection_runs

`id`, `trigger` (`scheduled` / `manual`), `scope` (`daily` / `open`), `started_by` (nullable user), `status` (`running` / `succeeded` / `partial` / `failed`), `started_at`, `finished_at`, `results` JSON `{source: {count, error}}`, `closed_stale` (int).

### tenders (Stage 1 table) — added column

`collected_tender_id` nullable FK → `collected_tenders`, indexed (not unique: "Register anyway" may create a second).

## 5. Collection runs

- **Daily (scheduled, scope `daily`)**: MyProcurement open ×3 + daily results ×2; SPAN current year; LLM open + results; then stale-open closer.
- **Collect now (manual, scope `open`)**: open jobs only for all three sources, then stale-open closer. Managers and Admins only (policy-checked server-side).
- **One at a time**: a run cannot start while another is `running`; the button shows "Already collecting".
- **Stuck runs**: a `running` row older than 2 hours is marked `failed` ("did not finish") before a new run starts and when the status line renders.
- **Run status**: `succeeded` if every source succeeded; `partial` if at least one source failed; `failed` if all sources failed.
- **Zero-result guard (MyProcurement only)**: MyProcurement always has hundreds of open tenders, so an open job that parses 0 tenders records the source as failed with "returned 0 tenders — page layout may have changed". SPAN and LLM legitimately have zero or few open tenders, so they get no such guard.

## 6. Screens

### Sidebar
"Find Tenders" added at the top of **Operations**, above In Progress.

### Find Tenders list (`/find-tenders`)
- Default: status Open, closing soonest first (nulls last), 25 per page.
- Columns: Reference (with source badges), Title, Ministry / Agency, Type, Advertised, Closing (+ "x days left" for open), Indicative price, "Registered as WO …" badge.
- Filters: search (FULLTEXT over title / reference / agency, with a LIKE fallback for short terms), status (Open / Closed / All), source, type, ministry (searchable list of distinct ministries), field codes (multi-select, match any), closing-date range. All filters live in the URL.
- Status line: last finished run time (Malaysia time) + per-source counts; "Collecting now…" while running; failed sources with their reason. Managers/Admins see **Collect now**.

### Collected tender detail (`/find-tenders/{id}`)
- All fields, events table, winners table (closed), links to each source's original page, collapsible "All original fields" (raw).
- **Register this tender** opens the Stage 1 Register modal pre-filled:

| Collected field | Pipeline field |
|---|---|
| reference_no | tender code |
| title | title |
| agency ?? ministry | client |
| advertised_date | publish date |
| closing_date | closing date (left blank if missing — user must fill) |
| indicative_price_sen | estimated value |
| procurement_type tender / quotation / requisition | type Tender / Quotation / Quotation |
| source myprocurement / other | mode EP / Non-EP |
| — | category General, PIC chosen by user |

- Saving creates the pipeline tender with `collected_tender_id` set (Stage 1 duplicate-code warning still applies).
- If already registered: button reads **Open in pipeline (WO …)** linking to the first linked tender.
- Pipeline tender detail (Stage 1) shows "Collected from <source> — view original" when linked.

## 7. Legacy import

- Command: `php artisan collector:import-legacy --mongo-uri=<uri> [--database=tms] [--batch=1000]`.
- Needs the `mongodb` PHP extension in the app image (import only).
- Maps each Mongo `tenders` document → `collected_tenders` + sources + field codes; prices ringgit → sen; `_provenance` → `field_updated_at`.
- Idempotent: upsert by `dedup_key`.
- Prints progress and final per-source counts. Expected: myprocurement 206,291 (204,772 closed + 1,519 open), span 181, llm 148, kwsp 126.
- Verified first against a copy of the Mongo data (`mongodump` → separate container), never the live tms-v2 DB.

## 8. Error handling

- Source failure → recorded in run results; other sources continue; next daily run retries.
- Invalid record → skipped, logged with source and reason; batch continues.
- Unexpected exception in a job → run marked `failed` with the message; Laravel log keeps the trace.
- Live sites are never contacted by tests.

## 9. Testing

- Parsers: fixture-based tests, outputs compared field-by-field with the values the tms-v2 tests assert for the same fixtures.
- PoliteFetcher: fake HTTP + fake sleep — pauses, retries, back-off, Retry-After, grace wait, SPAN-only certificate.
- Sources: fake fetcher returning fixtures — paging, job selection by scope, zero-result guard.
- Merger: newest-wins, null-never-clobbers, out-of-order patches ignored, sources upsert.
- RunCollection: one-at-a-time, stuck-run timeout, partial/failed status, permissions on Collect now.
- Import: against a small in-test fake of Mongo documents (mapping logic isolated from the Mongo client).
- Livewire: list filters, status line, detail, Register pre-fill + link + badge.
- Browser walkthrough with imported data; one supervised live run at the end.
- Coverage ≥ 80% (existing gate).

## 10. Out of scope

KWSP collection, archive backfill jobs, dashboard, ministry and contractor analytics pages, change alerts (closing-date extensions / winner published), email notifications.

## 11. Retiring tms-v2

After Stage 2 is merged and the imported data has been checked by the user, Claude asks before stopping the tms-v2 containers. No code or data is deleted.
