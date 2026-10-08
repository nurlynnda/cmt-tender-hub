# CMT Tender Hub — Stage 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first usable version of CMT Tender Hub in Laravel — login, user management, the tender register, the four pipeline lists, tender detail (Overview / Documents / Activity), status changes and in-app notifications.

**Architecture:** A single Laravel app in a new repo (`C:\Projects\cmt-tender-hub`) running entirely in Docker (app, MySQL, scheduler, Vite, Mailpit). Screens are Livewire components that stay thin: they validate input and call one Action class per business operation. Actions own every rule (status transitions, edit-conflict checks, activity logging) and authorize through Policies, so all rules are testable without a browser.

**Tech Stack:** PHP 8.4, Laravel (latest stable), Livewire (latest stable, class components), Alpine.js (bundled with Livewire), Tailwind CSS v4, MySQL 8.4, Pest, pcov, Docker Compose.

**Spec:** `C:\Projects\tms-v2\docs\superpowers\specs\2026-10-06-cmt-tender-hub-stage1-design.md` (copied into the new repo in Task 1). Read it before starting any task.

**Plan assets (copied into the new repo in Task 1 / Task 15):**
- `C:\Projects\tms-v2\docs\superpowers\plans\assets\ministries.json` — 47 ministry names from the prototype
- `C:\Projects\tms-v2\docs\superpowers\plans\assets\prototype-tenders.json` — the prototype's 41 tenders, already normalised (ISO dates, money in sen, enum values)

## Global Constraints

- PHP is NOT installed on the host. Every `php`, `composer`, `artisan`, `pest` command runs inside Docker: `docker compose exec -T app <cmd>` (stack running) or `docker compose run --rm app <cmd>`.
- Shell is Git Bash on Windows: prefix any `docker run` with a `-v` host path with `MSYS_NO_PATHCONV=1`.
- Money is always integer **sen** in PHP and the database (`*_sen` columns, `unsignedBigInteger`). Convert only at the edges with `App\Support\Money`.
- Timestamps are stored UTC. Calendar dates (`wo_date`, `publish_date`, `closing_date`, `briefing_date`) are plain `DATE` columns meaning a Malaysia calendar day. "Today" is always `App\Support\MalaysiaTime::today()`. **Compare calendar dates as `Y-m-d` strings, never as Carbon instants** (a `date` cast is midnight UTC; Malaysia midnight is 8 hours earlier).
- WO number format: `200-DDMMYYYY-NNN`, NNN restarts at 001 every Malaysia calendar day.
- Statuses: `in_progress`, `done`, `awarded`, `lost`. Allowed transitions only: in_progress→done (Mark Done), in_progress→lost (Cancel), done→awarded, done→lost, {done,awarded,lost}→in_progress (Reopen, manager/admin only).
- Roles: `staff`, `manager`, `admin`. Staff edit only tenders where they are PIC; manager/admin edit all; only admin manages users.
- Every versioned change (edit, document change, status change) checks and increments `tenders.version`, and writes an `activity_logs` row.
- Standard documents, in order: `Borang ISI (Tender Form)`, `Pricing Schedule`, `Company Profile / SSM Registration`, `Technical Proposal`, `Bid Bond / Bank Guarantee`.
- Categories: `IT Infrastructure`, `Software Development`, `Civil Works`, `General` (default).
- TDD: failing test → run, see it fail for the right reason → minimal code → run, see it pass → commit. Never commit red. Never skip the pre-commit hook.
- Tests use the real MySQL test database `tender_hub_test`, never SQLite.
- Coverage ≥ 80% lines over `app/` (gate switched on in Task 16).
- UI copy is plain English with no unexplained jargon (the user's standing rule). Status labels: "In Progress", "Done", "Awarded", "Lost".
- Commit messages end with: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## Review Focus

1. **Two browser tabs editing the same tender** — the second save must be refused with "changed by <name>", never silently overwrite. Pinned in Task 7 (UpdateTender) and Task 12 (detail screen conflict banner).
2. **Registering just after Malaysia midnight** (e.g. 16:30 UTC = 00:30 MYT next day) — WO number and WO date must use the Malaysia date. Pinned in Task 6 and Task 7.
3. **Money typed in human formats** — `RM 1,234.50`, `1234.5`, ` 1,000 ` accepted; `-5`, `1.234`, `1,23`, `abc` rejected with a clear message. Pinned in Task 2.
4. **A user deactivated while logged in** — their next request logs them out. Pinned in Task 4.
5. **A staff user calling an edit/status method on someone else's tender by crafting the request** (bypassing hidden buttons) — must get 403. Pinned in Task 8 (actions) and Task 12 (Livewire methods called directly).

---

## File Structure (new repo `C:\Projects\cmt-tender-hub`)

| Path | Responsibility |
|---|---|
| `docker-compose.yml`, `docker/php/Dockerfile`, `docker/mysql/init.sql` | Runtime: app, mysql, scheduler, vite, mailpit |
| `.githooks/pre-commit` | Runs the full Pest suite before every commit |
| `CLAUDE.md`, `README.md` | Working rules; how to run |
| `app/Support/Money.php`, `app/Support/MalaysiaTime.php` | Sen ↔ ringgit conversion/formatting; Malaysia "now/today" |
| `app/Rules/MoneyAmount.php` | Validation rule for typed ringgit amounts |
| `app/Enums/{Role,TenderStatus,TenderMode,TenderType,TenderCategory}.php` | Fixed value lists with labels |
| `app/Models/{User,Tender,TenderDocument,ActivityLog}.php` | Eloquent models |
| `app/Exceptions/{StaleTenderException,InvalidTenderTransition,DocumentsIncomplete}.php` | Domain errors |
| `app/Policies/TenderPolicy.php` + `manage-users` gate | Who may do what |
| `app/Actions/Tenders/*.php` | One class per tender operation |
| `app/Actions/Users/*.php` | CreateUser, ChangeUserRole, SetUserActive |
| `app/Queries/TenderListQuery.php` | Filtering/sorting for the four lists |
| `app/Notifications/*.php`, `app/Support/AssignmentNotifier.php`, `app/Console/Commands/SendTenderReminders.php` | In-app notifications |
| `app/Http/Middleware/EnsureUserIsActive.php`, `app/Http/Controllers/LogoutController.php` | Auth plumbing |
| `app/Livewire/Auth/*`, `app/Livewire/{TenderList,RegisterTenderModal,TenderDetail,NotificationBell,Settings,ManageUsers}.php`, `app/Livewire/Forms/TenderForm.php` | Screens |
| `app/View/Composers/SidebarComposer.php` | Live counts in the sidebar |
| `resources/views/layouts/{app,guest}.blade.php`, `resources/views/layouts/partials/sidebar.blade.php`, `resources/views/livewire/**` | Templates |
| `resources/css/app.css` | Tailwind + prototype colour tokens (light/dark) |
| `config/tenderhub.php`, `resources/data/ministries.json` | Ministry list, seed password |
| `database/migrations/*`, `database/factories/*`, `database/seeders/DatabaseSeeder.php`, `database/seeders/data/prototype-tenders.json` | Schema, test factories, dev sample data |

---

### Task 1: Scaffold the project, Docker stack, Pest and the test database

**Files:**
- Create: whole Laravel skeleton in `C:\Projects\cmt-tender-hub`
- Create: `docker-compose.yml`, `docker/php/Dockerfile`, `docker/mysql/init.sql`, `.githooks/pre-commit`, `CLAUDE.md`, `docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage1-design.md` (copy), `docs/superpowers/plans/2026-10-06-cmt-tender-hub-stage1.md` (copy)
- Modify: `.env.example`, `phpunit.xml`, `vite.config.js`, `tests/TestCase.php`, `tests/Pest.php`
- Test: `tests/Feature/HealthTest.php`

**Interfaces:**
- Produces: running stack (`docker compose up -d`), app at `http://localhost:8080`, Mailpit at `http://localhost:8025`, test command `docker compose exec -T app ./vendor/bin/pest`, `Tests\TestCase` (calls `withoutVite()`), Pest config applying `RefreshDatabase` to `tests/Feature`.

- [ ] **Step 1: Create the Laravel skeleton with the official Composer image**

```bash
cd /c/Projects
MSYS_NO_PATHCONV=1 docker run --rm -v "C:/Projects:/work" -w /work composer:2 \
  create-project --no-scripts --ignore-platform-reqs laravel/laravel cmt-tender-hub
cd /c/Projects/cmt-tender-hub && git init -b main && cp .env.example .env
```

Expected: `cmt-tender-hub` folder with `artisan`, `composer.json`, `vendor/`.

- [ ] **Step 2: Write `docker/php/Dockerfile`**

```dockerfile
FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
 && docker-php-ext-install pdo_mysql zip intl bcmath pcntl \
 && pecl install pcov && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
```

- [ ] **Step 3: Write `docker/mysql/init.sql`** (creates the separate test database on first MySQL start)

```sql
CREATE DATABASE IF NOT EXISTS tender_hub_test;
GRANT ALL PRIVILEGES ON tender_hub_test.* TO 'tenderhub'@'%';
FLUSH PRIVILEGES;
```

- [ ] **Step 4: Write `docker-compose.yml`**

```yaml
services:
  app:
    build: { context: ., dockerfile: docker/php/Dockerfile }
    command: php artisan serve --host=0.0.0.0 --port=8000
    environment:
      PHP_CLI_SERVER_WORKERS: 4
    ports: ["8080:8000"]
    volumes: [".:/var/www/html"]
    depends_on:
      mysql: { condition: service_healthy }

  scheduler:
    build: { context: ., dockerfile: docker/php/Dockerfile }
    command: php artisan schedule:work
    volumes: [".:/var/www/html"]
    depends_on:
      mysql: { condition: service_healthy }

  mysql:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: tender_hub
      MYSQL_USER: tenderhub
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    volumes:
      - mysql-data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-uroot", "-prootsecret"]
      interval: 5s
      retries: 30

  vite:
    image: node:24-alpine
    working_dir: /app
    command: sh -c "npm install && npm run dev"
    ports: ["5173:5173"]
    volumes: [".:/app"]

  mailpit:
    image: axllent/mailpit
    ports: ["8025:8025"]

volumes:
  mysql-data: {}
```

- [ ] **Step 5: Set environment values in both `.env` and `.env.example`**

Replace the matching keys (leave the rest as generated):

```dotenv
APP_NAME="CMT Tender Hub"
APP_URL=http://localhost:8080
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tender_hub
DB_USERNAME=tenderhub
DB_PASSWORD=secret

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="noreply@cmt-tenderhub.test"
MAIL_FROM_NAME="CMT Tender Hub"

QUEUE_CONNECTION=sync

# Development-only password for the sample accounts created by `php artisan db:seed`
SEED_USER_PASSWORD=TenderHub-dev-2026
```

Delete any `DB_*` lines that are commented out or point at sqlite.

- [ ] **Step 6: Point Vite at the container network — replace `vite.config.js`**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'http://localhost:5173',
        hmr: { host: 'localhost' },
        watch: { usePolling: true },
    },
});
```

If the skeleton's `package.json` lacks `tailwindcss` / `@tailwindcss/vite`, run `docker compose run --rm vite npm install -D tailwindcss @tailwindcss/vite`.

- [ ] **Step 7: Build, start, install PHP packages, generate the app key**

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app composer require livewire/livewire
docker compose run --rm app composer remove --dev phpunit/phpunit
docker compose run --rm app composer require --dev pestphp/pest pestphp/pest-plugin-laravel --with-all-dependencies
docker compose run --rm app ./vendor/bin/pest --init
docker compose up -d
```

Expected: `docker compose ps` shows app, scheduler, mysql (healthy), vite, mailpit running. If the `mysql-data` volume pre-existed without the test DB, run `docker compose down -v` and start again so `init.sql` runs.

- [ ] **Step 8: Configure tests — edit `phpunit.xml`**

In `<php>`, remove the `DB_CONNECTION`/`DB_DATABASE` sqlite lines and add:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="mysql"/>
<env name="DB_DATABASE" value="tender_hub_test"/>
<env name="DB_USERNAME" value="tenderhub"/>
<env name="DB_PASSWORD" value="secret"/>
```

Ensure `<source>` contains:

```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
```

- [ ] **Step 9: Replace `tests/TestCase.php` and `tests/Pest.php`**

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
```

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');
```

Delete `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`.

- [ ] **Step 10: Write the failing smoke test `tests/Feature/HealthTest.php`**

```php
<?php

use Illuminate\Support\Facades\DB;

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('runs tests against the real MySQL test database', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('tender_hub_test');
});
```

- [ ] **Step 11: Run it**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/HealthTest.php`
Expected: both PASS. (If Step 8 was skipped it fails with `sqlite`/`:memory:` — that is the failure this test guards against. Temporarily revert Step 8 once to watch it fail, then restore.)

- [ ] **Step 12: Pre-commit hook, CLAUDE.md, copy spec and plan**

`.githooks/pre-commit`:

```sh
#!/bin/sh
echo "Running test suite before commit..."
docker compose exec -T app ./vendor/bin/pest || {
  echo "Tests failed - commit blocked. Fix the failing tests first."
  exit 1
}
```

```bash
chmod +x .githooks/pre-commit && git config core.hooksPath .githooks
mkdir -p docs/superpowers/specs docs/superpowers/plans
cp /c/Projects/tms-v2/docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage1-design.md docs/superpowers/specs/
cp /c/Projects/tms-v2/docs/superpowers/plans/2026-10-06-cmt-tender-hub-stage1.md docs/superpowers/plans/
```

`CLAUDE.md`:

```markdown
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

Spec: docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage1-design.md
```

- [ ] **Step 13: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel app with Docker, MySQL test DB and Pest

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Expected: hook prints "Running test suite before commit...", tests pass, commit created.

---

### Task 2: Money and Malaysia-time helpers

**Files:**
- Create: `app/Support/Money.php`, `app/Support/MalaysiaTime.php`, `app/Rules/MoneyAmount.php`
- Test: `tests/Unit/Support/MoneyTest.php`, `tests/Unit/Support/MalaysiaTimeTest.php`, `tests/Unit/Rules/MoneyAmountTest.php`

**Interfaces:**
- Produces:
  - `Money::parse(?string $input): ?int` — sen; `null` for empty; throws `InvalidArgumentException` for invalid/negative
  - `Money::format(?int $sen): string` — `RM 1,234.56`, `—` for null
  - `Money::toInput(?int $sen): string` — `1234.56`, `''` for null
  - `Money::variant(?int $numerator, ?int $denominator): ?string` — `215.1%`
  - `MalaysiaTime::TZ`, `MalaysiaTime::now(): CarbonImmutable`, `MalaysiaTime::today(): CarbonImmutable`
  - `new MoneyAmount(bool $mustBePositive = false)` validation rule

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Support/MoneyTest.php`:

```php
<?php

use App\Support\Money;

it('parses human ringgit amounts into sen', function (string $input, int $sen) {
    expect(Money::parse($input))->toBe($sen);
})->with([
    ['1234', 123400],
    ['1234.5', 123450],
    ['1,234.56', 123456],
    ['RM 1,234.50', 123450],
    ['rm1000', 100000],
    [' 1,000 ', 100000],
    ['0', 0],
    ['0.05', 5],
]);

it('treats empty input as no amount', function (?string $input) {
    expect(Money::parse($input))->toBeNull();
})->with([null, '', '   ', 'RM ']);

it('rejects invalid amounts', function (string $input) {
    Money::parse($input);
})->with(['-5', '1.234', '1,23', 'abc', '12a', '1,2345'])->throws(InvalidArgumentException::class);

it('formats sen as ringgit', function () {
    expect(Money::format(123456))->toBe('RM 1,234.56')
        ->and(Money::format(5))->toBe('RM 0.05')
        ->and(Money::format(0))->toBe('RM 0.00')
        ->and(Money::format(null))->toBe('—');
});

it('formats sen for an input box', function () {
    expect(Money::toInput(123450))->toBe('1234.50')
        ->and(Money::toInput(null))->toBe('');
});

it('computes a variant percentage to one decimal place', function () {
    expect(Money::variant(86617900, 40275400))->toBe('215.1%')
        ->and(Money::variant(85444700, 26658700))->toBe('320.5%')
        ->and(Money::variant(null, 100))->toBeNull()
        ->and(Money::variant(100, null))->toBeNull()
        ->and(Money::variant(100, 0))->toBeNull();
});
```

`tests/Unit/Support/MalaysiaTimeTest.php`:

```php
<?php

use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;

it('gives the Malaysia calendar day even when UTC is still on the previous day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 00:30 MYT on the 7th

    expect(MalaysiaTime::today()->toDateString())->toBe('2026-10-07')
        ->and(MalaysiaTime::now()->format('H:i'))->toBe('00:30')
        ->and(MalaysiaTime::now()->getTimezone()->getName())->toBe('Asia/Kuala_Lumpur');
});
```

`tests/Unit/Rules/MoneyAmountTest.php`:

```php
<?php

use App\Rules\MoneyAmount;
use Illuminate\Support\Facades\Validator;

function moneyPasses(mixed $value, bool $positive = false): bool
{
    return Validator::make(['amount' => $value], ['amount' => [new MoneyAmount($positive)]])->passes();
}

it('accepts valid and empty amounts', function () {
    expect(moneyPasses('1,234.50'))->toBeTrue()
        ->and(moneyPasses(''))->toBeTrue()
        ->and(moneyPasses(null))->toBeTrue()
        ->and(moneyPasses('0'))->toBeTrue();
});

it('rejects invalid amounts with a helpful message', function () {
    $v = Validator::make(['amount' => '-5'], ['amount' => [new MoneyAmount()]]);
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->first('amount'))->toBe('Enter an amount like 12,345.67.');
});

