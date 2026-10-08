# CMT Tender Hub — Stage 2 Implementation Plan (Find Tenders + Collector)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the MyProcurement, SPAN and LLM collectors from the Node app `tms-v2` into Tender Hub, import its 206,645 collected tenders, and add the Find Tenders list/detail pages with "Register this tender".

**Architecture:** Pure parser classes turn saved/live pages into validated `TenderPatch` objects; one `CollectorSource` per site pages through its listings via a shared `PoliteFetcher`; a `Merger` saves patches with tms-v2's field-level rules into MySQL. A queued `RunCollection` job (run by a new `worker` container) executes a run; a 5-minute scheduled check starts the daily 12:01 MYT run (and catches up if missed); "Collect now" starts an open-only run. Livewire pages read the collected tables.

**Tech Stack:** Laravel 13, Livewire 4, Pest 5, MySQL 8.4 (FULLTEXT), symfony/dom-crawler + symfony/css-selector + masterminds/html5 (HTML5 parsing like cheerio/parse5), Laravel HTTP client + `Sleep` facade, database queue, mongodb PHP extension + mongodb/mongodb (import only).

**Spec:** `docs/superpowers/specs/2026-10-06-cmt-tender-hub-stage2-design.md` — read it first. Stage 1 spec/plan in the same folders describe existing code.

**Reference implementation (read-only):** `C:\Projects\tms-v2\backend\src\scrapers\**`, `src\parsing\text.ts`, `src\http\politeFetch.ts`, `src\storage\repository.ts`, tests in `C:\Projects\tms-v2\backend\test\*.test.ts`. When this plan says "port the remaining cases of X.test.ts", each listed `it(...)` becomes one Pest test with the same input and expected values.

## Global Constraints

- Branch: `stage-2` in `C:\Projects\cmt-tender-hub` (already created from `stage-1`). Commit per task; never commit red; never skip the pre-commit hook (it runs `pest --coverage --min=80`).
- PHP is not on the host: run everything via `docker compose exec -T app …`. Shell is Git Bash — write PHP files with the editor tool, not `sed`/heredoc substitutions containing backslashes.
- **Tests must never contact a real website.** `Http::preventStrayRequests()` is enabled for every test (Task 1).
- Money is integer **sen**. Collected prices arrive in ringgit; convert with `Text::rmPriceSen()` / `(int) round($ringgit * 100)`.
- Calendar dates are `Y-m-d` strings in Malaysia time; compare as strings; "now/today" = `App\Support\MalaysiaTime`.
- `scrapedAt` / provenance timestamps are UTC ISO strings `Y-m-d\TH:i:s.v\Z` (lexicographically comparable, same as tms-v2).
- `dedup_key` = reference number upper-cased with all whitespace removed; empty → `<source>:<sourceId>`.
- Sources: `myprocurement`, `span`, `llm` (collected); `kwsp` exists only in imported history. Display names: MyProcurement, SPAN, LLM, KWSP.
- Scopes: `daily` (scheduled) and `open` (Collect now). Run triggers: `scheduled`, `manual`. Run statuses: `running`, `succeeded`, `partial`, `failed`. Stuck threshold: 2 hours.
- Requisition maps to pipeline type **Quotation**; MyProcurement → mode **EP**, others **Non-EP**.
- User-Agent: `CMTTenderHub/1.0`. Polite pacing: 300 ms + 0–200 ms jitter before every request; 3 attempts; back-off 1 s/4 s/16 s; 429/503 waits `Retry-After` seconds (else 60 s), first such wait is free.
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Plain-English UI copy (user rule).

## Review Focus

1. **A government page that changes layout** — parsers must return `[]`/skip, never throw, and MyProcurement's zero-open guard must flag the run. Pinned in Tasks 3, 7.
2. **Out-of-order or partial observations** (e.g. a results page with no closing date arriving after a listing that had one) — must never blank a known value or overwrite a newer one. Pinned in Task 9.
3. **Two runs at once / a run that died** — "Collect now" during a run must refuse; a 2-hour-old `running` row must be marked failed before the next run. Pinned in Task 11.
4. **Search input with punctuation** (`UTHM/54(KTKEM)`, `+`, `"`, `*`) — must not cause a MySQL FULLTEXT syntax error. Pinned in Task 13.
5. **Registering the same collected tender twice / tampering with the collected id** — link must point to a real collected tender; button turns into "Open in pipeline". Pinned in Task 14.

---

## File Structure

| Path | Responsibility |
|---|---|
| `app/Collector/Support/Text.php` | Whitespace clean-up, date and RM-price parsing (port of `parsing/text.ts`) |
| `app/Collector/TenderPatch.php` | Validated value object for one observation of one tender |
| `app/Collector/CollectorException.php` | Fetch/parse failure that marks a source failed |
| `app/Collector/Parsers/{MyProcurementParser,SpanParser,LlmParser}.php` | Pure HTML/JSON → patches |
| `app/Collector/Fetcher.php`, `app/Collector/PoliteFetcher.php`, `app/Collector/SpanCertificate.php` | Downloading |
| `app/Collector/CollectorSource.php`, `app/Collector/Sources/{MyProcurement,Span,Llm}Source.php` | Per-site paging and job selection |
| `app/Collector/Merger.php`, `app/Collector/StaleOpenCloser.php` | Saving rules; closing past-due tenders |
| `app/Collector/CollectionRunner.php`, `app/Actions/Collector/StartCollection.php`, `app/Jobs/RunCollection.php`, `app/Console/Commands/CollectDaily.php` | Runs |
| `app/Collector/Legacy/{LegacyTenderSource,MongoLegacyTenderSource,LegacyTenderMapper}.php`, `app/Console/Commands/ImportLegacyTenders.php` | One-time import |
| `app/Models/{CollectedTender,CollectedTenderSource,CollectedTenderFieldCode,CollectionRun}.php` | Models |
| `app/Queries/CollectedTenderQuery.php` | Find Tenders filtering/sorting |
| `app/Livewire/{FindTenders,CollectedTenderDetail}.php` + views | Screens |
| `resources/certs/span-digicert-intermediate.pem` | SPAN's missing intermediate certificate |
| `tests/Fixtures/collector/*` | Saved real pages copied from tms-v2 |
| `tests/Integration/**` | Tests needing committed data (FULLTEXT) — use `DatabaseTruncation` |

---

### Task 1: Dependencies, worker container, fixtures and text helpers

**Files:**
- Modify: `composer.json` (via composer), `docker-compose.yml`, `.env`, `.env.example`, `tests/TestCase.php`, `tests/Pest.php`, `phpunit.xml`
- Create: `tests/Fixtures/collector/*` (copies), `app/Collector/Support/Text.php`, `app/Collector/CollectorException.php`
- Test: `tests/Unit/Collector/TextTest.php`

**Interfaces:**
- Produces: `Text::clean(?string): string`, `Text::ddmmyyyy(?string): ?string`, `Text::isoPrefix(?string): ?string`, `Text::dotted(?string): ?string`, `Text::dashed(?string): ?string`, `Text::rmPriceSen(?string): ?int`, `Text::fieldCodes(?string): array`, `Text::scrapedAtNow(): string`; `CollectorException extends RuntimeException`; `tests/Integration` suite with `DatabaseTruncation`; worker container running the database queue.

- [ ] **Step 1: Install parsing packages**

```bash
cd /c/Projects/cmt-tender-hub
docker compose exec -T app composer require symfony/dom-crawler symfony/css-selector masterminds/html5
```

- [ ] **Step 2: Copy fixtures (no KWSP)**

```bash
mkdir -p tests/Fixtures/collector
cd /c/Projects/tms-v2/backend/test/fixtures
cp archive-quotation-p1.json open-quotation-p1.json open-requisition-p1.json open-tender-p1.json results-quotation-p1.json span-2026.html llm-tender_detail_12540.html llm-tender_keputusan.html llm-tender_tawaran.html /c/Projects/cmt-tender-hub/tests/Fixtures/collector/
```

- [ ] **Step 3: Worker container and database queue**

Append to `docker-compose.yml` services (same image/volumes as `scheduler`):

```yaml
  worker:
    build: { context: ., dockerfile: docker/php/Dockerfile }
    command: php artisan queue:work --tries=1 --timeout=7500 --sleep=3
    volumes: [".:/var/www/html", "vendor:/var/www/html/vendor"]
    depends_on:
      mysql: { condition: service_healthy }
```

In `.env` and `.env.example` set `QUEUE_CONNECTION=database` and add `DB_QUEUE_RETRY_AFTER=7800` (must exceed the job timeout or the queue re-runs a long collection). `phpunit.xml` keeps `QUEUE_CONNECTION=sync`. Then `docker compose up -d worker`.

- [ ] **Step 4: Block real HTTP in every test; add Integration suite**

`tests/TestCase.php` `setUp()` becomes:

```php
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }
```

Append to `tests/Pest.php`:

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\DatabaseTruncation::class)
    ->in('Integration');
```

In `phpunit.xml` `<testsuites>` add:

```xml
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
```

- [ ] **Step 5: Write the failing test `tests/Unit/Collector/TextTest.php`**

```php
<?php

use App\Collector\Support\Text;
use Carbon\CarbonImmutable;

it('collapses whitespace including non-breaking spaces', function () {
    expect(Text::clean("  A \n\t B\u{00A0}C  "))->toBe('A B C')
        ->and(Text::clean(null))->toBe('');
});

it('parses the four date shapes the sites use, rejecting impossible dates', function () {
    expect(Text::ddmmyyyy('07/07/2026'))->toBe('2026-07-07')
        ->and(Text::ddmmyyyy('31/02/2026'))->toBeNull()
        ->and(Text::ddmmyyyy('7/7/2026'))->toBeNull()
        ->and(Text::isoPrefix('2026-07-06 12:00PM'))->toBe('2026-07-06')
        ->and(Text::isoPrefix('2026-02-30'))->toBeNull()
        ->and(Text::dotted('20.07.2026'))->toBe('2026-07-20')
        ->and(Text::dashed('20-07-2026'))->toBe('2026-07-20')
        ->and(Text::dashed('20-07-2026 extra'))->toBeNull()
        ->and(Text::ddmmyyyy(null))->toBeNull();
});

it('parses RM prices into sen', function () {
    expect(Text::rmPriceSen('RM 28,800.00'))->toBe(2880000)
        ->and(Text::rmPriceSen('RM429,782.20'))->toBe(42978220)
        ->and(Text::rmPriceSen('rm 5'))->toBe(500)
        ->and(Text::rmPriceSen('28,800.00'))->toBeNull()
        ->and(Text::rmPriceSen(null))->toBeNull();
});

it('splits comma-separated field codes', function () {
    expect(Text::fieldCodes('E05, E32,'))->toBe(['E05', 'E32'])
        ->and(Text::fieldCodes(''))->toBe([]);
});

it('stamps observations in sortable UTC ISO form', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 04:05:06.789', 'UTC'));
    expect(Text::scrapedAtNow())->toBe('2026-10-06T04:05:06.789Z');
});
```

- [ ] **Step 6: Run — expect FAIL** (`Class "App\Collector\Support\Text" not found`)

Run: `docker compose exec -T app ./vendor/bin/pest --colors=never tests/Unit/Collector/TextTest.php`

- [ ] **Step 7: Implement**

`app/Collector/CollectorException.php`:

```php
<?php

namespace App\Collector;

use RuntimeException;

/** A download or page-reading failure that makes one source fail for this run. */
class CollectorException extends RuntimeException {}
```

`app/Collector/Support/Text.php`:

```php
<?php

namespace App\Collector\Support;

use Carbon\CarbonImmutable;

/** Port of tms-v2 backend/src/parsing/text.ts. */
final class Text
{
    public static function clean(?string $s): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $s ?? ''));
    }

    public static function ddmmyyyy(?string $s): ?string
    {
        return self::date($s, '/^(\d{2})\/(\d{2})\/(\d{4})$/', 3, 2, 1);
    }

    public static function isoPrefix(?string $s): ?string
    {
        return self::date($s, '/^(\d{4})-(\d{2})-(\d{2})/', 1, 2, 3);
    }

    public static function dotted(?string $s): ?string
    {
        return self::date($s, '/^(\d{2})\.(\d{2})\.(\d{4})/', 3, 2, 1);
    }

    public static function dashed(?string $s): ?string
    {
        return self::date($s, '/^(\d{2})-(\d{2})-(\d{4})$/', 3, 2, 1);
    }

    public static function rmPriceSen(?string $s): ?int
    {
        if ($s === null || $s === '') {
            return null;
        }
        if (! preg_match('/RM\s*(\d+(?:\.\d+)?)/i', str_replace(',', '', $s), $m)) {
            return null;
        }

        return (int) round(((float) $m[1]) * 100);
    }

    /** @return list<string> */
    public static function fieldCodes(?string $s): array
    {
        if ($s === null || $s === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $s)), fn ($c) => $c !== ''));
    }

    public static function scrapedAtNow(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    private static function date(?string $s, string $pattern, int $y, int $m, int $d): ?string
    {
        if ($s === null || ! preg_match($pattern, trim($s), $x)) {
            return null;
        }
        if (! checkdate((int) $x[$m], (int) $x[$d], (int) $x[$y])) {
            return null;
        }

        return "{$x[$y]}-{$x[$m]}-{$x[$d]}";
    }
}
```

- [ ] **Step 8: Run full suite — expect PASS.** `docker compose exec -T app ./vendor/bin/pest --colors=never`

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "chore: collector dependencies, worker container, fixtures and text helpers

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: TenderPatch value object

**Files:**
- Create: `app/Collector/TenderPatch.php`
- Test: `tests/Unit/Collector/TenderPatchTest.php`

**Interfaces:**
- Produces: `TenderPatch::make(array $a): TenderPatch` (throws `InvalidArgumentException`), `TenderPatch::dedupKey(string $referenceNo, string $fallback): string`, readonly props `dedupKey, referenceNo, title, status, procurementType, scrapedAt, source, sourceId, sourceUrl`, `array $observed` (only optional fields this job saw, snake_case keys from `TenderPatch::OPTIONAL`), `has(string): bool`, `get(string): mixed`, `with(array): TenderPatch`.
- Required keys for `make`: `reference_no, title, status, procurement_type, scraped_at, source, source_id, source_url`; `dedup_key` optional (computed). Optional keys: `ministry, agency, category, field_codes, advertised_date, closing_date, indicative_price_sen, events (list of {label,date,address}), winners (list of {name,price_sen}|null), raw (array<string,string>)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\TenderPatch;

function patchInput(array $o = []): array
{
    return array_merge([
        'reference_no' => 'QT 123', 'title' => 'T', 'status' => 'open', 'procurement_type' => 'quotation',
        'scraped_at' => '2026-07-07T12:00:00.000Z', 'source' => 'myprocurement', 'source_id' => '789',
        'source_url' => 'https://myprocurement.treasury.gov.my/x',
    ], $o);
}

it('computes the dedup key like tms-v2', function () {
    expect(TenderPatch::dedupKey(' qt 12 3 ', 'x:1'))->toBe('QT123')
        ->and(TenderPatch::dedupKey('  ', 'myprocurement:789'))->toBe('myprocurement:789')
        ->and(TenderPatch::make(patchInput())->dedupKey)->toBe('QT123')
        ->and(TenderPatch::make(patchInput(['reference_no' => '']))->dedupKey)->toBe('myprocurement:789');
});

it('keeps only the optional fields that were observed', function () {
    $p = TenderPatch::make(patchInput(['ministry' => null, 'winners' => [['name' => 'A', 'price_sen' => 100]]]));

    expect($p->has('ministry'))->toBeTrue()
        ->and($p->get('ministry'))->toBeNull()
        ->and($p->has('closing_date'))->toBeFalse()
        ->and($p->get('winners'))->toBe([['name' => 'A', 'price_sen' => 100]]);
});

it('returns a copy with extra fields', function () {
    $p = TenderPatch::make(patchInput());
    $q = $p->with(['winners' => null]);

    expect($p->has('winners'))->toBeFalse()->and($q->has('winners'))->toBeTrue();
});

it('rejects invalid observations', function (array $bad) {
    TenderPatch::make(patchInput($bad));
})->with([
    'empty title' => [['title' => '  ']],
    'bad status' => [['status' => 'pending']],
    'bad type' => [['procurement_type' => 'auction']],
    'no source id' => [['source_id' => '']],
    'bad url' => [['source_url' => 'not a url']],
    'unknown field' => [['colour' => 'red']],
    'winner without name' => [['winners' => [['name' => '', 'price_sen' => 1]]]],
    'event without label' => [['events' => [['label' => '', 'date' => null, 'address' => null]]]],
])->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run — expect FAIL** (class not found): `docker compose exec -T app ./vendor/bin/pest --colors=never tests/Unit/Collector/TenderPatchTest.php`

- [ ] **Step 3: Implement `app/Collector/TenderPatch.php`**

```php
<?php

namespace App\Collector;

use InvalidArgumentException;

/** One observation of one tender by one collector job (port of tms-v2 TenderPatch). */
final class TenderPatch
{
    public const OPTIONAL = [
        'ministry', 'agency', 'category', 'field_codes', 'advertised_date', 'closing_date',
        'indicative_price_sen', 'events', 'winners', 'raw',
    ];

    private const REQUIRED = [
        'reference_no', 'title', 'status', 'procurement_type', 'scraped_at', 'source', 'source_id', 'source_url',
    ];

    private function __construct(
        public readonly string $dedupKey,
        public readonly string $referenceNo,
        public readonly string $title,
        public readonly string $status,
        public readonly ?string $procurementType,
        public readonly string $scrapedAt,
        public readonly string $source,
        public readonly string $sourceId,
        public readonly string $sourceUrl,
        public readonly array $observed,
    ) {}

    public static function dedupKey(string $referenceNo, string $fallback): string
    {
        $normalized = preg_replace('/\s+/u', '', mb_strtoupper($referenceNo));

        return $normalized !== '' ? $normalized : $fallback;
    }

    public static function make(array $a): self
    {
        foreach (self::REQUIRED as $key) {
            if (! array_key_exists($key, $a)) {
                throw new InvalidArgumentException("Missing {$key}");
            }
        }
        $unknown = array_diff(array_keys($a), [...self::REQUIRED, ...self::OPTIONAL, 'dedup_key']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown fields: '.implode(', ', $unknown));
        }
        if (trim((string) $a['title']) === '') {
            throw new InvalidArgumentException('Title is empty');
        }
        if (! in_array($a['status'], ['open', 'closed'], true)) {
            throw new InvalidArgumentException("Bad status {$a['status']}");
        }
        if (! in_array($a['procurement_type'], ['quotation', 'tender', 'requisition', null], true)) {
            throw new InvalidArgumentException('Bad procurement type');
        }
        if (trim((string) $a['source_id']) === '' || trim((string) $a['source']) === '') {
            throw new InvalidArgumentException('Missing source id');
        }
        if (filter_var($a['source_url'], FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Bad source url {$a['source_url']}");
        }
        foreach ($a['winners'] ?? [] as $w) {
            if (trim((string) ($w['name'] ?? '')) === '') {
                throw new InvalidArgumentException('Winner without a name');
            }
        }
        foreach ($a['events'] ?? [] as $e) {
            if (trim((string) ($e['label'] ?? '')) === '') {
                throw new InvalidArgumentException('Event without a label');
            }
        }

        $fallback = "{$a['source']}:{$a['source_id']}";

        return new self(
            dedupKey: $a['dedup_key'] ?? self::dedupKey((string) $a['reference_no'], $fallback),
            referenceNo: (string) $a['reference_no'],
            title: (string) $a['title'],
            status: $a['status'],
            procurementType: $a['procurement_type'],
            scrapedAt: (string) $a['scraped_at'],
            source: (string) $a['source'],
            sourceId: (string) $a['source_id'],
            sourceUrl: (string) $a['source_url'],
            observed: array_intersect_key($a, array_flip(self::OPTIONAL)),
        );
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->observed);
    }

    public function get(string $field): mixed
    {
        return $this->observed[$field] ?? null;
    }

    public function with(array $fields): self
    {
        return self::make([
            'dedup_key' => $this->dedupKey, 'reference_no' => $this->referenceNo, 'title' => $this->title,
            'status' => $this->status, 'procurement_type' => $this->procurementType, 'scraped_at' => $this->scrapedAt,
            'source' => $this->source, 'source_id' => $this->sourceId, 'source_url' => $this->sourceUrl,
            ...$this->observed, ...$fields,
        ]);
    }
}
```

- [ ] **Step 4: Run full suite — expect PASS.**
- [ ] **Step 5: Commit** — `feat: TenderPatch value object for collected observations`

---

### Task 3: MyProcurement parsers

**Files:**
- Create: `app/Collector/Parsers/MyProcurementParser.php`, `app/Collector/Parsers/Dom.php`
- Test: `tests/Unit/Collector/MyProcurementParserTest.php`

**Interfaces:**
- Consumes: `Text`, `TenderPatch`
- Produces: `MyProcurementParser::listing(string $html, string $status, string $procurementType, string $scrapedAt): list<TenderPatch>`; `MyProcurementParser::results(string $html, string $procurementType, string $scrapedAt): list<TenderPatch>`; `Dom::load(string $html): Crawler` (HTML5 parsing).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\Parsers\MyProcurementParser;

