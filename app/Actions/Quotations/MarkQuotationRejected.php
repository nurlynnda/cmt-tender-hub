<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, Quotation, User};
use Illuminate\Support\Facades\DB;

final class MarkQuotationRejected
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Sent], 'marked Rejected');
            $q->forceFill(['status' => QuotationStatus::Rejected, 'rejected_at' => now(), 'rejected_by' => $actor->id]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_rejected', 'Marked Rejected');

            return $q->fresh();
        });
    }
}