it('can require a positive amount', function () {
    expect(moneyPasses('0', positive: true))->toBeFalse()
        ->and(moneyPasses('0.01', positive: true))->toBeTrue();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Unit`
Expected: FAIL — `Class "App\Support\Money" not found` (and similar).

- [ ] **Step 3: Implement**

`app/Support/Money.php`:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function parse(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        $clean = trim(preg_replace('/^\s*RM\s*/i', '', $input));
        if ($clean === '') {
            return null;
        }

        if (! preg_match('/^(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Not a valid ringgit amount: {$input}");
        }

        $ringgit = (int) str_replace(',', '', $m[1]);
        $sen = isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0;

        return $ringgit * 100 + $sen;
    }

    public static function format(?int $sen): string
    {
        if ($sen === null) {
            return '—';
        }

        $sign = $sen < 0 ? '-' : '';
        $abs = abs($sen);

        return $sign.'RM '.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function toInput(?int $sen): string
    {
        if ($sen === null) {
            return '';
        }

        return intdiv($sen, 100).'.'.str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function variant(?int $numerator, ?int $denominator): ?string
    {
        if ($numerator === null || $denominator === null || $denominator === 0) {
            return null;
        }

        return number_format($numerator * 100 / $denominator, 1).'%';
    }
}
```

`app/Support/MalaysiaTime.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class MalaysiaTime
{
    public const TZ = 'Asia/Kuala_Lumpur';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ);
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }
}
```

`app/Rules/MoneyAmount.php`:

```php
<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class MoneyAmount implements ValidationRule
{
    public function __construct(private bool $mustBePositive = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        try {
            $sen = Money::parse((string) $value);
        } catch (InvalidArgumentException) {
            $fail('Enter an amount like 12,345.67.');

            return;
        }

        if ($this->mustBePositive && $sen <= 0) {
            $fail('The amount must be more than RM 0.00.');
        }
    }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Unit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support app/Rules tests/Unit
git commit -m "feat: add sen-based Money helper, Malaysia time and MoneyAmount rule

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Enums and user roles

**Files:**
- Create: `app/Enums/Role.php`, `app/Enums/TenderStatus.php`, `app/Enums/TenderMode.php`, `app/Enums/TenderType.php`, `app/Enums/TenderCategory.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`
- Test: `tests/Unit/Enums/EnumsTest.php`, `tests/Feature/Models/UserTest.php`

**Interfaces:**
- Produces:
  - `Role::{Staff,Manager,Admin}` (`'staff'|'manager'|'admin'`), `->label()`, `->canManageAllTenders(): bool`
  - `TenderStatus::{InProgress,Done,Awarded,Lost}` (`'in_progress'|'done'|'awarded'|'lost'`), `->label()`, `->slug()` (`in-progress|done|awarded|lost`), `TenderStatus::fromSlug(string): self` (throws `ValueError` on unknown), `->listTitle()`, `->listSubtitle()`
  - `TenderMode::{Ep,NonEp}` (`'EP'|'NON_EP'`), `->label()` (`EP|Non-EP`)
  - `TenderType::{Tender,Quotation}` (`'TENDER'|'QUOTATION'`), `->label()` (`Tender|Quotation`)
  - `TenderCategory` cases whose values are the display names; `TenderCategory::Default` constant = `General`
  - `User` has `role: Role`, `is_active: bool`, `initials(): string`; factory states `manager()`, `admin()`, `inactive()`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Enums/EnumsTest.php`:

```php
<?php

use App\Enums\{Role, TenderCategory, TenderMode, TenderStatus, TenderType};

it('labels roles and knows who manages all tenders', function () {
    expect(Role::Staff->label())->toBe('Staff')
        ->and(Role::Staff->canManageAllTenders())->toBeFalse()
        ->and(Role::Manager->canManageAllTenders())->toBeTrue()
        ->and(Role::Admin->canManageAllTenders())->toBeTrue();
});

it('maps statuses to labels and URL slugs both ways', function () {
    expect(TenderStatus::InProgress->label())->toBe('In Progress')
        ->and(TenderStatus::InProgress->slug())->toBe('in-progress')
        ->and(TenderStatus::fromSlug('awarded'))->toBe(TenderStatus::Awarded)
        ->and(TenderStatus::Lost->listTitle())->toBe('Lost Tenders');
});

it('refuses unknown status slugs', function () {
    TenderStatus::fromSlug('nope');
})->throws(ValueError::class);

it('labels mode, type and category', function () {
    expect(TenderMode::NonEp->label())->toBe('Non-EP')
        ->and(TenderType::Quotation->label())->toBe('Quotation')
        ->and(TenderCategory::from('Civil Works'))->toBe(TenderCategory::CivilWorks)
        ->and(TenderCategory::Default)->toBe(TenderCategory::General);
});
```

`tests/Feature/Models/UserTest.php`:

```php
<?php

use App\Enums\Role;
use App\Models\User;

it('stores role and active flag with sensible defaults', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe(Role::Staff)
        ->and($user->fresh()->is_active)->toBeTrue();
});

it('has manager, admin and inactive factory states', function () {
    expect(User::factory()->manager()->create()->role)->toBe(Role::Manager)
        ->and(User::factory()->admin()->create()->role)->toBe(Role::Admin)
        ->and(User::factory()->inactive()->create()->is_active)->toBeFalse();
});

it('derives initials from the first two words of the name', function () {
    expect(User::factory()->make(['name' => 'Siti Aisyah'])->initials())->toBe('SA')
        ->and(User::factory()->make(['name' => 'Madonna'])->initials())->toBe('M')
        ->and(User::factory()->make(['name' => 'muhammad hafiz bin ali'])->initials())->toBe('MH');
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Unit/Enums tests/Feature/Models/UserTest.php`
Expected: FAIL — `Class "App\Enums\Role" not found`.

- [ ] **Step 3: Implement the enums**

`app/Enums/Role.php`:

```php
<?php

namespace App\Enums;

enum Role: string
{
    case Staff = 'staff';
    case Manager = 'manager';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Manager => 'Manager',
            self::Admin => 'Admin',
        };
    }

    public function canManageAllTenders(): bool
    {
        return $this !== self::Staff;
    }
}
```

`app/Enums/TenderStatus.php`:

```php
<?php

namespace App\Enums;

use ValueError;

enum TenderStatus: string
{
    case InProgress = 'in_progress';
    case Done = 'done';
    case Awarded = 'awarded';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Done => 'Done',
            self::Awarded => 'Awarded',
            self::Lost => 'Lost',
        };
    }

    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromSlug(string $slug): self
    {
        return self::tryFrom(str_replace('-', '_', $slug))
            ?? throw new ValueError("Unknown tender list: {$slug}");
    }

    public function listTitle(): string
    {
        return $this->label().' Tenders';
    }

    public function listSubtitle(): string
    {
        return match ($this) {
            self::InProgress => 'Tenders currently being worked on',
            self::Done => 'Submitted tenders waiting for a result',
            self::Awarded => 'Tenders won and confirmed',
            self::Lost => 'Tenders lost, cancelled or with no award news',
        };
    }
}
```

`app/Enums/TenderMode.php`:

```php
<?php

namespace App\Enums;

enum TenderMode: string
{
    case Ep = 'EP';
    case NonEp = 'NON_EP';

    public function label(): string
    {
        return $this === self::Ep ? 'EP' : 'Non-EP';
    }
}
```

`app/Enums/TenderType.php`:

```php
<?php

namespace App\Enums;

enum TenderType: string
{
    case Tender = 'TENDER';
    case Quotation = 'QUOTATION';

    public function label(): string
    {
        return $this === self::Tender ? 'Tender' : 'Quotation';
    }
}
```

`app/Enums/TenderCategory.php`:

```php
<?php

namespace App\Enums;

enum TenderCategory: string
{
    case ItInfrastructure = 'IT Infrastructure';
    case SoftwareDevelopment = 'Software Development';
    case CivilWorks = 'Civil Works';
    case General = 'General';

    public const Default = self::General;
}
```

- [ ] **Step 4: Add role/is_active to users**

In `database/migrations/0001_01_01_000000_create_users_table.php`, inside `Schema::create('users', ...)`, after the `password` column:

```php
            $table->string('role', 20)->default('staff');
            $table->boolean('is_active')->default(true);
```

In `app/Models/User.php`: add `use App\Enums\Role;`, set

```php
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];
```

extend `casts()`:

```php
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }
```

and add:

```php
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name));

        return strtoupper(implode('', array_map(
            fn (string $w) => mb_substr($w, 0, 1),
            array_slice($words, 0, 2),
        )));
    }
```

In `database/factories/UserFactory.php`: add `'role' => \App\Enums\Role::Staff, 'is_active' => true,` to `definition()` and these states:

```php
    public function manager(): static
    {
        return $this->state(['role' => \App\Enums\Role::Manager]);
    }

    public function admin(): static
    {
        return $this->state(['role' => \App\Enums\Role::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
```

- [ ] **Step 5: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS (whole suite).

- [ ] **Step 6: Commit**

```bash
git add app/Enums app/Models/User.php database tests
git commit -m "feat: add role, status, mode, type and category enums; user roles

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Login, logout, password reset and blocking deactivated users

**Files:**
- Create: `app/Livewire/Auth/Login.php`, `app/Livewire/Auth/ForgotPassword.php`, `app/Livewire/Auth/ResetPassword.php`, `app/Http/Controllers/LogoutController.php`, `app/Http/Middleware/EnsureUserIsActive.php`, `resources/views/layouts/guest.blade.php`, `resources/views/livewire/auth/login.blade.php`, `resources/views/livewire/auth/forgot-password.blade.php`, `resources/views/livewire/auth/reset-password.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Auth/ActiveUserTest.php`

**Interfaces:**
- Consumes: `User` (`is_active`), `Role`
- Produces: routes `login`, `password.request`, `password.reset`, `logout` (POST); middleware alias `active`; a **temporary** placeholder route `tenders.index` (`/tenders/{list}`) that Task 10 replaces; layout `layouts.guest` with a `{{ $slot }}`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Auth/LoginTest.php`:

```php
<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('sends guests to the login page', function () {
    $this->get('/tenders/in-progress')->assertRedirect('/login');
});

it('logs in an active user and lands on In Progress', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass-1')
        ->call('login')
        ->assertRedirect(route('tenders.index', 'in-progress'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('rejects a deactivated user even with the right password', function () {
    $user = User::factory()->inactive()->create(['password' => 'secret-pass-1']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass-1')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('locks out after five failed attempts', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);
    $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong');

    foreach (range(1, 5) as $_) {
        $component->call('login');
    }

    $component->set('password', 'secret-pass-1')->call('login')
        ->assertHasErrors('email')
        ->assertSee('Too many attempts');
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
```

`tests/Feature/Auth/PasswordResetTest.php`:

```php
<?php

use App\Livewire\Auth\{ForgotPassword, ResetPassword};
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotification;
use Illuminate\Support\Facades\{Hash, Notification, Password};
use Livewire\Livewire;

it('emails a reset link and shows the same message whether or not the email exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send')
        ->assertSee('If that email belongs to an account');
    Livewire::test(ForgotPassword::class)->set('email', 'nobody@example.com')->call('send')
        ->assertSee('If that email belongs to an account');

    Notification::assertSentTo($user, ResetNotification::class);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'brand-new-pass-9')
        ->set('password_confirmation', 'brand-new-pass-9')
        ->call('resetPassword')
        ->assertRedirect(route('login'));

    expect(Hash::check('brand-new-pass-9', $user->fresh()->password))->toBeTrue();
});

it('refuses an invalid token', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => 'bogus'])
        ->set('email', $user->email)
        ->set('password', 'brand-new-pass-9')
        ->set('password_confirmation', 'brand-new-pass-9')
        ->call('resetPassword')
        ->assertHasErrors('email');
});
```

`tests/Feature/Auth/ActiveUserTest.php`:

```php
<?php

use App\Models\User;

it('logs out a user who was deactivated while signed in', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/tenders/in-progress')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/tenders/in-progress')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Auth`
Expected: FAIL — `/login` 404 / classes not found.

- [ ] **Step 3: Middleware, logout controller, routes**

`app/Http/Middleware/EnsureUserIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact an administrator.']);
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php` `->withMiddleware(function (Middleware $middleware) { ... })` add:

```php
        $middleware->alias(['active' => \App\Http\Middleware\EnsureUserIsActive::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/tenders/in-progress');
```

`app/Http/Controllers/LogoutController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    public function __invoke(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

`routes/web.php` (full replacement):

```php
<?php

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\{ForgotPassword, Login, ResetPassword};
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/tenders/in-progress');
    // TEMPORARY placeholder — replaced by the TenderList screen in Task 10.
    Route::get('/tenders/{list}', fn (string $list) => 'Tender list coming soon')
        ->whereIn('list', ['in-progress', 'done', 'awarded', 'lost'])
        ->name('tenders.index');
    Route::post('/logout', LogoutController::class)->name('logout');
});
```

- [ ] **Step 4: Livewire auth components**

`app/Livewire/Auth/Login.php`:

```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required']);

        $key = 'login:'.Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $credentials = ['email' => $this->email, 'password' => $this->password, 'is_active' => true];
        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'These details do not match an active account.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return $this->redirectIntended(route('tenders.index', 'in-progress'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
```

`app/Livewire/Auth/ForgotPassword.php`:

```php
<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Forgot password')]
class ForgotPassword extends Component
{
    public string $email = '';
    public ?string $status = null;

    public function send(): void
    {
        $this->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => $this->email]);

        // Same message either way, so nobody can use this form to discover who has an account.
        $this->status = 'If that email belongs to an account, a reset link is on its way.';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
```

`app/Livewire/Auth/ResetPassword.php`:

```php
<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Reset password')]
class ResetPassword extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or has expired.']);
        }

        session()->flash('status', 'Password changed. Please sign in.');

        return $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
```

- [ ] **Step 5: Guest layout and views**

`resources/views/layouts/guest.blade.php` (the colour tokens it uses are defined in Task 10; until then it renders unstyled, which is fine):

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CMT Tender Hub' }} · CMT Tender Hub</title>
    <script>
        if (localStorage.theme === 'dark' || (!localStorage.theme && matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-body text-ink antialiased flex items-center justify-center p-4">
    <main class="w-full max-w-sm rounded-2xl bg-surface border border-line p-8 shadow-sm">
        <div class="mb-6 flex items-center gap-2">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-accent font-bold text-accent-ink">T</span>
            <span class="text-lg font-semibold">CMT Tender Hub</span>
        </div>
        {{ $slot }}
    </main>
</body>
</html>
```

`resources/views/livewire/auth/login.blade.php`:

```blade
<form wire:submit="login" class="space-y-4">
    <h1 class="text-xl font-semibold">Sign in</h1>
    @if (session('status'))
        <p class="rounded-lg bg-good-bg px-3 py-2 text-sm text-good-ink">{{ session('status') }}</p>
    @endif
    <label class="block text-sm">
        <span class="text-muted">Email</span>
        <input type="email" wire:model="email" autocomplete="username" required
               class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2">
    </label>
    @error('email') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <label class="block text-sm">
        <span class="text-muted">Password</span>
        <input type="password" wire:model="password" autocomplete="current-password" required
               class="mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2">
    </label>
    <label class="flex items-center gap-2 text-sm text-muted">
        <input type="checkbox" wire:model="remember"> Keep me signed in
    </label>
    <button type="submit" class="w-full rounded-lg bg-chip px-4 py-2 font-medium text-chip-ink hover:bg-chip-hover">
        Sign in
    </button>
    <a href="{{ route('password.request') }}" class="block text-center text-sm text-muted hover:text-ink">Forgot password?</a>
</form>
```

`resources/views/livewire/auth/forgot-password.blade.php`:

```blade
<form wire:submit="send" class="space-y-4">
    <h1 class="text-xl font-semibold">Forgot password</h1>
    <p class="text-sm text-muted">Enter your work email and we'll send you a link to choose a new password.</p>
    @if ($status)
        <p class="rounded-lg bg-good-bg px-3 py-2 text-sm text-good-ink">{{ $status }}</p>
    @endif
    <input type="email" wire:model="email" required placeholder="you@company.com"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    @error('email') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <button type="submit" class="w-full rounded-lg bg-chip px-4 py-2 font-medium text-chip-ink">Send reset link</button>
    <a href="{{ route('login') }}" class="block text-center text-sm text-muted hover:text-ink">Back to sign in</a>
</form>
```

`resources/views/livewire/auth/reset-password.blade.php`:

```blade
<form wire:submit="resetPassword" class="space-y-4">
    <h1 class="text-xl font-semibold">Choose a new password</h1>
    <input type="email" wire:model="email" required placeholder="Email"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    @error('email') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <input type="password" wire:model="password" required placeholder="New password (at least 8 characters)"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    @error('password') <p class="text-sm text-bad-ink">{{ $message }}</p> @enderror
    <input type="password" wire:model="password_confirmation" required placeholder="Type it again"
           class="w-full rounded-lg border border-line bg-surface px-3 py-2">
    <button type="submit" class="w-full rounded-lg bg-chip px-4 py-2 font-medium text-chip-ink">Change password</button>
</form>
```

- [ ] **Step 6: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: email/password login, logout, password reset, deactivated-user lockout

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Tender schema, models, factories and domain exceptions

**Files:**
- Create: `database/migrations/2026_10_06_000001_create_tender_tables.php`, notifications migration (via artisan), `app/Models/Tender.php`, `app/Models/TenderDocument.php`, `app/Models/ActivityLog.php`, `database/factories/TenderFactory.php`, `app/Exceptions/StaleTenderException.php`, `app/Exceptions/InvalidTenderTransition.php`, `app/Exceptions/DocumentsIncomplete.php`
- Test: `tests/Feature/Models/TenderTest.php`, `tests/Feature/Models/ActivityLogTest.php`

**Interfaces:**
- Consumes: enums (Task 3), `Money`, `MalaysiaTime` (Task 2)
- Produces:
  - `Tender` relations `pic()`, `owner()`, `documents()` (ordered by `position`), `activity()` (newest first)
  - `Tender::scopeWithDocumentCounts()` adds `documents_count`, `documents_done_count`
  - `Tender->isLocked(): bool`, `->closingState(): ?string` (`'overdue'|'soon'|null`), `->documentPercent(): int`, `->companyVariant(): ?string`, `->winVariant(): ?string`
  - `TenderDocument::STANDARD` (array of 5 names)
  - `ActivityLog::record(Tender $tender, ?User $user, string $event, string $description): ActivityLog`
  - `StaleTenderException::for(Tender $fresh)`, `InvalidTenderTransition::make(TenderStatus $from, string $action)`, `new DocumentsIncomplete(array $pending)` with public `$pending`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Models/TenderTest.php`:

```php
<?php

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, TenderDocument, User};
use Carbon\CarbonImmutable;

it('casts enums, dates, money and version', function () {
    $tender = Tender::factory()->create([
        'closing_date' => '2026-10-15',
        'estimated_value_sen' => 16200610,
    ])->fresh();

    expect($tender->status)->toBe(TenderStatus::InProgress)
        ->and($tender->mode)->toBeInstanceOf(TenderMode::class)
        ->and($tender->category)->toBeInstanceOf(TenderCategory::class)
        ->and($tender->closing_date->toDateString())->toBe('2026-10-15')
        ->and($tender->estimated_value_sen)->toBe(16200610)
        ->and($tender->version)->toBe(1);
});

it('belongs to a PIC and an optional opportunity owner', function () {
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'owner_id' => null]);

    expect($tender->pic->is($pic))->toBeTrue()->and($tender->owner)->toBeNull();
});

it('orders documents by position and counts progress', function () {
    $tender = Tender::factory()->create();
    TenderDocument::factory()->for($tender)->create(['name' => 'B', 'position' => 2, 'is_done' => true]);
    TenderDocument::factory()->for($tender)->create(['name' => 'A', 'position' => 1]);
    TenderDocument::factory()->for($tender)->create(['name' => 'C', 'position' => 3]);

    expect($tender->documents->pluck('name')->all())->toBe(['A', 'B', 'C'])
        ->and($tender->documentPercent())->toBe(33)
        ->and(Tender::withDocumentCounts()->find($tender->id)->documentPercent())->toBe(33);
});

it('reports 0% when there are no documents', function () {
    expect(Tender::factory()->create()->documentPercent())->toBe(0);
});

it('flags closing dates by Malaysia day', function (string $closing, ?string $state) {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 7 Oct in Malaysia

    expect(Tender::factory()->make(['closing_date' => $closing])->closingState())->toBe($state);
})->with([
    ['2026-10-06', 'overdue'],
    ['2026-10-07', 'soon'],
    ['2026-10-14', 'soon'],
    ['2026-10-15', null],
]);

it('only flags closing dates while in progress', function () {
    expect(Tender::factory()->make([
        'closing_date' => '2020-01-01', 'status' => TenderStatus::Done,
    ])->closingState())->toBeNull();
});

it('computes company and win variants against the estimated value', function () {
    $tender = Tender::factory()->make([
        'estimated_value_sen' => 40275400,
        'submitted_price_sen' => 52828500,
        'winning_price_sen' => 86617900,
    ]);

    expect($tender->winVariant())->toBe('215.1%')
        ->and($tender->companyVariant())->toBe('131.2%');
});

it('is locked unless in progress', function () {
    expect(Tender::factory()->make()->isLocked())->toBeFalse()
        ->and(Tender::factory()->make(['status' => TenderStatus::Awarded])->isLocked())->toBeTrue();
});

it('lists the five standard documents in order', function () {
    expect(TenderDocument::STANDARD)->toBe([
        'Borang ISI (Tender Form)',
        'Pricing Schedule',
        'Company Profile / SSM Registration',
        'Technical Proposal',
        'Bid Bond / Bank Guarantee',
    ]);
});
```

`tests/Feature/Models/ActivityLogTest.php`:

```php
<?php

use App\Exceptions\StaleTenderException;
use App\Models\{ActivityLog, Tender, User};

it('records an activity entry and lists newest first', function () {
    $tender = Tender::factory()->create();
    $user = User::factory()->create(['name' => 'Nurul Ain']);

    ActivityLog::record($tender, $user, 'registered', 'Tender registered');
    ActivityLog::record($tender, $user, 'updated', 'Details updated: title');

    expect($tender->activity->pluck('event')->all())->toBe(['updated', 'registered'])
        ->and($tender->activity->first()->user->name)->toBe('Nurul Ain');
});

it('cannot be edited once written', function () {
    $log = ActivityLog::record(Tender::factory()->create(), null, 'registered', 'Tender registered');

    $log->description = 'tampered';
    $log->save();

    expect($log->fresh()->description)->toBe('Tender registered');
});

it('names the last person to change a tender in the stale message', function () {
    $tender = Tender::factory()->create();
    ActivityLog::record($tender, User::factory()->create(['name' => 'Ahmad Faizal']), 'updated', 'x');

    expect(StaleTenderException::for($tender)->getMessage())
        ->toBe('This tender was changed by Ahmad Faizal — reload to see their changes.');
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Models`
Expected: FAIL — `Class "App\Models\Tender" not found`.

- [ ] **Step 3: Migrations**

```bash
docker compose exec -T app php artisan make:notifications-table
```

`database/migrations/2026_10_06_000001_create_tender_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wo_sequences', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('last_seq')->default(0);
        });

        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number', 20)->unique();
            $table->date('wo_date');
            $table->string('mode', 10);
            $table->string('type', 20);
            $table->string('category', 50);
            $table->string('tender_code', 100)->index();
            $table->text('title');
            $table->string('client');
            $table->text('scope')->nullable();
            $table->foreignId('pic_id')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->date('publish_date')->nullable();
            $table->date('closing_date')->index();
            $table->boolean('has_briefing')->default(false);
            $table->date('briefing_date')->nullable();
            $table->unsignedBigInteger('estimated_value_sen')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('submitted_price_sen')->nullable();
            $table->unsignedBigInteger('winning_price_sen')->nullable();
            $table->text('lost_reason')->nullable();
            $table->boolean('was_cancelled')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('closing_soon_notified_at')->nullable();
            $table->timestamp('briefing_notified_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('tender_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('event', 40);
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('tender_documents');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('wo_sequences');
    }
};
```

- [ ] **Step 4: Models**

`app/Models/Tender.php`:

```php
<?php

namespace App\Models;

