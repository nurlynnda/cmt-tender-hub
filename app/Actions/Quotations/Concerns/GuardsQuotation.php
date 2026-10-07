<?php

namespace App\Actions\Quotations\Concerns;

use App\Enums\QuotationStatus;
use App\Exceptions\{InvalidQuotationTransition, QuotationLocked, StaleQuotation};
use App\Models\{Quotation, User};
use Illuminate\Support\Facades\Gate;

trait GuardsQuotation
{
    /** Call inside DB::transaction. Locks the row, checks permission, then checks nobody saved in between. */
    private function lockQuotation(User $actor, Quotation $quotation, int $expectedVersion, string $ability = 'update'): Quotation
    {
        $q = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
        Gate::forUser($actor)->authorize($ability, $q);
        if ($q->version !== $expectedVersion) {
            throw StaleQuotation::for($q);
        }

        return $q;
    }

    private function requireDraft(Quotation $q): void
    {
        if ($q->status !== QuotationStatus::Draft) {
            throw new QuotationLocked;
        }
    }

    /** @param list<QuotationStatus> $statuses */
    private function requireStatus(Quotation $q, array $statuses, string $action): void
    {
        if (! in_array($q->status, $statuses, true)) {
            throw new InvalidQuotationTransition("A {$q->displayLabel()} quotation cannot be {$action}.");
        }
    }

    private function bump(Quotation $q, User $actor): void
    {
        $q->forceFill(['updated_by' => $actor->id, 'version' => $q->version + 1])->save();
    }
}
