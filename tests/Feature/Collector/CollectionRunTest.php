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