use App\Enums\{TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Support\{MalaysiaTime, Money};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Tender extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenderStatus::class,
            'mode' => TenderMode::class,
            'type' => TenderType::class,
            'category' => TenderCategory::class,
            'wo_date' => 'immutable_date',
            'publish_date' => 'immutable_date',
            'closing_date' => 'immutable_date',
            'briefing_date' => 'immutable_date',
            'has_briefing' => 'boolean',
            'was_cancelled' => 'boolean',
            'estimated_value_sen' => 'integer',
            'submitted_price_sen' => 'integer',
            'winning_price_sen' => 'integer',
            'version' => 'integer',
            'done_at' => 'immutable_datetime',
            'awarded_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
            'closing_soon_notified_at' => 'immutable_datetime',
            'briefing_notified_at' => 'immutable_datetime',
        ];
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenderDocument::class)->orderBy('position');
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    public function scopeWithDocumentCounts(Builder $query): Builder
    {
        return $query->withCount([
            'documents',
            'documents as documents_done_count' => fn (Builder $q) => $q->where('is_done', true),
        ]);
    }

    public function isLocked(): bool
    {
        return $this->status !== TenderStatus::InProgress;
    }

    public function closingState(): ?string
    {
        if ($this->status !== TenderStatus::InProgress || $this->closing_date === null) {
            return null;
        }

        $closing = $this->closing_date->toDateString();
        $today = MalaysiaTime::today();

        if ($closing < $today->toDateString()) {
            return 'overdue';
        }

        return $closing <= $today->addDays(7)->toDateString() ? 'soon' : null;
    }

    public function documentPercent(): int
    {
        $total = $this->documents_count ?? $this->documents()->count();
        $done = $this->documents_done_count ?? $this->documents()->where('is_done', true)->count();

        return $total === 0 ? 0 : (int) round($done * 100 / $total);
    }

    public function companyVariant(): ?string
    {
        return Money::variant($this->submitted_price_sen, $this->estimated_value_sen);
    }

    public function winVariant(): ?string
    {
        return Money::variant($this->winning_price_sen, $this->estimated_value_sen);
    }
}
```

`app/Models/TenderDocument.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderDocument extends Model
{
    use HasFactory;

    public const STANDARD = [
        'Borang ISI (Tender Form)',
        'Pricing Schedule',
        'Company Profile / SSM Registration',
        'Technical Proposal',
        'Bid Bond / Bank Guarantee',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'immutable_datetime', 'position' => 'integer'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
```

`app/Models/ActivityLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        // Append-only: refuse any update.
        static::updating(fn () => false);
    }

    public static function record(Tender $tender, ?User $user, string $event, string $description): self
    {
        return static::create([
            'tender_id' => $tender->id,
            'user_id' => $user?->id,
            'event' => $event,
            'description' => $description,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Exceptions**

`app/Exceptions/StaleTenderException.php`:

```php
<?php

namespace App\Exceptions;

use App\Models\Tender;
use RuntimeException;

class StaleTenderException extends RuntimeException
{
    public static function for(Tender $fresh): self
    {
        $name = $fresh->activity()->with('user')->first()?->user?->name ?? 'someone else';

        return new self("This tender was changed by {$name} — reload to see their changes.");
    }
}
```

`app/Exceptions/InvalidTenderTransition.php`:

```php
<?php

namespace App\Exceptions;

use App\Enums\TenderStatus;
use RuntimeException;

class InvalidTenderTransition extends RuntimeException
{
    public static function make(TenderStatus $from, string $action): self
    {
        return new self("You can't {$action} a tender that is {$from->label()}.");
    }
}
```

`app/Exceptions/DocumentsIncomplete.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class DocumentsIncomplete extends RuntimeException
{
    /** @param list<string> $pending */
    public function __construct(public readonly array $pending)
    {
        parent::__construct(count($pending).' document(s) still not ticked: '.implode(', ', $pending).'.');
    }
}
```

- [ ] **Step 6: Factories**

`database/factories/TenderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\{TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Models\User;
use App\Support\MalaysiaTime;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wo_number' => fake()->unique()->numerify('200-01012026-###'),
            'wo_date' => '2026-01-01',
            'mode' => TenderMode::Ep,
            'type' => TenderType::Tender,
            'category' => TenderCategory::ItInfrastructure,
            'tender_code' => fake()->numerify('QT2600000000#####'),
            'title' => strtoupper(fake()->sentence(8)),
            'client' => 'KEMENTERIAN KESIHATAN',
            'pic_id' => User::factory(),
            'owner_id' => null,
            'closing_date' => MalaysiaTime::today()->addDays(30)->toDateString(),
            'has_briefing' => false,
            'estimated_value_sen' => 50000000,
            'status' => TenderStatus::InProgress,
            'version' => 1,
        ];
    }

    public function status(TenderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
```

`database/factories/TenderDocumentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenderDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'name' => fake()->words(3, true),
            'position' => 1,
            'is_done' => false,
        ];
    }
}
```

- [ ] **Step 7: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tender, document and activity-log schema with models and factories

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: WO number generator and permission rules

**Files:**
- Create: `app/Actions/Tenders/GenerateWoNumber.php`, `app/Policies/TenderPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Actions/GenerateWoNumberTest.php`, `tests/Feature/Policies/PermissionsTest.php`

**Interfaces:**
- Produces:
  - `GenerateWoNumber::next(CarbonImmutable $malaysiaDay): string` — must be called inside a DB transaction by its caller (it opens its own nested one too)
  - `TenderPolicy` abilities: `viewAny`, `view`, `create`, `update`, `reopen`
  - Gate `manage-users` (admin only)

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Actions/GenerateWoNumberTest.php`:

```php
<?php

use App\Actions\Tenders\GenerateWoNumber;
use App\Models\Tender;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

it('numbers WOs per day and restarts at 001 the next day', function () {
    $wo = app(GenerateWoNumber::class);
    $day1 = CarbonImmutable::parse('2026-10-06', 'Asia/Kuala_Lumpur');
    $day2 = $day1->addDay();

    expect($wo->next($day1))->toBe('200-06102026-001')
        ->and($wo->next($day1))->toBe('200-06102026-002')
        ->and($wo->next($day2))->toBe('200-07102026-001')
        ->and($wo->next($day1))->toBe('200-06102026-003');
});

it('has a database backstop against duplicate WO numbers', function () {
    Tender::factory()->create(['wo_number' => '200-06102026-001']);
    Tender::factory()->create(['wo_number' => '200-06102026-001']);
})->throws(QueryException::class);
```

`tests/Feature/Policies/PermissionsTest.php`:

```php
<?php

use App\Models\{Tender, User};
use Illuminate\Support\Facades\Gate;

it('lets everyone view and register tenders', function () {
    $staff = User::factory()->create();
    $tender = Tender::factory()->create();

    expect(Gate::forUser($staff)->allows('view', $tender))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('create', Tender::class))->toBeTrue();
});

it('lets staff edit only tenders where they are PIC', function () {
    $staff = User::factory()->create();
    $mine = Tender::factory()->create(['pic_id' => $staff->id]);
    $theirs = Tender::factory()->create(['owner_id' => $staff->id]); // owner is not enough

    expect(Gate::forUser($staff)->allows('update', $mine))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('update', $theirs))->toBeFalse();
});

it('lets managers and admins edit and reopen any tender', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $tender = Tender::factory()->create();

    expect(Gate::forUser($user)->allows('update', $tender))->toBeTrue()
        ->and(Gate::forUser($user)->allows('reopen', $tender))->toBeTrue();
})->with(['manager', 'admin']);

it('does not let staff reopen, even their own tender', function () {
    $staff = User::factory()->create();

    expect(Gate::forUser($staff)->allows('reopen', Tender::factory()->create(['pic_id' => $staff->id])))->toBeFalse();
});

it('lets only admins manage users', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('manage-users'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->manager()->create())->allows('manage-users'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('manage-users'))->toBeFalse();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Actions/GenerateWoNumberTest.php tests/Feature/Policies`
Expected: FAIL — class not found / gate denies.

- [ ] **Step 3: Implement**

`app/Actions/Tenders/GenerateWoNumber.php`:

```php
<?php

namespace App\Actions\Tenders;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateWoNumber
{
    /** @param CarbonImmutable $malaysiaDay a Malaysia calendar day (see MalaysiaTime::today()) */
    public function next(CarbonImmutable $malaysiaDay): string
    {
        return DB::transaction(function () use ($malaysiaDay) {
            $date = $malaysiaDay->toDateString();

            DB::table('wo_sequences')->insertOrIgnore(['date' => $date, 'last_seq' => 0]);
            // Row lock: two people registering at the same moment queue here instead of sharing a number.
            $current = DB::table('wo_sequences')->where('date', $date)->lockForUpdate()->value('last_seq');
            $next = $current + 1;
            DB::table('wo_sequences')->where('date', $date)->update(['last_seq' => $next]);

            return sprintf('200-%s-%03d', $malaysiaDay->format('dmY'), $next);
        });
    }
}
```

`app/Policies/TenderPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\{Tender, User};

class TenderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tender $tender): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tender $tender): bool
    {
        return $user->role->canManageAllTenders() || $tender->pic_id === $user->id;
    }

    public function reopen(User $user, Tender $tender): bool
    {
        return $user->role->canManageAllTenders();
    }
}
```

In `app/Providers/AppServiceProvider.php` `boot()`:

```php
        \Illuminate\Support\Facades\Gate::define(
            'manage-users',
            fn (\App\Models\User $user) => $user->role === \App\Enums\Role::Admin,
        );
```

- [ ] **Step 4: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: daily WO number sequence and tender/user permission rules

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Register and edit tenders (with edit-conflict protection)

**Files:**
- Create: `app/Actions/Tenders/Concerns/GuardsTender.php`, `app/Actions/Tenders/RegisterTender.php`, `app/Actions/Tenders/UpdateTender.php`
- Test: `tests/Feature/Actions/RegisterTenderTest.php`, `tests/Feature/Actions/UpdateTenderTest.php`

**Interfaces:**
- Consumes: `GenerateWoNumber::next`, `TenderPolicy`, `ActivityLog::record`, exceptions (Task 5)
- Produces:
  - `RegisterTender::FIELDS` (allowed data keys), `RegisterTender::handle(User $actor, array $data): Tender`
  - `UpdateTender::handle(User $actor, Tender $tender, int $expectedVersion, array $data): Tender` (returns fresh model)
  - Trait `GuardsTender` with `lockForChange(User $actor, Tender $tender, int $expectedVersion, string $ability = 'update'): Tender` and `requireStatus(Tender $tender, TenderStatus $status, string $action): void` — callers MUST be inside `DB::transaction`
  - Data array keys (snake_case, values already typed): `mode` (TenderMode), `type` (TenderType), `category` (TenderCategory), `tender_code`, `title`, `client`, `scope` (?string), `pic_id` (int), `owner_id` (?int), `publish_date` (?'Y-m-d'), `closing_date` ('Y-m-d'), `has_briefing` (bool), `briefing_date` (?'Y-m-d'), `estimated_value_sen` (?int)

- [ ] **Step 1: Write the failing tests**

Shared helper — add to the bottom of `tests/Pest.php`:

```php
function tenderData(array $overrides = []): array
{
    return array_merge([
        'mode' => App\Enums\TenderMode::Ep,
        'type' => App\Enums\TenderType::Tender,
        'category' => App\Enums\TenderCategory::SoftwareDevelopment,
        'tender_code' => 'QT260000000041127',
        'title' => 'PERKHIDMATAN PEMBANGUNAN SISTEM',
        'client' => 'JABATAN PERPADUAN NEGARA DAN INTEGRASI NASIONAL',
        'scope' => null,
        'pic_id' => App\Models\User::factory()->create()->id,
        'owner_id' => null,
        'publish_date' => '2026-09-08',
        'closing_date' => '2026-10-15',
        'has_briefing' => false,
        'briefing_date' => null,
        'estimated_value_sen' => 16200610,
    ], $overrides);
}
```

`tests/Feature/Actions/RegisterTenderTest.php`:

```php
<?php

use App\Actions\Tenders\RegisterTender;
use App\Enums\TenderStatus;
use App\Models\{TenderDocument, User};
use Carbon\CarbonImmutable;

it('registers a tender with a Malaysia-dated WO number, standard documents and a log entry', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 00:30 on 7 Oct in Malaysia
    $actor = User::factory()->create();

    $tender = app(RegisterTender::class)->handle($actor, tenderData());

    expect($tender->wo_number)->toBe('200-07102026-001')
        ->and($tender->wo_date->toDateString())->toBe('2026-10-07')
        ->and($tender->status)->toBe(TenderStatus::InProgress)
        ->and($tender->version)->toBe(1)
        ->and($tender->documents->pluck('name')->all())->toBe(TenderDocument::STANDARD)
        ->and($tender->activity->first()->event)->toBe('registered')
        ->and($tender->activity->first()->user_id)->toBe($actor->id);
});

it('ignores keys that are not tender fields', function () {
    $tender = app(RegisterTender::class)->handle(
        User::factory()->create(),
        tenderData(['status' => 'awarded', 'version' => 99, 'wo_number' => 'hack']),
    );

    expect($tender->status)->toBe(TenderStatus::InProgress)
        ->and($tender->version)->toBe(1)
        ->and($tender->wo_number)->not->toBe('hack');
});
```

`tests/Feature/Actions/UpdateTenderTest.php`:

```php
<?php

use App\Actions\Tenders\UpdateTender;
use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;

function editable(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Nurul Ain']);
    $tender = Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'closing_date' => '2026-10-15'], $attrs));

    return [$pic, $tender];
}

it('saves changes, bumps the version and logs which fields changed', function () {
    [$pic, $tender] = editable();

    $updated = app(UpdateTender::class)->handle($pic, $tender, 1, [
        'title' => 'NEW TITLE', 'closing_date' => '2026-10-20',
    ]);

    expect($updated->title)->toBe('NEW TITLE')
        ->and($updated->version)->toBe(2)
        ->and($updated->activity->first()->description)->toBe('Details updated: title, closing date');
});

it('logs a PIC change with both names', function () {
    [$pic, $tender] = editable();
    $new = User::factory()->create(['name' => 'Siti Aisyah']);

    $updated = app(UpdateTender::class)->handle($pic, $tender, 1, ['pic_id' => $new->id]);

    expect($updated->activity->first()->event)->toBe('pic_changed')
        ->and($updated->activity->first()->description)->toBe('PIC changed from Nurul Ain to Siti Aisyah');
});

it('does nothing when nothing changed', function () {
    [$pic, $tender] = editable();

    $same = app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => $tender->title]);

    expect($same->version)->toBe(1)->and(ActivityLog::count())->toBe(0);
});

it('refuses a save based on an out-of-date copy (two tabs)', function () {
    [$pic, $tender] = editable();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);

    app(UpdateTender::class)->handle($manager, $tender, 1, ['title' => 'FIRST']);

    expect(fn () => app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => 'SECOND']))
        ->toThrow(StaleTenderException::class, 'This tender was changed by Ahmad Faizal');
    expect($tender->fresh()->title)->toBe('FIRST');
});

it('re-arms reminders when the closing or briefing date changes', function () {
    [$pic, $tender] = editable(['closing_soon_notified_at' => now(), 'briefing_notified_at' => now()]);

    $updated = app(UpdateTender::class)->handle($pic, $tender, 1, [
        'closing_date' => '2026-11-01', 'has_briefing' => true, 'briefing_date' => '2026-10-25',
    ]);

    expect($updated->closing_soon_notified_at)->toBeNull()
        ->and($updated->briefing_notified_at)->toBeNull();
});

it('refuses staff who are not the PIC', function () {
    [, $tender] = editable();

    app(UpdateTender::class)->handle(User::factory()->create(), $tender, 1, ['title' => 'X']);
})->throws(AuthorizationException::class);

it('refuses edits once the tender is no longer in progress', function () {
    [$pic, $tender] = editable(['status' => TenderStatus::Done]);

    app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => 'X']);
})->throws(InvalidTenderTransition::class);
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Actions`
Expected: FAIL — `RegisterTender` / `UpdateTender` not found.

- [ ] **Step 3: Implement**

`app/Actions/Tenders/Concerns/GuardsTender.php`:

```php
<?php

namespace App\Actions\Tenders\Concerns;

use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, User};
use Illuminate\Support\Facades\Gate;

trait GuardsTender
{
    /** Call inside DB::transaction. Locks the row, checks permission, then checks nobody saved in between. */
    private function lockForChange(User $actor, Tender $tender, int $expectedVersion, string $ability = 'update'): Tender
    {
        $fresh = Tender::query()->lockForUpdate()->findOrFail($tender->id);

        Gate::forUser($actor)->authorize($ability, $fresh);

        if ($fresh->version !== $expectedVersion) {
            throw StaleTenderException::for($fresh);
        }

        return $fresh;
    }

    private function requireStatus(Tender $tender, TenderStatus $status, string $action): void
    {
        if ($tender->status !== $status) {
            throw InvalidTenderTransition::make($tender->status, $action);
        }
    }
}
```

`app/Actions/Tenders/RegisterTender.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Gate};

final class RegisterTender
{
    public const FIELDS = [
        'mode', 'type', 'category', 'tender_code', 'title', 'client', 'scope',
        'pic_id', 'owner_id', 'publish_date', 'closing_date',
        'has_briefing', 'briefing_date', 'estimated_value_sen',
    ];

    public function __construct(private GenerateWoNumber $woNumbers) {}

    public function handle(User $actor, array $data): Tender
    {
        Gate::forUser($actor)->authorize('create', Tender::class);

        return DB::transaction(function () use ($actor, $data) {
            $today = MalaysiaTime::today();

            $tender = new Tender(Arr::only($data, self::FIELDS));
            $tender->forceFill([
                'wo_number' => $this->woNumbers->next($today),
                'wo_date' => $today->toDateString(),
                'status' => TenderStatus::InProgress,
                'version' => 1,
            ])->save();

            foreach (TenderDocument::STANDARD as $i => $name) {
                $tender->documents()->create(['name' => $name, 'position' => $i + 1]);
            }

            ActivityLog::record($tender, $actor, 'registered', 'Tender registered');

            return $tender->fresh();
        });
    }
}
```

`app/Actions/Tenders/UpdateTender.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateTender
{
    use GuardsTender;

    private const LABELS = [
        'mode' => 'mode', 'type' => 'type', 'category' => 'category',
        'tender_code' => 'tender code', 'title' => 'title', 'client' => 'client', 'scope' => 'scope',
        'publish_date' => 'publish date', 'closing_date' => 'closing date',
        'has_briefing' => 'briefing', 'briefing_date' => 'briefing date',
        'estimated_value_sen' => 'estimated value',
    ];

    public function handle(User $actor, Tender $tender, int $expectedVersion, array $data): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $data) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'edit');

            $oldPicName = $t->pic->name;
            $oldOwnerName = $t->owner?->name ?? 'nobody';

            $t->fill(Arr::only($data, RegisterTender::FIELDS));
            if (! $t->isDirty()) {
                return $t;
            }

            $changed = array_keys($t->getDirty());
            if ($t->isDirty('closing_date')) {
                $t->closing_soon_notified_at = null;
            }
            if ($t->isDirty(['briefing_date', 'has_briefing'])) {
                $t->briefing_notified_at = null;
            }
            $t->version = $t->version + 1;
            $t->save();
            $t->load('pic', 'owner');

            if (in_array('pic_id', $changed, true)) {
                ActivityLog::record($t, $actor, 'pic_changed', "PIC changed from {$oldPicName} to {$t->pic->name}");
            }
            if (in_array('owner_id', $changed, true)) {
                $newOwner = $t->owner?->name ?? 'nobody';
                ActivityLog::record($t, $actor, 'owner_changed', "Opportunity Owner changed from {$oldOwnerName} to {$newOwner}");
            }

            $other = array_values(array_intersect(array_keys(self::LABELS), $changed));
            if ($other !== []) {
                $labels = array_map(fn (string $f) => self::LABELS[$f], $other);
                ActivityLog::record($t, $actor, 'updated', 'Details updated: '.implode(', ', $labels));
            }

            return $t->fresh();
        });
    }
}
```

Note: `$other` follows `LABELS` order, so "title, closing date" in the test matches the LABELS ordering (title before closing date).

- [ ] **Step 4: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: register and edit tenders with version-based conflict protection

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Status changes — Mark Done, Cancel, Mark Awarded, Mark Lost, Reopen

**Files:**
- Create: `app/Actions/Tenders/MarkTenderDone.php`, `app/Actions/Tenders/CancelTender.php`, `app/Actions/Tenders/MarkTenderAwarded.php`, `app/Actions/Tenders/MarkTenderLost.php`, `app/Actions/Tenders/ReopenTender.php`
- Test: `tests/Feature/Actions/StatusChangesTest.php`

**Interfaces:**
- Consumes: `GuardsTender`, `ActivityLog::record`, `Money::format`, `DocumentsIncomplete`
- Produces (all return the fresh `Tender`):
  - `MarkTenderDone::handle(User $actor, Tender $tender, int $expectedVersion, int $submittedPriceSen): Tender`
  - `CancelTender::handle(User $actor, Tender $tender, int $expectedVersion, string $reason): Tender`
  - `MarkTenderAwarded::handle(User $actor, Tender $tender, int $expectedVersion): Tender`
  - `MarkTenderLost::handle(User $actor, Tender $tender, int $expectedVersion, ?int $winningPriceSen, ?string $reason): Tender`
  - `ReopenTender::handle(User $actor, Tender $tender, int $expectedVersion): Tender`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Actions/StatusChangesTest.php`:

