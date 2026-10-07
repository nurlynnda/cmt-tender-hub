<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;

final class MoveQuotationItem
{
    use GuardsQuotation;

    /** $direction: -1 up, +1 down. Moving past either end does nothing. */
    public function handle(User $actor, QuotationItem $item, int $expectedVersion, int $direction): Quotation
    {
        return DB::transaction(function () use ($actor, $item, $expectedVersion, $direction) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $ids = $q->items()->pluck('id')->all();
            $from = array_search($item->id, $ids, true);
            $to = $from + ($direction < 0 ? -1 : 1);
            if ($from !== false && isset($ids[$to])) {
                [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
                foreach ($ids as $i => $id) {
                    $q->items()->whereKey($id)->update(['position' => $i + 1]);
                }
                $this->bump($q, $actor);
            }

            return $q->fresh();
        });
    }
}
