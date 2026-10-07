<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, CompanyProfile, Quotation, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Facades\{DB, Gate};

/** A fresh draft for the person duplicating, with the current letterhead. Anyone who can view may duplicate. */
final class DuplicateQuotation
{
    public function __construct(private GenerateQuotationNumber $numbers) {}

    public function handle(User $actor, Quotation $source): Quotation
    {
        Gate::forUser($actor)->authorize('view', $source);

        return DB::transaction(function () use ($actor, $source) {
            $today = MalaysiaTime::today();
            $samePerson = $source->prepared_by === $actor->id;
            $copy = Quotation::create([
                ...$source->only(['validity_days', 'customer_name', 'attention', 'attention_phone', 'attention_email',
                    'customer_address', 'subject', 'show_signature', 'show_stamp', 'sst_bp', 'terms']),
                'number' => $this->numbers->next($today->year),
                'status' => QuotationStatus::Draft,
                'quote_date' => $today->format('Y-m-d'),
                'prepared_by' => $actor->id,
                'preparer_position' => $samePerson ? $source->preparer_position : null,
                'preparer_phone' => $samePerson ? $source->preparer_phone : null,
                'preparer_email' => $samePerson ? $source->preparer_email : $actor->email,
                'letterhead' => CompanyProfile::current()->letterhead(),
                'updated_by' => $actor->id,
                'version' => 1,
            ]);
            foreach ($source->items as $item) {
                $copy->items()->create($item->only(['position', 'title', 'details', 'quantity', 'unit', 'unit_price_sen']));
            }
            ActivityLog::record($copy, $actor, 'quotation_created', "Duplicated from {$source->number}");

            return $copy->fresh();
        });
    }
}
