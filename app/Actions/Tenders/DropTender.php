<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

/** The company decided not to bid: In Progress → Dropped (kept out of the win rate and bid values). */
final class DropTender
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, ?string $reason): Tender
    {
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $reason) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'drop');

            $t->forceFill([
                'status' => TenderStatus::Dropped,
                'drop_reason' => $reason,
                'dropped_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'dropped', 'Dropped'.($reason !== null ? " — reason: {$reason}" : ''));

            return $t->fresh();
        });
    }
}
