<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Models\{Quotation, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Saves Details / Terms fields of a Draft. Fields not listed here (number, status, letterhead…) are ignored. */
final class UpdateQuotation
{
    use GuardsQuotation;

    public const FIELDS = [
        'quote_date', 'validity_days', 'customer_name', 'attention', 'attention_phone', 'attention_email', 'customer_address',
        'subject', 'prepared_by', 'preparer_position', 'preparer_phone', 'preparer_email', 'show_signature', 'show_stamp',
        'sst_bp', 'terms',
    ];

    public function handle(User $actor, Quotation $quotation, int $expectedVersion, array $data): Quotation
    {
        $values = Arr::only($data, self::FIELDS);
        if (array_key_exists('prepared_by', $values) && ! User::whereKey($values['prepared_by'])->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Choose an active person as the preparer.');
        }

        return DB::transaction(function () use ($actor, $quotation, $expectedVersion, $values) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireDraft($q);
            $q->fill($values);
            $this->bump($q, $actor);

            return $q->fresh();
        });
    }
}
