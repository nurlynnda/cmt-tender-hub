# CMT Tender Hub

Internal web app for CMT's sales team to register government tenders, track the
documents each bid needs, and record whether each bid was won or lost.

## Running it (everything runs in Docker — PHP does not need to be installed)

    docker compose up -d
    docker compose run --rm app composer install
    docker compose exec -T app php artisan key:generate
    docker compose exec -T app php artisan migrate:fresh --seed

- App: http://localhost:8080
- Test mailbox (catches password-reset emails): http://localhost:8025

Sample accounts (development only), password = `SEED_USER_PASSWORD` in `.env`:
`admin@cmt.test` (Admin), `manager@cmt.test` (Manager),
`ahmad.faizal@cmt.test`, `nurul.ain@cmt.test`, `siti.aisyah@cmt.test`,
`muhammad.hafiz@cmt.test` (Staff).

PHP libraries (`vendor/`) and JavaScript libraries (`node_modules/`) live in Docker
volumes, not in this folder — reading thousands of small files through the
Windows↔Docker file share was ~10× slower.

## Tests

    docker compose exec -T app composer test            # all tests
    docker compose exec -T app composer test:coverage   # with the 80% coverage gate

Tests use a separate MySQL database (`tender_hub_test`). The git pre-commit hook
runs the suite with the coverage gate — enable it once with
`git config core.hooksPath .githooks`.

## Reminders

The `scheduler` container runs `php artisan tenders:send-reminders` every hour
("closes in 3 days" and "briefing tomorrow" notifications).

## Collector (Find Tenders)

- Collects MyProcurement, SPAN and LLM daily at 12:01pm Malaysia time (a check runs every
  5 minutes, so a missed run catches up when the PC comes back on), plus **Collect now** for
  Managers/Admins (open tenders only). Collection runs in the `worker` container; only one
  run at a time, and a run still going after 2 hours is marked failed.
- KWSP was dropped (Cloudflare blocks non-browser downloads); its imported history remains.
- `php artisan collector:import-legacy --mongo-uri=… --database=…` copied tms-v2's 206k
  tenders once (safe to re-run — it updates rather than duplicates).
- Tests never contact the real sites (`Http::preventStrayRequests()`); the page readers are
  tested on saved pages in `tests/Fixtures/collector`.

## Market Insights and awarded tenders

- **Find Tenders → Status: Awarded** lists closed tenders with a published winner, the price
  won, a Contractor search box and an **Our wins** button. A green "Ours" tag marks our company.
- **Market Insights** (sidebar → Insights) shows open tenders right now, then for a chosen year from 2023 on
  (or "2023 – now"; earlier years have too few awards, set in `config/tenderhub.php`): awarded tenders, value and contractors, our rank, awards by year, spend by
  ministry and top contractors, with "See all" pages for every ministry and contractor.
- Winners are copied into a searchable table, `collected_tender_winners`. The daily collection
  keeps it current. **After `collector:import-legacy`, run `php artisan collector:index-winners`
  once** (the import bulk-inserts and skips that step). It's safe to re-run at any time.
- "Our company" is set in **Finance Settings → Our company names in tender results** (one name
  per line). Names match regardless of capitals, dots, commas and spaces.

## Costing

- Each In Progress tender has a **Costing** tab: cost lines (with optional sub-items), a
  margin % per line, and a **frequency** (a whole number: 12 = monthly over a year). Line total =
  price × quantity × frequency.
- Price per unit = cost ÷ (1 − margin), rounded **up** to the whole ringgit — the same as the
  Excel sheet. You can also **type a line's selling price**: the margin is then worked out
  backwards from it (red when below cost); changing the margin or clearing the price goes back
  to the worked-out price. The lines' selling prices add up to the suggested bid price; you can
  type your own bid price instead, and the margin is always worked out from the price actually used.
- Margins under 18% are flagged. Rows can be pasted straight from Excel (Bulk import).
- **Mark Done** needs a saved costing and records its bid price as the submitted price.
- All maths lives in `app/Costing/CostingCalculator.php` (whole sen, no rounding drift).

## PD (project finance)

- When a tender is marked **Awarded** it gets a **PD tab**. Its budget is copied from the costing
  (each line under **Principal** — move it to another group on the PD page if needed) plus a
  "Contract value" collection line; after that the PD is independent.
- Budget vs actual Profit & Loss: GP = revenue − costs − project charges; commission =
  (GP − approved margin) × commission share; net = GP − commission. Actual figures use invoices.
- Each line keeps its documents (PR, PO, invoice, payment; or invoice and receipt for collections).
  Every change saves immediately; two people only clash if they edit the same line.
- Admins manage project types (approved margins) and company defaults under **Finance Settings**.
- Managers/Admins can adjust a single project's rates and close/reopen it.
- All maths lives in `app/Pd/PdCalculator.php`.

## Quotations

- Quick quotations outside the tender process: **Quotations** in the sidebar. New Quotation opens a draft;
  every field saves when you leave it. Items can carry spec lines ("Label: value" prints the label in bold).