const MP_NOW = '2026-07-07T12:00:00.000Z';

function mpCard(): string
{
    // Verbatim from tms-v2 test/parseListing.test.ts CARD_HTML.
    return <<<'HTML'
<div>
  <div x-data="{ selected: false, open: true }" class="flex flex-col">
    <div class="flex">
      <button x-on:click="selected = !selected; $dispatch('select-procurement', { id: 789195 })"></button>
    </div>
    <div class="flex-grow text-sm md:text-base break-words">
      <div>
        <div class="mx-4 px-4 py-2 inline-block rounded-md bg-primary/20">
          Tarikh Pelawaan: 07/07/2026
        </div>
        <div class="px-4 py-2 rounded-md">
          <span class="font-bold">No. Sebut Harga</span>: UTHM/54(KTKEM)/P/02/023/2026(1)
        </div>
        <div class="px-4 py-2 rounded-md text-justify font-bold text-primary uppercase">
          <a href="https://myprocurement.treasury.gov.my/advertisements/quotation/71ebb6ee">MAKMAL ELEKTRIK &amp; ELEKTRONIK 2</a>
        </div>
        <div x-show="open" class="flex flex-col w-full px-4">
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Kementerian:</div>
            <div class="w-full sm:w-2/3 uppercase">KEMENTERIAN PENDIDIKAN TINGGI</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Agensi:</div>
            <div class="w-full sm:w-2/3 uppercase">UNIVERSITI TUN HUSSEIN ONN MALAYSIA (UTHM)</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Kategori Perolehan:</div>
            <div class="w-full sm:w-2/3 uppercase">Perkhidmatan Bukan Perunding</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Kod Bidang:</div>
            <div class="w-full sm:w-2/3 uppercase">E05, E32</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Tarikh Tutup Pelawaan:</div>
            <div class="w-full sm:w-2/3 uppercase">17/07/2026</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Harga Indikatif Jabatan:</div>
            <div class="w-full sm:w-2/3 uppercase">RM 28,800.00</div>
          </div>
        </div>
        <div x-show="open" class="mt-2 w-full">
          <table class="w-full hidden md:block">
            <tr class="bg-primary/20"><th>Bil.</th><th>Perkara</th><th>Tarikh</th><th>Alamat</th></tr>
            <tr class="uppercase">
              <td>1.</td>
              <td>Lawatan Tapak</td>
              <td>10/07/2026</td>
              <td class="w-full">MAKMAL OR, BLOK A, STRIDE, KAJANG, SELANGOR</td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
HTML;
}

function mpResultsCard(string $rows = '<tr><td>1.</td><td>EVERLASTING LUCK SDN. BHD.</td><td>72,000.00</td></tr>'): string
{
    // Verbatim shell from tms-v2 test/parseResults.test.ts SINGLE_WINNER_CARD.
    return <<<HTML
<div x-data="{ selected: false, open: true }">
  <button x-on:click="\$dispatch('select-procurement', { id: 980576 })"></button>
  <div class="mx-4 px-4 py-2 inline-block rounded-md bg-primary/20">Tarikh Paparan Keputusan: 01/07/2026</div>
  <div class="px-4 py-2 rounded-md"><span class="font-bold">No. Sebut Harga</span>: 52000003</div>
  <div class="font-bold text-primary uppercase">
    <a href="https://myprocurement.treasury.gov.my/archive/results-quotation/f256becbbb44e73ed436120b9b0ab381">PERKHIDMATAN SEWAAN 45 UNIT RUMAH KELUARGA</a>
  </div>
  <div class="w-full flex flex-col px-4">
    <div class="font-bold align-top">Kementerian:</div><div>KEMENTERIAN PERTAHANAN</div>
    <div class="font-bold align-top">Agensi:</div><div>TENTERA LAUT DIRAJA MALAYSIA (TLDM)</div>
    <div class="font-bold align-top">Kategori Perolehan:</div><div>Perkhidmatan Bukan Perunding</div>
  </div>
  <table>
    <tr><th>Bil.</th><th>Nama Petender Berjaya</th><th>Harga Setuju Terima (RM)</th></tr>
    {$rows}
  </table>
</div>
HTML;
}

function mpFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/collector/{$name}.json")), true);
}

it('extracts every field from a listing card', function () {
    [$t] = MyProcurementParser::listing(mpCard(), 'open', 'quotation', MP_NOW);

    expect($t->source)->toBe('myprocurement')
        ->and($t->sourceId)->toBe('789195')
        ->and($t->sourceUrl)->toBe('https://myprocurement.treasury.gov.my/advertisements/quotation/71ebb6ee')
        ->and($t->referenceNo)->toBe('UTHM/54(KTKEM)/P/02/023/2026(1)')
        ->and($t->dedupKey)->toBe('UTHM/54(KTKEM)/P/02/023/2026(1)')
        ->and($t->title)->toBe('MAKMAL ELEKTRIK & ELEKTRONIK 2')
        ->and($t->status)->toBe('open')
        ->and($t->procurementType)->toBe('quotation')
        ->and($t->get('ministry'))->toBe('KEMENTERIAN PENDIDIKAN TINGGI')
        ->and($t->get('agency'))->toBe('UNIVERSITI TUN HUSSEIN ONN MALAYSIA (UTHM)')
        ->and($t->get('category'))->toBe('Perkhidmatan Bukan Perunding')
        ->and($t->get('field_codes'))->toBe(['E05', 'E32'])
        ->and($t->get('advertised_date'))->toBe('2026-07-07')
        ->and($t->get('closing_date'))->toBe('2026-07-17')
        ->and($t->get('indicative_price_sen'))->toBe(2880000)
        ->and($t->get('events'))->toBe([['label' => 'Lawatan Tapak', 'date' => '2026-07-10', 'address' => 'MAKMAL OR, BLOK A, STRIDE, KAJANG, SELANGOR']])
        ->and($t->get('raw')['No. Sebut Harga'])->toBe('UTHM/54(KTKEM)/P/02/023/2026(1)')
        ->and($t->get('raw')['Harga Indikatif Jabatan'])->toBe('RM 28,800.00')
        ->and($t->scrapedAt)->toBe(MP_NOW);
});

it('tags status and type from the job, not the page', function () {
    [$t] = MyProcurementParser::listing(mpCard(), 'closed', 'tender', MP_NOW);
    expect($t->status)->toBe('closed')->and($t->procurementType)->toBe('tender');
});

it('skips cards without a title link, and ignores non-card x-data wrappers', function () {
    $noLink = preg_replace('/<a href="[^"]*">.*?<\/a>/s', '', mpCard());
    expect(MyProcurementParser::listing($noLink, 'open', 'quotation', MP_NOW))->toBe([]);

    $withPager = '<div x-data="{ page: 1 }">pager</div>'.mpCard();
    expect(MyProcurementParser::listing($withPager, 'open', 'quotation', MP_NOW))->toHaveCount(1);
});

it('defaults a missing event address to null and falls back the dedup key', function () {
    $html = str_replace('<td class="w-full">MAKMAL OR, BLOK A, STRIDE, KAJANG, SELANGOR</td>', '<td class="w-full"></td>', mpCard());
    $html = str_replace(': UTHM/54(KTKEM)/P/02/023/2026(1)', ':', $html);
    [$t] = MyProcurementParser::listing($html, 'open', 'quotation', MP_NOW);

    expect($t->get('events')[0]['address'])->toBeNull()
        ->and($t->referenceNo)->toBe('')
        ->and($t->dedupKey)->toBe('myprocurement:789195');
});

it('parses every card in each saved listing page', function (string $file, string $status, string $type) {
    $page = mpFixture($file);
    $patches = MyProcurementParser::listing($page['html'], $status, $type, MP_NOW);
    preg_match_all("/select-procurement'?,?\s*\{\s*id:\s*(\d+)/", $page['html'], $m);

    expect($patches)->not->toBeEmpty()
        ->and(collect($patches)->pluck('sourceId')->sort()->values()->all())->toBe(collect($m[1])->unique()->sort()->values()->all());
    foreach ($patches as $t) {
        expect($t->status)->toBe($status)->and($t->procurementType)->toBe($type);
    }
})->with([
    ['open-quotation-p1', 'open', 'quotation'],
    ['open-tender-p1', 'open', 'tender'],
    ['open-requisition-p1', 'open', 'requisition'],
    ['archive-quotation-p1', 'closed', 'quotation'],
]);

it('extracts a results card with a single winner and only the fields results pages carry', function () {
    [$t] = MyProcurementParser::results(mpResultsCard(), 'quotation', MP_NOW);

    expect($t->sourceId)->toBe('980576')
        ->and($t->referenceNo)->toBe('52000003')
        ->and($t->status)->toBe('closed')
        ->and($t->procurementType)->toBe('quotation')
        ->and($t->get('ministry'))->toBe('KEMENTERIAN PERTAHANAN')
        ->and($t->get('agency'))->toBe('TENTERA LAUT DIRAJA MALAYSIA (TLDM)')
        ->and($t->get('winners'))->toBe([['name' => 'EVERLASTING LUCK SDN. BHD.', 'price_sen' => 7200000]])
        ->and($t->has('field_codes'))->toBeFalse()
        ->and($t->has('closing_date'))->toBeFalse()
        ->and($t->has('indicative_price_sen'))->toBeFalse();
});

it('parses multi-lot winners', function () {
    $rows = '<tr><td>1.</td><td>DOUBLE R ENTERPRISE</td><td>15,000.00</td></tr><tr><td>2.</td><td>IMPIAN BENTARA</td><td>429,782.20</td></tr>';
    [$t] = MyProcurementParser::results(mpResultsCard($rows), 'tender', MP_NOW);

    expect($t->procurementType)->toBe('tender')
        ->and($t->get('winners'))->toBe([
            ['name' => 'DOUBLE R ENTERPRISE', 'price_sen' => 1500000],
            ['name' => 'IMPIAN BENTARA', 'price_sen' => 42978220],
        ]);
});

it('parses every card in the saved results page, each with winners', function () {
    $page = mpFixture('results-quotation-p1');
    $patches = MyProcurementParser::results($page['html'], 'quotation', MP_NOW);

    expect($patches)->toHaveCount(2);
    foreach ($patches as $t) {
        expect($t->status)->toBe('closed')->and($t->get('winners'))->not->toBeEmpty();
    }
});

it('returns nothing (never throws) for unrelated html', function () {
    expect(MyProcurementParser::listing('<p>maintenance</p>', 'open', 'tender', MP_NOW))->toBe([])
        ->and(MyProcurementParser::results('', 'tender', MP_NOW))->toBe([]);
});
```

- [ ] **Step 2: Run — expect FAIL** (class not found).

- [ ] **Step 3: Implement**

`app/Collector/Parsers/Dom.php`:

```php
<?php

namespace App\Collector\Parsers;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

/** HTML5 parsing (masterminds/html5), matching how browsers and cheerio read these pages. */
final class Dom
{
    public static function load(string $html): Crawler
    {
        $crawler = new Crawler(null, null, null, true);
        $crawler->addHtmlContent($html === '' ? '<html></html>' : $html, 'UTF-8');

        return $crawler;
    }

    public static function text(Crawler $node): string
    {
        return $node->count() ? \App\Collector\Support\Text::clean($node->text('', false)) : '';
    }

    public static function nodeText(?DOMNode $node): string
    {
        return \App\Collector\Support\Text::clean($node?->textContent);
    }
}
```

`app/Collector/Parsers/MyProcurementParser.php`:

```php
<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use App\Collector\TenderPatch;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/** Port of tms-v2 scrapers/myprocurement/parseListing.ts and parseResults.ts. */
final class MyProcurementParser
{
    private const SOURCE = 'myprocurement';

    /** @return list<TenderPatch> */
    public static function listing(string $html, string $status, string $procurementType, string $scrapedAt): array
    {
        return self::eachCard($html, function (Crawler $card) use ($status, $procurementType, $scrapedAt) {
            $base = self::identity($card);
            if ($base === null) {
                return null;
            }
            [$sourceId, $title, $sourceUrl, $raw, $referenceNo] = $base;

            if (preg_match('/Tarikh Pelawaan:\s*([\d\/]+)/', $card->text('', false), $m)) {
                $raw['Tarikh Pelawaan'] = $m[1];
            }

            $events = [];
            $card->filter('table tr')->each(function (Crawler $row) use (&$events) {
                $cells = $row->filter('td');
                if ($cells->count() < 4) {
                    return;
                }
                $address = Dom::text($cells->eq(3));
                $events[] = [
                    'label' => Dom::text($cells->eq(1)),
                    'date' => Text::ddmmyyyy(Dom::text($cells->eq(2))),
                    'address' => $address !== '' ? $address : null,
                ];
            });

            return [
                'reference_no' => $referenceNo, 'title' => $title, 'status' => $status,
                'procurement_type' => $procurementType, 'scraped_at' => $scrapedAt,
                'source' => self::SOURCE, 'source_id' => $sourceId, 'source_url' => $sourceUrl,
                'ministry' => ($raw['Kementerian'] ?? '') ?: null,
                'agency' => ($raw['Agensi'] ?? '') ?: null,
                'category' => ($raw['Kategori Perolehan'] ?? '') ?: null,
                'field_codes' => Text::fieldCodes($raw['Kod Bidang'] ?? null),
                'advertised_date' => Text::ddmmyyyy($raw['Tarikh Pelawaan'] ?? null),
                'closing_date' => Text::ddmmyyyy($raw['Tarikh Tutup Pelawaan'] ?? null),
                'indicative_price_sen' => Text::rmPriceSen($raw['Harga Indikatif Jabatan'] ?? null),
                'events' => $events,
                'raw' => $raw,
            ];
        });
    }

    /** @return list<TenderPatch> */
    public static function results(string $html, string $procurementType, string $scrapedAt): array
    {
        return self::eachCard($html, function (Crawler $card) use ($procurementType, $scrapedAt) {
            $base = self::identity($card);
            if ($base === null) {
                return null;
            }
            [$sourceId, $title, $sourceUrl, $raw, $referenceNo] = $base;

            // The header row has <th> cells, so "at least 3 <td>" skips it naturally.
            $winners = [];
            $card->filter('table tr')->each(function (Crawler $row) use (&$winners) {
                $cells = $row->filter('td');
                if ($cells->count() < 3) {
                    return;
                }
                $name = Dom::text($cells->eq(1));
                if ($name === '') {
                    return;
                }
                $winners[] = ['name' => $name, 'price_sen' => Text::rmPriceSen('RM '.Dom::text($cells->eq(2)))];
            });

            return [
                'reference_no' => $referenceNo, 'title' => $title, 'status' => 'closed',
                'procurement_type' => $procurementType, 'scraped_at' => $scrapedAt,
                'source' => self::SOURCE, 'source_id' => $sourceId, 'source_url' => $sourceUrl,
                'ministry' => ($raw['Kementerian'] ?? '') ?: null,
                'agency' => ($raw['Agensi'] ?? '') ?: null,
                'category' => ($raw['Kategori Perolehan'] ?? '') ?: null,
                'winners' => $winners,
                'raw' => $raw,
            ];
        });
    }

    /** @return array{0:string,1:string,2:string,3:array<string,string>,4:string}|null */
    private static function identity(Crawler $card): ?array
    {
        if (! preg_match("/select-procurement'?,?\s*\{\s*id:\s*(\d+)/", $card->html(''), $m)) {
            return null;
        }
        $link = $card->filter('div.font-bold.text-primary a')->first();
        $title = Dom::text($link);
        $sourceUrl = $link->count() ? (string) $link->attr('href') : '';
        if ($title === '' || $sourceUrl === '') {
            return null;
        }

        $raw = [];
        $card->filter('div.font-bold.align-top')->each(function (Crawler $label) use (&$raw) {
            $name = preg_replace('/:$/', '', Dom::text($label));
            $next = $label->nextAll()->first();
            $value = ($next->count() && $next->nodeName() === 'div') ? Dom::text($next) : '';
            if ($name !== '') {
                $raw[$name] = $value;
            }
        });

        $referenceNo = '';
        $card->filter('span.font-bold')->each(function (Crawler $span) use (&$raw, &$referenceNo) {
            $label = Dom::text($span);
            if (! str_starts_with($label, 'No.')) {
                return;
            }
            $parentText = Dom::nodeText($span->getNode(0)->parentNode);
            $after = substr($parentText, strpos($parentText, $label) + strlen($label));
            $referenceNo = Text::clean(preg_replace('/^:/', '', $after));
            $raw[$label] = $referenceNo;
        });

        return [$m[1], $title, $sourceUrl, $raw, $referenceNo];
    }

    /** @return list<TenderPatch> */
    private static function eachCard(string $html, callable $build): array
    {
        $patches = [];
        Dom::load($html)->filter('div[x-data]')->each(function (Crawler $card) use ($build, &$patches) {
            if (! str_contains((string) $card->attr('x-data'), 'selected')) {
                return; // pagination wrapper etc.
            }
            $candidate = $build($card);
            if ($candidate === null) {
                return;
            }
            try {
                $patches[] = TenderPatch::make($candidate);
            } catch (InvalidArgumentException $e) {
                Log::warning('[myprocurement] skipping invalid card: '.$e->getMessage());
            }
        });

        return $patches;
    }
}
```

- [ ] **Step 4: Run full suite — expect PASS.** If a fixture test fails, compare with the tms-v2 output by running its test (`cd /c/Projects/tms-v2 && npx vitest run backend/test/parseListing.test.ts`) and fix the PHP port, not the test.
- [ ] **Step 5: Commit** — `feat: MyProcurement listing and results parsers`

---

### Task 4: SPAN parsers

**Files:**
- Create: `app/Collector/Parsers/SpanParser.php`
- Test: `tests/Unit/Collector/SpanParserTest.php`

**Interfaces:**
- Produces: `SpanParser::listing(string $html, string $scrapedAt): list<TenderPatch>` (agency `Suruhanjaya Perkhidmatan Air Negara (SPAN)`, observes only `agency, advertised_date, closing_date, raw`); `SpanParser::winners(string $html): list<array{name:string,price_sen:int}>`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\Parsers\SpanParser;

const SPAN_NOW = '2026-07-09T12:00:00.000Z';

function spanCard(): string
{
    return <<<'HTML'
<div class="table-listing">
    <a href="https://www.span.gov.my/tender/view/188">
        <h3>SPAN/BKP/PROC/STM/26(8)</h3>
        CADANGAN UNTUK MELANTIK PERUNDING BAGI PELAKSANAAN KAJIAN HALA TUJU PELAN STRATEGIK ICT (GAP ANALYSIS) SURUHANJAYA PERKHIDMATAN AIR NEGARA (SPAN) 2026-2030 SECARA SEBUT HARGA TERBUKA<br>
        Tarikh Iklan 2026-06-22<br>
        Tarikh Tutup 2026-07-06 12:00PM<br>
        Maklumat Sebutharga:
            <span class="badge badge-warning">Diiklankan</span>
                </a>
</div>
HTML;
}

it('extracts every field from a listing card', function () {
    [$t] = SpanParser::listing(spanCard(), SPAN_NOW);

    expect($t->source)->toBe('span')
        ->and($t->sourceId)->toBe('188')
        ->and($t->sourceUrl)->toBe('https://www.span.gov.my/tender/view/188')
        ->and($t->referenceNo)->toBe('SPAN/BKP/PROC/STM/26(8)')
        ->and($t->title)->toBe('CADANGAN UNTUK MELANTIK PERUNDING BAGI PELAKSANAAN KAJIAN HALA TUJU PELAN STRATEGIK ICT (GAP ANALYSIS) SURUHANJAYA PERKHIDMATAN AIR NEGARA (SPAN) 2026-2030 SECARA SEBUT HARGA TERBUKA')
        ->and($t->status)->toBe('open')
        ->and($t->procurementType)->toBe('quotation')
        ->and($t->get('agency'))->toBe('Suruhanjaya Perkhidmatan Air Negara (SPAN)')
        ->and($t->get('advertised_date'))->toBe('2026-06-22')
        ->and($t->get('closing_date'))->toBe('2026-07-06')
        ->and($t->get('raw')['Status'])->toBe('Diiklankan');
    foreach (['ministry', 'category', 'field_codes', 'indicative_price_sen', 'events', 'winners'] as $f) {
        expect($t->has($f))->toBeFalse();
    }
});

it('maps Selesai and Dibatalkan badges to closed', function () {
    expect(SpanParser::listing(str_replace('badge-warning">Diiklankan', 'badge-info">Selesai', spanCard()), SPAN_NOW)[0]->status)->toBe('closed')
        ->and(SpanParser::listing(str_replace('Diiklankan', 'Dibatalkan', spanCard()), SPAN_NOW)[0]->status)->toBe('closed');
});

it('infers the type from the title', function () {
    expect(SpanParser::listing(str_replace('SECARA SEBUT HARGA TERBUKA', 'SECARA TENDER TERBUKA', spanCard()), SPAN_NOW)[0]->procurementType)->toBe('tender')
        ->and(SpanParser::listing(str_replace('SECARA SEBUT HARGA TERBUKA', 'UNTUK KEGUNAAN SURUHANJAYA', spanCard()), SPAN_NOW)[0]->procurementType)->toBeNull();
});

it('skips cards with no link or empty reference', function () {
    expect(SpanParser::listing(str_replace('href="https://www.span.gov.my/tender/view/188"', '', spanCard()), SPAN_NOW))->toBe([])
        ->and(SpanParser::listing(str_replace('<h3>SPAN/BKP/PROC/STM/26(8)</h3>', '<h3></h3>', spanCard()), SPAN_NOW))->toBe([]);
});

it('parses the saved 2026 page: 5 tenders, 3 open, 2 closed', function () {
    $patches = SpanParser::listing(file_get_contents(base_path('tests/Fixtures/collector/span-2026.html')), SPAN_NOW);

    expect($patches)->toHaveCount(5)
        ->and(collect($patches)->where('status', 'open'))->toHaveCount(3)
        ->and(collect($patches)->where('status', 'closed'))->toHaveCount(2);
});

it('reads winners from adjacent or colon-separated cells, ignoring bidder tables', function () {
    $adjacent = '<table><tr><td>Kod Penyebut Harga</td><td>Kos</td></tr><tr><td>Petender 1/4</td><td>RM150,377.47</td></tr></table>'
        .'<table><tr><td>Nama Pembekal</td><td><p>UMPSA SERVICES SDN BHD</p></td><td>Harga Tawaran</td><td><b><span>RM132,192.00</span></b><br></td></tr>'
        .'<tr><td>Tarikh Mula Kontrak</td><td>-</td><td>Tarikh Tamat Kontrak</td><td>-</td></tr></table>';
    $colon = '<table><tr><td>Nama Pembekal</td><td><div align="center">:</div></td><td><p>RANHILL CONSULTING SDN BHD</p></td>'
        .'<td>Harga Tawaran</td><td><div align="center">:</div></td><td>RM1,285,996.03</td></tr></table>';
    $multi = '<table><tr><td>Nama Pembekal</td><td>ALPHA ENGINEERING SDN BHD</td><td>Harga Tawaran</td><td>RM50,000.00</td></tr></table>'
        .'<table><tr><td>Nama Pembekal</td><td>BETA CONSTRUCTION SDN BHD</td><td>Harga Tawaran</td><td>RM75,500.50</td></tr></table>';

    expect(SpanParser::winners($adjacent))->toBe([['name' => 'UMPSA SERVICES SDN BHD', 'price_sen' => 13219200]])
        ->and(SpanParser::winners($colon))->toBe([['name' => 'RANHILL CONSULTING SDN BHD', 'price_sen' => 128599603]])
        ->and(SpanParser::winners($multi))->toBe([
            ['name' => 'ALPHA ENGINEERING SDN BHD', 'price_sen' => 5000000],
            ['name' => 'BETA CONSTRUCTION SDN BHD', 'price_sen' => 7550050],
        ]);
});

it('returns no winners for postponed, cancelled or unrelated pages', function () {
    $postponed = '<table><tr><td>Nama Pembekal</td><td>:</td><td colspan="4"><p>SEBUTHARGA DITANGGUHKAN</p></td></tr></table>';
    expect(SpanParser::winners($postponed))->toBe([])
        ->and(SpanParser::winners('<p>Dibatalkan <br>*Sebutharga terbatal</p>'))->toBe([])
        ->and(SpanParser::winners(''))->toBe([]);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement `app/Collector/Parsers/SpanParser.php`**

```php
<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use App\Collector\TenderPatch;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/** Port of tms-v2 scrapers/span/parseListing.ts and parseDetail.ts. */
final class SpanParser
{
    public const AGENCY = 'Suruhanjaya Perkhidmatan Air Negara (SPAN)';