```php
<?php

use App\Actions\Tenders\{CancelTender, MarkTenderAwarded, MarkTenderDone, MarkTenderLost, ReopenTender};
use App\Enums\TenderStatus;
use App\Exceptions\{DocumentsIncomplete, InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, TenderDocument, User};
use Illuminate\Auth\Access\AuthorizationException;

function tenderWithDocs(TenderStatus $status = TenderStatus::InProgress, bool $allDone = true): array
{
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'status' => $status]);
    TenderDocument::factory()->for($tender)->create(['name' => 'Borang ISI (Tender Form)', 'position' => 1, 'is_done' => true]);
    TenderDocument::factory()->for($tender)->create(['name' => 'Bid Bond / Bank Guarantee', 'position' => 2, 'is_done' => $allDone]);

    return [$pic, $tender];
}

it('marks a tender Done with its submitted price', function () {
    [$pic, $tender] = tenderWithDocs();

    $done = app(MarkTenderDone::class)->handle($pic, $tender, 1, 16605900);

    expect($done->status)->toBe(TenderStatus::Done)
        ->and($done->submitted_price_sen)->toBe(16605900)
        ->and($done->done_at)->not->toBeNull()
        ->and($done->version)->toBe(2)
        ->and($done->activity->first()->description)->toBe('Marked Done — submitted price RM 166,059.00');
});

it('blocks Mark Done while documents are unticked and names them', function () {
    [$pic, $tender] = tenderWithDocs(allDone: false);

    try {
        app(MarkTenderDone::class)->handle($pic, $tender, 1, 100);
        $this->fail('Expected DocumentsIncomplete');
    } catch (DocumentsIncomplete $e) {
        expect($e->pending)->toBe(['Bid Bond / Bank Guarantee']);
    }
    expect($tender->fresh()->status)->toBe(TenderStatus::InProgress);
});

it('requires a positive submitted price', function () {
    [$pic, $tender] = tenderWithDocs();

    app(MarkTenderDone::class)->handle($pic, $tender, 1, 0);
})->throws(InvalidArgumentException::class);

it('cancels an in-progress tender into Lost with a reason', function () {
    [$pic, $tender] = tenderWithDocs(allDone: false);

    $lost = app(CancelTender::class)->handle($pic, $tender, 1, '  Not our scope  ');

    expect($lost->status)->toBe(TenderStatus::Lost)
        ->and($lost->was_cancelled)->toBeTrue()
        ->and($lost->lost_reason)->toBe('Not our scope')
        ->and($lost->activity->first()->description)->toBe('Cancelled — reason: Not our scope');
});

it('requires a cancel reason', function () {
    [$pic, $tender] = tenderWithDocs();

    app(CancelTender::class)->handle($pic, $tender, 1, '   ');
})->throws(InvalidArgumentException::class);

it('marks a Done tender Awarded', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Done);

    $won = app(MarkTenderAwarded::class)->handle($pic, $tender, 1);

    expect($won->status)->toBe(TenderStatus::Awarded)->and($won->awarded_at)->not->toBeNull();
});

it('marks a Done tender Lost with optional winning price and reason', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Done);

    $lost = app(MarkTenderLost::class)->handle($pic, $tender, 1, 86617900, 'Lower bidder');

    expect($lost->status)->toBe(TenderStatus::Lost)
        ->and($lost->was_cancelled)->toBeFalse()
        ->and($lost->winning_price_sen)->toBe(86617900)
        ->and($lost->activity->first()->description)->toBe('Marked Lost — winning price RM 866,179.00 — reason: Lower bidder');

    [$pic2, $tender2] = tenderWithDocs(TenderStatus::Done);
    expect(app(MarkTenderLost::class)->handle($pic2, $tender2, 1, null, null)->activity->first()->description)
        ->toBe('Marked Lost');
});

it('refuses transitions the lifecycle does not allow', function (string $action, TenderStatus $from) {
    [$pic, $tender] = tenderWithDocs($from);
    $call = match ($action) {
        'done' => fn () => app(MarkTenderDone::class)->handle($pic, $tender, 1, 100),
        'cancel' => fn () => app(CancelTender::class)->handle($pic, $tender, 1, 'x'),
        'awarded' => fn () => app(MarkTenderAwarded::class)->handle($pic, $tender, 1),
        'lost' => fn () => app(MarkTenderLost::class)->handle($pic, $tender, 1, null, null),
    };

    expect($call)->toThrow(InvalidTenderTransition::class);
})->with([
    ['done', TenderStatus::Done], ['done', TenderStatus::Awarded], ['done', TenderStatus::Lost],
    ['cancel', TenderStatus::Done], ['cancel', TenderStatus::Lost],
    ['awarded', TenderStatus::InProgress], ['awarded', TenderStatus::Lost],
    ['lost', TenderStatus::InProgress], ['lost', TenderStatus::Awarded],
]);

it('lets a manager reopen a closed tender and clears the outcome', function () {
    [, $tender] = tenderWithDocs(TenderStatus::Lost);
    $tender->update(['submitted_price_sen' => 100, 'winning_price_sen' => 200, 'lost_reason' => 'x', 'was_cancelled' => true, 'lost_at' => now()]);

    $open = app(ReopenTender::class)->handle(User::factory()->manager()->create(), $tender, 1);

    expect($open->status)->toBe(TenderStatus::InProgress)
        ->and($open->submitted_price_sen)->toBeNull()
        ->and($open->winning_price_sen)->toBeNull()
        ->and($open->lost_reason)->toBeNull()
        ->and($open->was_cancelled)->toBeFalse()
        ->and($open->lost_at)->toBeNull()
        ->and($open->activity->first()->description)->toBe('Reopened (was Lost)');
});

it('refuses reopen by staff and on in-progress tenders', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Awarded);
    expect(fn () => app(ReopenTender::class)->handle($pic, $tender, 1))->toThrow(AuthorizationException::class);

    [, $open] = tenderWithDocs();
    expect(fn () => app(ReopenTender::class)->handle(User::factory()->admin()->create(), $open, 1))
        ->toThrow(InvalidTenderTransition::class);
});

it('refuses status changes by staff who are not the PIC', function () {
    [, $tender] = tenderWithDocs();

    app(CancelTender::class)->handle(User::factory()->create(), $tender, 1, 'x');
})->throws(AuthorizationException::class);

it('refuses status changes from an out-of-date page', function () {
    [$pic, $tender] = tenderWithDocs(TenderStatus::Done);

    app(MarkTenderAwarded::class)->handle($pic, $tender, 7);
})->throws(StaleTenderException::class);
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Actions/StatusChangesTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement**

`app/Actions/Tenders/MarkTenderDone.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Exceptions\DocumentsIncomplete;
use App\Models\{ActivityLog, Tender, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MarkTenderDone
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $submittedPriceSen): Tender
    {
        if ($submittedPriceSen <= 0) {
            throw new InvalidArgumentException('Submitted price must be more than zero.');
        }

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $submittedPriceSen) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'mark as Done');

            $pending = $t->documents()->where('is_done', false)->pluck('name')->all();
            if ($pending !== []) {
                throw new DocumentsIncomplete($pending);
            }

            $t->forceFill([
                'status' => TenderStatus::Done,
                'submitted_price_sen' => $submittedPriceSen,
                'done_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'marked_done', 'Marked Done — submitted price '.Money::format($submittedPriceSen));

            return $t->fresh();
        });
    }
}
```

`app/Actions/Tenders/CancelTender.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelTender
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, string $reason): Tender
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to cancel a tender.');
        }

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $reason) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'cancel');

            $t->forceFill([
                'status' => TenderStatus::Lost,
                'was_cancelled' => true,
                'lost_reason' => $reason,
                'lost_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'cancelled', "Cancelled — reason: {$reason}");

            return $t->fresh();
        });
    }
}
```

`app/Actions/Tenders/MarkTenderAwarded.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class MarkTenderAwarded
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::Done, 'mark as Awarded');

            $t->forceFill([
                'status' => TenderStatus::Awarded,
                'awarded_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'marked_awarded', 'Marked Awarded');

            return $t->fresh();
        });
    }
}
```

`app/Actions/Tenders/MarkTenderLost.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MarkTenderLost
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, ?int $winningPriceSen, ?string $reason): Tender
    {
        if ($winningPriceSen !== null && $winningPriceSen < 0) {
            throw new InvalidArgumentException('Winning price cannot be negative.');
        }
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $winningPriceSen, $reason) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::Done, 'mark as Lost');

            $t->forceFill([
                'status' => TenderStatus::Lost,
                'winning_price_sen' => $winningPriceSen,
                'lost_reason' => $reason,
                'lost_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            $description = 'Marked Lost'
                .($winningPriceSen !== null ? ' — winning price '.Money::format($winningPriceSen) : '')
                .($reason !== null ? " — reason: {$reason}" : '');
            ActivityLog::record($t, $actor, 'marked_lost', $description);

            return $t->fresh();
        });
    }
}
```

`app/Actions/Tenders/ReopenTender.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Exceptions\InvalidTenderTransition;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class ReopenTender
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion, 'reopen');
            if ($t->status === TenderStatus::InProgress) {
                throw InvalidTenderTransition::make($t->status, 'reopen');
            }
            $was = $t->status->label();

            $t->forceFill([
                'status' => TenderStatus::InProgress,
                'submitted_price_sen' => null,
                'winning_price_sen' => null,
                'lost_reason' => null,
                'was_cancelled' => false,
                'done_at' => null,
                'awarded_at' => null,
                'lost_at' => null,
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'reopened', "Reopened (was {$was})");

            return $t->fresh();
        });
    }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: tender status changes with lifecycle, document gate and reopen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Document checklist actions

**Files:**
- Create: `app/Actions/Tenders/ToggleDocument.php`, `app/Actions/Tenders/AddDocument.php`, `app/Actions/Tenders/RemoveDocument.php`
- Test: `tests/Feature/Actions/DocumentActionsTest.php`

**Interfaces:**
- Consumes: `GuardsTender`, `ActivityLog::record`
- Produces (all return fresh `Tender`):
  - `ToggleDocument::handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender`
  - `AddDocument::handle(User $actor, Tender $tender, int $expectedVersion, string $name): Tender`
  - `RemoveDocument::handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Actions/DocumentActionsTest.php`:

```php
<?php

use App\Actions\Tenders\{AddDocument, RemoveDocument, ToggleDocument};
use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, TenderDocument, User};
use Illuminate\Database\Eloquent\ModelNotFoundException;

function docTender(TenderStatus $status = TenderStatus::InProgress): array
{
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'status' => $status]);
    $doc = TenderDocument::factory()->for($tender)->create(['name' => 'Pricing Schedule', 'position' => 1]);

    return [$pic, $tender, $doc];
}

it('ticks and unticks a document, recording who and when', function () {
    [$pic, $tender, $doc] = docTender();

    $t = app(ToggleDocument::class)->handle($pic, $tender, 1, $doc->id);
    expect($doc->fresh()->is_done)->toBeTrue()
        ->and($doc->fresh()->done_by)->toBe($pic->id)
        ->and($t->version)->toBe(2)
        ->and($t->activity->first()->description)->toBe('Ticked: Pricing Schedule');

    $t = app(ToggleDocument::class)->handle($pic, $t, 2, $doc->id);
    expect($doc->fresh()->is_done)->toBeFalse()
        ->and($doc->fresh()->done_by)->toBeNull()
        ->and($t->activity->first()->description)->toBe('Unticked: Pricing Schedule');
});

it('adds a document at the end of the list', function () {
    [$pic, $tender] = docTender();

    $t = app(AddDocument::class)->handle($pic, $tender, 1, '  Surat Akuan  ');

    expect($t->documents->pluck('name')->all())->toBe(['Pricing Schedule', 'Surat Akuan'])
        ->and($t->documents->last()->position)->toBe(2)
        ->and($t->activity->first()->description)->toBe('Added document: Surat Akuan');
});

it('refuses a blank document name', function () {
    [$pic, $tender] = docTender();

    app(AddDocument::class)->handle($pic, $tender, 1, '  ');
})->throws(InvalidArgumentException::class);

it('removes a document', function () {
    [$pic, $tender, $doc] = docTender();

    $t = app(RemoveDocument::class)->handle($pic, $tender, 1, $doc->id);

    expect($t->documents)->toHaveCount(0)
        ->and($t->activity->first()->description)->toBe('Removed document: Pricing Schedule');
});

it('cannot touch a document belonging to another tender', function () {
    [$pic, $tender] = docTender();
    [, , $foreign] = docTender();

    app(ToggleDocument::class)->handle($pic, $tender, 1, $foreign->id);
})->throws(ModelNotFoundException::class);

it('locks the checklist once the tender is no longer in progress', function () {
    [$pic, $tender, $doc] = docTender(TenderStatus::Done);

    app(ToggleDocument::class)->handle($pic, $tender, 1, $doc->id);
})->throws(InvalidTenderTransition::class);

it('refuses checklist changes from an out-of-date page', function () {
    [$pic, $tender, $doc] = docTender();

    app(ToggleDocument::class)->handle($pic, $tender, 5, $doc->id);
})->throws(StaleTenderException::class);
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Actions/DocumentActionsTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement**

`app/Actions/Tenders/ToggleDocument.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class ToggleDocument
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $documentId) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $doc = $t->documents()->findOrFail($documentId);
            $nowDone = ! $doc->is_done;
            $doc->forceFill([
                'is_done' => $nowDone,
                'done_by' => $nowDone ? $actor->id : null,
                'done_at' => $nowDone ? now() : null,
            ])->save();

            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record(
                $t, $actor,
                $nowDone ? 'document_ticked' : 'document_unticked',
                ($nowDone ? 'Ticked: ' : 'Unticked: ').$doc->name,
            );

            return $t->fresh();
        });
    }
}
```

`app/Actions/Tenders/AddDocument.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AddDocument
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, string $name): Tender
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Document name must be 1–255 characters.');
        }

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $name) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $t->documents()->create([
                'name' => $name,
                'position' => (int) $t->documents()->max('position') + 1,
            ]);
            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'document_added', "Added document: {$name}");

            return $t->fresh();
        });
    }
}
```

`app/Actions/Tenders/RemoveDocument.php`:

```php
<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class RemoveDocument
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $documentId) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $doc = $t->documents()->findOrFail($documentId);
            $name = $doc->name;
            $doc->delete();

            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'document_removed', "Removed document: {$name}");

            return $t->fresh();
        });
    }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: tick, add and remove tender checklist documents

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: App layout (sidebar, theme, phone drawer) and the four tender lists

**Files:**
- Create: `resources/css/app.css` (replace), `resources/views/layouts/app.blade.php`, `resources/views/layouts/partials/sidebar.blade.php`, `app/View/Composers/SidebarComposer.php`, `app/Queries/TenderListQuery.php`, `app/Livewire/TenderList.php`, `resources/views/livewire/tender-list.blade.php`, `resources/views/components/status-badge.blade.php`, `resources/views/components/avatar.blade.php`
- Modify: `routes/web.php` (replace placeholder), `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Queries/TenderListQueryTest.php`, `tests/Feature/Livewire/TenderListTest.php`, `tests/Feature/LayoutTest.php`

**Interfaces:**
- Consumes: `Tender` scopes/helpers (Task 5), enums, `Money`
- Produces:
  - `TenderListQuery::build(TenderStatus $status, User $viewer, array $filters): Builder` — filter keys `search`, `mine`, `mode`, `pic`, `category`, `from`, `to`
  - Livewire `TenderList` at route `tenders.index` (`/tenders/{list}`)
  - Layout `layouts.app` (`{{ $slot }}`, `$title`), containing `<livewire:notification-bell />` slot area (an HTML comment placeholder `{{-- bell --}}` here; Task 13 inserts the component)
  - Blade components `<x-status-badge :status="..."/>`, `<x-avatar :user="..."/>`
  - Tailwind colour utilities from tokens: `bg-body`, `bg-surface`, `bg-canvas`, `bg-subtle`, `border-line`, `text-ink`, `text-muted`, `bg-hover`, `bg-chip`, `text-chip-ink`, `bg-chip-hover`, `bg-accent`, `text-accent-ink`, `bg-accent-tint`, `bg-good-bg`/`text-good-ink`, `bg-warn-bg`/`text-warn-ink`, `bg-bad-bg`/`text-bad-ink`, `bg-info-bg`/`text-info-ink`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Queries/TenderListQueryTest.php`:

```php
<?php

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, User};
use App\Queries\TenderListQuery;

function listOf(TenderStatus $status, array $filters = [], ?User $viewer = null): array
{
    return TenderListQuery::build($status, $viewer ?? User::factory()->create(), $filters)
        ->pluck('wo_number')->all();
}

it('shows only the requested status, soonest closing first for In Progress', function () {
    Tender::factory()->create(['wo_number' => 'A', 'closing_date' => '2026-12-01']);
    Tender::factory()->create(['wo_number' => 'B', 'closing_date' => '2026-11-01']);
    Tender::factory()->status(TenderStatus::Done)->create(['wo_number' => 'C']);

    expect(listOf(TenderStatus::InProgress))->toBe(['B', 'A']);
});

it('sorts closed lists latest closing first', function () {
    Tender::factory()->status(TenderStatus::Done)->create(['wo_number' => 'A', 'closing_date' => '2026-01-01']);
    Tender::factory()->status(TenderStatus::Done)->create(['wo_number' => 'B', 'closing_date' => '2026-02-01']);

    expect(listOf(TenderStatus::Done))->toBe(['B', 'A']);
});

it('searches WO number, code, title and client, treating % and _ literally', function () {
    Tender::factory()->create(['wo_number' => 'W1', 'title' => 'SEWAAN KOMPUTER RIBA']);
    Tender::factory()->create(['wo_number' => 'W2', 'client' => 'PUSAT DARAH NEGARA']);
    Tender::factory()->create(['wo_number' => 'W3', 'tender_code' => 'SH250000000019233']);
    Tender::factory()->create(['wo_number' => 'W4', 'title' => '100% UPTIME']);

    expect(listOf(TenderStatus::InProgress, ['search' => 'riba']))->toBe(['W1'])
        ->and(listOf(TenderStatus::InProgress, ['search' => 'darah']))->toBe(['W2'])
        ->and(listOf(TenderStatus::InProgress, ['search' => 'SH2500']))->toBe(['W3'])
        ->and(listOf(TenderStatus::InProgress, ['search' => 'W2']))->toBe(['W2'])
        ->and(listOf(TenderStatus::InProgress, ['search' => '%']))->toBe(['W4']);
});

it('filters to my tenders as PIC or opportunity owner', function () {
    $me = User::factory()->create();
    Tender::factory()->create(['wo_number' => 'PIC', 'pic_id' => $me->id]);
    Tender::factory()->create(['wo_number' => 'OO', 'owner_id' => $me->id]);
    Tender::factory()->create(['wo_number' => 'OTHER']);

    expect(listOf(TenderStatus::InProgress, ['mine' => true], $me))->toEqualCanonicalizing(['PIC', 'OO']);
});

it('filters by mode, PIC, category and closing range', function () {
    $pic = User::factory()->create();
    Tender::factory()->create(['wo_number' => 'X', 'mode' => TenderMode::NonEp, 'pic_id' => $pic->id,
        'category' => TenderCategory::CivilWorks, 'closing_date' => '2026-10-10']);
    Tender::factory()->create(['wo_number' => 'Y', 'closing_date' => '2026-12-10']);

    expect(listOf(TenderStatus::InProgress, ['mode' => 'NON_EP']))->toBe(['X'])
        ->and(listOf(TenderStatus::InProgress, ['pic' => (string) $pic->id]))->toBe(['X'])
        ->and(listOf(TenderStatus::InProgress, ['category' => 'Civil Works']))->toBe(['X'])
        ->and(listOf(TenderStatus::InProgress, ['from' => '2026-11-01', 'to' => '2026-12-31']))->toBe(['Y']);
});

it('ignores filter values that are not valid', function () {
    Tender::factory()->create(['wo_number' => 'Z']);

    expect(listOf(TenderStatus::InProgress, ['mode' => 'BOGUS', 'category' => 'nope', 'from' => 'garbage', 'pic' => 'abc']))
        ->toBe(['Z']);
});
```

`tests/Feature/Livewire/TenderListTest.php`:

```php
<?php

use App\Enums\TenderStatus;
use App\Livewire\TenderList;
use App\Models\{Tender, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders each list at its URL', function (string $slug, string $title) {
    $this->get("/tenders/{$slug}")->assertOk()->assertSee($title);
})->with([
    ['in-progress', 'In Progress Tenders'],
    ['done', 'Done Tenders'],
    ['awarded', 'Awarded Tenders'],
    ['lost', 'Lost Tenders'],
]);

it('404s an unknown list', function () {
    $this->get('/tenders/bogus')->assertNotFound();
});

it('shows list-specific columns', function () {
    Tender::factory()->status(TenderStatus::Lost)->create([
        'estimated_value_sen' => 40275400, 'submitted_price_sen' => 52828500, 'winning_price_sen' => 86617900,
        'was_cancelled' => true,
    ]);

    Livewire::test(TenderList::class, ['list' => 'lost'])
        ->assertSee('Win Variant')
        ->assertSee('RM 866,179.00')
        ->assertSee('215.1%')
        ->assertSee('Cancelled')
        ->assertDontSee('Briefing');

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSee('Briefing')->assertSee('Register Tender');
});

it('paginates ten per page', function () {
    Tender::factory()->count(12)->create();

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertSee('Showing 1–10 of 12');
});

it('highlights tenders closing soon and overdue', function () {
    Tender::factory()->create(['wo_number' => 'SOON-1', 'closing_date' => now('Asia/Kuala_Lumpur')->addDays(2)->toDateString()]);
    Tender::factory()->create(['wo_number' => 'LATE-1', 'closing_date' => now('Asia/Kuala_Lumpur')->subDay()->toDateString()]);

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->assertSeeHtml('data-closing="soon"')
        ->assertSeeHtml('data-closing="overdue"');
});

it('resets to page 1 when the search changes', function () {
    Tender::factory()->count(12)->create();

    Livewire::test(TenderList::class, ['list' => 'in-progress'])
        ->call('gotoPage', 2)
        ->set('search', 'x')
        ->assertSet('paginators.page', 1);
});
```

`tests/Feature/LayoutTest.php`:

```php
<?php

use App\Enums\TenderStatus;
use App\Models\{Tender, User};

it('shows the sidebar with live counts and the signed-in user', function () {
    $user = User::factory()->create(['name' => 'Siti Aisyah']);
    Tender::factory()->count(2)->create();
    Tender::factory()->status(TenderStatus::Lost)->create();

    $this->actingAs($user)->get('/tenders/in-progress')
        ->assertSeeInOrder(['In Progress', '2'])
        ->assertSeeInOrder(['Lost', '1'])
        ->assertSee('Siti Aisyah')
        ->assertSee('Staff')
        ->assertDontSee('Quotation')
        ->assertDontSee('Dashboard');
});

