<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;

final class RemoveQuotationItem
{
    use GuardsQuotation;

    public function handle(User $actor, QuotationItem $item, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $item, $expectedVersion) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $item->delete();
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