    /** @return list<TenderPatch> */
    public static function listing(string $html, string $scrapedAt): array
    {
        $patches = [];
        Dom::load($html)->filter('div.table-listing')->each(function (Crawler $card) use ($scrapedAt, &$patches) {
            $link = $card->filter('a')->first();
            $sourceUrl = $link->count() ? (string) $link->attr('href') : '';
            if (! preg_match('/\/tender\/view\/(\d+)/', $sourceUrl, $id)) {
                return;
            }
            $referenceNo = Dom::text($link->filter('h3')->first());
            if ($referenceNo === '') {
                return;
            }
            $fullText = Dom::text($link);
            $afterHeading = substr($fullText, strpos($fullText, $referenceNo) + strlen($referenceNo));
            $title = preg_match('/^(.*?)\s*Tarikh Iklan/', $afterHeading, $t) ? Text::clean($t[1]) : '';
            if ($title === '') {
                return;
            }
            $advertised = preg_match('/Tarikh Iklan\s*([\d-]+)/', $fullText, $a) ? $a[1] : null;
            $closing = preg_match('/Tarikh Tutup\s*([\d-]+(?:\s+\d{1,2}:\d{2}[AP]M)?)/', $fullText, $c) ? $c[1] : null;
            $badge = Dom::text($card->filter('.badge')->first());

            $raw = ['No Sebut Harga' => $referenceNo, 'Tajuk' => $title, 'Status' => $badge];
            if ($advertised !== null) {
                $raw['Tarikh Iklan'] = $advertised;
            }
            if ($closing !== null) {
                $raw['Tarikh Tutup'] = $closing;
            }

            try {
                $patches[] = TenderPatch::make([
                    'reference_no' => $referenceNo, 'title' => $title,
                    'status' => $badge === 'Diiklankan' ? 'open' : 'closed',
                    'procurement_type' => self::type($title), 'scraped_at' => $scrapedAt,
                    'source' => 'span', 'source_id' => $id[1], 'source_url' => $sourceUrl,
                    'agency' => self::AGENCY,
                    'advertised_date' => Text::isoPrefix($advertised),
                    'closing_date' => Text::isoPrefix($closing),
                    'raw' => $raw,
                ]);
            } catch (InvalidArgumentException $e) {
                Log::warning('[span] skipping invalid card: '.$e->getMessage());
            }
        });

        return $patches;
    }

    /** @return list<array{name:string,price_sen:int}> */
    public static function winners(string $html): array
    {
        $winners = [];
        Dom::load($html)->filter('tr')->each(function (Crawler $row) use (&$winners) {
            $cells = array_map(fn ($n) => Dom::nodeText($n), iterator_to_array($row->filter('td')));
            $nameIdx = array_search('Nama Pembekal', $cells, true);
            $priceIdx = array_search('Harga Tawaran', $cells, true);
            if ($nameIdx === false || $priceIdx === false) {
                return;
            }
            $name = self::valueAfter($cells, $nameIdx);
            $price = Text::rmPriceSen(self::valueAfter($cells, $priceIdx));
            if ($name === '' || $price === null) {
                return;
            }
            $winners[] = ['name' => $name, 'price_sen' => $price];
        });

        return $winners;
    }

    private static function valueAfter(array $cells, int $labelIdx): string
    {
        for ($i = $labelIdx + 1; $i < count($cells); $i++) {
            if ($cells[$i] !== '' && $cells[$i] !== ':') {
                return $cells[$i];
            }
        }

        return '';
    }

    private static function type(string $title): ?string
    {
        if (preg_match('/TENDER/i', $title)) {
            return 'tender';
        }

        return preg_match('/SEBUT\s*HARGA/i', $title) ? 'quotation' : null;
    }
}
```

- [ ] **Step 4: Run full suite — expect PASS.**
- [ ] **Step 5: Commit** — `feat: SPAN listing and winner parsers`

---

### Task 5: LLM parsers

**Files:**
- Create: `app/Collector/Parsers/LlmParser.php`
- Test: `tests/Unit/Collector/LlmParserTest.php`

**Interfaces:**
- Produces: `LlmParser::listing(string $html): list<array{source_id:string,source_url:string}>`; `LlmParser::results(string $html): list<array{source_id:string,source_url:string,winner:?array{name:string,price_sen:?int}}>`; `LlmParser::detail(string $html, string $sourceId, string $sourceUrl, string $status, string $scrapedAt): ?TenderPatch` (agency `Lembaga Lebuhraya Malaysia (LLM)`); `LlmParser::fieldCodes(string $text): list<string>`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\Parsers\LlmParser;

const LLM_NOW = '2026-07-16T12:00:00.000Z';
const LLM_URL = 'https://www.llm.gov.my/swasta/tender_detail/12543/';

function llmDetail(array $o = []): string
{
    // Same template as tms-v2 test/llmParseDetail.test.ts detailHtml().
    $o += ['title' => 'T', 'saleStart' => '20.07.2026', 'jenis' => 'Tender', 'kategori' => 'Kerja',
        'dipelawaKepada' => 'Syarikat-syarikat yang berdaftar dengan SSM', 'syaratPendaftaran' => '-',
        'closingDate' => '2026-10-14', 'lawatanTapak' => '-'];

    return <<<HTML
<div class="panel clear-padding" id="tender-table-head">
    <div class="panel-content" id="tender-printarea">
      <header style="font-weight: bold;">{$o['title']}<div style="float:right"><a href="#"><i class="fa fa-print"></i></a></div></header>
      <table class="tender-content"><tbody>
        <tr><td style="font-weight: bold;">Tarikh Mula Jualan Dokumen</td><td>{$o['saleStart']}</td></tr>
        <tr><td style="font-weight: bold;">Tarikh Tamat Jualan Dokumen</td><td>14.09.2026</td></tr>
        <tr><td style="font-weight: bold;">Tender / Sebutharga Adalah Dipelawa kepada</td><td>{$o['dipelawaKepada']}</td></tr>
        <tr><td style="font-weight: bold;">Jenis</td><td>{$o['jenis']}</td></tr>
        <tr><td style="font-weight: bold;">Kategori</td><td>{$o['kategori']}</td></tr>
        <tr><td style="font-weight: bold;">Syarat Pendaftaran</td><td>{$o['syaratPendaftaran']}</td></tr>
        <tr><td style="font-weight: bold;">Lawatan Tapak</td><td>{$o['lawatanTapak']}</td></tr>
        <tr><td style="font-weight: bold;">Tarikh dan Waktu Tutup</td><td>{$o['closingDate']}</td></tr>
      </tbody></table>
    </div>
  </div>
HTML;
}

function llmParse(array $o = [], string $status = 'open')
{
    return LlmParser::detail(llmDetail($o), '12543', LLM_URL, $status, LLM_NOW);
}

it('reads listing links, stripping the #fragment and de-duplicating', function () {
    $row = '<div id="tender-table-head"><table><tbody><tr><td><a href="https://www.llm.gov.my/swasta/tender_detail/12543/#tender-table-head"><header>TITLE ONE</header></a></td></tr></tbody></table></div>';

    expect(LlmParser::listing($row.$row))->toBe([['source_id' => '12543', 'source_url' => 'https://www.llm.gov.my/swasta/tender_detail/12543/']])
        ->and(LlmParser::listing('<div></div>'))->toBe([]);
});

it('parses the saved open listing page: 6 links', function () {
    $links = LlmParser::listing(file_get_contents(base_path('tests/Fixtures/collector/llm-tender_tawaran.html')));

    expect($links)->toHaveCount(6);
    foreach ($links as $l) {
        expect($l['source_url'])->toMatch('#^https://www\.llm\.gov\.my/swasta/tender_detail/\d+/$#');
    }
});

it('reads result rows with the winner', function () {
    $html = '<div id="tender-table-head"><table><tbody><tr><th>Tajuk</th><th>Kontraktor</th><th>Nilai</th></tr><tr>'
        ."<td><a href=\"\n   https://www.llm.gov.my/swasta/tender_detail/12526#tender-table-head\"><header>T</header></a></td>"
        ."<td>D'FA PRINT SDN BHD</td><td>RM 62180.00</td></tr></tbody></table></div>";
    $empty = str_replace(["D'FA PRINT SDN BHD", 'RM 62180.00'], ['', ''], $html);

    expect(LlmParser::results($html))->toBe([[
        'source_id' => '12526', 'source_url' => 'https://www.llm.gov.my/swasta/tender_detail/12526',
        'winner' => ['name' => "D'FA PRINT SDN BHD", 'price_sen' => 6218000],
    ]])->and(LlmParser::results($empty)[0]['winner'])->toBeNull();
});

it('parses the saved results page: 6 rows each with a winner', function () {
    $rows = LlmParser::results(file_get_contents(base_path('tests/Fixtures/collector/llm-tender_keputusan.html')));

    expect($rows)->toHaveCount(6);
    foreach ($rows as $r) {
        expect($r['winner'])->not->toBeNull();
    }
});

it('extracts every field from a detail page', function () {
    $t = llmParse(['title' => 'TAWARAN REQUEST FOR PROPOSAL (RFP) BAGI CADANGAN']);

    expect($t->source)->toBe('llm')
        ->and($t->sourceId)->toBe('12543')
        ->and($t->title)->toBe('TAWARAN REQUEST FOR PROPOSAL (RFP) BAGI CADANGAN')
        ->and($t->status)->toBe('open')
        ->and($t->procurementType)->toBe('tender')
        ->and($t->get('agency'))->toBe('Lembaga Lebuhraya Malaysia (LLM)')
        ->and($t->get('category'))->toBe('Kerja')
        ->and($t->get('advertised_date'))->toBe('2026-07-20')
        ->and($t->get('closing_date'))->toBe('2026-10-14')
        ->and($t->get('raw')['Jenis'])->toBe('Tender')
        ->and($t->dedupKey)->toBe('llm:12543');
});

it('extracts reference numbers in all three title shapes', function (string $title, string $ref) {
    $t = llmParse(['title' => $title, 'jenis' => 'Sebut Harga']);
    expect($t->referenceNo)->toBe($ref)->and($t->dedupKey)->toBe($ref)->and($t->procurementType)->toBe('quotation');
})->with([
    ['(NO. SEBUT HARGA: LLM/KEW/SH:9/6/2026) - SEBUT HARGA BAGI PERKHIDMATAN', 'LLM/KEW/SH:9/6/2026'],
    ['NO. SEBUT HARGA: LLM/KEW/SH:4/4/2026 - PERKHIDMATAN MEREKA BENTUK, MENTERJEMAH, MENCETAK DAN MEMBEKAL BUKU', 'LLM/KEW/SH:4/4/2026'],
    ['NO. SEBUT HARGA: LLM/KEW/SH:3/2/2026 SEBUT HARGA PERKHIDMATAN PEMULIHAN BENCANA', 'LLM/KEW/SH:3/2/2026'],
]);

it('maps an unknown Jenis to null and honours the given status', function () {
    expect(llmParse(['jenis' => 'Lain-lain'])->procurementType)->toBeNull()
        ->and(llmParse([], 'closed')->status)->toBe('closed');
});

it('extracts field codes from the registration text', function () {
    expect(llmParse(['dipelawaKepada' => 'Pelawaan adalah terbuka kepada Syarikat Bumiputera dan Bukan Bumiputera yang berkelayakan dan berdaftar dengan Kementerian Kewangan Malaysia di bawah Kod Bidang: 2221302 - (Rakaman) 221304 - (Audio Visual) atau 221303 - (Fotografi) yang mana pendaftarannya masih berkuatkuasa.'])->get('field_codes'))
        ->toBe(['2221302', '221304', '221303'])
        ->and(llmParse(['dipelawaKepada' => 'Berdaftar dengan Kementerian Kewangan Malaysia (MOF) Kod Bidang 210103 Dan Sijil Pematuhan Cukai'])->get('field_codes'))->toBe(['210103'])
        ->and(llmParse(['dipelawaKepada' => 'Syarikat berdaftar dengan SSM', 'syaratPendaftaran' => 'Wajib berdaftar di bawah Kod Bidang: 040101 - (Elektrik) sahaja.'])->get('field_codes'))->toBe(['040101'])
        ->and(llmParse()->get('field_codes'))->toBe([])
        ->and(LlmParser::fieldCodes('Kod Bidang: 040101 - (Elektrik). Sila rujuk juga Kod Bidang: 040101 - (Elektrik) di atas.'))->toBe(['040101'])
        ->and(LlmParser::fieldCodes('KOD BIDANG: 040101 - (Elektrik) DAN 040102 - (Mekanikal)'))->toBe(['040101', '040102']);
});

it('turns Lawatan Tapak into an event, relabelled when a briefing is mentioned', function () {
    $address = 'Auditorium, Blok B, Ibu Pejabat, LLM, Kajang, Selangor.';

    expect(llmParse(['lawatanTapak' => "Tarikh: 20-07-2026  Tempat: {$address}  Masa: 10:00 AM"])->get('events'))
        ->toBe([['label' => 'Lawatan Tapak', 'date' => '2026-07-20', 'address' => $address]])
        ->and(llmParse([
            'syaratPendaftaran' => 'Taklimat Tender: Tarikh: 6 Julai 2026 Masa: 11.00 Pagi (KEHADIRAN TAKLIMAT TENDER ADALAH DIWAJIBKAN)',
            'lawatanTapak' => "Tarikh: 06-07-2026  Tempat: {$address}  Masa: 11:00 AM",
        ])->get('events'))->toBe([['label' => 'Taklimat & Lawatan Tapak', 'date' => '2026-07-06', 'address' => $address]])
        ->and(llmParse(['lawatanTapak' => '-'])->get('events'))->toBe([])
        ->and(llmParse(['lawatanTapak' => 'Tarikh: Tempat: - Masa: 8:00 AM'])->get('events'))->toBe([]);
});

it('returns null for a non-tender page or an empty title', function () {
    expect(LlmParser::detail('<div>not a tender page</div>', '1', LLM_URL, 'open', LLM_NOW))->toBeNull()
        ->and(llmParse(['title' => '']))->toBeNull();
});

it('parses the saved detail page 12540', function () {
    $t = LlmParser::detail(file_get_contents(base_path('tests/Fixtures/collector/llm-tender_detail_12540.html')),
        '12540', 'https://www.llm.gov.my/swasta/tender_detail/12540/', 'open', LLM_NOW);

    expect($t->title)->toBe('PERKHIDMATAN LESEN PENGOPERASIAN PUSAT DATA DI LEMBAGA LEBUHRAYA MALAYSIA BAGI TEMPOH TIGA (3) TAHUN (2026-2029)')
        ->and($t->procurementType)->toBe('tender')
        ->and($t->get('category'))->toBe('Bekalan Perkhidmatan')
        ->and($t->get('events'))->toBe([['label' => 'Taklimat & Lawatan Tapak', 'date' => '2026-07-06', 'address' => 'Auditorium, Blok B, Ibu Pejabat, LLM, Kajang, Selangor.']])
        ->and($t->get('field_codes'))->toBe(['210103'])
        ->and($t->get('advertised_date'))->toBe('2026-07-06')
        ->and($t->get('closing_date'))->toBe('2026-07-24');
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement `app/Collector/Parsers/LlmParser.php`**

```php
<?php

namespace App\Collector\Parsers;

use App\Collector\Support\Text;
use App\Collector\TenderPatch;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/** Port of tms-v2 scrapers/llm/parseListing.ts, parseResults.ts and parseDetail.ts. */
final class LlmParser
{
    public const AGENCY = 'Lembaga Lebuhraya Malaysia (LLM)';

    /** @return list<array{source_id:string,source_url:string}> */
    public static function listing(string $html): array
    {
        $links = [];
        Dom::load($html)->filter('a[href*="/swasta/tender_detail/"]')->each(function (Crawler $a) use (&$links) {
            $href = explode('#', (string) $a->attr('href'))[0];
            if (preg_match('#/swasta/tender_detail/(\d+)/?#', $href, $m) && ! isset($links[$m[1]])) {
                $links[$m[1]] = ['source_id' => $m[1], 'source_url' => $href];
            }
        });

        return array_values($links);
    }

    /** @return list<array{source_id:string,source_url:string,winner:?array}> */
    public static function results(string $html): array
    {
        $rows = [];
        Dom::load($html)->filter('#tender-table-head table tr')->each(function (Crawler $row) use (&$rows) {
            $cells = $row->filter('td');
            if ($cells->count() < 3) {
                return;
            }
            $link = $cells->eq(0)->filter('a');
            $href = trim(explode('#', trim($link->count() ? (string) $link->attr('href') : ''))[0]);
            if (! preg_match('#/swasta/tender_detail/(\d+)/?#', $href, $m) || isset($rows[$m[1]])) {
                return;
            }
            $name = Dom::text($cells->eq(1));
            $rows[$m[1]] = [
                'source_id' => $m[1],
                'source_url' => $href,
                'winner' => $name !== '' ? ['name' => $name, 'price_sen' => Text::rmPriceSen(Dom::text($cells->eq(2)))] : null,
            ];
        });

        return array_values($rows);
    }

