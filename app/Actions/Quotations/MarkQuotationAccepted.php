<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, Quotation, User};
use Illuminate\Support\Facades\DB;

final class MarkQuotationAccepted
{
    use GuardsQuotation;

    /** Also allowed after it expired: a customer can still say yes late. */
    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Sent], 'marked Accepted');
            $q->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now(), 'accepted_by' => $actor->id]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_accepted', 'Marked Accepted');

            return $q->fresh();
        });
    }
}
