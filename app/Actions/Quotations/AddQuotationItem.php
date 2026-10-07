<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, User};
use Illuminate\Support\Facades\DB;

final class AddQuotationItem
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Quotation
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireDraft($q);
            $q->items()->create([
                'position' => (int) $q->items()->max('position') + 1,
                'title' => 'New item',
                'quantity' => 1,
                'unit' => 'Unit',
                'unit_price_sen' => 0,
            ]);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