    public static function detail(string $html, string $sourceId, string $sourceUrl, string $status, string $scrapedAt): ?TenderPatch
    {
        $panel = Dom::load($html)->filter('#tender-table-head');
        if ($panel->count() === 0) {
            return null;
        }
        $title = Dom::text($panel->filter('header')->first());
        if ($title === '') {
            return null;
        }

        $raw = ['Tajuk' => $title];
        $panel->filter('table.tender-content tr')->each(function (Crawler $row) use (&$raw) {
            $cells = $row->filter('td');
            if ($cells->count() < 2) {
                return;
            }
            $label = Dom::text($cells->eq(0));
            if ($label !== '') {
                $raw[$label] = Dom::text($cells->eq(1));
            }
        });

        $codeText = ($raw['Tender / Sebutharga Adalah Dipelawa kepada'] ?? '').' '.($raw['Syarat Pendaftaran'] ?? '');

        try {
            return TenderPatch::make([
                'reference_no' => preg_match('/NO\.?\s*SEBUT\s*HARGA:?\s*([^\s)]+)/i', $title, $m) ? Text::clean($m[1]) : '',
                'title' => $title,
                'status' => $status,
                'procurement_type' => self::type($raw['Jenis'] ?? ''),
                'scraped_at' => $scrapedAt,
                'source' => 'llm', 'source_id' => $sourceId, 'source_url' => $sourceUrl,
                'agency' => self::AGENCY,
                'category' => ($raw['Kategori'] ?? '') ?: null,
                'field_codes' => self::fieldCodes($codeText),
                'advertised_date' => Text::dotted($raw['Tarikh Mula Jualan Dokumen'] ?? null),
                'closing_date' => Text::isoPrefix($raw['Tarikh dan Waktu Tutup'] ?? null),
                'events' => self::siteVisit($raw['Lawatan Tapak'] ?? null, (bool) preg_match('/taklimat/i', $codeText)),
                'raw' => $raw,
            ]);
        } catch (InvalidArgumentException $e) {
            Log::warning("[llm] skipping invalid detail page {$sourceUrl}: ".$e->getMessage());

            return null;
        }
    }

    /** @return list<string> */
    public static function fieldCodes(string $text): array
    {
        $codes = [];
        preg_match_all('/kod\s*bidang\s*:?\s*/i', $text, $markers, PREG_OFFSET_CAPTURE);
        foreach ($markers[0] as [$marker, $offset]) {
            $rest = substr($text, $offset + strlen($marker));
            while (preg_match('/^(\d{5,7})(?:\s*-\s*\([^)]*\))?\s*(?:,|atau|dan|\/)?\s*/i', $rest, $c)) {
                $codes[] = $c[1];
                $rest = substr($rest, strlen($c[0]));
            }
        }

        return array_values(array_unique($codes));
    }

    private static function type(string $jenis): ?string
    {
        $v = strtolower(trim($jenis));
        if ($v === 'tender') {
            return 'tender';
        }

        return str_contains($v, 'sebut') ? 'quotation' : null;
    }

    private static function siteVisit(?string $text, bool $mentionsBriefing): array
    {
        if ($text === null || $text === '-') {
            return [];
        }
        $date = preg_match('/Tarikh:\s*([\d-]+)/', $text, $d) ? Text::dashed($d[1]) : null;
        $address = preg_match('/Tempat:\s*(.*?)\s*Masa:/', $text, $a) ? Text::clean($a[1]) : '';
        $address = ($address !== '' && $address !== '-') ? $address : null;
        if ($date === null && $address === null) {
            return [];
        }

        return [['label' => $mentionsBriefing ? 'Taklimat & Lawatan Tapak' : 'Lawatan Tapak', 'date' => $date, 'address' => $address]];
    }
}
```

- [ ] **Step 4: Run full suite — expect PASS.**
- [ ] **Step 5: Commit** — `feat: LLM listing, results and detail parsers`

---

### Task 6: Polite downloader and SPAN certificate

**Files:**
- Create: `app/Collector/Fetcher.php`, `app/Collector/PoliteFetcher.php`, `app/Collector/SpanCertificate.php`, `resources/certs/span-digicert-intermediate.pem`
- Test: `tests/Feature/Collector/PoliteFetcherTest.php`

**Interfaces:**
- Produces: `interface Fetcher { getJson(string $url): array; getText(string $url): string; }` (throw `CollectorException`); `new PoliteFetcher(array $httpOptions = [], ?Closure $random = null)`, `PoliteFetcher::httpOptions(): array`, `PoliteFetcher::forSpan(): PoliteFetcher`; `SpanCertificate::bundlePath(): string`.

- [ ] **Step 1: Extract SPAN's certificate from tms-v2**

```bash
cd /c/Projects/cmt-tender-hub && mkdir -p resources/certs
node -e "const s=require('fs').readFileSync('C:/Projects/tms-v2/backend/src/scrapers/span/digicertIntermediateCert.ts','utf8');const m=s.match(/-----BEGIN CERTIFICATE-----[\s\S]+?-----END CERTIFICATE-----/);require('fs').writeFileSync('resources/certs/span-digicert-intermediate.pem',m[0]+'\n')"
head -1 resources/certs/span-digicert-intermediate.pem
```

Expected: `-----BEGIN CERTIFICATE-----`.

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Collector\CollectorException;
use App\Collector\PoliteFetcher;
use App\Collector\SpanCertificate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(fn () => Sleep::fake());

function fetcher(): PoliteFetcher
{
    return new PoliteFetcher(random: fn () => 0.5); // jitter = 100 ms → 400 ms pause
}

it('pauses politely, identifies itself and returns the body', function () {
    Http::fake(['*' => Http::response(['html' => '<p/>', 'lastPage' => 1])]);

    expect(fetcher()->getJson('https://example.test/a'))->toBe(['html' => '<p/>', 'lastPage' => 1]);

    Sleep::assertSequence([Sleep::for(400)->milliseconds()]);
    Http::assertSent(fn ($r) => $r->hasHeader('User-Agent', 'CMTTenderHub/1.0') && $r->hasHeader('Accept', 'application/json'));
});

it('asks for HTML in text mode', function () {
    Http::fake(['*' => Http::response('<html>ok</html>')]);

    expect(fetcher()->getText('https://example.test/b'))->toBe('<html>ok</html>');
    Http::assertSent(fn ($r) => $r->hasHeader('Accept', 'text/html'));
});

it('retries failures with growing back-off, then gives up', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect(fn () => fetcher()->getText('https://example.test/c'))->toThrow(CollectorException::class, 'after 3 attempts');

    Sleep::assertSequence([
        Sleep::for(400)->milliseconds(), Sleep::for(1000)->milliseconds(),
        Sleep::for(400)->milliseconds(), Sleep::for(4000)->milliseconds(),
        Sleep::for(400)->milliseconds(),
    ]);
    Http::assertSentCount(3);
});

it('waits as long as Retry-After says, and the first such wait is free', function () {
    Http::fake(['*' => Http::sequence()
        ->push('', 429, ['Retry-After' => '7'])
        ->push('', 503)
        ->push('<p>ok</p>', 200)]);

    expect(fetcher()->getText('https://example.test/d'))->toBe('<p>ok</p>');

    Sleep::assertSequence([
        Sleep::for(400)->milliseconds(), Sleep::for(7000)->milliseconds(),                                  // 429: free wait
        Sleep::for(400)->milliseconds(), Sleep::for(60000)->milliseconds(), Sleep::for(1000)->milliseconds(), // 503: penalty + back-off
        Sleep::for(400)->milliseconds(),
    ]);
});

it('treats a connection failure like any other failed attempt', function () {
    Http::fake(['*' => Http::sequence()->pushFailedConnection()->push('<p>ok</p>')]);

    expect(fetcher()->getText('https://example.test/e'))->toBe('<p>ok</p>');
});

it('rejects a body that is not JSON in JSON mode', function () {
    Http::fake(['*' => Http::response('<html>maintenance</html>')]);

    fetcher()->getJson('https://example.test/f');
})->throws(CollectorException::class, 'not JSON');

it('trusts SPAN\'s extra certificate only on the SPAN downloader', function () {
    $bundle = file_get_contents(SpanCertificate::bundlePath());

    expect(PoliteFetcher::forSpan()->httpOptions()['verify'])->toBe(SpanCertificate::bundlePath())
        ->and($bundle)->toContain(trim(file_get_contents(resource_path('certs/span-digicert-intermediate.pem'))))
        ->and(substr_count($bundle, 'BEGIN CERTIFICATE'))->toBeGreaterThan(50)
        ->and((new PoliteFetcher)->httpOptions())->toBe([]);
});
```

- [ ] **Step 3: Run — expect FAIL.**

- [ ] **Step 4: Implement**

`app/Collector/Fetcher.php`:

```php
<?php

namespace App\Collector;

interface Fetcher
{
    /** @throws CollectorException */
    public function getJson(string $url): array;

    /** @throws CollectorException */
    public function getText(string $url): string;
}
```

`app/Collector/PoliteFetcher.php`:

```php
<?php

namespace App\Collector;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** Port of tms-v2 http/politeFetch.ts: serial, paced, retrying, rate-limit aware. */
final class PoliteFetcher implements Fetcher
{
    private const BASE_DELAY_MS = 300;
    private const JITTER_MS = 200;
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_MS = [1000, 4000, 16000];
    private const PENALTY_MS = 60000;

    public function __construct(private array $httpOptions = [], private ?Closure $random = null) {}

    public static function forSpan(): self
    {
        return new self(['verify' => SpanCertificate::bundlePath()]);
    }

    public function httpOptions(): array
    {
        return $this->httpOptions;
    }

    public function getJson(string $url): array
    {
        $data = json_decode($this->request($url, 'application/json'), true);
        if (! is_array($data)) {
            throw new CollectorException("Response was not JSON: {$url}");
        }

        return $data;
    }

    public function getText(string $url): string
    {
        return $this->request($url, 'text/html');
    }

    private function request(string $url, string $accept): string
    {
        $attempt = 0;
        $graceUsed = false;
        while ($attempt < self::MAX_ATTEMPTS) {
            Sleep::for(self::BASE_DELAY_MS + $this->jitter())->milliseconds();
            try {
                $response = Http::withOptions($this->httpOptions)
                    ->withHeaders(['User-Agent' => 'CMTTenderHub/1.0', 'Accept' => $accept])
                    ->timeout(60)
                    ->get($url);
                if ($response->successful()) {
                    return $response->body();
                }
                if (in_array($response->status(), [429, 503], true)) {
                    $retryAfter = (int) $response->header('Retry-After');
                    Sleep::for($retryAfter > 0 ? $retryAfter * 1000 : self::PENALTY_MS)->milliseconds();
                    if (! $graceUsed) {
                        $graceUsed = true; // the first rate-limit wait doesn't use up an attempt

                        continue;
                    }
                }
                $attempt++;
            } catch (ConnectionException) {
                $attempt++;
            }
            if ($attempt < self::MAX_ATTEMPTS) {
                Sleep::for(self::BACKOFF_MS[min($attempt - 1, count(self::BACKOFF_MS) - 1)])->milliseconds();
            }
        }

        throw new CollectorException('Download failed after '.self::MAX_ATTEMPTS." attempts: {$url}");
    }

    private function jitter(): int
    {
        $random = $this->random ?? fn () => mt_rand() / mt_getrandmax();

        return (int) floor($random() * self::JITTER_MS);
    }
}
```

`app/Collector/SpanCertificate.php`:

```php
<?php

namespace App\Collector;

/**
 * span.gov.my sends only its own certificate and omits DigiCert's intermediate, so a normal
 * HTTPS client can't verify it. We trust the normal public roots plus that one intermediate —
 * for SPAN requests only (see tms-v2 scrapers/span/digicertIntermediateCert.ts).
 */
final class SpanCertificate
{
    public static function bundlePath(): string
    {
        $path = storage_path('app/span-ca-bundle.pem');
        if (! is_file($path)) {
            $system = openssl_get_cert_locations()['default_cert_file'] ?? '/etc/ssl/certs/ca-certificates.crt';
            $roots = is_file($system) ? file_get_contents($system) : file_get_contents('/etc/ssl/certs/ca-certificates.crt');
            file_put_contents($path, rtrim($roots)."\n".file_get_contents(resource_path('certs/span-digicert-intermediate.pem')));
        }

        return $path;
    }
}
```

Add `/storage/app/span-ca-bundle.pem` to `.gitignore`.

- [ ] **Step 5: Run full suite — expect PASS.** If `Sleep::assertSequence` differs, print `Sleep::$sequence` and correct the *code* to match tms-v2's order (pause → request → rate-limit wait → back-off).
- [ ] **Step 6: Commit** — `feat: polite downloader with retries, Retry-After and SPAN-only certificate`

---

### Task 7: Collector sources (MyProcurement, SPAN, LLM)

**Files:**
- Create: `app/Collector/CollectorSource.php`, `app/Collector/Sources/MyProcurementSource.php`, `app/Collector/Sources/SpanSource.php`, `app/Collector/Sources/LlmSource.php`, `tests/Support/FakeFetcher.php`
- Modify: `composer.json` autoload-dev (`"Tests\\Support\\": "tests/Support/"` is covered by the existing `"Tests\\": "tests/"`; no change needed)
- Test: `tests/Feature/Collector/SourcesTest.php`

**Interfaces:**
- Consumes: parsers (Tasks 3–5), `Fetcher`, `Text::scrapedAtNow()`, `MalaysiaTime`
- Produces: `interface CollectorSource { name(): string; collect(string $scope, Closure $onBatch): int; }` — `$scope` is `daily|open`; `$onBatch(list<TenderPatch>)`; returns tenders seen; throws `CollectorException` on unrecoverable fetch failure or MyProcurement zero-open guard. Constructors: `new MyProcurementSource(Fetcher)`, `new SpanSource(Fetcher)`, `new LlmSource(Fetcher)`.
- `Tests\Support\FakeFetcher`: `new FakeFetcher(array $responses)` mapping URL → `array` (JSON) or `string` (HTML) or `Throwable`; `->urls` lists requested URLs in order.

- [ ] **Step 1: Write the fake and the failing test**

`tests/Support/FakeFetcher.php`:

```php
<?php

namespace Tests\Support;

use App\Collector\CollectorException;
use App\Collector\Fetcher;
use Throwable;

final class FakeFetcher implements Fetcher
{
    public array $urls = [];

    public function __construct(private array $responses) {}

    public function getJson(string $url): array
    {
        return $this->respond($url);
    }

    public function getText(string $url): string
    {
        return $this->respond($url);
    }

    private function respond(string $url): mixed
    {
        $this->urls[] = $url;
        if (! array_key_exists($url, $this->responses)) {
            throw new CollectorException("No fake response for {$url}");
        }
        $r = $this->responses[$url];
        if ($r instanceof Throwable) {
            throw $r;
        }

        return $r;
    }
}
```

`tests/Feature/Collector/SourcesTest.php`:

```php
<?php

use App\Collector\CollectorException;
use App\Collector\Sources\{LlmSource, MyProcurementSource, SpanSource};
use Carbon\CarbonImmutable;
use Tests\Support\FakeFetcher;

function fx(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/collector/{$name}"));
}

function mpUrl(string $type, string $category, int $page = 1): string
{
    return "https://myprocurement.treasury.gov.my/procurements/fetch?page={$page}&itemsPerPage=100&type={$type}&category={$category}";
}

function collectAll($source, string $scope): array
{
    $batches = [];
    $count = $source->collect($scope, function (array $batch) use (&$batches) { $batches[] = $batch; });

    return [$count, array_merge(...($batches ?: [[]]))];
}

it('MyProcurement daily: pages through 3 open jobs and 2 results jobs', function () {
    $open = json_decode(fx('open-quotation-p1.json'), true);
    $results = json_decode(fx('results-quotation-p1.json'), true);
    $one = fn ($p) => ['html' => $p['html'], 'lastPage' => 1];
    $two = fn ($p) => ['html' => $p['html'], 'lastPage' => 2];
    $fetcher = new FakeFetcher([
        mpUrl('advertisements', 'quotation') => $two($open),
        mpUrl('advertisements', 'quotation', 2) => $two($open),
        mpUrl('advertisements', 'tender') => $one(json_decode(fx('open-tender-p1.json'), true)),
        mpUrl('advertisements', 'requisition') => $one(json_decode(fx('open-requisition-p1.json'), true)),
        mpUrl('results', 'quotation') => $one($results),
        mpUrl('results', 'tender') => $one($results),
    ]);

    [$count, $patches] = collectAll(new MyProcurementSource($fetcher), 'daily');

    expect($fetcher->urls)->toHaveCount(6)
        ->and($count)->toBe(count($patches))
        ->and(collect($patches)->where('status', 'closed')->pluck('procurementType')->unique()->sort()->values()->all())->toBe(['quotation', 'tender']);
});

it('MyProcurement open scope: open jobs only', function () {
    $page = fn ($f) => ['html' => json_decode(fx($f), true)['html'], 'lastPage' => 1];
    $fetcher = new FakeFetcher([
        mpUrl('advertisements', 'quotation') => $page('open-quotation-p1.json'),
        mpUrl('advertisements', 'tender') => $page('open-tender-p1.json'),
        mpUrl('advertisements', 'requisition') => $page('open-requisition-p1.json'),
    ]);

    collectAll(new MyProcurementSource($fetcher), 'open');

    expect($fetcher->urls)->toHaveCount(3);
});

it('MyProcurement flags a layout change when no open tenders are found at all', function () {
    $empty = ['html' => '<div>nothing</div>', 'lastPage' => 1];
    $fetcher = new FakeFetcher([
        mpUrl('advertisements', 'quotation') => $empty,
        mpUrl('advertisements', 'tender') => $empty,
        mpUrl('advertisements', 'requisition') => $empty,
    ]);

    collectAll(new MyProcurementSource($fetcher), 'open');
})->throws(CollectorException::class, 'returned 0 tenders');

it('MyProcurement rejects a response without html/lastPage', function () {
    $fetcher = new FakeFetcher([mpUrl('advertisements', 'quotation') => ['error' => 'x']]);

    collectAll(new MyProcurementSource($fetcher), 'open');
})->throws(CollectorException::class, 'unexpected response');

it('SPAN: current Malaysia year; daily also reads winners of closed tenders, skipping failed detail pages', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-31 16:30', 'UTC')); // already 2027 in Malaysia
    $listing = fx('span-2026.html');
    preg_match_all('#https://www\.span\.gov\.my/tender/view/\d+#', $listing, $m);
    $closed = collect(\App\Collector\Parsers\SpanParser::listing($listing, 'x'))->where('status', 'closed')->values();
    $winnerHtml = '<table><tr><td>Nama Pembekal</td><td>ACME SDN BHD</td><td>Harga Tawaran</td><td>RM1,000.00</td></tr></table>';
    $fetcher = new FakeFetcher([
        'https://www.span.gov.my/tender/2027' => $listing,
        $closed[0]->sourceUrl => $winnerHtml,
        $closed[1]->sourceUrl => new CollectorException('boom'),
    ]);

    [$count, $patches] = collectAll(new SpanSource($fetcher), 'daily');

    expect($count)->toBe(5)
        ->and($fetcher->urls[0])->toBe('https://www.span.gov.my/tender/2027')
        ->and(collect($patches)->filter(fn ($p) => $p->has('winners'))->first()->get('winners'))
        ->toBe([['name' => 'ACME SDN BHD', 'price_sen' => 100000]]);

    $open = new FakeFetcher(['https://www.span.gov.my/tender/2027' => $listing]);
    collectAll(new SpanSource($open), 'open');
    expect($open->urls)->toHaveCount(1);
});

it('LLM daily: pages the open listing until empty, reads each detail, attaches winners from results', function () {
    $detail = fx('llm-tender_detail_12540.html');
    $listing = fx('llm-tender_tawaran.html');
    $links = \App\Collector\Parsers\LlmParser::listing($listing);
    $results = \App\Collector\Parsers\LlmParser::results(fx('llm-tender_keputusan.html'));
    $responses = [
        'https://www.llm.gov.my/swasta/tender_tawaran/' => $listing,
        'https://www.llm.gov.my/swasta/tender_tawaran/6' => '<div>no more</div>',
        'https://www.llm.gov.my/swasta/tender_keputusan/' => fx('llm-tender_keputusan.html'),
    ];
    foreach ([...$links, ...$results] as $l) {
        $responses[$l['source_url']] = $detail;
    }
    $responses[$links[0]['source_url']] = new CollectorException('one bad page');
    $fetcher = new FakeFetcher($responses);

    [$count, $patches] = collectAll(new LlmSource($fetcher), 'daily');

    $closed = collect($patches)->where('status', 'closed');
    expect($count)->toBe(11)                       // 6 open - 1 failed + 6 results
        ->and($closed)->toHaveCount(6)
        ->and($closed->first()->get('winners'))->toBe([$results[0]['winner']])
        ->and($fetcher->urls)->not->toContain('https://www.llm.gov.my/swasta/tender_keputusan/6');
});

it('LLM open scope skips the results listing', function () {
    $fetcher = new FakeFetcher([
        'https://www.llm.gov.my/swasta/tender_tawaran/' => '<div>none</div>',
    ]);

    [$count] = collectAll(new LlmSource($fetcher), 'open');

    expect($count)->toBe(0)->and($fetcher->urls)->toBe(['https://www.llm.gov.my/swasta/tender_tawaran/']);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`app/Collector/CollectorSource.php`:

```php
<?php

