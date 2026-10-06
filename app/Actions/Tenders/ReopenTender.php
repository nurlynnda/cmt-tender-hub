<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Exceptions\InvalidTenderTransition;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class ReopenTender
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion, 'reopen');
            if ($t->status === TenderStatus::InProgress) {
                throw InvalidTenderTransition::make($t->status, 'reopen');
            }
            $was = $t->status->label();

            $t->forceFill([
                'status' => TenderStatus::InProgress,
                'submitted_price_sen' => null,
                'winning_price_sen' => null,
                'lost_reason' => null,
                'was_cancelled' => false,
                'done_at' => null,
                'awarded_at' => null,
                'lost_at' => null,
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'reopened', "Reopened (was {$was})");

            return $t->fresh();
        });
    }
}