it('shows Manage Users only to admins', function () {
    $this->actingAs(User::factory()->create())->get('/tenders/in-progress')->assertDontSee('Manage Users');
    $this->actingAs(User::factory()->admin()->create())->get('/tenders/in-progress')->assertSee('Manage Users');
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Queries tests/Feature/Livewire tests/Feature/LayoutTest.php`
Expected: FAIL — `TenderListQuery` not found; placeholder route returns plain text.

- [ ] **Step 3: Styles — replace `resources/css/app.css`**

```css
@import 'tailwindcss';

@source '../views';
@source '../../app/Livewire';

@custom-variant dark (&:where(.dark, .dark *));

:root {
    --body: #E7E6E2; --surface: #FFFFFF; --canvas: #F5F4FA; --subtle: #FAFAFB;
    --line: #EDEAF5; --hover: #F3F1FB;
    --ink: #1E1B2E; --muted: #6B7280;
    --chip: #1F1E1C; --chip-hover: #333230; --chip-ink: #FFFFFF;
    --accent: #8BE36B; --accent-ink: #16330B; --accent-tint: #EFF9E8;
    --good-bg: #E6F7EA; --good-ink: #18794E; --warn-bg: #FDF0E3; --warn-ink: #A35A12;
    --bad-bg: #FDECEA; --bad-ink: #C0342B; --info-bg: #E8F0FD; --info-ink: #2F6FD0;
    color-scheme: light;
}

.dark {
    --body: #121210; --surface: #1B1A18; --canvas: #141312; --subtle: #201F1D;
    --line: #2A2926; --hover: #2A2926;
    --ink: #F5F4F0; --muted: #96958D;
    --chip: #2E2D29; --chip-hover: #3A3934; --chip-ink: #F5F4F0;
    --accent: #8BE36B; --accent-ink: #16330B; --accent-tint: #1E2A18;
    --good-bg: #17301F; --good-ink: #6FD39A; --warn-bg: #33240F; --warn-ink: #EDA75C;
    --bad-bg: #351A17; --bad-ink: #F28C81; --info-bg: #1B2A3F; --info-ink: #7FAAE8;
    color-scheme: dark;
}

@theme inline {
    --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    --color-body: var(--body);
    --color-surface: var(--surface);
    --color-canvas: var(--canvas);
    --color-subtle: var(--subtle);
    --color-line: var(--line);
    --color-hover: var(--hover);
    --color-ink: var(--ink);
    --color-muted: var(--muted);
    --color-chip: var(--chip);
    --color-chip-hover: var(--chip-hover);
    --color-chip-ink: var(--chip-ink);
    --color-accent: var(--accent);
    --color-accent-ink: var(--accent-ink);
    --color-accent-tint: var(--accent-tint);
    --color-good-bg: var(--good-bg);
    --color-good-ink: var(--good-ink);
    --color-warn-bg: var(--warn-bg);
    --color-warn-ink: var(--warn-ink);
    --color-bad-bg: var(--bad-bg);
    --color-bad-ink: var(--bad-ink);
    --color-info-bg: var(--info-bg);
    --color-info-ink: var(--info-ink);
}

[x-cloak] { display: none !important; }
```

- [ ] **Step 4: Query class**

`app/Queries/TenderListQuery.php`:

```php
<?php

namespace App\Queries;

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, User};
use Illuminate\Database\Eloquent\Builder;

final class TenderListQuery
{
    public static function build(TenderStatus $status, User $viewer, array $filters): Builder
    {
        $query = Tender::query()
            ->where('status', $status)
            ->with(['pic', 'owner'])
            ->withDocumentCounts();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn (Builder $q) => $q
                ->where('wo_number', 'like', $like)
                ->orWhere('tender_code', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('client', 'like', $like));
        }

        if (! empty($filters['mine'])) {
            $query->where(fn (Builder $q) => $q->where('pic_id', $viewer->id)->orWhere('owner_id', $viewer->id));
        }
        if ($mode = TenderMode::tryFrom((string) ($filters['mode'] ?? ''))) {
            $query->where('mode', $mode);
        }
        if (ctype_digit((string) ($filters['pic'] ?? ''))) {
            $query->where('pic_id', (int) $filters['pic']);
        }
        if ($category = TenderCategory::tryFrom((string) ($filters['category'] ?? ''))) {
            $query->where('category', $category);
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = (string) ($filters[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $query->where('closing_date', $operator, $date);
            }
        }

        $direction = $status === TenderStatus::InProgress ? 'asc' : 'desc';

        return $query->orderBy('closing_date', $direction)->orderBy('id', $direction);
    }
}
```

- [ ] **Step 5: Sidebar composer and registration**

`app/View/Composers/SidebarComposer.php`:

```php
<?php

namespace App\View\Composers;

use App\Enums\TenderStatus;
use App\Models\Tender;
use Illuminate\View\View;

class SidebarComposer
{
    public function compose(View $view): void
    {
        $counts = Tender::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $view->with('counts', collect(TenderStatus::cases())
            ->mapWithKeys(fn (TenderStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]));
    }
}
```

In `AppServiceProvider::boot()` add:

```php
        \Illuminate\Support\Facades\View::composer('layouts.partials.sidebar', \App\View\Composers\SidebarComposer::class);
```

- [ ] **Step 6: Layout, sidebar and small components**

`resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CMT Tender Hub' }} · CMT Tender Hub</title>
    <script>
        if (localStorage.theme === 'dark' || (!localStorage.theme && matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-body font-sans text-ink antialiased" x-data="{ drawer: false }">
<div class="flex min-h-screen">
    {{-- Desktop sidebar --}}
    <aside class="hidden w-60 shrink-0 border-r border-line bg-surface md:block">
        @include('layouts.partials.sidebar')
    </aside>

    {{-- Phone drawer --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-40 md:hidden">
        <div class="absolute inset-0 bg-black/40" @click="drawer = false"></div>
        <aside class="absolute inset-y-0 left-0 w-64 bg-surface shadow-xl" @click.outside="drawer = false">
            @include('layouts.partials.sidebar')
        </aside>
    </div>

    <main class="min-w-0 flex-1 bg-canvas">
        <nav class="flex items-center justify-between border-b border-line bg-surface px-4 py-3">
            <button type="button" class="rounded-lg p-2 hover:bg-hover md:hidden" @click="drawer = true" aria-label="Open menu">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" aria-label="Toggle theme" class="rounded-lg p-2 hover:bg-hover"
                        x-data
                        @click="const d = document.documentElement.classList.toggle('dark'); localStorage.theme = d ? 'dark' : 'light'">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                </button>
                {{-- bell --}}
            </div>
        </nav>
        <div class="p-4 md:p-6">
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
```

`resources/views/layouts/partials/sidebar.blade.php`:

```blade
@php
    $item = fn (string $route, array $params, string $label, ?int $count = null) => [
        'href' => route($route, $params), 'label' => $label, 'count' => $count,
        'active' => url()->current() === route($route, $params),
    ];
    $groups = [
        'Operations' => [$item('tenders.index', ['in-progress'], 'In Progress', $counts['in_progress'])],
        'Pipeline' => [
            $item('tenders.index', ['done'], 'Done', $counts['done']),
            $item('tenders.index', ['awarded'], 'Awarded', $counts['awarded']),
            $item('tenders.index', ['lost'], 'Lost', $counts['lost']),
        ],
    ];
@endphp
<div class="flex h-full flex-col p-4">
    <a href="{{ route('tenders.index', 'in-progress') }}" class="mb-6 flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent font-bold text-accent-ink">T</span>
        <span class="font-semibold">TenderHub</span>
    </a>

    <nav class="flex-1 space-y-5">
        @foreach ($groups as $heading => $items)
            <div>
                <p class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-muted">{{ $heading }}</p>
                @foreach ($items as $i)
                    <a href="{{ $i['href'] }}" @class([
                        'flex items-center justify-between rounded-lg px-2 py-1.5 text-sm',
                        'bg-accent-tint font-medium' => $i['active'],
                        'hover:bg-hover' => ! $i['active'],
                    ])>
                        <span>{{ $i['label'] }}</span>
                        <span class="rounded-full bg-subtle px-2 text-xs text-muted">{{ $i['count'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach

        <div>
            <p class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-muted">Account</p>
            <a href="{{ route('settings') }}" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-hover">Settings</a>
            @can('manage-users')
                <a href="{{ route('users.index') }}" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-hover">Manage Users</a>
            @endcan
        </div>
    </nav>

    <div class="mt-4 flex items-center gap-2 border-t border-line pt-4">
        <x-avatar :user="auth()->user()" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
            <p class="text-xs text-muted">{{ auth()->user()->role->label() }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg p-2 text-muted hover:bg-hover hover:text-ink" title="Log out" aria-label="Log out">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </form>
    </div>
</div>
```

Note: `settings` and `users.index` routes are added in this task as placeholders below and become real screens in Task 14.

`resources/views/components/avatar.blade.php`:

```blade
@props(['user'])
@if ($user)
    <span {{ $attributes->merge(['class' => 'grid h-7 w-7 shrink-0 place-items-center rounded-full bg-accent text-[11px] font-semibold text-accent-ink']) }}
          title="{{ $user->name }}">{{ $user->initials() }}</span>
@endif
```

`resources/views/components/status-badge.blade.php`:

```blade
@props(['status'])
@php
    $classes = match ($status) {
        \App\Enums\TenderStatus::InProgress => 'bg-info-bg text-info-ink',
        \App\Enums\TenderStatus::Done => 'bg-subtle text-muted',
        \App\Enums\TenderStatus::Awarded => 'bg-good-bg text-good-ink',
        \App\Enums\TenderStatus::Lost => 'bg-bad-bg text-bad-ink',
    };
@endphp
<span {{ $attributes->merge(['class' => "rounded-full px-2 py-0.5 text-xs font-medium {$classes}"]) }}>{{ $status->label() }}</span>
```

- [ ] **Step 7: The list component**

`app/Livewire/TenderList.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\User;
use App\Queries\TenderListQuery;
use Livewire\Attributes\{Layout, Url};
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TenderList extends Component
{
    use WithPagination;

    public string $list = 'in-progress';

    #[Url] public string $search = '';
    #[Url] public bool $mine = false;
    #[Url] public string $mode = '';
    #[Url] public string $pic = '';
    #[Url] public string $category = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';

    public function mount(string $list): void
    {
        $this->list = $list;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'mine', 'mode', 'pic', 'category', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'mine', 'mode', 'pic', 'category', 'from', 'to']);
        $this->resetPage();
    }

    public function render()
    {
        $status = TenderStatus::fromSlug($this->list);
        $filters = ['search' => $this->search, 'mine' => $this->mine, 'mode' => $this->mode,
            'pic' => $this->pic, 'category' => $this->category, 'from' => $this->from, 'to' => $this->to];

        return view('livewire.tender-list', [
            'status' => $status,
            'tenders' => TenderListQuery::build($status, auth()->user(), $filters)->paginate(10),
            'people' => User::orderBy('name')->get(['id', 'name']),
            'modes' => TenderMode::cases(),
            'categories' => TenderCategory::cases(),
        ])->title($status->listTitle());
    }
}
```

`resources/views/livewire/tender-list.blade.php`:

```blade
@php
    use App\Enums\TenderStatus;
    use App\Support\Money;
    $isOpen = $status === TenderStatus::InProgress;
@endphp
<div class="space-y-4">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ $status->listTitle() }}</h1>
            <p class="text-sm text-muted">{{ $status->listSubtitle() }}</p>
        </div>
        @if ($isOpen)
            <button type="button" wire:click="$dispatch('open-register-tender')"
                    class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">
                Register Tender
            </button>
        @endif
    </header>

    <section class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-3 text-sm">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search WO, code, title, client"
               class="min-w-48 flex-1 rounded-lg border border-line bg-surface px-3 py-1.5">
        <label class="flex items-center gap-1.5"><input type="checkbox" wire:model.live="mine"> My tenders</label>
        <select wire:model.live="mode" class="rounded-lg border border-line bg-surface px-2 py-1.5" aria-label="Mode">
            <option value="">All modes</option>
            @foreach ($modes as $m) <option value="{{ $m->value }}">{{ $m->label() }}</option> @endforeach
        </select>
        <select wire:model.live="pic" class="rounded-lg border border-line bg-surface px-2 py-1.5" aria-label="PIC">
            <option value="">All PICs</option>
            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
        </select>
        <select wire:model.live="category" class="rounded-lg border border-line bg-surface px-2 py-1.5" aria-label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $c) <option value="{{ $c->value }}">{{ $c->value }}</option> @endforeach
        </select>
        <label class="flex items-center gap-1">Closing <input type="date" wire:model.live="from" class="rounded-lg border border-line bg-surface px-2 py-1"></label>
        <label class="flex items-center gap-1">to <input type="date" wire:model.live="to" class="rounded-lg border border-line bg-surface px-2 py-1"></label>
        <button type="button" wire:click="clearFilters" class="text-muted hover:text-ink">Clear</button>
    </section>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-3 py-2">WO Number</th>
                    <th class="px-3 py-2">Tender</th>
                    <th class="px-3 py-2">Agency</th>
                    <th class="px-3 py-2">Assigned To</th>
                    <th class="px-3 py-2">Deadline</th>
                    @if ($isOpen) <th class="px-3 py-2">Briefing</th> @endif
                    <th class="px-3 py-2 text-right">Est. Value</th>
                    @if ($status === TenderStatus::Done)
                        <th class="px-3 py-2 text-right">Submit Price</th>
                        <th class="px-3 py-2 text-right">Company Variant</th>
                    @elseif ($status === TenderStatus::Lost)
                        <th class="px-3 py-2 text-right">Submitted Price</th>
                        <th class="px-3 py-2 text-right">Win Price</th>
                        <th class="px-3 py-2 text-right">Win Variant</th>
                    @else
                        <th class="px-3 py-2 text-right">Documents</th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse ($tenders as $t)
                @php $closing = $t->closingState(); @endphp
                <tr wire:key="tender-{{ $t->id }}" data-closing="{{ $closing }}"
                    onclick="window.location='{{ route('tenders.show', $t) }}'"
                    @class([
                        'cursor-pointer border-t border-line align-top hover:bg-hover',
                        'bg-warn-bg/50' => $closing === 'soon',
                        'bg-bad-bg/60' => $closing === 'overdue',
                    ])>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="{{ route('tenders.show', $t) }}" class="font-medium hover:underline">{{ $t->wo_number }}</a>
                        <div class="text-xs text-muted">{{ $t->wo_date->format('d M Y') }}</div>
                    </td>
                    <td class="max-w-md px-3 py-2">
                        <div class="text-xs text-muted">{{ $t->tender_code }}</div>
                        <div class="line-clamp-2">{{ $t->title }}</div>
                        @if ($t->was_cancelled) <span class="mt-1 inline-block rounded bg-bad-bg px-1.5 text-xs text-bad-ink">Cancelled</span> @endif
                    </td>
                    <td class="px-3 py-2">{{ $t->client }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2"><x-avatar :user="$t->pic" /> <span>{{ $t->pic->name }}</span></div>
                    </td>
                    <td @class(['px-3 py-2 whitespace-nowrap', 'font-semibold text-bad-ink' => $closing === 'overdue'])>
                        {{ $t->closing_date->format('d M Y') }}
                    </td>
                    @if ($isOpen)
                        <td class="px-3 py-2">
                            <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-good-bg text-good-ink' => $t->has_briefing, 'bg-bad-bg text-bad-ink' => ! $t->has_briefing])>
                                {{ $t->has_briefing ? 'Yes' : 'No' }}
                            </span>
                        </td>
                    @endif
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->estimated_value_sen) }}</td>
                    @if ($status === TenderStatus::Done)
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->submitted_price_sen) }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->companyVariant() ?? '—' }}</td>
                    @elseif ($status === TenderStatus::Lost)
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->submitted_price_sen) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->winning_price_sen) }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->winVariant() ?? '—' }}</td>
                    @else
                        <td class="px-3 py-2 text-right">{{ $t->documentPercent() }}%</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="10" class="px-3 py-10 text-center text-muted">No tenders match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <footer class="flex items-center justify-between text-sm text-muted">
        <span>
            @if ($tenders->total())
                Showing {{ $tenders->firstItem() }}–{{ $tenders->lastItem() }} of {{ $tenders->total() }}
            @endif
        </span>
        {{ $tenders->links() }}
    </footer>

    @if ($isOpen)
        {{-- register modal --}}
    @endif
</div>
```

- [ ] **Step 8: Routes — replace the placeholder in `routes/web.php`**

Inside the `auth`/`active` group, replace the placeholder `tenders.index` route with:

```php
    Route::get('/tenders/{list}', \App\Livewire\TenderList::class)
        ->whereIn('list', ['in-progress', 'done', 'awarded', 'lost'])
        ->name('tenders.index');
    // Placeholders until Tasks 12 and 14 build these screens:
    Route::get('/tenders/{tender}', fn (\App\Models\Tender $tender) => $tender->wo_number)
        ->whereNumber('tender')->name('tenders.show');
    Route::get('/settings', fn () => 'Settings coming soon')->name('settings');
    Route::get('/settings/users', fn () => 'Manage users coming soon')
        ->middleware('can:manage-users')->name('users.index');
```

- [ ] **Step 9: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 10: Look at it in the browser**

`docker compose exec -T app php artisan migrate`, then create a user: `docker compose exec -T app php artisan tinker --execute="App\Models\User::factory()->admin()->create(['email'=>'admin@cmt.test','password'=>'TenderHub-dev-2026']); App\Models\Tender::factory()->count(12)->create();"`. Follow `.claude/skills/e2e-playwright-verification/SKILL.md` if present in tms-v2 (otherwise use the Playwright MCP tools directly): log in at `http://localhost:8080`, check the four lists, filters, pagination, light/dark toggle, and the phone drawer at 375px width. Fix anything broken before committing.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: app layout with sidebar counts, theme toggle, phone drawer and tender lists

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11: Register Tender form (shared tender form object)

**Files:**
- Create: `app/Livewire/Forms/TenderForm.php`, `app/Livewire/RegisterTenderModal.php`, `resources/views/livewire/register-tender-modal.blade.php`, `resources/views/livewire/partials/tender-fields.blade.php`, `config/tenderhub.php`, `resources/data/ministries.json`
- Modify: `resources/views/livewire/tender-list.blade.php` (replace `{{-- register modal --}}`)
- Test: `tests/Feature/Livewire/RegisterTenderModalTest.php`

**Interfaces:**
- Consumes: `RegisterTender::handle`, `MoneyAmount`, `Money`
- Produces:
  - `TenderForm` (Livewire Form) with string/bool properties `mode, type, category, tenderCode, title, client, scope, picId, ownerId, publishDate, closingDate, hasBriefing, briefingDate, estimatedValue`; `rules(): array`; `fillFrom(Tender $t): void`; `toData(): array` (keys = `RegisterTender::FIELDS`)
  - Partial `livewire.partials.tender-fields` rendering all fields bound to `form.*` (expects `$people`, `$ministries`)
  - `config('tenderhub.ministries')` array; `config('tenderhub.seed_password')`
  - Livewire event `open-register-tender` opens the modal

- [ ] **Step 1: Copy the ministry list and write config**

```bash
mkdir -p resources/data
cp /c/Projects/tms-v2/docs/superpowers/plans/assets/ministries.json resources/data/ministries.json
```

`config/tenderhub.php`:

```php
<?php

return [
    'ministries' => json_decode(file_get_contents(resource_path('data/ministries.json')), true),
    'seed_password' => env('SEED_USER_PASSWORD', 'TenderHub-dev-2026'),
];
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Livewire/RegisterTenderModalTest.php`:

```php
<?php

use App\Livewire\RegisterTenderModal;
use App\Models\{Tender, User};
use Livewire\Livewire;

function filledRegister(User $pic)
{
    return Livewire::test(RegisterTenderModal::class)
        ->call('show')
        ->set('form.tenderCode', 'QT260000000041127')
        ->set('form.title', 'PERKHIDMATAN PEMBANGUNAN SISTEM')
        ->set('form.client', 'KEMENTERIAN KESIHATAN')
        ->set('form.picId', (string) $pic->id)
        ->set('form.closingDate', '2026-10-15')
        ->set('form.estimatedValue', 'RM 162,006.10');
}

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('registers a tender and opens it', function () {
    $pic = User::factory()->create();

    $component = filledRegister($pic)->call('save');

    $tender = Tender::sole();
    $component->assertRedirect(route('tenders.show', $tender));
    expect($tender->estimated_value_sen)->toBe(16200610)
        ->and($tender->pic_id)->toBe($pic->id)
        ->and($tender->documents)->toHaveCount(5);
});

it('requires the essentials', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')->call('save')
        ->assertHasErrors(['form.tenderCode', 'form.title', 'form.client', 'form.picId', 'form.closingDate']);
    expect(Tender::count())->toBe(0);
});

it('rejects a closing date before the publish date, bad money and missing briefing date', function () {
    filledRegister(User::factory()->create())
        ->set('form.publishDate', '2026-10-20')
        ->set('form.estimatedValue', '-5')
        ->set('form.hasBriefing', true)
        ->call('save')
        ->assertHasErrors(['form.closingDate', 'form.estimatedValue', 'form.briefingDate']);
});

it('refuses a deactivated PIC', function () {
    filledRegister(User::factory()->inactive()->create())->call('save')->assertHasErrors('form.picId');
});

it('warns about a duplicate tender code and registers only after confirmation', function () {
    Tender::factory()->create(['tender_code' => 'QT260000000041127', 'wo_number' => '200-01012026-009']);

    $component = filledRegister(User::factory()->create())->call('save')
        ->assertSee('200-01012026-009')
        ->assertSet('confirmDuplicate', true);
    expect(Tender::count())->toBe(1);

    $component->call('save');
    expect(Tender::count())->toBe(2);
});

it('drops the duplicate confirmation when the code is edited', function () {
    Tender::factory()->create(['tender_code' => 'QT260000000041127']);

    filledRegister(User::factory()->create())->call('save')
        ->set('form.tenderCode', 'QT999')
        ->assertSet('confirmDuplicate', false);
});
```

- [ ] **Step 3: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Livewire/RegisterTenderModalTest.php`
Expected: FAIL — `RegisterTenderModal` not found.

- [ ] **Step 4: Implement the form object**

`app/Livewire/Forms/TenderForm.php`:

```php
<?php

namespace App\Livewire\Forms;

use App\Enums\{TenderCategory, TenderMode, TenderType};
use App\Models\Tender;
use App\Rules\MoneyAmount;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TenderForm extends Form
{
    public string $mode = 'EP';
    public string $type = 'TENDER';
    public string $category = 'General';
    public string $tenderCode = '';
    public string $title = '';
    public string $client = '';
    public string $scope = '';
    public string $picId = '';
    public string $ownerId = '';
    public string $publishDate = '';
    public string $closingDate = '';
    public bool $hasBriefing = false;
    public string $briefingDate = '';
    public string $estimatedValue = '';

    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->where('is_active', true);

        return [
            'mode' => ['required', Rule::enum(TenderMode::class)],
            'type' => ['required', Rule::enum(TenderType::class)],
            'category' => ['required', Rule::enum(TenderCategory::class)],
            'tenderCode' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:2000'],
            'client' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'picId' => ['required', $activeUser],
            'ownerId' => ['nullable', $activeUser],
            'publishDate' => ['nullable', 'date_format:Y-m-d'],
            'closingDate' => ['required', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if ($this->publishDate !== '' && $value < $this->publishDate) {
                    $fail('The closing date cannot be before the publish date.');
                }
            }],
            'hasBriefing' => ['boolean'],
            'briefingDate' => $this->hasBriefing ? ['required', 'date_format:Y-m-d'] : ['nullable'],
            'estimatedValue' => ['nullable', new MoneyAmount],
        ];
    }

    public function fillFrom(Tender $t): void
    {
        $this->mode = $t->mode->value;
        $this->type = $t->type->value;
        $this->category = $t->category->value;
        $this->tenderCode = $t->tender_code;
        $this->title = $t->title;
        $this->client = $t->client;
        $this->scope = (string) $t->scope;
        $this->picId = (string) $t->pic_id;
        $this->ownerId = (string) ($t->owner_id ?? '');
        $this->publishDate = (string) $t->publish_date?->toDateString();
        $this->closingDate = $t->closing_date->toDateString();
        $this->hasBriefing = $t->has_briefing;
        $this->briefingDate = (string) $t->briefing_date?->toDateString();
        $this->estimatedValue = Money::toInput($t->estimated_value_sen);
    }

    /** Call only after validate(). */
    public function toData(): array
    {
        $scope = trim($this->scope);

        return [
            'mode' => TenderMode::from($this->mode),
            'type' => TenderType::from($this->type),
            'category' => TenderCategory::from($this->category),
            'tender_code' => trim($this->tenderCode),
            'title' => trim($this->title),
            'client' => trim($this->client),
            'scope' => $scope === '' ? null : $scope,
            'pic_id' => (int) $this->picId,
            'owner_id' => $this->ownerId === '' ? null : (int) $this->ownerId,
            'publish_date' => $this->publishDate === '' ? null : $this->publishDate,
            'closing_date' => $this->closingDate,
            'has_briefing' => $this->hasBriefing,
            'briefing_date' => $this->hasBriefing ? $this->briefingDate : null,
            'estimated_value_sen' => Money::parse($this->estimatedValue),
        ];
    }
}
```

- [ ] **Step 5: Implement the modal component and views**

`app/Livewire/RegisterTenderModal.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Tenders\RegisterTender;
use App\Enums\{TenderCategory, TenderMode, TenderType};
use App\Livewire\Forms\TenderForm;
use App\Models\{Tender, User};
use Livewire\Attributes\On;
use Livewire\Component;

class RegisterTenderModal extends Component
{
    public TenderForm $form;
    public bool $open = false;
    public bool $confirmDuplicate = false;
    public array $duplicateWoNumbers = [];

