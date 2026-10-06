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
