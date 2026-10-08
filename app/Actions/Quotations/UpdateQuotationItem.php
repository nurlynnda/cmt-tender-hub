<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Costing\CostingCalculator;
use App\Models\{Quotation, QuotationItem, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateQuotationItem
{
    use GuardsQuotation;

    /**
     * Saves an item's customer fields and its costing; the selling unit price is worked out here (or taken as typed)
     * and stored, so the totals and the PDF read one saved price.
     *
     * @param array $data title, details, has_sst, plus CostingForm::lineToData() keys (description ignored)
     */
    public function handle(User $actor, QuotationItem $item, int $expectedVersion, array $data): Quotation
    {
        $title = trim($data['title']);
        if ($title === '' || $data['quantity'] < 1 || $data['frequency'] < 1) {
            throw new InvalidArgumentException('An item needs a title, a quantity of at least 1 and a frequency of at least 1.');
        }
        $price = CostingCalculator::line($data)['price_per_unit_sen'];

        return DB::transaction(function () use ($actor, $item, $expectedVersion, $data, $title, $price) {
            $q = $this->lockQuotation($actor, $item->quotation, $expectedVersion);
            $this->requireDraft($q);
            $item->update([
                'title' => mb_substr($title, 0, 255),
                'details' => trim((string) $data['details']) ?: null,
                'quantity' => $data['quantity'],
                'unit' => trim($data['unit']) ?: 'Unit',
                'frequency' => $data['frequency'],
                'unit_cost_sen' => $data['unit_cost_sen'],
                'margin_bp' => $data['margin_bp'],
                'unit_price_override_sen' => $data['unit_price_override_sen'],
                'unit_price_sen' => $price,
                'vendor' => $data['vendor'],
                'quote_url' => $data['quote_url'],
                'has_sst' => (bool) $data['has_sst'],
                'sub_items' => $data['sub_items'] ?: null,
            ]);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
