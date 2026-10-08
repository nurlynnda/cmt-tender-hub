<?php

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

it('seeds the prototype quotations', function () {
    $this->seed(DatabaseSeeder::class);

    $q12 = Quotation::where('number', 'QTN-2026-0012')->first();
    $q10 = Quotation::where('number', 'QTN-2026-0010')->first();

    expect($q12->status)->toBe(QuotationStatus::Sent)
        ->and($q12->totals()['total_sen'])->toBe(3844800)
        ->and($q12->items)->toHaveCount(2)
        ->and($q12->preparer->name)->toBe('Siti Aisyah')
        ->and($q12->letterhead['name'])->toBe('CMT Sdn. Bhd.')
        ->and(Quotation::where('number', 'QTN-2026-0011')->first()->status)->toBe(QuotationStatus::Accepted)
        ->and(Quotation::where('number', 'QTN-2026-0011')->first()->totals()['total_sen'])->toBe(2052000)
        ->and($q10->totals()['total_sen'])->toBe(3150000)
        ->and($q10->isExpired(CarbonImmutable::parse('2026-10-07')))->toBeTrue()
        ->and(DB::table('quotation_sequences')->where('year', 2026)->value('last_seq'))->toBe(12);
});
