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
