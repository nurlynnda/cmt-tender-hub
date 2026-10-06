<?php

use App\Actions\Collector\StartCollection;
use App\Collector\{CollectionRunner, CollectorSource, TenderPatch};
use App\Collector\Legacy\LegacyTenderMapper;
use App\Models\{CollectedTender, CollectionRun};
use App\Queries\CollectedTenderQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Queue, Schema};

// Final-review fixes (Stage 2). Each test reproduces one finding.

it('I1: sorts closed/all lists by an indexable order and has a matching index', function () {
    expect(CollectedTenderQuery::build(['status' => 'closed'])->toSql())->not->toContain('IS NULL')
        ->and(CollectedTenderQuery::build(['status' => 'all'])->toSql())->not->toContain('IS NULL');

    $indexes = collect(Schema::getIndexes('collected_tenders'))->pluck('columns')->map(fn ($c) => implode(',', $c));
    expect($indexes)->toContain('status,closing_date,id');
});

it('I2: never lists a tender as open once its closing time has passed, even mid-run', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:05', 'Asia/Kuala_Lumpur'));
    CollectedTender::factory()->create(['reference_no' => 'PAST', 'closing_date' => '2026-09-01']);   // status still "open"
    CollectedTender::factory()->create(['reference_no' => 'TODAY', 'closing_date' => '2026-10-07']); // past 12:01 today
    CollectedTender::factory()->create(['reference_no' => 'FUTURE', 'closing_date' => '2026-10-08']);
    CollectedTender::factory()->create(['reference_no' => 'UNDATED', 'closing_date' => null]);

    expect(CollectedTenderQuery::build([])->pluck('reference_no')->all())->toBe(['FUTURE', 'UNDATED']);
});

it('I2: still lists today\'s tenders as open before 12:01pm Malaysia time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 11:30', 'Asia/Kuala_Lumpur'));
    CollectedTender::factory()->create(['reference_no' => 'TODAY', 'closing_date' => '2026-10-07']);

    expect(CollectedTenderQuery::build([])->pluck('reference_no')->all())->toBe(['TODAY']);
});

it('I3: a run with no progress for 15 minutes counts as dead, even if it started under 2 hours ago', function () {
    Queue::fake();
    $dead = CollectionRun::create(['trigger' => 'manual', 'scope' => 'open', 'status' => 'running',
        'started_at' => now()->subMinutes(30), 'heartbeat_at' => now()->subMinutes(20)]);
    $alive = fn () => CollectionRun::create(['trigger' => 'manual', 'scope' => 'open', 'status' => 'running',
        'started_at' => now()->subMinutes(30), 'heartbeat_at' => now()->subMinutes(2)]);

    expect(app(StartCollection::class)->handle(null, 'scheduled', 'daily'))->not->toBeNull()
        ->and($dead->fresh()->status)->toBe('failed');

    CollectionRun::query()->update(['status' => 'succeeded']);
    $alive();
    expect(app(StartCollection::class)->handle(null, 'scheduled', 'daily'))->toBeNull();
});

it('I3: the runner records a heartbeat as each batch is saved', function () {
    app()->instance('collector.sources', [new class implements CollectorSource
    {
        public function name(): string { return 'llm'; }

        public function collect(string $scope, Closure $onBatch): int
        {
            $onBatch([TenderPatch::make(['reference_no' => 'H1', 'title' => 'T', 'status' => 'open', 'procurement_type' => null,
                'scraped_at' => '2026-10-07T04:00:00.000Z', 'source' => 'llm', 'source_id' => '1', 'source_url' => 'https://llm.example.test/1'])]);

            return 1;
        }
    }]);
    $run = CollectionRun::create(['trigger' => 'manual', 'scope' => 'open', 'status' => 'running', 'started_at' => now()->subMinute()]);

    app(CollectionRunner::class)->run($run);

    expect($run->fresh()->heartbeat_at)->not->toBeNull();
});

it('I3: gives the daily run another chance when today\'s scheduled run died', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:00', 'Asia/Kuala_Lumpur'));
    CollectionRun::create(['trigger' => 'scheduled', 'scope' => 'daily', 'status' => 'failed',
        'started_at' => now()->subHours(2), 'finished_at' => now(), 'results' => ['error' => 'Did not finish (no progress for 15 minutes)']]);

    $this->artisan('collector:daily');

    expect(CollectionRun::where('trigger', 'scheduled')->count())->toBe(2);
});

it('I3: does not retry a daily run that finished (even if sources failed)', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:00', 'Asia/Kuala_Lumpur'));
    CollectionRun::create(['trigger' => 'scheduled', 'scope' => 'daily', 'status' => 'failed',
        'started_at' => now()->subHour(), 'finished_at' => now(), 'results' => ['span' => ['count' => 0, 'error' => 'down']]]);

    $this->artisan('collector:daily');

    expect(CollectionRun::where('trigger', 'scheduled')->count())->toBe(1);
});

it('M1: refuses source links that are not http(s)', function () {
    TenderPatch::make(['reference_no' => 'X', 'title' => 'T', 'status' => 'open', 'procurement_type' => null,
        'scraped_at' => '2026-10-07T04:00:00.000Z', 'source' => 'llm', 'source_id' => '1', 'source_url' => 'javascript://x/%0aalert(1)']);
})->throws(InvalidArgumentException::class);

it('M1: drops non-http(s) links from imported documents', function () {
    $m = LegacyTenderMapper::map(['_id' => 'K', 'title' => 'T', 'status' => 'closed', 'sources' => [
        ['source' => 'llm', 'sourceId' => '1', 'sourceUrl' => 'javascript://x/%0aalert(1)'],
        ['source' => 'span', 'sourceId' => '2', 'sourceUrl' => 'https://www.span.gov.my/tender/view/2'],
    ]]);

    expect(array_column($m['sources'], 'source'))->toBe(['span']);
});
