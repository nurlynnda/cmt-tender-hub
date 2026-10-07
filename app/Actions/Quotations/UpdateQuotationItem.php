<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateQuotationItem
{
    use GuardsQuotation;

    /** @param array{title:string, details:?string, quantity:int, unit:string, unit_price_sen:int} $data */
    public function handle(User $actor, QuotationItem $item, int $expectedVersion, array $data): Quotation
    {
        $title = trim($data['title']);
        if ($title === '' || $data['quantity'] < 1 || $data['unit_price_sen'] < 0) {
            throw new InvalidArgumentException('An item needs a title, a quantity of at least 1 and a price of RM 0.00 or more.');
        }

        return DB::transaction(function () use ($actor, $item, $expectedVersion, $data, $title) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $item->update([
                'title' => mb_substr($title, 0, 255),
                'details' => trim((string) $data['details']) ?: null,
                'quantity' => $data['quantity'],
                'unit' => trim($data['unit']) ?: 'Unit',
                'unit_price_sen' => $data['unit_price_sen'],
            ]);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