- **Mark as Sent** locks it. Then Accepted / Rejected, or **Revise** (makes `…-R1`). It shows Expired once
  its valid-until date passes. Managers/Admins can move it back to Draft (not once it has a project).
- **Download PDF** is made on the server (Dompdf, which needs the PHP `gd` extension in the Docker image);
  the Preview tab shows the same PDF. **Duplicate** copies any quotation into a new draft.
- The typed signature uses the **Allura** handwriting font, stored in `resources/fonts` (free SIL Open Font
  License, see `OFL.txt` there). Dompdf keeps its processed copy in `storage/fonts`, which must stay writable.
- **Items are costed like tender costing:** unit cost (or sub-items), margin %, vendor and quote link sit in
  a "Costing (not shown to the customer)" row under each item; the unit price is worked out from them, or
  typed (the margin is then worked out backwards). Items also have a **frequency** and an **SST tick**:
  SST is charged only on ticked items, which the PDF marks with \*. A **Profit (internal)** box shows total
  cost, margin and the 18% warning; the quotation's default margin is used for new items.
- An Accepted quotation can **Create project** — a PD (see above) with the subtotal as contract value and
  each costed item as a Principal cost line.
- Admins set the letterhead, stamp, default terms and default SST in **Finance Settings**. Each quotation
  keeps a copy of the letterhead it was created with; stamp files are never deleted.

## Dashboard and Status

- The **Dashboard** is the home page: overview cards, upcoming deadlines, portfolio mix (by status and
  EP / Non-EP), PIC summary, and Quotations / Projects cards.
- **Status** (Insights) shows each PIC's tenders, win rate, bid value and won value; click a column to sort.
- Both follow a period filter by **WO date** (All time, this/last month, this year, a month, custom).
- Win rate = Awarded ÷ (Awarded + Lost), cancelled left out. Bid value = submitted price, or the estimated
  value while a tender is in progress. Figures live in `app/Reports/`.

## Look and feel

The screens follow the Claude Design prototype (copy in `docs/superpowers/plans/assets/prototype-ui/`).
- The sidebar folds to an icon strip (button beside the logo); the choice is kept in a `sidebar` cookie.
- Drag the edge of a table heading to resize a column; widths are kept in this browser
  (`localStorage`, key `tenderhub-cols:<table>`). Double-click the edge to reset.
- Lists show a **Filters** panel; the number on the button is how many filters are on.
- Shared pieces live in `resources/views/components/` (`icon`, `page-heading`, `filter-bar`, `data-table`)
  and `resources/js/resizable-columns.js`.
- Round 2: the tender page, Quotations, every dialog (`x-dialog`), settings and sign-in follow the prototype
  too (`status-pill`, `fact`, `card`, `tabs`, buttons `btn btn-primary|dark|outline|danger`).
  **Bulk Add** on a tender's Documents tab adds one document per line.

## Importing the tender register

The team's register spreadsheet (saved as CSV) is imported with a command — the file stays outside the project:

    docker compose cp "/path/to/register.csv" app:/tmp/register.csv
    docker compose exec app php artisan tenders:import-register /tmp/register.csv            # preview only
    docker compose exec app php artisan tenders:import-register /tmp/register.csv --commit   # save

- Statuses: Open/Assigned → In Progress, Submitted → Done, Won → Awarded, Lost → Lost,
  Cancelled → Lost (cancelled), Drop → **Dropped** (left out of the win rate and bid values).
- Matching is by WO number, so re-running with a newer register updates tenders instead of duplicating;
  category, ticked documents and costing lines people added are kept.
- New PICs become **switched-off** accounts (`name@import.invalid`); an admin sets the real email and
  switches them on in Manage Users. Blank PIC → "Unassigned".
- Submitted Cost becomes one costing line, "Imported cost (2026 register)", so Gross matches the sheet.
- `--replace-samples` (first import only) removes **all** existing pipeline tenders, quotations, projects and
  sample staff first — keeps admin@cmt.test, manager@cmt.test and Find Tenders data. One transaction:
  if anything fails, nothing changes.

## Production (the DigitalOcean server)

- Step-by-step guide: **`docs/DEPLOY.md`** (first setup, moving the data, backups, updates).
- `docker-compose.prod.yml` + `docker/prod/`: FrankenPHP (web server with automatic HTTPS) runs the
  site; the same image runs the worker and scheduler; MySQL is capped to fit a 2 GB server.
- The server's settings live in its own `.env` (template: `.env.production.example`), never in Git.
- `php artisan users:set-password {email}` sets a password without showing it (used after moving the data).
- Never run `db:seed` in production (it refuses anyway).

## Design documents

- Spec: `docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage1-design.md`
- Plan: `docs/superpowers/plans/2026-10-06-cmt-tender-hub-stage1.md`
- Stage 2 spec/plan: `docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage2-design.md`,
  `docs/superpowers/plans/2026-10-06-cmt-tender-hub-stage2.md`