namespace App\Collector;

use Closure;

interface CollectorSource
{
    public function name(): string;

    /**
     * @param  'daily'|'open'  $scope
     * @param  Closure(list<TenderPatch>):void  $onBatch
     * @return int number of tenders seen
     *
     * @throws CollectorException
     */
    public function collect(string $scope, Closure $onBatch): int;
}
```

`app/Collector/Sources/MyProcurementSource.php`:

```php
<?php

namespace App\Collector\Sources;

use App\Collector\{CollectorException, CollectorSource, Fetcher};
use App\Collector\Parsers\MyProcurementParser;
use App\Collector\Support\Text;
use Closure;

final class MyProcurementSource implements CollectorSource
{
    private const BASE_URL = 'https://myprocurement.treasury.gov.my/procurements/fetch';

    private const JOBS = [
        ['kind' => 'open', 'type' => 'advertisements', 'category' => 'quotation', 'procurement' => 'quotation'],
        ['kind' => 'open', 'type' => 'advertisements', 'category' => 'tender', 'procurement' => 'tender'],
        ['kind' => 'open', 'type' => 'advertisements', 'category' => 'requisition', 'procurement' => 'requisition'],
        ['kind' => 'results', 'type' => 'results', 'category' => 'quotation', 'procurement' => 'quotation'],
        ['kind' => 'results', 'type' => 'results', 'category' => 'tender', 'procurement' => 'tender'],
    ];

    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'myprocurement';
    }

    public function collect(string $scope, Closure $onBatch): int
    {
        $total = 0;
        $openTotal = 0;
        foreach (self::JOBS as $job) {
            if ($scope === 'open' && $job['kind'] !== 'open') {
                continue;
            }
            $page = 1;
            do {
                $url = self::BASE_URL."?page={$page}&itemsPerPage=100&type={$job['type']}&category={$job['category']}";
                $body = $this->fetcher->getJson($url);
                if (! is_string($body['html'] ?? null) || ! is_int($body['lastPage'] ?? null) || $body['lastPage'] < 1) {
                    throw new CollectorException("MyProcurement gave an unexpected response for {$url}");
                }
                $now = Text::scrapedAtNow();
                $patches = $job['kind'] === 'open'
                    ? MyProcurementParser::listing($body['html'], 'open', $job['procurement'], $now)
                    : MyProcurementParser::results($body['html'], $job['procurement'], $now);
                $onBatch($patches);
                $total += count($patches);
                if ($job['kind'] === 'open') {
                    $openTotal += count($patches);
                }
                $page++;
            } while ($page <= $body['lastPage']);
        }

        if ($openTotal === 0) {
            throw new CollectorException('MyProcurement returned 0 tenders — page layout may have changed');
        }

        return $total;
    }
}
```

`app/Collector/Sources/SpanSource.php`:

```php
<?php

namespace App\Collector\Sources;

use App\Collector\{CollectorException, CollectorSource, Fetcher};
use App\Collector\Parsers\SpanParser;
use App\Collector\Support\Text;
use App\Support\MalaysiaTime;
use Closure;
use Illuminate\Support\Facades\Log;

final class SpanSource implements CollectorSource
{
    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'span';
    }

    public function collect(string $scope, Closure $onBatch): int
    {
        $patches = SpanParser::listing(
            $this->fetcher->getText('https://www.span.gov.my/tender/'.MalaysiaTime::now()->year),
            Text::scrapedAtNow(),
        );
        $onBatch($patches);

        if ($scope === 'daily') {
            foreach ($patches as $patch) {
                if ($patch->status !== 'closed') {
                    continue;
                }
                try {
                    $winners = SpanParser::winners($this->fetcher->getText($patch->sourceUrl));
                } catch (CollectorException $e) {
                    Log::warning("[span] skipping detail page {$patch->sourceUrl}: ".$e->getMessage());

                    continue;
                }
                $onBatch([$patch->with(['winners' => $winners !== [] ? $winners : null])]);
            }
        }

        return count($patches);
    }
}
```

`app/Collector/Sources/LlmSource.php`:

```php
<?php

namespace App\Collector\Sources;

use App\Collector\{CollectorException, CollectorSource, Fetcher};
use App\Collector\Parsers\LlmParser;
use App\Collector\Support\Text;
use Closure;
use Illuminate\Support\Facades\Log;

final class LlmSource implements CollectorSource
{
    private const OPEN_URL = 'https://www.llm.gov.my/swasta/tender_tawaran';
    private const RESULTS_URL = 'https://www.llm.gov.my/swasta/tender_keputusan';
    private const PAGE_SIZE = 6;
    private const MAX_PAGES = 50; // safety stop if the site ever repeats pages forever

    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'llm';
    }

    public function collect(string $scope, Closure $onBatch): int
    {
        $count = $this->detailPages($this->openLinks(), 'open', $onBatch);

        if ($scope === 'daily') {
            // Their results pagination past page 1 is broken (404s), so only the first page is read.
            $rows = LlmParser::results($this->fetcher->getText(self::RESULTS_URL.'/'));
            $count += $this->detailPages($rows, 'closed', $onBatch);
        }

        return $count;
    }

    private function openLinks(): array
    {
        $links = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $offset = $page * self::PAGE_SIZE;
            $html = $this->fetcher->getText(self::OPEN_URL.'/'.($offset === 0 ? '' : $offset));
            $pageLinks = LlmParser::listing($html);
            if ($pageLinks === []) {
                break;
            }
            array_push($links, ...$pageLinks);
        }

        return $links;
    }

    private function detailPages(array $links, string $status, Closure $onBatch): int
    {
        $count = 0;
        foreach ($links as $link) {
            try {
                $patch = LlmParser::detail(
                    $this->fetcher->getText($link['source_url']),
                    $link['source_id'], $link['source_url'], $status, Text::scrapedAtNow(),
                );
            } catch (CollectorException $e) {
                Log::warning("[llm] skipping detail page {$link['source_url']}: ".$e->getMessage());

                continue;
            }
            if ($patch === null) {
                continue;
            }
            if ($status === 'closed') {
                $patch = $patch->with(['winners' => ($link['winner'] ?? null) ? [$link['winner']] : null]);
            }
            $onBatch([$patch]);
            $count++;
        }

        return $count;
    }
}
```

- [ ] **Step 4: Run full suite — expect PASS.**
- [ ] **Step 5: Commit** — `feat: MyProcurement, SPAN and LLM collector sources`

---

### Task 8: Collected-tender tables and models

**Files:**
- Create: `database/migrations/2026_10_07_000001_create_collector_tables.php`, `app/Models/{CollectedTender,CollectedTenderSource,CollectedTenderFieldCode,CollectionRun}.php`, `database/factories/CollectedTenderFactory.php`, `app/Collector/SourceName.php`
- Modify: `app/Models/Tender.php` (relation `collectedTender()`), `app/Actions/Tenders/RegisterTender.php` (`FIELDS` gains `collected_tender_id`)
- Test: `tests/Feature/Models/CollectedTenderTest.php`

**Interfaces:**
- Produces: models with casts (`events`, `winners`, `raw`, `field_updated_at`, `results` → array; dates `immutable_date`; `scraped_at`, `started_at`, `finished_at` → `immutable_datetime`); `CollectedTender::sources(), fieldCodes(), pipelineTenders()`, `->daysLeft(): ?int`, `->sourceNames(): list<string>`, `->firstPipelineTender(): ?Tender`; `Tender::collectedTender()`; `SourceName::label(string): string`; factory with state `forSource(string $source, string $sourceId = null)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\SourceName;
use App\Models\{CollectedTender, Tender};
use Carbon\CarbonImmutable;

it('stores a collected tender with JSON fields, sources and field codes', function () {
    $t = CollectedTender::factory()->forSource('span', '188')->create([
        'winners' => [['name' => 'ACME', 'price_sen' => 100]],
        'events' => [['label' => 'Lawatan Tapak', 'date' => '2026-07-10', 'address' => null]],
    ]);
    $t->fieldCodes()->create(['code' => 'E05']);

    $fresh = $t->fresh();
    expect($fresh->winners)->toBe([['name' => 'ACME', 'price_sen' => 100]])
        ->and($fresh->sources->pluck('source')->all())->toBe(['span'])
        ->and($fresh->fieldCodes->pluck('code')->all())->toBe(['E05'])
        ->and($fresh->sourceNames())->toBe(['SPAN']);
});

it('counts days left by Malaysia date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30', 'UTC')); // 7 Oct MYT

    expect(CollectedTender::factory()->make(['closing_date' => '2026-10-10'])->daysLeft())->toBe(3)
        ->and(CollectedTender::factory()->make(['closing_date' => null])->daysLeft())->toBeNull();
});

it('links pipeline tenders back to the collected tender', function () {
    $c = CollectedTender::factory()->create();
    $p = Tender::factory()->create(['collected_tender_id' => $c->id]);

    expect($c->firstPipelineTender()->is($p))->toBeTrue()
        ->and($p->collectedTender->is($c))->toBeTrue();
});

it('labels sources for display', function () {
    expect(SourceName::label('myprocurement'))->toBe('MyProcurement')
        ->and(SourceName::label('kwsp'))->toBe('KWSP')
        ->and(SourceName::label('other'))->toBe('Other');
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collected_tenders', function (Blueprint $table) {
            $table->id();
            $table->string('dedup_key', 191)->unique();
            $table->string('reference_no', 191)->default('');
            $table->text('title');
            $table->string('status', 10)->index();
            $table->string('procurement_type', 20)->nullable()->index();
            $table->string('ministry')->nullable()->index();
            $table->string('agency')->nullable();
            $table->string('category')->nullable();
            $table->date('advertised_date')->nullable();
            $table->date('closing_date')->nullable()->index();
            $table->unsignedBigInteger('indicative_price_sen')->nullable();
            $table->json('events')->nullable();
            $table->json('winners')->nullable();
            $table->json('raw')->nullable();
            $table->json('field_updated_at')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();
            $table->fullText(['title', 'reference_no', 'agency']);
        });

        Schema::create('collected_tender_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collected_tender_id')->constrained()->cascadeOnDelete();
            $table->string('source', 30)->index();
            $table->string('source_id', 191);
            $table->string('source_url', 500);
            $table->unique(['collected_tender_id', 'source']);
        });

        Schema::create('collected_tender_field_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collected_tender_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->index();
            $table->unique(['collected_tender_id', 'code']);
        });

        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 20);
            $table->string('scope', 10);
            $table->foreignId('started_by')->nullable()->constrained('users');
            $table->string('status', 20)->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('results')->nullable();
            $table->unsignedInteger('closed_stale')->default(0);
        });

        Schema::table('tenders', function (Blueprint $table) {
            $table->foreignId('collected_tender_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->dropConstrainedForeignId('collected_tender_id'));
        Schema::dropIfExists('collection_runs');
        Schema::dropIfExists('collected_tender_field_codes');
        Schema::dropIfExists('collected_tender_sources');
        Schema::dropIfExists('collected_tenders');
    }
};
```

- [ ] **Step 4: Models, SourceName, factory**

`app/Collector/SourceName.php`:

```php
<?php

namespace App\Collector;

final class SourceName
{
    private const LABELS = ['myprocurement' => 'MyProcurement', 'span' => 'SPAN', 'llm' => 'LLM', 'kwsp' => 'KWSP'];

    public static function label(string $source): string
    {
        return self::LABELS[$source] ?? ucfirst($source);
    }

    /** @return array<string,string> collected sources a user can filter by (KWSP is history only but filterable) */
    public static function all(): array
    {
        return self::LABELS;
    }
}
```

`app/Models/CollectedTender.php`:

```php
<?php

namespace App\Models;

use App\Collector\SourceName;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectedTender extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'events' => 'array', 'winners' => 'array', 'raw' => 'array', 'field_updated_at' => 'array',
            'advertised_date' => 'immutable_date', 'closing_date' => 'immutable_date',
            'scraped_at' => 'immutable_datetime', 'indicative_price_sen' => 'integer',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CollectedTenderSource::class)->orderBy('source');
    }

    public function fieldCodes(): HasMany
    {
        return $this->hasMany(CollectedTenderFieldCode::class)->orderBy('code');
    }

    public function pipelineTenders(): HasMany
    {
        return $this->hasMany(Tender::class)->orderBy('id');
    }

    public function firstPipelineTender(): ?Tender
    {
        return $this->relationLoaded('pipelineTenders') ? $this->pipelineTenders->first() : $this->pipelineTenders()->first();
    }

    public function daysLeft(): ?int
    {
        if ($this->closing_date === null) {
            return null;
        }
        $closing = CarbonImmutable::parse($this->closing_date->toDateString(), MalaysiaTime::TZ);

        return (int) MalaysiaTime::today()->diffInDays($closing, false);
    }

    /** @return list<string> */
    public function sourceNames(): array
    {
        return $this->sources->map(fn ($s) => SourceName::label($s->source))->all();
    }
}
```

`app/Models/CollectedTenderSource.php` and `CollectedTenderFieldCode.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectedTenderSource extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectedTenderFieldCode extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
```

`app/Models/CollectionRun.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['results' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'closed_stale' => 'integer'];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
```

`database/factories/CollectedTenderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CollectedTender;
use App\Support\MalaysiaTime;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollectedTenderFactory extends Factory
{
    public function definition(): array
    {
        $ref = fake()->unique()->numerify('QT2600000000#####');

        return [
            'dedup_key' => $ref,
            'reference_no' => $ref,
            'title' => strtoupper(fake()->sentence(8)),
            'status' => 'open',
            'procurement_type' => 'quotation',
            'ministry' => 'KEMENTERIAN KESIHATAN',
            'agency' => 'HOSPITAL KUALA LUMPUR',
            'category' => 'Bekalan',
            'advertised_date' => MalaysiaTime::today()->subDays(3)->toDateString(),
            'closing_date' => MalaysiaTime::today()->addDays(10)->toDateString(),
            'indicative_price_sen' => 2880000,
            'events' => [], 'winners' => null, 'raw' => [], 'field_updated_at' => [],
            'scraped_at' => now(),
        ];
    }

    public function forSource(string $source, ?string $sourceId = null): static
    {
        return $this->afterCreating(fn (CollectedTender $t) => $t->sources()->create([
            'source' => $source,
            'source_id' => $sourceId ?? (string) $t->id,
            'source_url' => "https://{$source}.example.test/{$t->id}",
        ]));
    }
}
```

In `app/Models/Tender.php` add:

```php
    public function collectedTender(): BelongsTo
    {
        return $this->belongsTo(CollectedTender::class);
    }
```

In `RegisterTender::FIELDS` append `'collected_tender_id'`.

- [ ] **Step 5: Run full suite — expect PASS.**
- [ ] **Step 6: Commit** — `feat: collected tender, source, field code and run tables`

---

### Task 9: Merger (saving rules)

**Files:**
- Create: `app/Collector/Merger.php`
- Test: `tests/Feature/Collector/MergerTest.php`

**Interfaces:**
- Consumes: `TenderPatch`, models (Task 8)
- Produces: `Merger::merge(list<TenderPatch> $patches): void`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\{Merger, TenderPatch};
use App\Models\CollectedTender;

function patch(array $o = []): TenderPatch
{
    return TenderPatch::make(array_merge([
        'reference_no' => 'QT1', 'title' => 'TITLE', 'status' => 'open', 'procurement_type' => 'quotation',
        'scraped_at' => '2026-07-07T12:00:00.000Z', 'source' => 'myprocurement', 'source_id' => '1',
        'source_url' => 'https://myprocurement.treasury.gov.my/1',
    ], $o));
}

it('creates a tender with its source, field codes and provenance', function () {
    app(Merger::class)->merge([patch(['closing_date' => '2026-07-17', 'field_codes' => ['E05', 'E32'], 'indicative_price_sen' => 100])]);

    $t = CollectedTender::sole();
    expect($t->dedup_key)->toBe('QT1')
        ->and($t->closing_date->toDateString())->toBe('2026-07-17')
        ->and($t->fieldCodes->pluck('code')->all())->toBe(['E05', 'E32'])
        ->and($t->sources->pluck('source_url')->all())->toBe(['https://myprocurement.treasury.gov.my/1'])
        ->and($t->field_updated_at['closing_date'])->toBe('2026-07-07T12:00:00.000Z')
        ->and($t->events)->toBe([])
        ->and($t->scraped_at->toIso8601ZuluString())->toBe('2026-07-07T12:00:00Z');
});

it('lets a newer observation update a field', function () {
    app(Merger::class)->merge([
        patch(['closing_date' => '2026-07-17']),
        patch(['closing_date' => '2026-07-24', 'scraped_at' => '2026-07-08T12:00:00.000Z']),
    ]);

    expect(CollectedTender::sole()->closing_date->toDateString())->toBe('2026-07-24');
});

it('never lets an older observation overwrite a newer one', function () {
    app(Merger::class)->merge([
        patch(['title' => 'NEW', 'scraped_at' => '2026-07-08T12:00:00.000Z']),
        patch(['title' => 'OLD', 'scraped_at' => '2026-07-07T12:00:00.000Z']),
    ]);

    expect(CollectedTender::sole()->title)->toBe('NEW');
});

it('never lets "no information" wipe out a known value', function () {
    app(Merger::class)->merge([
        patch(['status' => 'closed', 'winners' => [['name' => 'ACME', 'price_sen' => 5]], 'ministry' => 'KKM']),
        patch(['status' => 'closed', 'winners' => null, 'ministry' => null, 'scraped_at' => '2026-07-09T00:00:00.000Z']),
    ]);

    $t = CollectedTender::sole();
    expect($t->winners)->toBe([['name' => 'ACME', 'price_sen' => 5]])->and($t->ministry)->toBe('KKM');
});

it('leaves fields a job did not observe untouched', function () {
    app(Merger::class)->merge([
        patch(['closing_date' => '2026-07-17', 'field_codes' => ['E05']]),
        patch(['status' => 'closed', 'winners' => [['name' => 'A', 'price_sen' => 1]], 'scraped_at' => '2026-07-20T00:00:00.000Z']),
    ]);

    $t = CollectedTender::sole();
    expect($t->closing_date->toDateString())->toBe('2026-07-17')
        ->and($t->fieldCodes->pluck('code')->all())->toBe(['E05'])
        ->and($t->status)->toBe('closed');
});

it('records each source once and merges two sources listing the same reference', function () {
    app(Merger::class)->merge([
        patch(),
        patch(['source_url' => 'https://myprocurement.treasury.gov.my/1-moved', 'scraped_at' => '2026-07-08T00:00:00.000Z']),
        patch(['source' => 'span', 'source_id' => '9', 'source_url' => 'https://www.span.gov.my/tender/view/9']),
    ]);

    expect(CollectedTender::count())->toBe(1)
        ->and(CollectedTender::sole()->sources->pluck('source_url', 'source')->all())->toBe([
            'myprocurement' => 'https://myprocurement.treasury.gov.my/1-moved',
            'span' => 'https://www.span.gov.my/tender/view/9',
        ]);
});

it('replaces field codes when a newer observation lists different ones', function () {
    app(Merger::class)->merge([
        patch(['field_codes' => ['E05', 'E32']]),
        patch(['field_codes' => ['E05'], 'scraped_at' => '2026-07-08T00:00:00.000Z']),
    ]);

    expect(CollectedTender::sole()->fieldCodes->pluck('code')->all())->toBe(['E05']);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement `app/Collector/Merger.php`**

```php
<?php

namespace App\Collector;

use App\Models\CollectedTender;
use Illuminate\Support\Facades\DB;

/** Port of tms-v2 storage/repository.ts mergeOne(): field-level, newest-wins, never blank a known value. */
final class Merger
{
    /** Fields where a null observation never overwrites a known value. */
    private const PROTECTED = [
        'ministry', 'agency', 'category', 'advertised_date', 'closing_date', 'indicative_price_sen',
        'winners', 'procurement_type',
    ];

    /** @param list<TenderPatch> $patches */
    public function merge(array $patches): void
    {
        foreach ($patches as $patch) {
            DB::transaction(fn () => $this->mergeOne($patch));
        }
    }

