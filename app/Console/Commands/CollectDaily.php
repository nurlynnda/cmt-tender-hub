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
        // A scheduled run that died part-way (worker/PC restarted) doesn't count — try again.
        $done = CollectionRun::where('trigger', 'scheduled')->where('started_at', '>=', $fireAt->utc())->get()
            ->reject(fn (CollectionRun $r) => $r->status === 'failed'
                && str_starts_with((string) ($r->results['error'] ?? ''), StartCollection::DIED_PREFIX));
        if ($done->isNotEmpty()) {
            return self::SUCCESS;
        }

        $run = $start->handle(null, 'scheduled', 'daily');
        $this->info($run ? "Started collection run #{$run->id}" : 'Another collection is running; will retry in 5 minutes');

        return self::SUCCESS;
    }
}
