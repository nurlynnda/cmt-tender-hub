<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, CompanyProfile, Quotation, User};
use App\Support\MalaysiaTime;
use Illuminate\Support\Facades\DB;

final class CreateQuotation
{
    public function __construct(private GenerateQuotationNumber $numbers) {}

    public function handle(User $actor): Quotation
    {
        return DB::transaction(function () use ($actor) {
            $company = CompanyProfile::current();
            $today = MalaysiaTime::today();
            $q = Quotation::create([
                'number' => $this->numbers->next($today->year),
                'status' => QuotationStatus::Draft,
                'quote_date' => $today->format('Y-m-d'),
                'validity_days' => 30,
                'prepared_by' => $actor->id,
                'preparer_email' => $actor->email,
                'show_signature' => true,
                'show_stamp' => true,
                'sst_bp' => $company->default_sst_bp,
                'terms' => $company->default_terms,
                'letterhead' => $company->letterhead(),
                'updated_by' => $actor->id,
                'version' => 1,
            ]);
            ActivityLog::record($q, $actor, 'quotation_created', "Quotation {$q->number} created");

            return $q->fresh();
        });
    }
}
