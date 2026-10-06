<?php

namespace App\Collector;

use App\Models\CollectionRun;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Runs every source for one collection run; a failing source is recorded and the rest carry on. */
final class CollectionRunner
{
    public function __construct(private Merger $merger, private StaleOpenCloser $closer) {}

    public function run(CollectionRun $run): CollectionRun
    {
        $results = [];
        foreach (app('collector.sources') as $source) {
            try {
                $count = $source->collect($run->scope, function (array $batch) use ($run) {
                    $this->merger->merge($batch);
                    $run->forceFill(['heartbeat_at' => now()])->save(); // proof of life for StartCollection::failStuckRuns
                });
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