    private function mergeOne(TenderPatch $p): void
    {
        $tender = CollectedTender::where('dedup_key', $p->dedupKey)->lockForUpdate()->first()
            ?? new CollectedTender(['dedup_key' => $p->dedupKey, 'events' => [], 'raw' => [], 'winners' => null]);
        $provenance = $tender->field_updated_at ?? [];
        $fieldCodes = null;

        $observed = [
            'reference_no' => $p->referenceNo, 'title' => $p->title,
            'status' => $p->status, 'procurement_type' => $p->procurementType,
        ] + $p->observed;

        foreach ($observed as $field => $value) {
            $current = $field === 'field_codes' ? null : $tender->getAttribute($field);
            if ($value === null && in_array($field, self::PROTECTED, true) && $current !== null) {
                continue; // "no information" never clobbers a known value
            }
            if (isset($provenance[$field]) && $p->scrapedAt < $provenance[$field]) {
                continue; // stale / out-of-order observation
            }
            if ($field === 'field_codes') {
                $fieldCodes = $value;
            } else {
                $tender->setAttribute($field, $value);
            }
            $provenance[$field] = $p->scrapedAt;
        }

        if (! isset($provenance['scraped_at']) || $p->scrapedAt >= $provenance['scraped_at']) {
            $provenance['scraped_at'] = $p->scrapedAt;
            $tender->scraped_at = $p->scrapedAt;
        }
        $tender->field_updated_at = $provenance;
        $tender->save();

        if ($fieldCodes !== null) {
            $tender->fieldCodes()->delete();
            foreach (array_unique($fieldCodes) as $code) {
                $tender->fieldCodes()->create(['code' => $code]);
            }
        }

        $tender->sources()->updateOrCreate(
            ['source' => $p->source],
            ['source_id' => $p->sourceId, 'source_url' => $p->sourceUrl],
        );
    }
}
```

Note: `scraped_at` column receives the ISO string; Eloquent parses `2026-07-07T12:00:00.000Z` into the datetime cast correctly (UTC).

- [ ] **Step 4: Run full suite — expect PASS.**
- [ ] **Step 5: Commit** — `feat: merger with newest-wins and never-blank rules`

---

### Task 10: Closing past-due tenders

**Files:**
- Create: `app/Collector/StaleOpenCloser.php`
- Test: `tests/Feature/Collector/StaleOpenCloserTest.php`

**Interfaces:**
- Produces: `StaleOpenCloser::run(): int` (number closed). Rule (tms-v2 `reconcileStaleOpen`): an `open` tender closes once its closing day's 12:01pm MYT has passed; with no closing date, once today (MYT) is on/after the advertised date plus one calendar month (clamped to month end).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Collector\StaleOpenCloser;
use App\Models\CollectedTender;
use Carbon\CarbonImmutable;

it('closes tenders whose closing day 12:01pm MYT has passed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:05', 'Asia/Kuala_Lumpur'));
    $yesterday = CollectedTender::factory()->create(['closing_date' => '2026-10-06']);
    $today = CollectedTender::factory()->create(['closing_date' => '2026-10-07']);
    $tomorrow = CollectedTender::factory()->create(['closing_date' => '2026-10-08']);

    expect(app(StaleOpenCloser::class)->run())->toBe(2)
        ->and($yesterday->fresh()->status)->toBe('closed')
        ->and($today->fresh()->status)->toBe('closed')
        ->and($tomorrow->fresh()->status)->toBe('open');
});

it('keeps today\'s tenders open before 12:01pm', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 11:59', 'Asia/Kuala_Lumpur'));
    $today = CollectedTender::factory()->create(['closing_date' => '2026-10-07']);

    app(StaleOpenCloser::class)->run();

    expect($today->fresh()->status)->toBe('open');
});

it('falls back to one month after advertising, clamped to month end', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-28 09:00', 'Asia/Kuala_Lumpur'));
    $due = CollectedTender::factory()->create(['closing_date' => null, 'advertised_date' => '2026-01-31']);
    $notYet = CollectedTender::factory()->create(['closing_date' => null, 'advertised_date' => '2026-02-01']);
    $unknown = CollectedTender::factory()->create(['closing_date' => null, 'advertised_date' => null]);

    app(StaleOpenCloser::class)->run();

    expect($due->fresh()->status)->toBe('closed')
        ->and($notYet->fresh()->status)->toBe('open')
        ->and($unknown->fresh()->status)->toBe('open');
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Collector;

use App\Models\CollectedTender;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;

/** Port of tms-v2 reconcileStaleOpen(): a correction from dates, not an observation (provenance untouched). */
final class StaleOpenCloser
{
    public function run(): int
    {
        $now = MalaysiaTime::now();
        $today = $now->toDateString();
        $pastNoon = $now->format('H:i') >= '12:01';

        $closed = CollectedTender::where('status', 'open')
            ->whereNotNull('closing_date')
            ->where(fn ($q) => $q->where('closing_date', '<', $today)
                ->when($pastNoon, fn ($q) => $q->orWhere('closing_date', $today)))
            ->update(['status' => 'closed']);

        $fallbackIds = CollectedTender::where('status', 'open')
            ->whereNull('closing_date')->whereNotNull('advertised_date')
            ->get(['id', 'advertised_date'])
            ->filter(fn ($t) => CarbonImmutable::parse($t->advertised_date->toDateString())->addMonthNoOverflow()->toDateString() <= $today)
            ->pluck('id');

        return $closed + CollectedTender::whereIn('id', $fallbackIds)->update(['status' => 'closed']);
    }
}
```

- [ ] **Step 4: Run full suite — expect PASS.**
- [ ] **Step 5: Commit** — `feat: close collected tenders once their closing time has passed`

---

### Task 11: Collection runs — runner, job, start rules, daily schedule

**Files:**
- Create: `app/Collector/CollectionRunner.php`, `app/Jobs/RunCollection.php`, `app/Actions/Collector/StartCollection.php`, `app/Console/Commands/CollectDaily.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind sources, gate `collect-now`), `routes/console.php`
- Test: `tests/Feature/Collector/CollectionRunTest.php`

**Interfaces:**
- Consumes: sources (Task 7), `Merger`, `StaleOpenCloser`, `CollectionRun`
- Produces:
  - Container binding `'collector.sources'` → `list<CollectorSource>` (MyProcurement and LLM with `new PoliteFetcher(['verify' => true])`-equivalent default `new PoliteFetcher`, SPAN with `PoliteFetcher::forSpan()`); tests rebind it.
  - `CollectionRunner::run(CollectionRun $run): CollectionRun`
  - `StartCollection::handle(?User $actor, string $trigger, string $scope): ?CollectionRun` (null = already running); `StartCollection::STUCK_AFTER_HOURS = 2`; `StartCollection::failStuckRuns(): void`
  - Gate `collect-now` (manager/admin; inactive users refused by the existing `Gate::before`)
  - Command `collector:daily` scheduled every five minutes

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Actions\Collector\StartCollection;
use App\Collector\{CollectionRunner, CollectorException, CollectorSource, TenderPatch};
use App\Jobs\RunCollection;
use App\Models\{CollectedTender, CollectionRun, User};
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;

function fakeSource(string $name, int|Throwable $outcome): CollectorSource
{
    return new class($name, $outcome) implements CollectorSource
    {
        public array $scopes = [];

        public function __construct(private string $n, private int|Throwable $outcome) {}

        public function name(): string { return $this->n; }

        public function collect(string $scope, Closure $onBatch): int
        {
            $this->scopes[] = $scope;
            if ($this->outcome instanceof Throwable) {
                throw $this->outcome;
            }
            $onBatch([TenderPatch::make([
                'reference_no' => "{$this->n}-1", 'title' => 'T', 'status' => 'open', 'procurement_type' => null,
                'scraped_at' => '2026-10-07T04:00:00.000Z', 'source' => $this->n, 'source_id' => '1',
                'source_url' => "https://{$this->n}.example.test/1",
            ])]);

            return $this->outcome;
        }
    };
}

function useSources(array $sources): void
{
    app()->instance('collector.sources', $sources);
}

function runRow(array $o = []): CollectionRun
{
    return CollectionRun::create(array_merge(['trigger' => 'manual', 'scope' => 'open', 'status' => 'running', 'started_at' => now()], $o));
}

it('runs every source, saves what they found and records per-source results', function () {
    useSources([fakeSource('myprocurement', 3), fakeSource('span', 1)]);

    $run = app(CollectionRunner::class)->run(runRow());

    expect($run->status)->toBe('succeeded')
        ->and($run->results)->toBe(['myprocurement' => ['count' => 3, 'error' => null], 'span' => ['count' => 1, 'error' => null]])
        ->and($run->finished_at)->not->toBeNull()
        ->and(CollectedTender::count())->toBe(2);
});

it('keeps going when one source fails and marks the run partial', function () {
    useSources([fakeSource('span', new CollectorException('SPAN is down')), fakeSource('llm', 2)]);

    $run = app(CollectionRunner::class)->run(runRow());

    expect($run->status)->toBe('partial')
        ->and($run->results['span'])->toBe(['count' => 0, 'error' => 'SPAN is down'])
        ->and($run->results['llm']['count'])->toBe(2);
});

it('marks the run failed when every source fails', function () {
    useSources([fakeSource('span', new CollectorException('down')), fakeSource('llm', new RuntimeException('bug'))]);

    expect(app(CollectionRunner::class)->run(runRow())->status)->toBe('failed');
});

it('passes the run scope to each source and closes past-due tenders', function () {
    $source = fakeSource('llm', 0);
    useSources([$source]);
    CollectedTender::factory()->create(['closing_date' => '2020-01-01']);

    $run = app(CollectionRunner::class)->run(runRow(['scope' => 'daily']));

    expect($source->scopes)->toBe(['daily'])->and($run->closed_stale)->toBe(1);
});

it('lets managers and admins start a run, queueing the job', function () {
    Queue::fake();

    $run = app(StartCollection::class)->handle(User::factory()->manager()->create(), 'manual', 'open');

    expect($run->status)->toBe('running')->and($run->scope)->toBe('open');
    Queue::assertPushed(RunCollection::class, fn ($job) => $job->runId === $run->id);
});

it('refuses staff', function () {
    app(StartCollection::class)->handle(User::factory()->create(), 'manual', 'open');
})->throws(AuthorizationException::class);

it('refuses to start while another run is going', function () {
    Queue::fake();
    runRow();

    expect(app(StartCollection::class)->handle(User::factory()->admin()->create(), 'manual', 'open'))->toBeNull();
    Queue::assertNothingPushed();
});

it('marks a run stuck for over 2 hours as failed, then starts', function () {
    Queue::fake();
    $stuck = runRow(['started_at' => now()->subHours(3)]);

    $run = app(StartCollection::class)->handle(null, 'scheduled', 'daily');

    expect($run)->not->toBeNull()
        ->and($stuck->fresh()->status)->toBe('failed')
        ->and($stuck->fresh()->results)->toBe(['error' => 'Did not finish within 2 hours']);
});

it('the queued job marks its run failed if it crashes', function () {
    $run = runRow();

    (new RunCollection($run->id))->failed(new RuntimeException('worker died'));

    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->finished_at)->not->toBeNull();
});

it('starts the daily run once, after 12:01pm Malaysia time, catching up if missed', function () {
    Queue::fake();

    $this->travelTo(CarbonImmutable::parse('2026-10-07 11:58', 'Asia/Kuala_Lumpur'));
    $this->artisan('collector:daily')->assertSuccessful();
    expect(CollectionRun::count())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:40', 'Asia/Kuala_Lumpur')); // computer was off at noon
    $this->artisan('collector:daily');
    $this->artisan('collector:daily');
    expect(CollectionRun::where('trigger', 'scheduled')->count())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:01', 'Asia/Kuala_Lumpur'));
    CollectionRun::query()->update(['status' => 'succeeded']);
    $this->artisan('collector:daily');
    expect(CollectionRun::where('trigger', 'scheduled')->count())->toBe(2);
});

it('is scheduled every five minutes', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'collector:daily'));

    expect($event?->expression)->toBe('*/5 * * * *');
});

it('binds the three real sources by default', function () {
    expect(collect(app('collector.sources'))->map->name()->all())->toBe(['myprocurement', 'span', 'llm']);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

`app/Collector/CollectionRunner.php`:

```php
<?php

namespace App\Collector;

use App\Models\CollectionRun;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class CollectionRunner
{
    public function __construct(private Merger $merger, private StaleOpenCloser $closer) {}

    public function run(CollectionRun $run): CollectionRun
    {
        $results = [];
        foreach (app('collector.sources') as $source) {
            try {
                $count = $source->collect($run->scope, fn (array $batch) => $this->merger->merge($batch));
                $results[$source->name()] = ['count' => $count, 'error' => null];
            } catch (Throwable $e) {
                report($e);
                $results[$source->name()] = ['count' => 0, 'error' => $e->getMessage()];
            }
            $run->update(['results' => $results]);
        }

        $failed = collect($results)->whereNotNull('error')->count();
        $run->update([
            'closed_stale' => $this->closer->run(),
            'status' => match (true) {
                $failed === 0 => 'succeeded',
                $failed === count($results) => 'failed',
                default => 'partial',
            },
            'finished_at' => now(),
        ]);
        Cache::forget('collector.ministries');

        return $run;
    }
}
```

`app/Jobs/RunCollection.php`:

```php
<?php

namespace App\Jobs;

use App\Collector\CollectionRunner;
use App\Models\CollectionRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunCollection implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $runId) {}

    public function handle(CollectionRunner $runner): void
    {
        $runner->run(CollectionRun::findOrFail($this->runId));
    }

    public function failed(?Throwable $e): void
    {
        CollectionRun::whereKey($this->runId)->where('status', 'running')->update([
            'status' => 'failed',
            'finished_at' => now(),
            'results' => json_encode(['error' => $e?->getMessage() ?? 'Collection stopped unexpectedly']),
        ]);
    }
}
```

`app/Actions/Collector/StartCollection.php`:

```php
<?php

namespace App\Actions\Collector;

use App\Jobs\RunCollection;
use App\Models\{CollectionRun, User};
use Illuminate\Support\Facades\{DB, Gate};

final class StartCollection
{
    public const STUCK_AFTER_HOURS = 2;

    /** @return CollectionRun|null null when a run is already going */
    public function handle(?User $actor, string $trigger, string $scope): ?CollectionRun
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('collect-now');
        }

        $run = DB::transaction(function () use ($actor, $trigger, $scope) {
            $this->failStuckRuns();
            if (CollectionRun::where('status', 'running')->lockForUpdate()->exists()) {
                return null;
            }

            return CollectionRun::create([
                'trigger' => $trigger, 'scope' => $scope, 'started_by' => $actor?->id,
                'status' => 'running', 'started_at' => now(),
            ]);
        });

        if ($run !== null) {
            RunCollection::dispatch($run->id);
        }

        return $run;
    }

    public function failStuckRuns(): void
    {
        CollectionRun::where('status', 'running')
            ->where('started_at', '<', now()->subHours(self::STUCK_AFTER_HOURS))
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'results' => json_encode(['error' => 'Did not finish within '.self::STUCK_AFTER_HOURS.' hours']),
            ]);
    }
}
```

`app/Console/Commands/CollectDaily.php`:

```php
<?php

namespace App\Console\Commands;

use App\Actions\Collector\StartCollection;
use App\Models\CollectionRun;
use App\Support\MalaysiaTime;
use Illuminate\Console\Command;

class CollectDaily extends Command
{
    protected $signature = 'collector:daily';

    protected $description = 'Start today\'s 12:01pm (Malaysia time) collection if it has not run yet';

    public function handle(StartCollection $start): int
    {
        $now = MalaysiaTime::now();
        $fireAt = $now->setTime(12, 1);
        if ($now->lt($fireAt)) {
            return self::SUCCESS;
        }
        if (CollectionRun::where('trigger', 'scheduled')->where('started_at', '>=', $fireAt->utc())->exists()) {
            return self::SUCCESS;
        }

        $run = $start->handle(null, 'scheduled', 'daily');
        $this->info($run ? "Started collection run #{$run->id}" : 'Another collection is running; will retry in 5 minutes');

        return self::SUCCESS;
    }
}
```

`routes/console.php` — append:

```php
Schedule::command('collector:daily')->everyFiveMinutes()->withoutOverlapping();
```

`AppServiceProvider::boot()` — add:

```php
        Gate::define('collect-now', fn (User $user) => $user->role->canManageAllTenders());
```

`AppServiceProvider::register()` — add:

```php
        $this->app->bind('collector.sources', fn () => [
            new \App\Collector\Sources\MyProcurementSource(new \App\Collector\PoliteFetcher),
            new \App\Collector\Sources\SpanSource(\App\Collector\PoliteFetcher::forSpan()),
            new \App\Collector\Sources\LlmSource(new \App\Collector\PoliteFetcher),
        ]);
```

- [ ] **Step 4: Run full suite — expect PASS.** (`app()->instance()` in `useSources` overrides the `bind`.)
- [ ] **Step 5: Commit** — `feat: collection runs with one-at-a-time rule, stuck-run timeout and daily schedule`

---

### Task 12: One-time import from the old collector

**Files:**
- Modify: `docker/php/Dockerfile` (mongodb extension), `composer.json` (via composer)
- Create: `app/Collector/Legacy/LegacyTenderSource.php`, `app/Collector/Legacy/MongoLegacyTenderSource.php`, `app/Collector/Legacy/LegacyTenderMapper.php`, `app/Console/Commands/ImportLegacyTenders.php`
- Test: `tests/Feature/Collector/ImportLegacyTendersTest.php`

**Interfaces:**
- Produces: `interface LegacyTenderSource { count(): int; documents(): iterable<array>; }`; `LegacyTenderMapper::map(array $doc): array{tender: array, sources: list<array>, codes: list<string>}`; command `collector:import-legacy {--mongo-uri=} {--database=tms} {--batch=1000}` (uses a container-bound `LegacyTenderSource` if present, otherwise Mongo).

- [ ] **Step 1: Add the MongoDB extension and library**

In `docker/php/Dockerfile`, after the `pecl install pcov` line add:

```dockerfile
RUN pecl install mongodb && docker-php-ext-enable mongodb
```

```bash
docker compose build app scheduler worker && docker compose up -d
docker compose exec -T app composer require mongodb/mongodb
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Collector\Legacy\{LegacyTenderMapper, LegacyTenderSource};
use App\Models\{CollectedTender, CollectedTenderSource};

function legacyDoc(array $o = []): array
{
    return array_merge([
        '_id' => 'QT260000000021376', 'dedupKey' => 'QT260000000021376', 'referenceNo' => 'QT260000000021376',
        'title' => 'BEKALAN UBAT', 'status' => 'closed', 'procurementType' => 'quotation',
        'ministry' => 'KEMENTERIAN KESIHATAN', 'agency' => 'HKL', 'category' => 'Bekalan',
        'fieldCodes' => ['Tiada Maklumat'], 'advertisedDate' => '2026-07-16', 'closingDate' => '2026-07-23',
        'indicativePrice' => 1234.5, 'currency' => 'MYR',
        'events' => [['label' => 'Taklimat', 'date' => '2026-07-18', 'address' => null]],
        'winners' => [['name' => 'KABIMAS MANUFACTURING SDN. BHD.', 'price' => 140520]],
        'raw' => ['Kementerian' => 'KEMENTERIAN KESIHATAN'], 'scrapedAt' => '2026-07-24T04:01:00.000Z',
        'sources' => [['source' => 'myprocurement', 'sourceId' => '980576', 'sourceUrl' => 'https://myprocurement.treasury.gov.my/x']],
        '_provenance' => ['closingDate' => '2026-07-16T04:01:00.000Z', 'winners' => '2026-07-24T04:01:00.000Z'],
    ], $o);
}

function bindLegacy(array $docs): void
{
    app()->instance(LegacyTenderSource::class, new class($docs) implements LegacyTenderSource
    {
        public function __construct(private array $docs) {}

        public function count(): int { return count($this->docs); }

        public function documents(): iterable { yield from $this->docs; }
    });
}

it('maps an old document, converting ringgit to sen and camelCase to columns', function () {
    $m = LegacyTenderMapper::map(legacyDoc());

    expect($m['tender']['dedup_key'])->toBe('QT260000000021376')
        ->and($m['tender']['indicative_price_sen'])->toBe(123450)
        ->and(json_decode($m['tender']['winners'], true))->toBe([['name' => 'KABIMAS MANUFACTURING SDN. BHD.', 'price_sen' => 14052000]])
        ->and(json_decode($m['tender']['field_updated_at'], true))->toBe(['closing_date' => '2026-07-16T04:01:00.000Z', 'winners' => '2026-07-24T04:01:00.000Z'])
        ->and($m['tender']['scraped_at'])->toBe('2026-07-24 04:01:00')
        ->and($m['sources'])->toBe([['source' => 'myprocurement', 'source_id' => '980576', 'source_url' => 'https://myprocurement.treasury.gov.my/x']])
        ->and($m['codes'])->toBe(['Tiada Maklumat']);
});

it('keeps nulls as nulls (no winners yet, no price)', function () {
    $m = LegacyTenderMapper::map(legacyDoc(['winners' => null, 'indicativePrice' => null, 'closingDate' => null]));

    expect($m['tender']['winners'])->toBeNull()
        ->and($m['tender']['indicative_price_sen'])->toBeNull()
        ->and($m['tender']['closing_date'])->toBeNull();
});

