<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Exceptions\QuotationIncomplete;
use App\Models\{ActivityLog, Quotation, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class MarkQuotationSent
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Draft], 'marked Sent');

            $missing = [];
            if (trim((string) $q->customer_name) === '') {
                $missing[] = 'a customer name';
            }
            if (trim((string) $q->subject) === '') {
                $missing[] = 'a subject';
            }
            if ($q->items->isEmpty()) {
                $missing[] = 'at least one item';
            } elseif ($q->totals()['total_sen'] <= 0) {
                $missing[] = 'a total above RM 0.00';
            }
            if ($missing !== []) {
                throw new QuotationIncomplete($missing);
            }

            $q->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now(), 'sent_by' => $actor->id]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_sent', 'Marked Sent — total '.Money::format($q->totals()['total_sen']));

            return $q->fresh();
        });
    }
}
