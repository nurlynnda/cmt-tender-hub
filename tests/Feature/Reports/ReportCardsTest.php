<?php

use App\Enums\{PdEntryType, PdGroup, QuotationStatus, TenderStatus};
use App\Models\{PdEntry, PdLine, Project, Quotation, QuotationItem, Tender};
use App\Reports\{ReportCards, ReportPeriod};
use Carbon\CarbonImmutable;

function cardQuote(QuotationStatus $status, string $date, int $priceSen): Quotation
{
    $q = Quotation::factory()->create(['status' => $status, 'quote_date' => $date, 'validity_days' => 30, 'sst_bp' => 0]);
    QuotationItem::factory()->for($q)->create(['quantity' => 1, 'unit_price_sen' => $priceSen]);

    return $q;
}

it('sums open and accepted quotations', function () {
    $today = CarbonImmutable::parse('2026-10-15');
    cardQuote(QuotationStatus::Sent, '2026-10-01', 1000);     // open
    cardQuote(QuotationStatus::Sent, '2026-08-01', 2000);     // expired → not open
    cardQuote(QuotationStatus::Accepted, '2026-10-02', 3000); // accepted in October
    cardQuote(QuotationStatus::Accepted, '2026-09-02', 4000); // accepted in September
    cardQuote(QuotationStatus::Draft, '2026-10-03', 5000);

    expect(app(ReportCards::class)->quotations(ReportPeriod::fromInput(['period' => 'this_month'], $today), $today))
        ->toBe(['open' => 1, 'open_total_sen' => 1000, 'accepted' => 1, 'accepted_total_sen' => 3000])
        ->and(app(ReportCards::class)->quotations(ReportPeriod::fromInput([], $today), $today)['accepted'])->toBe(2);
});

it('counts running projects and those below their approved margin', function () {
    $healthy = Project::factory()->create(['approved_margin_bp' => 1500]);
    $line = PdLine::factory()->for($healthy)->create(['pd_group' => PdGroup::Collection]);
    PdEntry::factory()->for($line, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 100000]);

    $poor = Project::factory()->create(['approved_margin_bp' => 1500]);
    $rev = PdLine::factory()->for($poor)->create(['pd_group' => PdGroup::Collection]);
    $cost = PdLine::factory()->for($poor)->create(['pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($rev, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 100000]);
    PdEntry::factory()->for($cost, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 90000]);

    Project::factory()->create(['closed_at' => now()]);                                                       // closed
    Project::factory()->for(Tender::factory()->status(TenderStatus::InProgress))->create();                    // tender reopened
    Project::factory()->create(['tender_id' => null, 'quotation_id' => Quotation::factory()->create(['status' => QuotationStatus::Accepted])->id]);

    expect(app(ReportCards::class)->projects())->toBe(['running' => 3, 'below_margin' => 1]);
});