it('imports in batches, prints counts per source, and is safe to run twice', function () {
    bindLegacy([
        legacyDoc(),
        legacyDoc(['_id' => 'SPAN/1', 'dedupKey' => 'SPAN/1', 'referenceNo' => 'SPAN/1', 'sources' => [['source' => 'span', 'sourceId' => '1', 'sourceUrl' => 'https://www.span.gov.my/tender/view/1']]]),
        legacyDoc(['_id' => 'K1', 'dedupKey' => 'K1', 'referenceNo' => 'K1', 'fieldCodes' => [], 'sources' => [['source' => 'kwsp', 'sourceId' => 'k1', 'sourceUrl' => 'https://www.kwsp.gov.my/x']]]),
    ]);

    $this->artisan('collector:import-legacy', ['--batch' => 2])
        ->expectsOutputToContain('Imported 3 tenders')
        ->expectsOutputToContain('myprocurement: 1')
        ->expectsOutputToContain('kwsp: 1')
        ->assertSuccessful();
    $this->artisan('collector:import-legacy', ['--batch' => 2])->assertSuccessful();

    expect(CollectedTender::count())->toBe(3)
        ->and(CollectedTenderSource::count())->toBe(3)
        ->and(CollectedTender::firstWhere('dedup_key', 'QT260000000021376')->fieldCodes->pluck('code')->all())->toBe(['Tiada Maklumat']);
});

it('skips a broken document and reports it', function () {
    bindLegacy([legacyDoc(['title' => null]), legacyDoc(['_id' => 'OK', 'dedupKey' => 'OK'])]);

    $this->artisan('collector:import-legacy')->expectsOutputToContain('Skipped 1')->assertSuccessful();

    expect(CollectedTender::pluck('dedup_key')->all())->toBe(['OK']);
});
```

- [ ] **Step 3: Run — expect FAIL.**

- [ ] **Step 4: Implement**

`app/Collector/Legacy/LegacyTenderSource.php`:

```php
<?php

namespace App\Collector\Legacy;

interface LegacyTenderSource
{
    public function count(): int;

    /** @return iterable<array> raw tms-v2 Mongo documents as arrays */
    public function documents(): iterable;
}
```

`app/Collector/Legacy/MongoLegacyTenderSource.php`:

```php
<?php

namespace App\Collector\Legacy;

use MongoDB\Client;

/** Reads tms-v2's MongoDB `tenders` collection. Only used by the one-time import. */
final class MongoLegacyTenderSource implements LegacyTenderSource
{
    private \MongoDB\Collection $collection;

    public function __construct(string $uri, string $database)
    {
        $this->collection = (new Client($uri, [], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]))
            ->selectCollection($database, 'tenders');
    }

    public function count(): int
    {
        return $this->collection->countDocuments();
    }

    public function documents(): iterable
    {
        yield from $this->collection->find([], ['batchSize' => 1000]);
    }
}
```

`app/Collector/Legacy/LegacyTenderMapper.php`:

```php
<?php

namespace App\Collector\Legacy;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class LegacyTenderMapper
{
    private const COLUMN = [
        'referenceNo' => 'reference_no', 'procurementType' => 'procurement_type', 'fieldCodes' => 'field_codes',
        'advertisedDate' => 'advertised_date', 'closingDate' => 'closing_date',
        'indicativePrice' => 'indicative_price_sen', 'scrapedAt' => 'scraped_at',
    ];

    /** @return array{tender: array, sources: list<array>, codes: list<string>} */
    public static function map(array $d): array
    {
        $key = (string) ($d['_id'] ?? $d['dedupKey'] ?? '');
        if ($key === '' || trim((string) ($d['title'] ?? '')) === '' || ! in_array($d['status'] ?? null, ['open', 'closed'], true)) {
            throw new InvalidArgumentException("Unusable legacy document {$key}");
        }

        $sen = fn ($ringgit) => $ringgit === null ? null : (int) round(((float) $ringgit) * 100);
        $winners = isset($d['winners']) && is_array($d['winners'])
            ? array_map(fn ($w) => ['name' => (string) $w['name'], 'price_sen' => $sen($w['price'] ?? null)], $d['winners'])
            : null;
        $provenance = [];
        foreach ($d['_provenance'] ?? [] as $field => $at) {
            $provenance[self::COLUMN[$field] ?? $field] = (string) $at;
        }
        $now = now()->format('Y-m-d H:i:s');

        return [
            'tender' => [
                'dedup_key' => $key,
                'reference_no' => (string) ($d['referenceNo'] ?? ''),
                'title' => (string) $d['title'],
                'status' => $d['status'],
                'procurement_type' => $d['procurementType'] ?? null,
                'ministry' => $d['ministry'] ?? null,
                'agency' => $d['agency'] ?? null,
                'category' => $d['category'] ?? null,
                'advertised_date' => $d['advertisedDate'] ?? null,
                'closing_date' => $d['closingDate'] ?? null,
                'indicative_price_sen' => $sen($d['indicativePrice'] ?? null),
                'events' => json_encode($d['events'] ?? []),
                'winners' => $winners === null ? null : json_encode($winners),
                'raw' => json_encode((object) ($d['raw'] ?? [])),
                'field_updated_at' => json_encode((object) $provenance),
                'scraped_at' => isset($d['scrapedAt']) ? CarbonImmutable::parse($d['scrapedAt'])->utc()->format('Y-m-d H:i:s') : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'sources' => array_map(fn ($s) => [
                'source' => (string) $s['source'], 'source_id' => (string) $s['sourceId'], 'source_url' => (string) $s['sourceUrl'],
            ], $d['sources'] ?? []),
            'codes' => array_values(array_unique(array_map('strval', $d['fieldCodes'] ?? []))),
        ];
    }
}
```

`app/Console/Commands/ImportLegacyTenders.php`:

```php
<?php

namespace App\Console\Commands;

use App\Collector\Legacy\{LegacyTenderMapper, LegacyTenderSource, MongoLegacyTenderSource};
use App\Models\CollectedTender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportLegacyTenders extends Command
{
    protected $signature = 'collector:import-legacy {--mongo-uri=mongodb://mongo:27017} {--database=tms} {--batch=1000}';

    protected $description = 'One-time copy of tms-v2\'s collected tenders from MongoDB (safe to re-run)';

    public function handle(): int
    {
        $source = app()->bound(LegacyTenderSource::class)
            ? app(LegacyTenderSource::class)
            : new MongoLegacyTenderSource($this->option('mongo-uri'), $this->option('database'));
        $batchSize = max(1, (int) $this->option('batch'));

        $bar = $this->output->createProgressBar($source->count());
        $batch = [];
        $imported = 0;
        $skipped = 0;
        foreach ($source->documents() as $doc) {
            try {
                $batch[] = LegacyTenderMapper::map($doc);
            } catch (InvalidArgumentException $e) {
                $skipped++;
                $this->warn($e->getMessage());
            }
            $bar->advance();
            if (count($batch) >= $batchSize) {
                $imported += $this->write($batch);
                $batch = [];
            }
        }
        $imported += $this->write($batch);
        $bar->finish();
        $this->newLine();

        $this->info("Imported {$imported} tenders. Skipped {$skipped}.");
        foreach (DB::table('collected_tender_sources')->selectRaw('source, count(*) as n')->groupBy('source')->orderBy('source')->get() as $row) {
            $this->line("  {$row->source}: {$row->n}");
        }

        return self::SUCCESS;
    }

    private function write(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }
        DB::transaction(function () use ($batch) {
            $rows = array_column($batch, 'tender');
            DB::table('collected_tenders')->upsert($rows, ['dedup_key'], array_diff(array_keys($rows[0]), ['dedup_key', 'created_at']));
            $ids = CollectedTender::whereIn('dedup_key', array_column($rows, 'dedup_key'))->pluck('id', 'dedup_key');

            DB::table('collected_tender_sources')->whereIn('collected_tender_id', $ids->values())->delete();
            DB::table('collected_tender_field_codes')->whereIn('collected_tender_id', $ids->values())->delete();
            $sources = [];
            $codes = [];
            foreach ($batch as $item) {
                $id = $ids[$item['tender']['dedup_key']];
                foreach ($item['sources'] as $s) {
                    $sources["{$id}|{$s['source']}"] = ['collected_tender_id' => $id] + $s; // one row per tender+source
                }
                foreach ($item['codes'] as $code) {
                    $codes["{$id}|{$code}"] = ['collected_tender_id' => $id, 'code' => $code];
                }
            }
            foreach (array_chunk(array_values($sources), 1000) as $chunk) {
                DB::table('collected_tender_sources')->insert($chunk);
            }
            foreach (array_chunk(array_values($codes), 1000) as $chunk) {
                DB::table('collected_tender_field_codes')->insert($chunk);
            }
        });

        return count($batch);
    }
}
```

- [ ] **Step 5: Run full suite — expect PASS.**
- [ ] **Step 6: Commit** — `feat: one-time import of tms-v2's collected tenders`

---

### Task 13: Find Tenders list, status line and "Collect now"

**Files:**
- Create: `app/Queries/CollectedTenderQuery.php`, `app/Livewire/FindTenders.php`, `resources/views/livewire/find-tenders.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/partials/sidebar.blade.php`
- Test: `tests/Feature/Livewire/FindTendersTest.php`, `tests/Integration/CollectedTenderSearchTest.php`

**Interfaces:**
- Produces: `CollectedTenderQuery::build(array $filters): Builder` (keys `search, status (open|closed|all), source, type, ministry, codes (comma-separated), from, to`); route `find-tenders.index` (`/find-tenders`); Livewire `FindTenders` with `collectNow()`; `CollectedTenderQuery::ministries(): list<string>` (cached `collector.ministries`, 1 hour).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Livewire/FindTendersTest.php`:

```php
<?php

use App\Livewire\FindTenders;
use App\Models\{CollectedTender, CollectionRun, Tender, User};
use App\Queries\CollectedTenderQuery;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function refs(array $filters): array
{
    return CollectedTenderQuery::build($filters)->pluck('reference_no')->all();
}

it('shows open tenders closing soonest first by default, undated last', function () {
    CollectedTender::factory()->create(['reference_no' => 'B', 'closing_date' => '2026-12-01']);
    CollectedTender::factory()->create(['reference_no' => 'A', 'closing_date' => '2026-11-01']);
    CollectedTender::factory()->create(['reference_no' => 'N', 'closing_date' => null]);
    CollectedTender::factory()->create(['reference_no' => 'C', 'status' => 'closed']);

    expect(refs([]))->toBe(['A', 'B', 'N'])
        ->and(refs(['status' => 'all']))->toContain('C');
});

it('filters by source, type, ministry, field codes and closing range', function () {
    $span = CollectedTender::factory()->forSource('span')->create(['reference_no' => 'S', 'procurement_type' => 'tender', 'ministry' => 'KKM', 'closing_date' => '2026-11-10']);
    $span->fieldCodes()->create(['code' => '210103']);
    CollectedTender::factory()->forSource('myprocurement')->create(['reference_no' => 'M', 'closing_date' => '2026-12-20']);

    expect(refs(['source' => 'span']))->toBe(['S'])
        ->and(refs(['type' => 'tender']))->toBe(['S'])
        ->and(refs(['ministry' => 'KKM']))->toBe(['S'])
        ->and(refs(['codes' => 'E05, 210103']))->toBe(['S'])
        ->and(refs(['from' => '2026-12-01', 'to' => '2026-12-31']))->toBe(['M'])
        ->and(refs(['type' => 'bogus', 'from' => 'garbage', 'status' => 'weird']))->toBe(['S', 'M']);
});

it('renders the list with sources, days left, price and the registered badge', function () {
    $c = CollectedTender::factory()->forSource('span')->create(['reference_no' => 'SPAN/1', 'indicative_price_sen' => 2880000]);
    Tender::factory()->create(['collected_tender_id' => $c->id, 'wo_number' => '200-07102026-001']);

    $this->get('/find-tenders')->assertOk()
        ->assertSee('Find Tenders')
        ->assertSee('SPAN/1')->assertSee('SPAN')
        ->assertSee('RM 28,800.00')
        ->assertSee('10 days left')
        ->assertSee('Registered as WO 200-07102026-001');
});

it('shows the latest run on the status line, including failures', function () {
    CollectionRun::create(['trigger' => 'scheduled', 'scope' => 'daily', 'status' => 'partial', 'started_at' => now(), 'finished_at' => now(),
        'results' => ['myprocurement' => ['count' => 1523, 'error' => null], 'span' => ['count' => 0, 'error' => 'SPAN is down']]]);

    Livewire::test(FindTenders::class)
        ->assertSee('MyProcurement 1,523')
        ->assertSee('SPAN failed: SPAN is down');
});

it('shows Collect now only to managers and admins, and starts an open-tenders run', function () {
    Queue::fake();
    Livewire::test(FindTenders::class)->assertDontSee('Collect now')->call('collectNow')->assertForbidden();

    Livewire::actingAs(User::factory()->manager()->create())->test(FindTenders::class)
        ->assertSee('Collect now')
        ->call('collectNow')
        ->assertSee('Collecting now');

    expect(CollectionRun::sole()->scope)->toBe('open');
});

it('tells the user when a collection is already running', function () {
    CollectionRun::create(['trigger' => 'scheduled', 'scope' => 'daily', 'status' => 'running', 'started_at' => now()]);

    Livewire::actingAs(User::factory()->admin()->create())->test(FindTenders::class)
        ->call('collectNow')
        ->assertSee('Already collecting');
});

it('appears in the sidebar for everyone', function () {
    $this->get('/tenders/in-progress')->assertSee('Find Tenders');
});
```

`tests/Integration/CollectedTenderSearchTest.php` (FULLTEXT only sees committed rows, hence the Integration suite):

```php
<?php

use App\Models\CollectedTender;
use App\Queries\CollectedTenderQuery;

function searchRefs(string $q): array
{
    return CollectedTenderQuery::build(['search' => $q, 'status' => 'all'])->pluck('reference_no')->sort()->values()->all();
}

beforeEach(function () {
    CollectedTender::factory()->create(['reference_no' => 'UTHM/54(KTKEM)/P/02/023/2026(1)', 'title' => 'MAKMAL ELEKTRIK DAN ELEKTRONIK']);
    CollectedTender::factory()->create(['reference_no' => 'QT1', 'title' => 'SEWAAN KOMPUTER RIBA', 'agency' => 'PUSAT DARAH NEGARA']);
});

it('finds words in title, agency and reference, matching word beginnings', function () {
    expect(searchRefs('elektrik'))->toBe(['UTHM/54(KTKEM)/P/02/023/2026(1)'])
        ->and(searchRefs('komputer riba'))->toBe(['QT1'])
        ->and(searchRefs('darah'))->toBe(['QT1'])
        ->and(searchRefs('kompu'))->toBe(['QT1']);
});

it('finds an exact reference number, punctuation and all', function () {
    expect(searchRefs('UTHM/54(KTKEM)/P/02/023/2026(1)'))->toBe(['UTHM/54(KTKEM)/P/02/023/2026(1)']);
});

it('never errors on search-operator characters or very short words', function (string $q) {
    expect(fn () => searchRefs($q))->not->toThrow(Throwable::class);
})->with(['+', '"', '*', '-', '()', '@@', 'a', 'QT', '"unclosed']);
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement the query**

```php
<?php

namespace App\Queries;

use App\Models\CollectedTender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, DB};

final class CollectedTenderQuery
{
    public static function build(array $f): Builder
    {
        $q = CollectedTender::query()->with(['sources', 'pipelineTenders:id,wo_number,collected_tender_id']);
        $status = in_array($f['status'] ?? 'open', ['open', 'closed', 'all'], true) ? ($f['status'] ?? 'open') : 'open';
        if ($status !== 'all') {
            $q->where('status', $status);
        }

        $search = trim((string) ($f['search'] ?? ''));
        if ($search !== '') {
            // Turn free text into safe FULLTEXT terms: letters/digits only, each "+word*".
            $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $search), fn ($w) => mb_strlen($w) >= 3);
            $q->where(function (Builder $w) use ($words, $search) {
                if ($words !== []) {
                    $w->whereRaw('MATCH(title, reference_no, agency) AGAINST(? IN BOOLEAN MODE)', [implode(' ', array_map(fn ($x) => "+{$x}*", $words))]);
                }
                $w->orWhere('reference_no', $search);
            });
        }

        if ($source = (string) ($f['source'] ?? '')) {
            $q->whereIn('id', DB::table('collected_tender_sources')->select('collected_tender_id')->where('source', $source));
        }
        if (in_array($f['type'] ?? '', ['quotation', 'tender', 'requisition'], true)) {
            $q->where('procurement_type', $f['type']);
        }
        if (($ministry = (string) ($f['ministry'] ?? '')) !== '') {
            $q->where('ministry', $ministry);
        }
        $codes = array_filter(array_map('trim', explode(',', (string) ($f['codes'] ?? ''))));
        if ($codes !== []) {
            $q->whereIn('id', DB::table('collected_tender_field_codes')->select('collected_tender_id')->whereIn('code', $codes));
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f[$key] ?? ''))) {
                $q->where('closing_date', $op, $f[$key]);
            }
        }

        return $status === 'open'
            ? $q->orderByRaw('closing_date IS NULL')->orderBy('closing_date')->orderBy('id')
            : $q->orderByRaw('closing_date IS NULL')->orderByDesc('closing_date')->orderByDesc('id');
    }

    /** @return list<string> */
    public static function ministries(): array
    {
        return Cache::remember('collector.ministries', 3600, fn () => CollectedTender::query()
            ->whereNotNull('ministry')->distinct()->orderBy('ministry')->pluck('ministry')->all());
    }
}
```

- [ ] **Step 4: Implement the screen**

`app/Livewire/FindTenders.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Collector\StartCollection;
use App\Collector\SourceName;
use App\Models\CollectionRun;
use App\Queries\CollectedTenderQuery;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Find Tenders')]
class FindTenders extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = 'open';
    #[Url] public string $source = '';
    #[Url] public string $type = '';
    #[Url] public string $ministry = '';
    #[Url] public string $codes = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';

    public ?string $notice = null;

    public function updated(string $property): void
    {
        if ($property !== 'notice') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'source', 'type', 'ministry', 'codes', 'from', 'to');
        $this->resetPage();
    }

    public function collectNow(): void
    {
        $this->authorize('collect-now');
        $run = app(StartCollection::class)->handle(auth()->user(), 'manual', 'open');
        $this->notice = $run ? 'Collecting now — new tenders will appear in a few minutes.' : 'Already collecting — please wait for the current run to finish.';
    }

    public function render()
    {
        app(StartCollection::class)->failStuckRuns();

        return view('livewire.find-tenders', [
            'tenders' => CollectedTenderQuery::build($this->only(['search', 'status', 'source', 'type', 'ministry', 'codes', 'from', 'to']))->paginate(25),
            'running' => CollectionRun::where('status', 'running')->latest('started_at')->first(),
            'lastRun' => CollectionRun::whereNotNull('finished_at')->latest('finished_at')->first(),
            'ministries' => CollectedTenderQuery::ministries(),
            'sources' => SourceName::all(),
        ]);
    }
}
```

`resources/views/livewire/find-tenders.blade.php`:

