<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Exceptions\DocumentsIncomplete;
use App\Models\{ActivityLog, Tender, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MarkTenderDone
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $submittedPriceSen): Tender
    {
        if ($submittedPriceSen <= 0) {
            throw new InvalidArgumentException('Submitted price must be more than zero.');
        }

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $submittedPriceSen) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'mark as Done');

            $pending = $t->documents()->where('is_done', false)->pluck('name')->all();
            if ($pending !== []) {
                throw new DocumentsIncomplete($pending);
            }

            $t->forceFill([
                'status' => TenderStatus::Done,
                'submitted_price_sen' => $submittedPriceSen,
                'done_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'marked_done', 'Marked Done — submitted price '.Money::format($submittedPriceSen));

            return $t->fresh();
        });
    }
}
