<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Exceptions\{CostingRequired, DocumentsIncomplete};
use App\Models\{ActivityLog, Tender, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class MarkTenderDone
{
    use GuardsTender;

    /** The submitted price is always the saved costing's bid price. */
    public function handle(User $actor, Tender $tender, int $expectedVersion): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'mark as Done');

            $pending = $t->documents()->where('is_done', false)->pluck('name')->all();
            if ($pending !== []) {
                throw new DocumentsIncomplete($pending);
            }
            if (! $t->hasCosting()) {
                throw new CostingRequired;
            }
            $price = $t->costingSummary()['bid_price_sen'];

            $t->forceFill([
                'status' => TenderStatus::Done,
                'submitted_price_sen' => $price,
                'done_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'marked_done', 'Marked Done — submitted price '.Money::format($price));

            return $t->fresh();
        });
    }
}
