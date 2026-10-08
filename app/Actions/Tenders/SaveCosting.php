<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use App\Support\{Money, Percent};
use Illuminate\Support\Facades\DB;

final class SaveCosting
{
    use GuardsTender;

    /** @param array{default_margin_bp:int, bid_price_override_sen:?int, lines:list<array>} $data from CostingForm::toData() */
    public function handle(User $actor, Tender $tender, int $expectedVersion, array $data): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $data) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the costing of');

            $t->costingLines()->delete(); // sub-items go with them (cascade)
            foreach ($data['lines'] as $i => $line) {
                $saved = $t->costingLines()->create(['position' => $i + 1] + array_diff_key($line, ['sub_items' => true]));
                foreach ($line['sub_items'] as $j => $sub) {
                    $saved->subItems()->create(['position' => $j + 1] + $sub);
                }
            }
            $t->forceFill([
                'default_margin_bp' => $data['default_margin_bp'],
                'bid_price_override_sen' => $data['bid_price_override_sen'],
                'version' => $t->version + 1,
            ])->save();

            $summary = $t->fresh()->costingSummary();
            ActivityLog::record($t, $actor, 'costing_saved', $summary
                ? 'Costing saved — bid price '.Money::format($summary['bid_price_sen']).', margin '.Percent::format($summary['margin_bp'])
                : 'Costing cleared');

            return $t->fresh();
        });
    }
}