    #[On('open-register-tender')]
    public function show(): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->confirmDuplicate = false;
        $this->duplicateWoNumbers = [];
        $this->open = true;
    }

    public function updated(string $property): void
    {
        if ($property === 'form.tenderCode') {
            $this->confirmDuplicate = false;
            $this->duplicateWoNumbers = [];
        }
    }

    public function save()
    {
        $this->form->validate();

        if (! $this->confirmDuplicate) {
            $existing = Tender::where('tender_code', trim($this->form->tenderCode))->pluck('wo_number')->all();
            if ($existing !== []) {
                $this->duplicateWoNumbers = $existing;
                $this->confirmDuplicate = true;

                return null;
            }
        }

        $tender = app(RegisterTender::class)->handle(auth()->user(), $this->form->toData());

        return $this->redirectRoute('tenders.show', $tender);
    }

    public function render()
    {
        return view('livewire.register-tender-modal', [
            'people' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ministries' => config('tenderhub.ministries'),
            'modes' => TenderMode::cases(),
            'types' => TenderType::cases(),
            'categories' => TenderCategory::cases(),
        ]);
    }
}
```

`resources/views/livewire/partials/tender-fields.blade.php`:

```blade
@php
    $input = 'mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm';
    $err = fn ($f) => $errors->first("form.$f");
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <label class="text-sm"><span class="text-muted">Mode</span>
        <select wire:model="form.mode" class="{{ $input }}">
            @foreach ($modes as $m) <option value="{{ $m->value }}">{{ $m->label() }}</option> @endforeach
        </select>
    </label>
    <label class="text-sm"><span class="text-muted">Type</span>
        <select wire:model="form.type" class="{{ $input }}">
            @foreach ($types as $t) <option value="{{ $t->value }}">{{ $t->label() }}</option> @endforeach
        </select>
    </label>
    <label class="text-sm"><span class="text-muted">Tender code *</span>
        <input wire:model.blur="form.tenderCode" class="{{ $input }}" placeholder="QT26…">
        @if ($e = $err('tenderCode')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Category</span>
        <select wire:model="form.category" class="{{ $input }}">
            @foreach ($categories as $c) <option value="{{ $c->value }}">{{ $c->value }}</option> @endforeach
        </select>
    </label>
    <label class="text-sm sm:col-span-2"><span class="text-muted">Title *</span>
        <textarea wire:model="form.title" rows="2" class="{{ $input }}"></textarea>
        @if ($e = $err('title')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm sm:col-span-2"><span class="text-muted">Client / agency * (pick a ministry or type any name)</span>
        <input wire:model="form.client" list="ministry-list" class="{{ $input }}">
        <datalist id="ministry-list">
            @foreach ($ministries as $m) <option value="{{ $m }}"></option> @endforeach
        </datalist>
        @if ($e = $err('client')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Person in charge (PIC) *</span>
        <select wire:model="form.picId" class="{{ $input }}">
            <option value="">Choose…</option>
            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
        </select>
        @if ($e = $err('picId')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Opportunity owner</span>
        <select wire:model="form.ownerId" class="{{ $input }}">
            <option value="">None</option>
            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
        </select>
        @if ($e = $err('ownerId')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Publish date</span>
        <input type="date" wire:model="form.publishDate" class="{{ $input }}">
        @if ($e = $err('publishDate')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm"><span class="text-muted">Closing date *</span>
        <input type="date" wire:model="form.closingDate" class="{{ $input }}">
        @if ($e = $err('closingDate')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <div class="text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.hasBriefing"> There is a briefing</label>
        @if ($form->hasBriefing)
            <input type="date" wire:model="form.briefingDate" class="{{ $input }}" aria-label="Briefing date">
            @if ($e = $err('briefingDate')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
        @endif
    </div>
    <label class="text-sm"><span class="text-muted">Estimated value (RM)</span>
        <input wire:model="form.estimatedValue" inputmode="decimal" class="{{ $input }}" placeholder="e.g. 162,006.10">
        @if ($e = $err('estimatedValue')) <span class="text-xs text-bad-ink">{{ $e }}</span> @endif
    </label>
    <label class="text-sm sm:col-span-2"><span class="text-muted">Scope of work</span>
        <textarea wire:model="form.scope" rows="3" class="{{ $input }}"></textarea>
    </label>
</div>
```

`resources/views/livewire/register-tender-modal.blade.php`:

```blade
<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <form wire:submit="save" class="my-8 w-full max-w-2xl space-y-4 rounded-2xl bg-surface p-6 shadow-xl">
                <header class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Register Tender</h2>
                    <button type="button" wire:click="$set('open', false)" class="text-muted hover:text-ink" aria-label="Close">✕</button>
                </header>

                @include('livewire.partials.tender-fields')

                @if ($confirmDuplicate)
                    <div class="rounded-lg bg-warn-bg p-3 text-sm text-warn-ink">
                        This tender code is already registered as {{ implode(', ', $duplicateWoNumbers) }}.
                        Click <strong>Register anyway</strong> if this is a separate bid.
                    </div>
                @endif

                <footer class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('open', false)" class="rounded-lg px-4 py-2 text-sm hover:bg-hover">Cancel</button>
                    <button type="submit" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">
                        {{ $confirmDuplicate ? 'Register anyway' : 'Register' }}
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
```

In `resources/views/livewire/tender-list.blade.php` replace `{{-- register modal --}}` with `<livewire:register-tender-modal />`.

- [ ] **Step 6: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 7: Look at it in the browser**

Open In Progress, click Register Tender, submit empty (errors appear), fill it in, check the ministry suggestions, submit, confirm you land on the (placeholder) tender page and the In Progress count went up. Try a duplicate code.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: Register Tender form with duplicate-code warning and ministry suggestions

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12: Tender detail screen — Overview, Documents, Activity and action buttons

**Files:**
- Create: `app/Livewire/TenderDetail.php`, `resources/views/livewire/tender-detail.blade.php`, `resources/views/livewire/tender-detail/overview.blade.php`, `resources/views/livewire/tender-detail/documents.blade.php`, `resources/views/livewire/tender-detail/activity.blade.php`, `resources/views/livewire/tender-detail/modals.blade.php`
- Modify: `routes/web.php` (replace `tenders.show` placeholder)
- Test: `tests/Feature/Livewire/TenderDetailTest.php`

**Interfaces:**
- Consumes: every action from Tasks 7–9, `TenderForm`, `MoneyAmount`, `Money`, exceptions
- Produces: Livewire `TenderDetail` at route `tenders.show` (`/tenders/{tender}`), public methods `startEdit`, `cancelEdit`, `save`, `openModal(string)`, `closeModal`, `markDone`, `cancelTender`, `markAwarded`, `markLost`, `reopen`, `toggleDocument(int)`, `addDocument`, `removeDocument(int)`; public props `version`, `tab`, `conflict`, `pendingDocuments`, `modal`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Livewire/TenderDetailTest.php`:

```php
<?php

use App\Actions\Tenders\UpdateTender;
use App\Enums\TenderStatus;
use App\Livewire\TenderDetail;
use App\Models\{Tender, TenderDocument, User};
use Livewire\Livewire;

function detailFixture(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'title' => 'SISTEM RONDAAN'], $attrs));
    foreach (TenderDocument::STANDARD as $i => $name) {
        TenderDocument::factory()->for($tender)->create(['name' => $name, 'position' => $i + 1]);
    }

    return [$pic, $tender];
}

it('shows the tender with its tabs', function () {
    [$pic, $tender] = detailFixture();

    $this->actingAs($pic)->get(route('tenders.show', $tender))
        ->assertOk()
        ->assertSee($tender->wo_number)
        ->assertSee('SISTEM RONDAAN')
        ->assertSee('Overview')->assertSee('Documents')->assertSee('Activity');
});

it('lets the PIC edit and save details', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('startEdit')
        ->set('form.title', 'NEW TITLE')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertSet('version', 2);

    expect($tender->fresh()->title)->toBe('NEW TITLE');
});

it('hides edit controls from staff who are not the PIC and forbids crafted calls', function () {
    [, $tender] = detailFixture();
    $other = User::factory()->create();

    Livewire::actingAs($other)->test(TenderDetail::class, ['tender' => $tender])
        ->assertDontSee('Edit details')
        ->assertDontSee('Mark Done')
        ->call('startEdit')->assertForbidden();

    Livewire::actingAs($other)->test(TenderDetail::class, ['tender' => $tender])
        ->call('toggleDocument', $tender->documents->first()->id)->assertForbidden();

    expect($tender->documents()->where('is_done', true)->count())->toBe(0);
});

it('shows a conflict message instead of overwriting a newer save', function () {
    [$pic, $tender] = detailFixture();
    $component = Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])->call('startEdit');

    app(UpdateTender::class)->handle(User::factory()->manager()->create(['name' => 'Ahmad Faizal']), $tender, 1, ['title' => 'THEIRS']);

    $component->set('form.title', 'MINE')->call('save')
        ->assertSee('This tender was changed by Ahmad Faizal');
    expect($tender->fresh()->title)->toBe('THEIRS');
});

it('blocks Mark Done with the list of unticked documents', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'done')
        ->assertSet('modal', null)
        ->assertSee('Bid Bond / Bank Guarantee')
        ->assertSee('still not ticked');
});

it('marks Done after all documents are ticked', function () {
    [$pic, $tender] = detailFixture();
    $tender->documents()->update(['is_done' => true]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'done')
        ->assertSet('modal', 'done')
        ->set('submittedPrice', '0')
        ->call('markDone')->assertHasErrors('submittedPrice')
        ->set('submittedPrice', '166,059.00')
        ->call('markDone')
        ->assertHasNoErrors();

    expect($tender->fresh()->status)->toBe(TenderStatus::Done)
        ->and($tender->fresh()->submitted_price_sen)->toBe(16605900);
});

it('ticks, adds and removes documents', function () {
    [$pic, $tender] = detailFixture();
    $first = $tender->documents->first();

    $c = Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('toggleDocument', $first->id)
        ->assertSee('1 / 5 done')
        ->set('newDocument', 'Surat Akuan')
        ->call('addDocument')
        ->assertSee('Surat Akuan')
        ->assertSee('1 / 6 done');

    $c->call('removeDocument', $first->id)->assertSee('0 / 5 done');
});

it('cancels with a required reason', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'cancel')
        ->call('cancelTender')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Not in our scope')
        ->call('cancelTender');

    expect($tender->fresh()->status)->toBe(TenderStatus::Lost)->and($tender->fresh()->was_cancelled)->toBeTrue();
});

it('marks a Done tender Awarded or Lost', function () {
    [$pic, $won] = detailFixture(['status' => TenderStatus::Done]);
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $won])->call('markAwarded');
    expect($won->fresh()->status)->toBe(TenderStatus::Awarded);

    [$pic2, $lost] = detailFixture(['status' => TenderStatus::Done]);
    Livewire::actingAs($pic2)->test(TenderDetail::class, ['tender' => $lost])
        ->call('openModal', 'lost')
        ->set('winningPrice', 'abc')->call('markLost')->assertHasErrors('winningPrice')
        ->set('winningPrice', '866,179')->set('lostReason', 'Lower bidder')->call('markLost');
    expect($lost->fresh()->status)->toBe(TenderStatus::Lost)->and($lost->fresh()->winning_price_sen)->toBe(86617900);
});

it('shows Reopen only to managers and admins', function () {
    [$pic, $tender] = detailFixture(['status' => TenderStatus::Awarded]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])->assertDontSee('Reopen')
        ->call('reopen')->assertForbidden();

    Livewire::actingAs(User::factory()->manager()->create())->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('Reopen')->call('reopen');
    expect($tender->fresh()->status)->toBe(TenderStatus::InProgress);
});

it('shows the activity log newest first', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('toggleDocument', $tender->documents->first()->id)
        ->set('tab', 'activity')
        ->assertSee('Ticked: Borang ISI (Tender Form)')
        ->assertSee('Siti Aisyah');
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Livewire/TenderDetailTest.php`
Expected: FAIL — `TenderDetail` not found.

- [ ] **Step 3: Implement the component**

`app/Livewire/TenderDetail.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Tenders\{AddDocument, CancelTender, MarkTenderAwarded, MarkTenderDone, MarkTenderLost, RemoveDocument, ReopenTender, ToggleDocument, UpdateTender};
use App\Enums\{TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Exceptions\{DocumentsIncomplete, InvalidTenderTransition, StaleTenderException};
use App\Livewire\Forms\TenderForm;
use App\Models\{Tender, User};
use App\Rules\MoneyAmount;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\{Layout, Url};
use Livewire\Component;

#[Layout('layouts.app')]
class TenderDetail extends Component
{
    public Tender $tender;
    public int $version;
    #[Url] public string $tab = 'overview';

    public TenderForm $form;
    public bool $editing = false;

    public ?string $modal = null;
    public ?string $conflict = null;
    public array $pendingDocuments = [];

    public string $submittedPrice = '';
    public string $cancelReason = '';
    public string $winningPrice = '';
    public string $lostReason = '';
    public string $newDocument = '';

    public function mount(Tender $tender): void
    {
        $this->tender = $tender;
        $this->version = $tender->version;
    }

    public function startEdit(): void
    {
        $this->authorize('update', $this->tender);
        $this->form->fillFrom($this->tender);
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    public function save(): void
    {
        $this->authorize('update', $this->tender);
        $this->form->validate();
        if ($this->apply(fn () => app(UpdateTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->form->toData()))) {
            $this->editing = false;
        }
    }

    public function openModal(string $name): void
    {
        $ability = $name === 'reopen' ? 'reopen' : 'update';
        $this->authorize($ability, $this->tender);
        $this->resetValidation();
        $this->pendingDocuments = [];

        if ($name === 'done') {
            $pending = $this->tender->documents()->where('is_done', false)->pluck('name')->all();
            if ($pending !== []) {
                $this->pendingDocuments = $pending;

                return;
            }
        }

        $this->modal = $name;
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    public function markDone(): void
    {
        $this->validate(['submittedPrice' => ['required', new MoneyAmount(mustBePositive: true)]]);
        $this->apply(fn () => app(MarkTenderDone::class)->handle(auth()->user(), $this->tender, $this->version, Money::parse($this->submittedPrice)));
    }

    public function cancelTender(): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:1000']]);
        $this->apply(fn () => app(CancelTender::class)->handle(auth()->user(), $this->tender, $this->version, $this->cancelReason));
    }

    public function markAwarded(): void
    {
        $this->apply(fn () => app(MarkTenderAwarded::class)->handle(auth()->user(), $this->tender, $this->version));
    }

    public function markLost(): void
    {
        $this->validate([
            'winningPrice' => ['nullable', new MoneyAmount],
            'lostReason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->apply(fn () => app(MarkTenderLost::class)->handle(
            auth()->user(), $this->tender, $this->version, Money::parse($this->winningPrice), $this->lostReason,
        ));
    }

    public function reopen(): void
    {
        $this->apply(fn () => app(ReopenTender::class)->handle(auth()->user(), $this->tender, $this->version));
    }

    public function toggleDocument(int $documentId): void
    {
        $this->apply(fn () => app(ToggleDocument::class)->handle(auth()->user(), $this->tender, $this->version, $documentId));
    }

    public function addDocument(): void
    {
        $this->validate(['newDocument' => ['required', 'string', 'max:255']]);
        if ($this->apply(fn () => app(AddDocument::class)->handle(auth()->user(), $this->tender, $this->version, $this->newDocument))) {
            $this->newDocument = '';
        }
    }

    public function removeDocument(int $documentId): void
    {
        $this->apply(fn () => app(RemoveDocument::class)->handle(auth()->user(), $this->tender, $this->version, $documentId));
    }

    /** Runs an action; on success refreshes local state. Returns false when the change was refused. */
    private function apply(callable $action): bool
    {
        try {
            $fresh = $action();
        } catch (StaleTenderException|InvalidTenderTransition $e) {
            $this->conflict = $e->getMessage();
            $this->modal = null;

            return false;
        } catch (DocumentsIncomplete $e) {
            $this->pendingDocuments = $e->pending;
            $this->modal = null;

            return false;
        }

        $this->tender = $fresh;
        $this->version = $fresh->version;
        $this->conflict = null;
        $this->modal = null;
        $this->pendingDocuments = [];
        $this->reset('submittedPrice', 'cancelReason', 'winningPrice', 'lostReason');

        return true;
    }

    public function render()
    {
        $documents = $this->tender->documents()->with('doneBy')->get();

        return view('livewire.tender-detail', [
            'documents' => $documents,
            'doneCount' => $documents->where('is_done', true)->count(),
            'activity' => $this->tender->activity()->with('user')->get(),
            'canEdit' => Gate::allows('update', $this->tender),
            'canReopen' => Gate::allows('reopen', $this->tender),
            'people' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ministries' => config('tenderhub.ministries'),
            'modes' => TenderMode::cases(),
            'types' => TenderType::cases(),
            'categories' => TenderCategory::cases(),
        ])->title($this->tender->wo_number);
    }
}
```

- [ ] **Step 4: Views**

`resources/views/livewire/tender-detail.blade.php`:

```blade
@php
    use App\Enums\TenderStatus;
    $status = $tender->status;
    $total = $documents->count();
    $pct = $total ? (int) round($doneCount * 100 / $total) : 0;
    $btn = 'rounded-lg px-3 py-1.5 text-sm font-medium';
@endphp
<div class="space-y-4">
    <a href="{{ route('tenders.index', $status->slug()) }}" class="text-sm text-muted hover:text-ink">← Back to list</a>

    @if ($conflict)
        <div class="flex items-center justify-between rounded-lg bg-warn-bg p-3 text-sm text-warn-ink" role="alert">
            <span>{{ $conflict }}</span>
            <a href="{{ route('tenders.show', $tender) }}" class="font-medium underline">Reload</a>
        </div>
    @endif

    @if ($pendingDocuments)
        <div class="rounded-lg bg-bad-bg p-3 text-sm text-bad-ink" role="alert">
            {{ count($pendingDocuments) }} document(s) still not ticked: {{ implode(', ', $pendingDocuments) }}.
            Tick every document before marking this tender as Done.
        </div>
    @endif

    <header class="rounded-xl border border-line bg-surface p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold">{{ $tender->wo_number }}</span>
            <x-status-badge :status="$status" />
            <span class="rounded-full bg-subtle px-2 py-0.5 text-xs">{{ $tender->mode->label() }}</span>
            <span class="rounded-full bg-subtle px-2 py-0.5 text-xs">Docs {{ $pct }}%</span>
            <div class="ml-auto flex flex-wrap gap-2">
                @if ($canEdit && $status === TenderStatus::InProgress)
                    <button wire:click="openModal('cancel')" class="{{ $btn }} border border-line hover:bg-hover">Cancel Tender</button>
                    <button wire:click="openModal('done')" class="{{ $btn }} bg-chip text-chip-ink hover:bg-chip-hover">Mark Done</button>
                @endif
                @if ($canEdit && $status === TenderStatus::Done)
                    <button wire:click="openModal('lost')" class="{{ $btn }} border border-line hover:bg-hover">Mark Lost</button>
                    <button wire:click="openModal('awarded')" class="{{ $btn }} bg-accent text-accent-ink">Mark Awarded</button>
                @endif
                @if ($canReopen && $status !== TenderStatus::InProgress)
                    <button wire:click="openModal('reopen')" class="{{ $btn }} border border-line hover:bg-hover">Reopen</button>
                @endif
            </div>
        </div>
        <h1 class="mt-3 text-lg font-semibold">{{ $tender->title }}</h1>
    </header>

    <nav class="flex gap-1 border-b border-line text-sm">
        @foreach (['overview' => 'Overview', 'documents' => 'Documents', 'activity' => 'Activity'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'px-3 py-2 -mb-px border-b-2',
                'border-ink font-medium' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @include('livewire.tender-detail.'.(in_array($tab, ['overview', 'documents', 'activity'], true) ? $tab : 'overview'))
    @include('livewire.tender-detail.modals')
</div>
```

`resources/views/livewire/tender-detail/overview.blade.php`:

```blade
@php use App\Support\Money; @endphp
<section class="rounded-xl border border-line bg-surface p-4">
    @if ($editing)
        <form wire:submit="save" class="space-y-4">
            @include('livewire.partials.tender-fields')
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancelEdit" class="rounded-lg px-4 py-2 text-sm hover:bg-hover">Cancel</button>
                <button type="submit" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Save changes</button>
            </div>
        </form>
    @else
        @if ($canEdit && ! $tender->isLocked())
            <div class="mb-3 flex justify-end">
                <button wire:click="startEdit" class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-hover">Edit details</button>
            </div>
        @endif
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Assigned PIC' => $tender->pic->name,
                'Opportunity owner' => $tender->owner?->name ?? '—',
                'Category' => $tender->category->value,
                'Tender code' => $tender->tender_code,
                'Client' => $tender->client,
                'Estimated value' => Money::format($tender->estimated_value_sen),
                'Type' => $tender->type->label(),
                'Mode' => $tender->mode->label(),
                'WO date' => $tender->wo_date->format('d M Y'),
                'Publish date' => $tender->publish_date?->format('d M Y') ?? '—',
                'Closing date' => $tender->closing_date->format('d M Y'),
                'Briefing' => $tender->has_briefing ? 'Yes — '.$tender->briefing_date?->format('d M Y') : 'No',
                'Submitted price' => Money::format($tender->submitted_price_sen),
                'Winning price' => Money::format($tender->winning_price_sen),
                'Lost / cancel reason' => $tender->lost_reason ?? '—',
            ] as $label => $value)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-muted">{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="mt-4">
            <p class="text-xs uppercase tracking-wide text-muted">Scope of work</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $tender->scope ?: '—' }}</p>
        </div>
    @endif
</section>
```

`resources/views/livewire/tender-detail/documents.blade.php`:

```blade
@php $editable = $canEdit && ! $tender->isLocked(); @endphp
<section class="space-y-3 rounded-xl border border-line bg-surface p-4">
    <div class="flex items-center justify-between text-sm">
        <span>Assigned to {{ $tender->pic->name }} (PIC)</span>
        <span class="font-medium">{{ $doneCount }} / {{ $documents->count() }} done</span>
    </div>
    <ul class="divide-y divide-line">
        @foreach ($documents as $doc)
            <li wire:key="doc-{{ $doc->id }}" class="flex items-center gap-3 py-2 text-sm">
                <input type="checkbox" @checked($doc->is_done) @disabled(! $editable)
                       wire:click="toggleDocument({{ $doc->id }})" aria-label="Tick {{ $doc->name }}">
                <div class="flex-1">
                    <p @class(['line-through text-muted' => $doc->is_done])>{{ $doc->name }}</p>
                    <p class="text-xs text-muted">
                        {{ $doc->is_done ? 'Ticked by '.($doc->doneBy?->name ?? 'someone').' · '.$doc->done_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y') : 'Tick when prepared' }}
                    </p>
                </div>
                @if ($editable)
                    <button wire:click="removeDocument({{ $doc->id }})" wire:confirm="Remove {{ $doc->name }} from the checklist?"
                            class="text-xs text-muted hover:text-bad-ink">Remove</button>
                @endif
            </li>
        @endforeach
    </ul>
    @if ($editable)
        <form wire:submit="addDocument" class="flex gap-2">
            <input wire:model="newDocument" placeholder="Add a document to the checklist"
                   class="flex-1 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm">
            <button class="rounded-lg border border-line px-3 py-1.5 text-sm hover:bg-hover">+ Add</button>
        </form>
        @error('newDocument') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
    @endif
</section>
```

`resources/views/livewire/tender-detail/activity.blade.php`:

```blade
<section class="rounded-xl border border-line bg-surface p-4">
    <ol class="space-y-3 text-sm">
        @forelse ($activity as $entry)
            <li class="flex gap-3">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                <div>
                    <p>{{ $entry->description }}</p>
                    <p class="text-xs text-muted">
                        {{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') }}
                    </p>
                </div>
            </li>
        @empty
            <li class="text-muted">No activity yet.</li>
        @endforelse
    </ol>
</section>
```

`resources/views/livewire/tender-detail/modals.blade.php`:

```blade
@if ($modal)
    @php $input = 'mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm'; @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md space-y-4 rounded-2xl bg-surface p-6 shadow-xl">
            @switch($modal)
                @case('done')
                    <h2 class="text-lg font-semibold">Mark as Done</h2>
                    <p class="text-sm text-muted">This records that the bid was submitted and locks the tender.</p>
                    <label class="block text-sm"><span class="text-muted">Submitted price (RM) *</span>
                        <input wire:model="submittedPrice" inputmode="decimal" class="{{ $input }}"></label>
                    @error('submittedPrice') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    @php $confirm = ['markDone', 'Mark Done']; @endphp
                    @break
                @case('cancel')
                    <h2 class="text-lg font-semibold">Cancel tender</h2>
                    <p class="text-sm text-muted">The tender moves to Lost and is marked as cancelled.</p>
                    <label class="block text-sm"><span class="text-muted">Reason *</span>
                        <textarea wire:model="cancelReason" rows="3" class="{{ $input }}"></textarea></label>
                    @error('cancelReason') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    @php $confirm = ['cancelTender', 'Cancel tender']; @endphp
                    @break
                @case('awarded')
                    <h2 class="text-lg font-semibold">Mark as Awarded</h2>
                    <p class="text-sm text-muted">Confirm CMT won this tender.</p>
                    @php $confirm = ['markAwarded', 'Mark Awarded']; @endphp
                    @break
                @case('lost')
                    <h2 class="text-lg font-semibold">Mark as Lost</h2>
                    <label class="block text-sm"><span class="text-muted">Winning price (RM), if known</span>
                        <input wire:model="winningPrice" inputmode="decimal" class="{{ $input }}"></label>
                    @error('winningPrice') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
                    <label class="block text-sm"><span class="text-muted">Reason, if known</span>
                        <textarea wire:model="lostReason" rows="2" class="{{ $input }}"></textarea></label>
                    @php $confirm = ['markLost', 'Mark Lost']; @endphp
                    @break
                @case('reopen')
                    <h2 class="text-lg font-semibold">Reopen tender</h2>
                    <p class="text-sm text-muted">Moves it back to In Progress and clears the submitted/winning prices. Use this to correct a mistake.</p>
                    @php $confirm = ['reopen', 'Reopen']; @endphp
                    @break
            @endswitch
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm hover:bg-hover">Back</button>
                <button type="button" wire:click="{{ $confirm[0] }}" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">{{ $confirm[1] }}</button>
            </div>
        </div>
    </div>
@endif
```

- [ ] **Step 5: Route — replace the `tenders.show` placeholder in `routes/web.php`**

```php
    Route::get('/tenders/{tender}', \App\Livewire\TenderDetail::class)
        ->whereNumber('tender')->name('tenders.show');
```

- [ ] **Step 6: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 7: Look at it in the browser**

Register a tender, open it, edit details, tick all documents, Mark Done, Mark Lost with a price, then (as admin) Reopen. Open the same tender in two tabs, save in one, then save in the other — confirm the "changed by" banner. Check the Activity tab and dark mode.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tender detail with overview editing, document checklist, activity and status actions

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 13: In-app notifications — assignment, closing soon, briefing tomorrow, bell

**Files:**
- Create: `app/Notifications/TenderAssigned.php`, `app/Notifications/TenderClosingSoon.php`, `app/Notifications/BriefingTomorrow.php`, `app/Support/AssignmentNotifier.php`, `app/Console/Commands/SendTenderReminders.php`, `app/Livewire/NotificationBell.php`, `resources/views/livewire/notification-bell.blade.php`
- Modify: `app/Actions/Tenders/RegisterTender.php`, `app/Actions/Tenders/UpdateTender.php`, `routes/console.php`, `resources/views/layouts/app.blade.php` (replace `{{-- bell --}}`)
- Test: `tests/Feature/Notifications/AssignmentTest.php`, `tests/Feature/Notifications/RemindersTest.php`, `tests/Feature/Livewire/NotificationBellTest.php`

**Interfaces:**
- Consumes: `RegisterTender`, `UpdateTender`, `MalaysiaTime`
- Produces:
  - `AssignmentNotifier::notify(Tender $tender, User $actor, ?int $previousPicId, ?int $previousOwnerId): void`
  - Notification data shape (all three): `['message' => string, 'tender_id' => int]`
  - Artisan command `tenders:send-reminders`, scheduled hourly
  - Livewire `NotificationBell` with `open(string $id)`, `markAllRead()`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Notifications/AssignmentTest.php`:

```php
<?php

use App\Actions\Tenders\{RegisterTender, UpdateTender};
use App\Models\User;
use App\Notifications\TenderAssigned;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('notifies the PIC and a different opportunity owner on registration, but not the registrant', function () {
    $actor = User::factory()->create();
    $pic = User::factory()->create();
    $owner = User::factory()->create();

    app(RegisterTender::class)->handle($actor, tenderData(['pic_id' => $pic->id, 'owner_id' => $owner->id]));

    Notification::assertSentTo($pic, TenderAssigned::class, fn ($n) => str_contains($n->toArray($pic)['message'], 'as PIC'));
    Notification::assertSentTo($owner, TenderAssigned::class, fn ($n) => str_contains($n->toArray($owner)['message'], 'as Opportunity Owner'));
    Notification::assertNotSentTo($actor, TenderAssigned::class);
});

it('sends one notification when PIC and owner are the same person', function () {
    $pic = User::factory()->create();

    app(RegisterTender::class)->handle(User::factory()->create(), tenderData(['pic_id' => $pic->id, 'owner_id' => $pic->id]));

    Notification::assertSentToTimes($pic, TenderAssigned::class, 1);
});

it('does not notify someone who registers a tender for themselves', function () {
    $me = User::factory()->create();

    app(RegisterTender::class)->handle($me, tenderData(['pic_id' => $me->id]));

    Notification::assertNothingSent();
});

it('notifies only the newly assigned PIC on edit', function () {
    $manager = User::factory()->manager()->create();
    $oldPic = User::factory()->create();
    $newPic = User::factory()->create();
    $tender = app(RegisterTender::class)->handle($manager, tenderData(['pic_id' => $oldPic->id]));
    Notification::fake();

    app(UpdateTender::class)->handle($manager, $tender, 1, ['pic_id' => $newPic->id, 'title' => 'CHANGED']);

    Notification::assertSentTo($newPic, TenderAssigned::class);
    Notification::assertNotSentTo($oldPic, TenderAssigned::class);
});

it('does not notify on edits that leave PIC and owner alone', function () {
    $pic = User::factory()->create();
    $tender = app(RegisterTender::class)->handle(User::factory()->manager()->create(), tenderData(['pic_id' => $pic->id]));
    Notification::fake();

    app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => 'CHANGED']);

    Notification::assertNothingSent();
});
```

`tests/Feature/Notifications/RemindersTest.php`:

```php
<?php

use App\Enums\TenderStatus;
use App\Models\{Tender, User};
use App\Notifications\{BriefingTomorrow, TenderClosingSoon};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 7 Oct, 00:30 in Malaysia
});

it('reminds PIC and owner once when closing is within 3 days', function () {
    $pic = User::factory()->create();
    $owner = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'owner_id' => $owner->id, 'closing_date' => '2026-10-10']);
    Tender::factory()->create(['closing_date' => '2026-10-11']); // 4 days — too early

    $this->artisan('tenders:send-reminders')->assertSuccessful();
    $this->artisan('tenders:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($pic, TenderClosingSoon::class, 1);
    Notification::assertSentToTimes($owner, TenderClosingSoon::class, 1);
    Notification::assertCount(2);
    expect($tender->fresh()->closing_soon_notified_at)->not->toBeNull()
        ->and($tender->fresh()->version)->toBe(1);
});

it('reminds about a briefing tomorrow in Malaysia time', function () {
    $pic = User::factory()->create();
    Tender::factory()->create(['pic_id' => $pic->id, 'has_briefing' => true, 'briefing_date' => '2026-10-08']);
    Tender::factory()->create(['has_briefing' => true, 'briefing_date' => '2026-10-07']); // today — not tomorrow

    $this->artisan('tenders:send-reminders');

    Notification::assertSentToTimes($pic, BriefingTomorrow::class, 1);
    Notification::assertCount(1);
});

it('skips closed tenders, past closing dates and deactivated people', function () {
    Tender::factory()->status(TenderStatus::Done)->create(['closing_date' => '2026-10-08']);
    Tender::factory()->create(['closing_date' => '2026-10-01']);
    Tender::factory()->create(['pic_id' => User::factory()->inactive(), 'closing_date' => '2026-10-08']);

    $this->artisan('tenders:send-reminders');

    Notification::assertNothingSent();
});

it('is scheduled hourly', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

    expect($events->first(fn ($e) => str_contains($e->command, 'tenders:send-reminders'))?->expression)->toBe('0 * * * *');
});
```

`tests/Feature/Livewire/NotificationBellTest.php`:

```php
<?php

use App\Livewire\NotificationBell;
use App\Models\{Tender, User};
use App\Notifications\TenderAssigned;
use Livewire\Livewire;

it('shows the unread count and opens a notification', function () {
    $user = User::factory()->create();
    $tender = Tender::factory()->create();
    $user->notify(new TenderAssigned($tender, 'PIC'));
    $user->notify(new TenderAssigned($tender, 'PIC'));

    $id = $user->notifications()->first()->id;

    Livewire::actingAs($user)->test(NotificationBell::class)
        ->assertSee('2')
        ->assertSee($tender->wo_number)
        ->call('open', $id)
        ->assertRedirect(route('tenders.show', $tender));

    expect($user->unreadNotifications()->count())->toBe(1);
});

it('marks everything read', function () {
    $user = User::factory()->create();
    $user->notify(new TenderAssigned(Tender::factory()->create(), 'PIC'));

    Livewire::actingAs($user)->test(NotificationBell::class)->call('markAllRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('cannot open someone else\'s notification', function () {
    $owner = User::factory()->create();
    $owner->notify(new TenderAssigned(Tender::factory()->create(), 'PIC'));

    Livewire::actingAs(User::factory()->create())->test(NotificationBell::class)
        ->call('open', $owner->notifications()->first()->id)
        ->assertNotFound();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Notifications tests/Feature/Livewire/NotificationBellTest.php`
Expected: FAIL — classes/command not found.

- [ ] **Step 3: Notifications**

`app/Notifications/TenderAssigned.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Tender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TenderAssigned extends Notification
{
    public function __construct(public Tender $tender, public string $asRole) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "You were assigned as {$this->asRole} on {$this->tender->wo_number} — ".Str::limit($this->tender->title, 60),
            'tender_id' => $this->tender->id,
        ];
    }
}
```

`app/Notifications/TenderClosingSoon.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Tender;
use App\Support\MalaysiaTime;
use Illuminate\Notifications\Notification;

class TenderClosingSoon extends Notification
{
    public function __construct(public Tender $tender) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $days = (int) MalaysiaTime::today()->diffInDays(
            \Carbon\CarbonImmutable::parse($this->tender->closing_date->toDateString(), MalaysiaTime::TZ),
        );
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days");

        return [
            'message' => "{$this->tender->wo_number} closes {$when} ({$this->tender->closing_date->format('d M Y')})",
            'tender_id' => $this->tender->id,
        ];
    }
}
```

`app/Notifications/BriefingTomorrow.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Tender;
use Illuminate\Notifications\Notification;

class BriefingTomorrow extends Notification
{
    public function __construct(public Tender $tender) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Briefing for {$this->tender->wo_number} is tomorrow ({$this->tender->briefing_date->format('d M Y')})",
            'tender_id' => $this->tender->id,
        ];
    }
}
```

- [ ] **Step 4: Assignment notifier and wiring into the actions**

`app/Support/AssignmentNotifier.php`:

```php
<?php

namespace App\Support;

use App\Models\{Tender, User};
use App\Notifications\TenderAssigned;

final class AssignmentNotifier
{
    public function notify(Tender $tender, User $actor, ?int $previousPicId, ?int $previousOwnerId): void
    {
        $recipients = [];
        if ($tender->pic_id !== $previousPicId) {
            $recipients[$tender->pic_id] = 'PIC';
        }
        if ($tender->owner_id !== null && $tender->owner_id !== $previousOwnerId && ! isset($recipients[$tender->owner_id])) {
            $recipients[$tender->owner_id] = 'Opportunity Owner';
        }
        unset($recipients[$actor->id]);

        if ($recipients === []) {
            return;
        }

        User::whereIn('id', array_keys($recipients))->where('is_active', true)->get()
            ->each(fn (User $u) => $u->notify(new TenderAssigned($tender, $recipients[$u->id])));
    }
}
```

In `RegisterTender`: change the constructor to `public function __construct(private GenerateWoNumber $woNumbers, private \App\Support\AssignmentNotifier $notifier) {}` and change `handle` so the transaction result is captured, then notify after commit:

```php
        $tender = DB::transaction(function () use ($actor, $data) {
            // ... existing body unchanged, ending with: return $tender->fresh();
        });

        $this->notifier->notify($tender, $actor, null, null);

        return $tender;
```

In `UpdateTender`: add `public function __construct(private \App\Support\AssignmentNotifier $notifier) {}`. Inside the transaction closure, before `$t->fill(...)`, capture `$oldPicId = $t->pic_id; $oldOwnerId = $t->owner_id;` and make the closure return `[$t->fresh(), $oldPicId, $oldOwnerId, $changed]` on the save path and `[$t, null, null, []]` on the no-change path. After the transaction:

```php
        [$fresh, $oldPicId, $oldOwnerId, $changed] = DB::transaction(function () use (...) { /* as described */ });

        if (array_intersect(['pic_id', 'owner_id'], $changed) !== []) {
            $this->notifier->notify($fresh, $actor, $oldPicId, $oldOwnerId);
        }

        return $fresh;
```

- [ ] **Step 5: Reminder command and schedule**

`app/Console/Commands/SendTenderReminders.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Notifications\{BriefingTomorrow, TenderClosingSoon};
use App\Support\MalaysiaTime;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendTenderReminders extends Command
{
    protected $signature = 'tenders:send-reminders';

    protected $description = 'Send "closes in 3 days" and "briefing tomorrow" notifications (once per tender)';

    public function handle(): int
    {
        $today = MalaysiaTime::today();

        $closing = Tender::with(['pic', 'owner'])
            ->where('status', TenderStatus::InProgress)
            ->whereNull('closing_soon_notified_at')
            ->whereBetween('closing_date', [$today->toDateString(), $today->addDays(3)->toDateString()])
            ->get();
        foreach ($closing as $tender) {
            $this->recipients($tender)->each->notify(new TenderClosingSoon($tender));
            $tender->forceFill(['closing_soon_notified_at' => now()])->saveQuietly();
        }

        $briefings = Tender::with(['pic', 'owner'])
            ->where('status', TenderStatus::InProgress)
            ->where('has_briefing', true)
            ->whereNull('briefing_notified_at')
            ->where('briefing_date', $today->addDay()->toDateString())
            ->get();
        foreach ($briefings as $tender) {
            $this->recipients($tender)->each->notify(new BriefingTomorrow($tender));
            $tender->forceFill(['briefing_notified_at' => now()])->saveQuietly();
        }

        $this->info("Closing reminders: {$closing->count()}, briefing reminders: {$briefings->count()}");

        return self::SUCCESS;
    }

    private function recipients(Tender $tender): Collection
    {
        return collect([$tender->pic, $tender->owner])
            ->filter()
            ->unique('id')
            ->filter(fn ($user) => $user->is_active)
            ->values();
    }
}
```

`routes/console.php` — append:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tenders:send-reminders')->hourly();
```

- [ ] **Step 6: Bell component**

`app/Livewire/NotificationBell.php`:

```php
<?php

namespace App\Livewire;

use Livewire\Component;

class NotificationBell extends Component
{
    public function open(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->redirectRoute('tenders.show', $notification->data['tender_id']);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(15)->get(),
        ]);
    }
}
```

`resources/views/livewire/notification-bell.blade.php`:

```blade
<div class="relative" x-data="{ open: false }" wire:poll.60s>
    <button type="button" @click="open = !open" class="relative rounded-lg p-2 hover:bg-hover" aria-label="Notifications">
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @if ($unread)
            <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-bad-ink px-1 text-[10px] font-semibold text-white">{{ $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute right-0 z-30 mt-2 w-80 rounded-xl border border-line bg-surface shadow-lg">
        <div class="flex items-center justify-between border-b border-line px-3 py-2 text-sm">
            <span class="font-medium">Notifications</span>
            @if ($unread) <button wire:click="markAllRead" class="text-xs text-muted hover:text-ink">Mark all read</button> @endif
        </div>
        <ul class="max-h-96 overflow-y-auto">
            @forelse ($items as $n)
                <li>
                    <button wire:click="open('{{ $n->id }}')" @class(['block w-full px-3 py-2 text-left text-sm hover:bg-hover', 'font-medium' => ! $n->read_at])>
                        {{ $n->data['message'] }}
                        <span class="block text-xs font-normal text-muted">{{ $n->created_at->diffForHumans() }}</span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-muted">You're all caught up.</li>
            @endforelse
        </ul>
    </div>
</div>
```

In `resources/views/layouts/app.blade.php` replace `{{-- bell --}}` with `<livewire:notification-bell />`.

- [ ] **Step 7: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 8: Look at it in the browser**

As admin, register a tender with another user as PIC; log in as that user and confirm the bell shows 1 and opens the tender. Set a tender's closing date to 2 days away, run `docker compose exec -T app php artisan tenders:send-reminders`, and confirm the reminder appears.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: in-app notifications for assignment, closing soon and briefing tomorrow

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 14: Settings and Manage Users

**Files:**
- Create: `app/Actions/Users/CreateUser.php`, `app/Actions/Users/ChangeUserRole.php`, `app/Actions/Users/SetUserActive.php`, `app/Livewire/Settings.php`, `app/Livewire/ManageUsers.php`, `resources/views/livewire/settings.blade.php`, `resources/views/livewire/manage-users.blade.php`
- Modify: `routes/web.php` (replace `settings` and `users.index` placeholders)
- Test: `tests/Feature/Actions/UserActionsTest.php`, `tests/Feature/Livewire/SettingsTest.php`, `tests/Feature/Livewire/ManageUsersTest.php`

**Interfaces:**
- Consumes: `Role`, gate `manage-users`
- Produces:
  - `CreateUser::handle(User $actor, string $name, string $email, Role $role, string $password): User`
  - `ChangeUserRole::handle(User $actor, User $target, Role $role): void` — throws `DomainException` when an admin demotes themselves
  - `SetUserActive::handle(User $actor, User $target, bool $active): void` — throws `DomainException` when an admin deactivates themselves

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Actions/UserActionsTest.php`:

```php
<?php

use App\Actions\Users\{ChangeUserRole, CreateUser, SetUserActive};
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

it('lets an admin create a user with a temporary password', function () {
    $user = app(CreateUser::class)->handle(User::factory()->admin()->create(), 'Nurul Ain', 'nurul@cmt.test', Role::Staff, 'temp-pass-123');

    expect($user->role)->toBe(Role::Staff)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('temp-pass-123', $user->password))->toBeTrue();
});