```blade
@php
    use App\Collector\SourceName;
    use App\Support\Money;
    $types = ['quotation' => 'Quotation', 'tender' => 'Tender', 'requisition' => 'Requisition'];
    $field = 'rounded-lg border border-line bg-surface px-2 py-1.5';
@endphp
<div class="space-y-4" @if ($running) wire:poll.15s @endif>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Find Tenders</h1>
            <p class="text-sm text-muted">Government tenders collected from MyProcurement, SPAN and LLM</p>
        </div>
        @can('collect-now')
            <button type="button" wire:click="collectNow" class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink hover:bg-chip-hover">Collect now</button>
        @endcan
    </header>

    <section class="rounded-xl border border-line bg-surface px-4 py-2 text-sm" aria-label="Collection status">
        @if ($running)
            <span class="font-medium">Collecting now…</span> started {{ $running->started_at->setTimezone('Asia/Kuala_Lumpur')->format('g:i a') }}
        @elseif ($lastRun)
            Last collected {{ $lastRun->finished_at->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') }} —
            @foreach (($lastRun->results ?? []) as $src => $r)
                @if (is_array($r))
                    @if ($r['error'])
                        <span class="text-bad-ink">{{ SourceName::label($src) }} failed: {{ $r['error'] }}</span>
                    @else
                        {{ SourceName::label($src) }} {{ number_format($r['count']) }}
                    @endif
                    @if (! $loop->last) · @endif
                @else
                    <span class="text-bad-ink">{{ $r }}</span>
                @endif
            @endforeach
        @else
            Not collected yet.
        @endif
        @if ($notice) <p class="mt-1 text-info-ink">{{ $notice }}</p> @endif
    </section>

    <section class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-3 text-sm">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search title, reference, agency" class="{{ $field }} min-w-48 flex-1">
        <select wire:model.live="status" class="{{ $field }}" aria-label="Status">
            <option value="open">Open</option><option value="closed">Closed</option><option value="all">All</option>
        </select>
        <select wire:model.live="source" class="{{ $field }}" aria-label="Source">
            <option value="">All sources</option>
            @foreach ($sources as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
        </select>
        <select wire:model.live="type" class="{{ $field }}" aria-label="Type">
            <option value="">All types</option>
            @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
        </select>
        <input wire:model.live.debounce.400ms="ministry" list="ministry-options" placeholder="Ministry" class="{{ $field }} w-56">
        <datalist id="ministry-options">@foreach ($ministries as $m) <option value="{{ $m }}"></option> @endforeach</datalist>
        <input wire:model.live.debounce.400ms="codes" placeholder="Field codes, e.g. 210103, E05" class="{{ $field }} w-52">
        <label class="flex items-center gap-1">Closing <input type="date" wire:model.live="from" class="{{ $field }}"></label>
        <label class="flex items-center gap-1">to <input type="date" wire:model.live="to" class="{{ $field }}"></label>
        <button type="button" wire:click="clearFilters" class="text-muted hover:text-ink">Clear</button>
    </section>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[960px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-3 py-2">Reference</th><th class="px-3 py-2">Title</th><th class="px-3 py-2">Ministry / Agency</th>
                    <th class="px-3 py-2">Type</th><th class="px-3 py-2">Advertised</th><th class="px-3 py-2">Closing</th>
                    <th class="px-3 py-2 text-right">Indicative price</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tenders as $t)
                @php $days = $t->status === 'open' ? $t->daysLeft() : null; $wo = $t->pipelineTenders->first(); @endphp
                <tr wire:key="ct-{{ $t->id }}" class="cursor-pointer border-t border-line align-top hover:bg-hover"
                    onclick="window.location='{{ route('find-tenders.show', $t) }}'">
                    <td class="px-3 py-2">
                        <a href="{{ route('find-tenders.show', $t) }}" class="font-medium hover:underline">{{ $t->reference_no ?: '—' }}</a>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($t->sources as $s) <span class="rounded bg-subtle px-1.5 text-xs text-muted">{{ SourceName::label($s->source) }}</span> @endforeach
                        </div>
                        @if ($wo) <span class="mt-1 inline-block rounded bg-good-bg px-1.5 text-xs text-good-ink">Registered as WO {{ $wo->wo_number }}</span> @endif
                    </td>
                    <td class="max-w-md px-3 py-2"><div class="line-clamp-3">{{ $t->title }}</div></td>
                    <td class="px-3 py-2">{{ $t->ministry ?? '—' }}<div class="text-xs text-muted">{{ $t->agency }}</div></td>
                    <td class="px-3 py-2">{{ $types[$t->procurement_type] ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $t->advertised_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $t->closing_date?->format('d M Y') ?? '—' }}
                        @if ($days !== null)
                            <div @class(['text-xs', 'text-bad-ink' => $days <= 3, 'text-muted' => $days > 3])>
                                {{ $days === 0 ? 'Closes today' : ($days === 1 ? '1 day left' : "{$days} days left") }}
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ Money::format($t->indicative_price_sen) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-10 text-center text-muted">No tenders match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <footer class="flex items-center justify-between text-sm text-muted">
        <span>@if ($tenders->total()) Showing {{ number_format($tenders->firstItem()) }}–{{ number_format($tenders->lastItem()) }} of {{ number_format($tenders->total()) }} @endif</span>
        {{ $tenders->links('pagination.pager') }}
    </footer>
</div>
```

Routes (inside the `auth`/`active` group in `routes/web.php`):

```php
    Route::get('/find-tenders', \App\Livewire\FindTenders::class)->name('find-tenders.index');
```

Sidebar (`layouts/partials/sidebar.blade.php`): change the Operations group to

```php
        'Operations' => [
            ['href' => route('find-tenders.index'), 'label' => 'Find Tenders', 'count' => null, 'active' => request()->routeIs('find-tenders.*')],
            $item('tenders.index', ['in-progress'], 'In Progress', $counts['in_progress']),
        ],
```

and wrap the count badge in `@if (! is_null($i['count']))`.

Note: `route('find-tenders.show', …)` is added in Task 14; until then add a placeholder route `Route::get('/find-tenders/{collectedTender}', fn () => '')->whereNumber('collectedTender')->name('find-tenders.show');` which Task 14 replaces.

- [ ] **Step 5: Run full suite (both suites) — expect PASS.** `docker compose exec -T app ./vendor/bin/pest --colors=never`
- [ ] **Step 6: Browser check** — log in as admin, open Find Tenders with a few factory rows (`php artisan tinker --execute="App\Models\CollectedTender::factory()->count(30)->forSource('myprocurement')->create();"`), try each filter, search, page buttons, dark mode, 375px width.
- [ ] **Step 7: Commit** — `feat: Find Tenders list with filters, status line and Collect now`

---

### Task 14: Collected tender detail, "Register this tender" and the pipeline link

**Files:**
- Create: `app/Livewire/CollectedTenderDetail.php`, `resources/views/livewire/collected-tender-detail.blade.php`
- Modify: `routes/web.php` (replace placeholder), `app/Livewire/RegisterTenderModal.php`, `app/Livewire/Forms/TenderForm.php`, `resources/views/livewire/register-tender-modal.blade.php`, `resources/views/livewire/tender-detail/overview.blade.php`
- Test: `tests/Feature/Livewire/CollectedTenderDetailTest.php`

**Interfaces:**
- Consumes: `RegisterTender` (FIELDS includes `collected_tender_id`), `TenderForm`, `CollectedTender`
- Produces: route `find-tenders.show` (`/find-tenders/{collectedTender}`); `TenderForm::fillFromCollected(CollectedTender $c): void`; `RegisterTenderModal::show(?int $collectedTenderId = null)` listening to `open-register-tender` with optional `collectedTenderId`; modal property `?int $collectedTenderId` validated with `exists:collected_tenders,id` on save.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Livewire\{CollectedTenderDetail, RegisterTenderModal, TenderDetail};
use App\Models\{CollectedTender, Tender, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function collected(array $o = [], string $source = 'myprocurement'): CollectedTender
{
    return CollectedTender::factory()->forSource($source, '980576')->create(array_merge([
        'reference_no' => 'QT260000000041127', 'title' => 'SISTEM RONDAAN', 'procurement_type' => 'requisition',
        'ministry' => 'KEMENTERIAN DALAM NEGERI', 'agency' => 'JABATAN PERPADUAN NEGARA',
        'advertised_date' => '2026-09-08', 'closing_date' => '2026-10-15', 'indicative_price_sen' => 16200610,
        'status' => 'closed', 'winners' => [['name' => 'ACME SDN BHD', 'price_sen' => 15000000]],
        'events' => [['label' => 'Taklimat', 'date' => '2026-09-22', 'address' => 'Putrajaya']],
        'raw' => ['Kod Bidang' => '210103'],
    ], $o));
}

it('shows all details, events, winners, source links and raw fields', function () {
    $c = collected();

    $this->get(route('find-tenders.show', $c))->assertOk()
        ->assertSee('SISTEM RONDAAN')->assertSee('QT260000000041127')
        ->assertSee('Taklimat')->assertSee('Putrajaya')
        ->assertSee('ACME SDN BHD')->assertSee('RM 150,000.00')
        ->assertSee('View on MyProcurement')->assertSee('https://myprocurement.example.test/'.$c->id)
        ->assertSee('All original fields')->assertSee('Kod Bidang')
        ->assertSee('Register this tender');
});

it('pre-fills the Register form from the collected tender', function () {
    $c = collected();

    Livewire::test(RegisterTenderModal::class)
        ->call('show', $c->id)
        ->assertSet('collectedTenderId', $c->id)
        ->assertSet('form.tenderCode', 'QT260000000041127')
        ->assertSet('form.title', 'SISTEM RONDAAN')
        ->assertSet('form.client', 'JABATAN PERPADUAN NEGARA')
        ->assertSet('form.publishDate', '2026-09-08')
        ->assertSet('form.closingDate', '2026-10-15')
        ->assertSet('form.estimatedValue', '162006.10')
        ->assertSet('form.type', 'QUOTATION')
        ->assertSet('form.mode', 'EP')
        ->assertSet('form.category', 'General')
        ->assertSet('form.picId', '');
});

it('uses the ministry when there is no agency, Non-EP for other sources, and Tender for tenders', function () {
    $c = collected(['agency' => null, 'procurement_type' => 'tender', 'closing_date' => null], 'span');

    Livewire::test(RegisterTenderModal::class)->call('show', $c->id)
        ->assertSet('form.client', 'KEMENTERIAN DALAM NEGERI')
        ->assertSet('form.mode', 'NON_EP')
        ->assertSet('form.type', 'TENDER')
        ->assertSet('form.closingDate', '');
});

it('saves the link and then offers "Open in pipeline" instead', function () {
    $c = collected();

    Livewire::test(RegisterTenderModal::class)->call('show', $c->id)
        ->set('form.picId', (string) User::factory()->create()->id)
        ->call('save');

    $tender = Tender::sole();
    expect($tender->collected_tender_id)->toBe($c->id);

    Livewire::test(CollectedTenderDetail::class, ['collectedTender' => $c])
        ->assertSee('Open in pipeline (WO '.$tender->wo_number.')')
        ->assertDontSee('Register this tender');
});

it('refuses a link to a collected tender that does not exist', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')
        ->set('collectedTenderId', 999999)
        ->set('form.tenderCode', 'X')->set('form.title', 'X')->set('form.client', 'X')
        ->set('form.picId', (string) User::factory()->create()->id)->set('form.closingDate', '2026-12-01')
        ->call('save')
        ->assertHasErrors('collectedTenderId');

    expect(Tender::count())->toBe(0);
});

it('the plain Register button still opens an empty form', function () {
    Livewire::test(RegisterTenderModal::class)->call('show')
        ->assertSet('collectedTenderId', null)->assertSet('form.tenderCode', '');
});

it('shows where a pipeline tender was collected from', function () {
    $c = collected();
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['collected_tender_id' => $c->id, 'pic_id' => $pic->id]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('Collected from MyProcurement')
        ->assertSee(route('find-tenders.show', $c));
});

it('404s for an unknown collected tender', function () {
    $this->get('/find-tenders/999999')->assertNotFound();
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Pre-fill in `TenderForm`** — add:

```php
    public function fillFromCollected(\App\Models\CollectedTender $c): void
    {
        $this->reset();
        $this->tenderCode = $c->reference_no;
        $this->title = $c->title;
        $this->client = (string) ($c->agency ?? $c->ministry ?? '');
        $this->publishDate = (string) $c->advertised_date?->toDateString();
        $this->closingDate = (string) $c->closing_date?->toDateString();
        $this->estimatedValue = Money::toInput($c->indicative_price_sen);
        $this->type = $c->procurement_type === 'tender' ? 'TENDER' : 'QUOTATION'; // requisition → Quotation
        $this->mode = $c->sources->contains('source', 'myprocurement') ? 'EP' : 'NON_EP';
        $this->category = 'General';
    }
```

- [ ] **Step 4: Modal changes (`RegisterTenderModal`)**

Add `public ?int $collectedTenderId = null;`. Replace `show()`:

```php
    #[On('open-register-tender')]
    public function show(?int $collectedTenderId = null): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->confirmDuplicate = false;
        $this->duplicateWoNumbers = [];
        $this->collectedTenderId = null;
        if ($collectedTenderId !== null && ($c = \App\Models\CollectedTender::with('sources')->find($collectedTenderId))) {
            $this->form->fillFromCollected($c);
            $this->collectedTenderId = $c->id;
        }
        $this->open = true;
    }
```

In `save()`, before `$this->form->validate()` add:

```php
        $this->validate(['collectedTenderId' => ['nullable', 'integer', 'exists:collected_tenders,id']]);
```

and pass the link when registering:

```php
        $tender = app(RegisterTender::class)->handle(auth()->user(), [
            ...$this->form->toData(),
            'collected_tender_id' => $this->collectedTenderId,
        ]);
```

In `register-tender-modal.blade.php`, under the heading, add:

```blade
                @if ($collectedTenderId)
                    <p class="rounded-lg bg-info-bg px-3 py-2 text-sm text-info-ink">Filled in from Find Tenders — check the details and choose a PIC.</p>
                @endif
```

- [ ] **Step 5: Detail screen**

`app/Livewire/CollectedTenderDetail.php`:

```php
<?php

namespace App\Livewire;

use App\Models\CollectedTender;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CollectedTenderDetail extends Component
{
    public CollectedTender $collectedTender;

    public function render()
    {
        $this->collectedTender->load(['sources', 'fieldCodes', 'pipelineTenders:id,wo_number,collected_tender_id']);

        return view('livewire.collected-tender-detail', ['t' => $this->collectedTender])
            ->title($this->collectedTender->reference_no ?: 'Collected tender');
    }
}
```

`resources/views/livewire/collected-tender-detail.blade.php`:

```blade
@php
    use App\Collector\SourceName;
    use App\Support\Money;
    $types = ['quotation' => 'Quotation', 'tender' => 'Tender', 'requisition' => 'Requisition'];
    $wo = $t->firstPipelineTender();
@endphp
<div class="space-y-4">
    <a href="{{ route('find-tenders.index') }}" class="text-sm text-muted hover:text-ink">← Back to Find Tenders</a>

    <header class="rounded-xl border border-line bg-surface p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold">{{ $t->reference_no ?: 'No reference number' }}</span>
            <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-info-bg text-info-ink' => $t->status === 'open', 'bg-subtle text-muted' => $t->status !== 'open'])>{{ ucfirst($t->status) }}</span>
            @foreach ($t->sources as $s) <span class="rounded bg-subtle px-1.5 text-xs text-muted">{{ SourceName::label($s->source) }}</span> @endforeach
            <div class="ml-auto">
                @if ($wo)
                    <a href="{{ route('tenders.show', $wo) }}" class="rounded-lg bg-good-bg px-3 py-1.5 text-sm font-medium text-good-ink">Open in pipeline (WO {{ $wo->wo_number }})</a>
                @else
                    <button type="button" wire:click="$dispatch('open-register-tender', { collectedTenderId: {{ $t->id }} })"
                            class="rounded-lg bg-chip px-3 py-1.5 text-sm font-medium text-chip-ink hover:bg-chip-hover">Register this tender</button>
                @endif
            </div>
        </div>
        <h1 class="mt-3 text-lg font-semibold">{{ $t->title }}</h1>
    </header>

    <section class="rounded-xl border border-line bg-surface p-4">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Ministry' => $t->ministry ?? '—', 'Agency' => $t->agency ?? '—', 'Category' => $t->category ?? '—',
                'Type' => $types[$t->procurement_type] ?? '—',
                'Advertised' => $t->advertised_date?->format('d M Y') ?? '—',
                'Closing' => $t->closing_date?->format('d M Y') ?? '—',
                'Indicative price' => Money::format($t->indicative_price_sen),
                'Field codes' => $t->fieldCodes->pluck('code')->implode(', ') ?: '—',
                'Last collected' => $t->scraped_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') ?? '—',
            ] as $label => $value)
                <div><dt class="text-xs uppercase tracking-wide text-muted">{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            @foreach ($t->sources as $s)
                <a href="{{ $s->source_url }}" target="_blank" rel="noopener noreferrer" class="text-info-ink underline">View on {{ SourceName::label($s->source) }}</a>
            @endforeach
        </div>
    </section>

    @if ($t->events)
        <section class="rounded-xl border border-line bg-surface p-4 text-sm">
            <h2 class="mb-2 font-medium">Briefings and site visits</h2>
            <table class="w-full"><tbody>
                @foreach ($t->events as $e)
                    <tr class="border-t border-line"><td class="py-1.5 pr-4">{{ $e['label'] }}</td><td class="py-1.5 pr-4 whitespace-nowrap">{{ $e['date'] ? \Carbon\CarbonImmutable::parse($e['date'])->format('d M Y') : '—' }}</td><td class="py-1.5">{{ $e['address'] ?? '—' }}</td></tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

    @if ($t->winners)
        <section class="rounded-xl border border-line bg-surface p-4 text-sm">
            <h2 class="mb-2 font-medium">Winners</h2>
            <table class="w-full"><tbody>
                @foreach ($t->winners as $w)
                    <tr class="border-t border-line"><td class="py-1.5">{{ $w['name'] }}</td><td class="py-1.5 text-right">{{ Money::format($w['price_sen']) }}</td></tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

    <details class="rounded-xl border border-line bg-surface p-4 text-sm">
        <summary class="cursor-pointer font-medium">All original fields</summary>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (($t->raw ?? []) as $label => $value)
                <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="break-words">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </details>

    <livewire:register-tender-modal />
</div>
```

Route — replace the Task 13 placeholder:

```php
    Route::get('/find-tenders/{collectedTender}', \App\Livewire\CollectedTenderDetail::class)
        ->whereNumber('collectedTender')->name('find-tenders.show');
```

Pipeline backlink — at the end of the read-only branch of `tender-detail/overview.blade.php` (after "Scope of work"):

```blade
        @if ($tender->collectedTender)
            <p class="mt-4 text-sm text-muted">
                Collected from {{ implode(', ', $tender->collectedTender->sourceNames()) }} —
                <a href="{{ route('find-tenders.show', $tender->collectedTender) }}" class="text-info-ink underline">view original</a>
            </p>
        @endif
```

- [ ] **Step 6: Run full suite — expect PASS.**
- [ ] **Step 7: Browser check** — open a collected tender, Register this tender, check every pre-filled field, choose a PIC, save; back on Find Tenders the row shows "Registered as WO …"; the detail button now opens the pipeline tender, whose Overview links back.
- [ ] **Step 8: Commit** — `feat: collected tender detail with pre-filled Register and pipeline link`

---

### Task 15: Import the real data, supervised live run, README and walkthrough

**Files:**
- Modify: `README.md`, `CLAUDE.md`

- [ ] **Step 1: Make a copy of the old database (never import from the live one first)**

```bash
docker exec tms-v2-mongo-1 sh -c "mongodump --archive --db=tms | mongorestore --archive --nsFrom='tms.*' --nsTo='tms_copy.*' --drop"
docker exec tms-v2-mongo-1 mongosh tms_copy --quiet --eval 'db.tenders.countDocuments()'
```

Expected: `206645` (or the current live count).

- [ ] **Step 2: Let Tender Hub reach that Mongo server**

```bash
docker network connect cmt-tender-hub_default tms-v2-mongo-1 --alias mongo
```

- [ ] **Step 3: Import from the copy and check counts**

```bash
docker compose exec -T app php artisan collector:import-legacy --mongo-uri=mongodb://mongo:27017 --database=tms_copy
```

Expected output ends with per-source counts matching the spec (myprocurement ≈ 206,291; span 181; llm 148; kwsp 126). Re-run once to prove it is idempotent (counts unchanged). Spot-check 3 tenders against tms-v2's UI (title, closing date, winners and prices).

- [ ] **Step 4: Supervised live run** — start `worker`, log in as admin, open Find Tenders, click **Collect now**. Watch `docker compose logs -f worker` until the run finishes. Expected: status line shows a finished run with MyProcurement ≈ 1,500, SPAN and LLM small counts, and no failures (or a clearly reported failure reason). This is the only contact with the real sites.

- [ ] **Step 5: Disconnect the old Mongo from Tender Hub's network**

```bash
docker network disconnect cmt-tender-hub_default tms-v2-mongo-1
```

- [ ] **Step 6: README and CLAUDE.md** — add a "Collector" section:

```markdown
## Collector (Find Tenders)

- Collects MyProcurement, SPAN and LLM daily at 12:01pm Malaysia time (catches up if the PC was off),
  plus "Collect now" for Managers/Admins (open tenders only). Runs in the `worker` container.
- KWSP was dropped (Cloudflare blocks non-browser downloads); its imported history remains.
- `php artisan collector:import-legacy --mongo-uri=… --database=…` copied tms-v2's 206k tenders (one-time; safe to re-run).
- Tests never contact the real sites (`Http::preventStrayRequests()`); parsers are tested on saved pages in `tests/Fixtures/collector`.
```

In `CLAUDE.md` add under Rules: `- Never let a test contact a government site; use tests/Fixtures/collector and Tests\Support\FakeFetcher.`

- [ ] **Step 7: Full browser walkthrough** (Playwright MCP): Find Tenders default list; each filter; search with punctuation; closed tender with winners; Register this tender → badge → Open in pipeline → backlink; Collect now as staff (no button) and as admin (already running message during a run); status line after a failed run (simulate by temporarily editing a run row's results in tinker, then restore); dark mode; 375px.

- [ ] **Step 8: Coverage and commit**

```bash
docker compose exec -T app ./vendor/bin/pest --coverage --min=80
git add -A
git commit -m "docs: collector notes; import and live run verified

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Self-review notes (spec coverage)

| Spec section | Task(s) |
|---|---|
| §2 Sources (jobs per scope, SPAN cert, LLM pagination quirk, parser parity) | 3, 4, 5, 6, 7 |
| §3 Architecture units (fetcher, sources, parsers, patch, merger, closer, job, import, scheduler, worker) | 1–12 |
| §4 Data model incl. `tenders.collected_tender_id`, FULLTEXT | 8, 13 |
| §5 Runs: daily/open scopes, one-at-a-time, stuck 2 h, statuses, MyProcurement zero guard | 7, 11 |
| §6 Screens: sidebar, list columns/filters/status line/Collect now, detail, pre-fill table, Open in pipeline, backlink | 13, 14 |
| §7 Legacy import, idempotent, counts, copy-first | 12, 15 |
| §8 Error handling | 3–7, 11, 12 |
| §9 Testing incl. no live calls, coverage | 1 (preventStrayRequests), all |
| §11 Retiring tms-v2 (ask first) | after merge — not a task |
