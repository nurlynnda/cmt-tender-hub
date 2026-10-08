<?php

namespace App\Actions\Collector;

use App\Jobs\RunCollection;
use App\Models\{CollectionRun, User};
use Illuminate\Support\Facades\{DB, Gate};

final class StartCollection
{
    public const STUCK_AFTER_HOURS = 2;

    /** A run reports progress after every saved batch; this long with none means the worker died. */
    public const NO_PROGRESS_MINUTES = 15;

    public const DIED_PREFIX = 'Did not finish';

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
                'status' => 'running', 'started_at' => now(), 'heartbeat_at' => now(),
            ]);
        });

        if ($run !== null) {
            RunCollection::dispatch($run->id);
        }

        return $run;
    }

    public function failStuckRuns(): void
    {
        $this->fail(
            CollectionRun::where('status', 'running')->where('started_at', '<', now()->subHours(self::STUCK_AFTER_HOURS)),
            self::DIED_PREFIX.' within '.self::STUCK_AFTER_HOURS.' hours',
        );
        $this->fail(
            CollectionRun::where('status', 'running')
                ->whereRaw('COALESCE(heartbeat_at, started_at) < ?', [now()->subMinutes(self::NO_PROGRESS_MINUTES)]),
            self::DIED_PREFIX.' (no progress for '.self::NO_PROGRESS_MINUTES.' minutes)',
        );
    }

    private function fail($query, string $reason): void
    {
        $query->update(['status' => 'failed', 'finished_at' => now(), 'results' => json_encode(['error' => $reason])]);
    }
}