it('refuses non-admins', function () {
    app(CreateUser::class)->handle(User::factory()->manager()->create(), 'X', 'x@cmt.test', Role::Staff, 'temp-pass-123');
})->throws(AuthorizationException::class);

it('changes roles and active state of other users', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    app(ChangeUserRole::class)->handle($admin, $target, Role::Manager);
    app(SetUserActive::class)->handle($admin, $target, false);

    expect($target->fresh()->role)->toBe(Role::Manager)->and($target->fresh()->is_active)->toBeFalse();
});

it('stops an admin from demoting or deactivating themselves', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => app(ChangeUserRole::class)->handle($admin, $admin, Role::Staff))->toThrow(DomainException::class)
        ->and(fn () => app(SetUserActive::class)->handle($admin, $admin, false))->toThrow(DomainException::class);
    expect($admin->fresh()->role)->toBe(Role::Admin)->and($admin->fresh()->is_active)->toBeTrue();
});
```

`tests/Feature/Livewire/SettingsTest.php`:

```php
<?php

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('updates own name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Settings::class)
        ->set('name', 'Siti Aisyah binti Ali')->call('saveProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Siti Aisyah binti Ali');
});

it('changes password only with the current password', function () {
    $user = User::factory()->create(['password' => 'old-pass-123']);

    Livewire::actingAs($user)->test(Settings::class)
        ->set('currentPassword', 'wrong')->set('newPassword', 'new-pass-456')->set('newPassword_confirmation', 'new-pass-456')
        ->call('changePassword')->assertHasErrors('currentPassword')
        ->set('currentPassword', 'old-pass-123')
        ->call('changePassword')->assertHasNoErrors()->assertSee('Password changed');

    expect(Hash::check('new-pass-456', $user->fresh()->password))->toBeTrue();
});
```

`tests/Feature/Livewire/ManageUsersTest.php`:

```php
<?php

use App\Enums\Role;
use App\Livewire\ManageUsers;
use App\Models\User;
use Livewire\Livewire;

