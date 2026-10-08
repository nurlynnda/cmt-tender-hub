<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MarkTenderLost
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, ?int $winningPriceSen, ?string $reason): Tender
    {
        if ($winningPriceSen !== null && $winningPriceSen < 0) {
            throw new InvalidArgumentException('Winning price cannot be negative.');
        }
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $winningPriceSen, $reason) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::Done, 'mark as Lost');

            $t->forceFill([
                'status' => TenderStatus::Lost,
                'winning_price_sen' => $winningPriceSen,
                'lost_reason' => $reason,
                'lost_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            $description = 'Marked Lost'
                .($winningPriceSen !== null ? ' — winning price '.Money::format($winningPriceSen) : '')
                .($reason !== null ? " — reason: {$reason}" : '');
            ActivityLog::record($t, $actor, 'marked_lost', $description);

            return $t->fresh();
        });
    }
}
