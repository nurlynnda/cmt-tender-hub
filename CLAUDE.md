# CMT Tender Hub

Internal Laravel app for CMT's sales team to track government tenders they bid on.
Replaces the Node apps tms-v2 (tender collector) and ProjectDeliverable.

## Communication style
Always explain things in plain, layman's terms — no unexplained jargon. If a technical
term is unavoidable, say what it means in the same breath.

## Running (PHP is NOT installed on the host — everything runs in Docker)
- `docker compose up -d` — app on http://localhost:8080, Mailpit on http://localhost:8025
- `docker compose exec -T app ./vendor/bin/pest` — all tests
- `docker compose exec -T app php artisan migrate:fresh --seed` — reset dev data

## Rules
- TDD is non-negotiable: failing test first, minimal code, green, commit. Never commit red.
- Tests hit the real MySQL test DB (`tender_hub_test`), never SQLite.
- Money is integer sen everywhere; convert with `App\Support\Money` only at the edges.
- Calendar dates are Malaysia days; compare them as `Y-m-d` strings; "today" = `MalaysiaTime::today()`.
- Livewire components stay thin; business rules live in `app/Actions` and `app/Policies`.
- Every tender change checks/increments `version` and writes an activity log row.
- After any browser-visible change, click through it in a real browser before calling it done.
- Never let a test contact a government site; use tests/Fixtures/collector and Tests\Support\FakeFetcher.
- Write PHP files with the editor tool, not sed/heredocs (Git Bash mangles backslashes).

Spec: docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage1-design.md
Plan: docs/superpowers/plans/2026-10-06-cmt-tender-hub-stage1.md