it('is only reachable by admins', function () {
    $this->actingAs(User::factory()->manager()->create())->get('/settings/users')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/settings/users')->assertOk()->assertSee('Manage Users');
});

it('adds a user and validates the form', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(ManageUsers::class)
        ->call('create')->assertHasErrors(['name', 'email', 'password'])
        ->set('name', 'Muhammad Hafiz')->set('email', 'hafiz@cmt.test')->set('role', 'manager')->set('password', 'temp-pass-123')
        ->call('create')->assertHasNoErrors()->assertSee('Muhammad Hafiz');

    expect(User::where('email', 'hafiz@cmt.test')->first()->role)->toBe(Role::Manager);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'dup@cmt.test']);

    Livewire::actingAs(User::factory()->admin()->create())->test(ManageUsers::class)
        ->set('name', 'X')->set('email', 'dup@cmt.test')->set('password', 'temp-pass-123')
        ->call('create')->assertHasErrors('email');
});

it('changes role and toggles active, and explains why self-lockout is refused', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    Livewire::actingAs($admin)->test(ManageUsers::class)
        ->call('changeRole', $other->id, 'manager')
        ->call('toggleActive', $other->id)
        ->call('toggleActive', $admin->id)
        ->assertSee('You cannot deactivate your own account');

    expect($other->fresh()->role)->toBe(Role::Manager)->and($other->fresh()->is_active)->toBeFalse();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/Actions/UserActionsTest.php tests/Feature/Livewire/SettingsTest.php tests/Feature/Livewire/ManageUsersTest.php`
Expected: FAIL — classes not found; placeholder routes return text.

- [ ] **Step 3: User actions**

`app/Actions/Users/CreateUser.php`:

```php
<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateUser
{
    public function handle(User $actor, string $name, string $email, Role $role, string $password): User
    {
        Gate::forUser($actor)->authorize('manage-users');

        return User::create([
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'role' => $role,
            'password' => $password,
            'is_active' => true,
        ]);
    }
}
```

`app/Actions/Users/ChangeUserRole.php`:

```php
<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;

final class ChangeUserRole
{
    public function handle(User $actor, User $target, Role $role): void
    {
        Gate::forUser($actor)->authorize('manage-users');
        if ($actor->is($target) && $role !== Role::Admin) {
            throw new DomainException('You cannot remove your own admin role — ask another admin.');
        }

        $target->update(['role' => $role]);
    }
}
```

`app/Actions/Users/SetUserActive.php`:

```php
<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;

final class SetUserActive
{
    public function handle(User $actor, User $target, bool $active): void
    {
        Gate::forUser($actor)->authorize('manage-users');
        if ($actor->is($target) && ! $active) {
            throw new DomainException('You cannot deactivate your own account — ask another admin.');
        }

        $target->update(['is_active' => $active]);
    }
}
```

- [ ] **Step 4: Screens**

`app/Livewire/Settings.php`:

```php
<?php

namespace App\Livewire;

use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public string $name = '';
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPassword_confirmation = '';
    public ?string $status = null;

    public function mount(): void
    {
        $this->name = auth()->user()->name;
    }

    public function saveProfile(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255']]);
        auth()->user()->update(['name' => trim($this->name)]);
        $this->status = 'Profile saved.';
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'newPassword' => ['required', 'min:8', 'confirmed'],
        ]);
        auth()->user()->update(['password' => $this->newPassword]);
        $this->reset('currentPassword', 'newPassword', 'newPassword_confirmation');
        $this->status = 'Password changed.';
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
```

`resources/views/livewire/settings.blade.php`:

```blade
@php $input = 'mt-1 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm'; @endphp
<div class="max-w-xl space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Settings</h1>
        <p class="text-sm text-muted">Your account details</p>
    </header>
    @if ($status) <p class="rounded-lg bg-good-bg px-3 py-2 text-sm text-good-ink">{{ $status }}</p> @endif

    <form wire:submit="saveProfile" class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Profile</h2>
        <label class="block text-sm"><span class="text-muted">Name</span><input wire:model="name" class="{{ $input }}"></label>
        @error('name') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        <p class="text-sm text-muted">Email: {{ auth()->user()->email }} · Role: {{ auth()->user()->role->label() }}</p>
        <button class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Save</button>
    </form>

    <form wire:submit="changePassword" class="space-y-3 rounded-xl border border-line bg-surface p-4">
        <h2 class="font-medium">Change password</h2>
        <label class="block text-sm"><span class="text-muted">Current password</span><input type="password" wire:model="currentPassword" class="{{ $input }}"></label>
        @error('currentPassword') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        <label class="block text-sm"><span class="text-muted">New password (at least 8 characters)</span><input type="password" wire:model="newPassword" class="{{ $input }}"></label>
        @error('newPassword') <p class="text-xs text-bad-ink">{{ $message }}</p> @enderror
        <label class="block text-sm"><span class="text-muted">Type it again</span><input type="password" wire:model="newPassword_confirmation" class="{{ $input }}"></label>
        <button class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Change password</button>
    </form>
</div>
```

`app/Livewire/ManageUsers.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Users\{ChangeUserRole, CreateUser, SetUserActive};
use App\Enums\Role;
use App\Models\User;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manage Users')]
class ManageUsers extends Component
{
    public string $name = '';
    public string $email = '';
    public string $role = 'staff';
    public string $password = '';
    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('manage-users');
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        app(CreateUser::class)->handle(auth()->user(), $this->name, $this->email, Role::from($this->role), $this->password);
        $this->notice = "Account created for {$this->name}. Share the temporary password with them privately.";
        $this->reset('name', 'email', 'role', 'password');
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->attempt(fn () => app(ChangeUserRole::class)->handle(auth()->user(), User::findOrFail($userId), Role::from($role)));
    }

    public function toggleActive(int $userId): void
    {
        $target = User::findOrFail($userId);
        $this->attempt(fn () => app(SetUserActive::class)->handle(auth()->user(), $target, ! $target->is_active));
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
            $this->notice = null;
        } catch (DomainException $e) {
            $this->notice = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.manage-users', [
            'users' => User::orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
```

`resources/views/livewire/manage-users.blade.php`:

```blade
@php $input = 'rounded-lg border border-line bg-surface px-3 py-2 text-sm'; @endphp
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Manage Users</h1>
        <p class="text-sm text-muted">Add staff accounts, change roles, deactivate leavers</p>
    </header>
    @if ($notice) <p class="rounded-lg bg-warn-bg px-3 py-2 text-sm text-warn-ink">{{ $notice }}</p> @endif

    <form wire:submit="create" class="grid gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-5">
        <div><input wire:model="name" placeholder="Full name" class="{{ $input }} w-full">@error('name')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
        <div><input wire:model="email" type="email" placeholder="Work email" class="{{ $input }} w-full">@error('email')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
        <select wire:model="role" class="{{ $input }}">
            @foreach ($roles as $r) <option value="{{ $r->value }}">{{ $r->label() }}</option> @endforeach
        </select>
        <div><input wire:model="password" type="text" placeholder="Temporary password" class="{{ $input }} w-full">@error('password')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
        <button class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Add user</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[600px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase tracking-wide text-muted">
                <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Role</th><th class="px-3 py-2">Status</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($users as $u)
                <tr wire:key="user-{{ $u->id }}" class="border-t border-line">
                    <td class="px-3 py-2"><div class="flex items-center gap-2"><x-avatar :user="$u" /> {{ $u->name }}</div></td>
                    <td class="px-3 py-2">{{ $u->email }}</td>
                    <td class="px-3 py-2">
                        <select wire:change="changeRole({{ $u->id }}, $event.target.value)" class="{{ $input }} py-1" aria-label="Role for {{ $u->name }}">
                            @foreach ($roles as $r) <option value="{{ $r->value }}" @selected($u->role === $r)>{{ $r->label() }}</option> @endforeach
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-good-bg text-good-ink' => $u->is_active, 'bg-bad-bg text-bad-ink' => ! $u->is_active])>
                            {{ $u->is_active ? 'Active' : 'Deactivated' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-right">
                        <button wire:click="toggleActive({{ $u->id }})" class="text-xs text-muted hover:text-ink">
                            {{ $u->is_active ? 'Deactivate' : 'Reactivate' }}
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Routes — replace the two placeholders in `routes/web.php`**

```php
    Route::get('/settings', \App\Livewire\Settings::class)->name('settings');
    Route::get('/settings/users', \App\Livewire\ManageUsers::class)
        ->middleware('can:manage-users')->name('users.index');
```

- [ ] **Step 6: Run to verify they pass**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 7: Look at it in the browser**

As admin: add a user, change their role, deactivate them, then try logging in as them (refused). Try to deactivate yourself (message shown). As that user (reactivated), change your password.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: settings (profile, password) and admin user management

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 15: Development sample data from the prototype

**Files:**
- Create: `database/seeders/data/prototype-tenders.json` (copy)
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/SeederTest.php`

**Interfaces:**
- Consumes: models, enums, `TenderDocument::STANDARD`, `ActivityLog::record`, `config('tenderhub.seed_password')`
- Produces: `php artisan db:seed` → 4 staff (Ahmad Faizal, Nurul Ain, Siti Aisyah, Muhammad Hafiz) + `manager@cmt.test` + `admin@cmt.test`, 41 tenders (13 in progress, 20 done, 3 awarded, 5 lost), WO sequences primed so new registrations never collide

- [ ] **Step 1: Copy the data file**

```bash
mkdir -p database/seeders/data
cp /c/Projects/tms-v2/docs/superpowers/plans/assets/prototype-tenders.json database/seeders/data/
```

Each record has: `wo_number, wo_date, title, tender_code, category, client, owner, pic, publish_date, closing_date, estimated_value_sen, doc_percent, status, mode, type, has_briefing, briefing_date, scope, submitted_price_sen, winning_price_sen` (dates `Y-m-d`; `owner`/`pic` are names).

- [ ] **Step 2: Write the failing test**

`tests/Feature/SeederTest.php`:

```php
<?php

use App\Enums\{Role, TenderStatus};
use App\Models\{Tender, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

it('loads the prototype people and tenders', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(6)
        ->and(User::where('email', 'admin@cmt.test')->first()->role)->toBe(Role::Admin)
        ->and(User::where('email', 'manager@cmt.test')->first()->role)->toBe(Role::Manager)
        ->and(Tender::count())->toBe(41)
        ->and(Tender::where('status', TenderStatus::InProgress)->count())->toBe(13)
        ->and(Tender::where('status', TenderStatus::Done)->count())->toBe(20)
        ->and(Tender::where('status', TenderStatus::Awarded)->count())->toBe(3)
        ->and(Tender::where('status', TenderStatus::Lost)->count())->toBe(5)
        ->and(Tender::where('wo_number', '200-10092026-001')->first()->documentPercent())->toBe(40)
        ->and(Tender::where('status', '!=', TenderStatus::InProgress)->get()->every(fn ($t) => $t->documentPercent() === 100))->toBeTrue()
        ->and(Tender::first()->activity()->count())->toBeGreaterThan(0);
});

it('primes the WO sequence so the next registration on a seeded day does not collide', function () {
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('wo_sequences')->where('date', '2025-12-22')->value('last_seq'))->toBe(6);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->seed(DatabaseSeeder::class);
})->throws(RuntimeException::class, 'never be loaded in production');
```

- [ ] **Step 3: Run to verify it fails**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/SeederTest.php`
Expected: FAIL — counts are wrong (default seeder creates one test user).

- [ ] **Step 4: Implement `database/seeders/DatabaseSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Enums\{Role, TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample data must never be loaded in production.');
        }

        $password = config('tenderhub.seed_password');
        $people = collect([
            ['Ahmad Faizal', 'ahmad.faizal@cmt.test', Role::Staff],
            ['Nurul Ain', 'nurul.ain@cmt.test', Role::Staff],
            ['Siti Aisyah', 'siti.aisyah@cmt.test', Role::Staff],
            ['Muhammad Hafiz', 'muhammad.hafiz@cmt.test', Role::Staff],
            ['Sales Manager', 'manager@cmt.test', Role::Manager],
            ['System Admin', 'admin@cmt.test', Role::Admin],
        ])->mapWithKeys(fn ($p) => [$p[0] => User::create([
            'name' => $p[0], 'email' => $p[1], 'role' => $p[2], 'password' => $password, 'is_active' => true,
        ])]);

        $rows = json_decode(file_get_contents(database_path('seeders/data/prototype-tenders.json')), true);
        $maxSeq = [];

        foreach ($rows as $row) {
            $status = TenderStatus::from($row['status']);
            $pic = $people[$row['pic']];
            $owner = $people[$row['owner']] ?? null;

            $tender = Tender::create([
                'wo_number' => $row['wo_number'],
                'wo_date' => $row['wo_date'],
                'mode' => TenderMode::from($row['mode']),
                'type' => TenderType::from($row['type']),
                'category' => TenderCategory::tryFrom($row['category']) ?? TenderCategory::Default,
                'tender_code' => $row['tender_code'],
                'title' => $row['title'],
                'client' => $row['client'],
                'scope' => $row['scope'],
                'pic_id' => $pic->id,
                'owner_id' => $owner?->id,
                'publish_date' => $row['publish_date'],
                'closing_date' => $row['closing_date'],
                'has_briefing' => $row['has_briefing'],
                'briefing_date' => $row['briefing_date'],
                'estimated_value_sen' => $row['estimated_value_sen'],
                'status' => $status,
                'submitted_price_sen' => $row['submitted_price_sen'],
                'winning_price_sen' => $row['winning_price_sen'],
                'done_at' => $status !== TenderStatus::InProgress ? now() : null,
                'awarded_at' => $status === TenderStatus::Awarded ? now() : null,
                'lost_at' => $status === TenderStatus::Lost ? now() : null,
                'version' => 1,
            ]);

            $ticked = $status === TenderStatus::InProgress ? (int) round($row['doc_percent'] / 20) : 5;
            foreach (TenderDocument::STANDARD as $i => $name) {
                $tender->documents()->create([
                    'name' => $name,
                    'position' => $i + 1,
                    'is_done' => $i < $ticked,
                    'done_by' => $i < $ticked ? $pic->id : null,
                    'done_at' => $i < $ticked ? now() : null,
                ]);
            }

            ActivityLog::record($tender, $owner ?? $pic, 'registered', 'Tender registered');
            if ($status !== TenderStatus::InProgress) {
                ActivityLog::record($tender, $pic, 'marked_done', 'Marked Done — submitted price '.Money::format($row['submitted_price_sen']));
            }
            if ($status === TenderStatus::Awarded) {
                ActivityLog::record($tender, $pic, 'marked_awarded', 'Marked Awarded');
            }
            if ($status === TenderStatus::Lost) {
                ActivityLog::record($tender, $pic, 'marked_lost', 'Marked Lost — winning price '.Money::format($row['winning_price_sen']));
            }

            // 200-DDMMYYYY-NNN → remember the highest NNN per day
            [, $dmy, $seq] = explode('-', $row['wo_number']);
            $date = substr($dmy, 4, 4).'-'.substr($dmy, 2, 2).'-'.substr($dmy, 0, 2);
            $maxSeq[$date] = max($maxSeq[$date] ?? 0, (int) $seq);
        }

        foreach ($maxSeq as $date => $seq) {
            DB::table('wo_sequences')->updateOrInsert(['date' => $date], ['last_seq' => $seq]);
        }
    }
}
```

Note: `doc_percent` values in the data are multiples of 5/20; `round(40/20) = 2` ticked of 5 → 40%. If any in-progress record yields a percent that doesn't match the test's `200-10092026-001` = 40%, check that row's `doc_percent` in the JSON first.

- [ ] **Step 5: Run to verify it passes**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS. (The production test sets the environment for that test only; `RefreshDatabase` rolls back.)

- [ ] **Step 6: Load it for real and look**

```bash
docker compose exec -T app php artisan migrate:fresh --seed
```

Log in as `admin@cmt.test` (password from `SEED_USER_PASSWORD` in `.env`) and browse all four lists. Note: the prototype's in-progress closing dates are mostly in early 2026, so they show as overdue (red) — expected with prototype data.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: development sample data from the prototype (41 tenders, 6 accounts)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 16: Error pages, coverage gate, README and full browser walkthrough

**Files:**
- Create: `resources/views/errors/403.blade.php`, `resources/views/errors/404.blade.php`, `resources/views/errors/500.blade.php`, `resources/views/errors/minimal-layout.blade.php`, `README.md` (replace)
- Modify: `.githooks/pre-commit`, `composer.json` (scripts)
- Test: `tests/Feature/ErrorPagesTest.php`

**Interfaces:**
- Produces: friendly error pages; `composer test` and `composer test:coverage` scripts; pre-commit enforces ≥ 80% line coverage.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ErrorPagesTest.php`:

```php
<?php

use App\Models\User;

it('shows a friendly 403 page', function () {
    $this->actingAs(User::factory()->create())->get('/settings/users')
        ->assertForbidden()
        ->assertSee("You don't have permission to do that");
});

it('shows a friendly 404 page', function () {
    $this->actingAs(User::factory()->create())->get('/tenders/999999')
        ->assertNotFound()
        ->assertSee("We couldn't find that page");
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec -T app ./vendor/bin/pest tests/Feature/ErrorPagesTest.php`
Expected: FAIL — default Laravel error text ("Forbidden" / "Not Found").

- [ ] **Step 3: Error views**

`resources/views/errors/minimal-layout.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · CMT Tender Hub</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F5F4FA;color:#1E1B2E;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif}
        @media (prefers-color-scheme:dark){body{background:#141312;color:#F5F4F0}}
        main{max-width:28rem;padding:2rem;text-align:center}
        a{color:inherit}
    </style>
</head>
<body><main>
    <h1>@yield('heading')</h1>
    <p>@yield('message')</p>
    <p><a href="{{ url('/') }}">Back to Tender Hub</a></p>
</main></body>
</html>
```

`resources/views/errors/403.blade.php`:

```blade
@extends('errors.minimal-layout')
@section('title', 'Not allowed')
@section('heading', "You don't have permission to do that")
@section('message', 'If you think you should, ask a manager or an admin.')
```

`resources/views/errors/404.blade.php`:

```blade
@extends('errors.minimal-layout')
@section('title', 'Not found')
@section('heading', "We couldn't find that page")
@section('message', 'The link may be old, or the tender may have been removed.')
```

`resources/views/errors/500.blade.php`:

```blade
@extends('errors.minimal-layout')
@section('title', 'Something went wrong')
@section('heading', 'Something went wrong on our side')
@section('message', 'The problem has been logged. Please try again in a moment.')
```

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec -T app ./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Coverage gate**

In `composer.json` `"scripts"` add:

```json
"test": "pest",
"test:coverage": "pest --coverage --min=80"
```

Replace `.githooks/pre-commit`:

```sh
#!/bin/sh
echo "Running test suite with coverage check before commit..."
docker compose exec -T app ./vendor/bin/pest --coverage --min=80 || {
  echo "Tests failed or coverage is below 80% - commit blocked."
  exit 1
}
```

Run: `docker compose exec -T app ./vendor/bin/pest --coverage --min=80`
Expected: PASS with total ≥ 80%. If below, look at the per-file report and add tests for the uncovered behaviour (do NOT lower the threshold or exclude business code).

- [ ] **Step 6: README**

`README.md`:

```markdown
# CMT Tender Hub

Internal web app for CMT's sales team to register government tenders, track the
documents each bid needs, and record whether each bid was won or lost.

## Running it (everything runs in Docker — PHP does not need to be installed)

    docker compose up -d
    docker compose exec -T app php artisan migrate:fresh --seed

- App: http://localhost:8080
- Test mailbox (catches password-reset emails): http://localhost:8025

Sample accounts (development only), password = `SEED_USER_PASSWORD` in `.env`:
`admin@cmt.test` (Admin), `manager@cmt.test` (Manager),
`ahmad.faizal@cmt.test`, `nurul.ain@cmt.test`, `siti.aisyah@cmt.test`,
`muhammad.hafiz@cmt.test` (Staff).

## Tests

    docker compose exec -T app ./vendor/bin/pest                       # all tests
    docker compose exec -T app ./vendor/bin/pest --coverage --min=80   # with coverage gate

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
```

- [ ] **Step 7: Full browser walkthrough (required before reporting Stage 1 done)**

With seeded data, using the Playwright MCP tools (see tms-v2's `.claude/skills/e2e-playwright-verification/SKILL.md` for the procedure), verify and screenshot:

1. Login (wrong password refused; correct password lands on In Progress); Forgot password email appears in Mailpit.
2. Sidebar counts 13 / 20 / 3 / 5; each list shows its own columns; search, My tenders, filters, pagination.
3. Register Tender: validation errors; duplicate-code warning; success opens the detail page.
4. Detail: edit and save; tick all documents; Mark Done blocked then allowed; Mark Lost with winning price; Reopen as admin; Activity tab entries.
5. Two tabs on the same tender: second save shows "changed by …".
6. Bell: assignment notification appears for the new PIC and opens the tender.
7. Settings: change name and password. Manage Users: add, change role, deactivate (then that user's session is logged out), self-deactivation refused.
8. Staff user opening another person's tender: no edit buttons.
9. Dark mode and 375px phone width (drawer opens/closes).
10. No errors in the browser console or `docker compose logs app`.

Fix anything found (with a failing test first where the bug is testable), then re-run the full suite.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: friendly error pages, 80% coverage gate and README

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Self-review notes (spec coverage)

| Spec section | Task(s) |
|---|---|
| §2 Project setup, Docker services, quality rules | 1, 16 |
| §3 users / tenders / tender_documents / activity_logs / notifications, sen, Malaysia time, WO generation with lock | 2, 3, 5, 6 |
| §4 Login + reset + deactivation | 4 |
| §4 Layout, sidebar counts, theme, phone drawer, hidden later-stage items, landing page | 10 |
| §4 Lists, columns, variants, search, filters, sort, pagination, highlights | 10 |
| §4 Register Tender incl. duplicate warning, standard docs, redirect | 7, 11 |
| §4 Detail tabs, actions table, locking, reopen | 8, 9, 12 |
| §4 Settings + Manage Users, self-lockout prevention | 14 |
| §5 Permissions (server-side) | 6, 7, 8, 9, 12, 14 |
| §6 Notifications + scheduler | 13 |
| §7 Validation, edit conflicts, 403, error pages | 7, 11, 12, 16 |
| §8 Sample data, production refusal | 15 |
| §9 Testing incl. real MySQL, frozen clock, coverage | all; 16 |
