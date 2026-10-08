<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, Quotation, QuotationItem, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Facades\DB;

final class ReviseQuotation
{
    use GuardsQuotation;

    /** Returns the new draft revision (QTN-…-R1, -R2 …); the original becomes Revised. */
    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Sent], 'revised');

            $base = preg_replace('/-R\d+$/', '', $q->number);
            $n = Quotation::where('number', 'like', $base.'-R%')->lockForUpdate()->count() + 1;

            $new = $q->replicate(['number', 'status', 'sent_at', 'sent_by', 'accepted_at', 'accepted_by', 'rejected_at', 'rejected_by']);
            $new->forceFill([
                'number' => "{$base}-R{$n}",
                'status' => QuotationStatus::Draft,
                'revision_of_id' => $q->id,
                'quote_date' => MalaysiaTime::today()->format('Y-m-d'),
                'updated_by' => $actor->id,
                'version' => 1,
            ])->save();
            foreach ($q->items as $item) {
                $new->items()->create($item->only(QuotationItem::COPIED));
            }

            $q->forceFill(['status' => QuotationStatus::Revised]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_revised', "Revised as {$new->number}");
            ActivityLog::record($new, $actor, 'quotation_created', "Revision of {$q->number} created");

            return $new->fresh();
        });
    }
}
