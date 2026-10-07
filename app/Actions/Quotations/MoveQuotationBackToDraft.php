<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\QuotationStatus;
use App\Exceptions\InvalidQuotationTransition;
use App\Models\{ActivityLog, Quotation, User};
use Illuminate\Support\Facades\DB;

/** Managers/Admins fixing a mistake. Not once a project exists, so the project's numbers stay true. */
final class MoveQuotationBackToDraft
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion, 'backToDraft');
            $this->requireStatus($q, [QuotationStatus::Sent, QuotationStatus::Rejected, QuotationStatus::Accepted], 'moved back to Draft');
            if ($q->project()->exists()) {
                throw new InvalidQuotationTransition('This quotation has a project, so it cannot go back to Draft.');
            }
            $was = $q->displayLabel();
            $q->forceFill([
                'status' => QuotationStatus::Draft,
                'sent_at' => null, 'sent_by' => null,
                'accepted_at' => null, 'accepted_by' => null,
                'rejected_at' => null, 'rejected_by' => null,
            ]);
            $this->bump($q, $actor);
            ActivityLog::record($q, $actor, 'quotation_reopened', "Moved back to Draft (was {$was})");

            return $q->fresh();
        });
    }
}
