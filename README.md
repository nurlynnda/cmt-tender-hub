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

## Before going live

- `php artisan serve` is for development; put a proper web server (e.g. Nginx +
  PHP-FPM or FrankenPHP) in front for production.
- Set real SMTP details for password-reset emails.
- Never run `db:seed` in production (it refuses anyway).

## Design documents

- Spec: `docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage1-design.md`
- Plan: `docs/superpowers/plans/2026-10-06-cmt-tender-hub-stage1.md`
