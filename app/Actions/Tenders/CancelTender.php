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
