<?php

use App\Actions\Quotations\GenerateQuotationNumber;
use App\Enums\QuotationStatus;
use App\Models\{ActivityLog, CompanyProfile, Project, Quotation, QuotationItem, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

it('ships the company letterhead, default terms and SST', function () {
    $c = CompanyProfile::current();

    expect($c->name)->toBe('CMT Sdn. Bhd.')
        ->and($c->default_sst_bp)->toBe(800)
        ->and(preg_split('/\R/', $c->default_terms))->toHaveCount(6)
        ->and($c->letterhead())->toMatchArray(['name' => 'CMT Sdn. Bhd.', 'website' => 'www.cmt.com.my', 'stamp_path' => null]);
});

it('numbers quotations per year without gaps', function () {
    $n = app(GenerateQuotationNumber::class);

    expect([$n->next(2026), $n->next(2026), $n->next(2027), $n->next(2026)])
        ->toBe(['QTN-2026-0001', 'QTN-2026-0002', 'QTN-2027-0001', 'QTN-2026-0003']);
});

it('stores a quotation with ordered items and works out validity', function () {
    $q = Quotation::factory()->create(['quote_date' => '2026-09-18', 'validity_days' => 30, 'status' => QuotationStatus::Sent]);
    QuotationItem::factory()->for($q)->create(['position' => 2, 'title' => 'B']);
    QuotationItem::factory()->for($q)->create(['position' => 1, 'title' => 'A']);

    $q = $q->fresh();
    expect($q->items->pluck('title')->all())->toBe(['A', 'B'])
        ->and($q->validUntil()->format('Y-m-d'))->toBe('2026-10-18')
        ->and($q->isExpired(CarbonImmutable::parse('2026-10-18')))->toBeFalse()
        ->and($q->isExpired(CarbonImmutable::parse('2026-10-19')))->toBeTrue()
        ->and($q->isDraft())->toBeFalse()
        ->and(Quotation::factory()->create(['status' => QuotationStatus::Draft, 'quote_date' => '2020-01-01'])->isExpired())->toBeFalse();
});

it('labels each status and shows Expired', function () {
    $expired = Quotation::factory()->make(['status' => QuotationStatus::Sent, 'quote_date' => '2020-01-01', 'validity_days' => 30]);
    $draft = Quotation::factory()->make(['status' => QuotationStatus::Draft]);

    expect($expired->displayStatus())->toBe('expired')->and($expired->displayLabel())->toBe('Expired')
        ->and($draft->displayStatus())->toBe('draft')->and($draft->displayLabel())->toBe('Draft')
        ->and(QuotationStatus::Revised->label())->toBe('Revised');
});

it('lets the preparer, managers and admins edit; only managers and admins move back to draft', function () {
    $preparer = User::factory()->create();
    $q = Quotation::factory()->create(['prepared_by' => $preparer->id]);
    $other = User::factory()->create();
    $manager = User::factory()->manager()->create();

    expect(Gate::forUser($preparer)->allows('update', $q))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $q))->toBeFalse()
        ->and(Gate::forUser($other)->allows('view', $q))->toBeTrue()
        ->and(Gate::forUser($other)->allows('viewAny', Quotation::class))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('update', $q))->toBeTrue()
        ->and(Gate::forUser($preparer)->allows('backToDraft', $q))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('backToDraft', $q))->toBeTrue();
});

it('records history and projects against a quotation', function () {
    $q = Quotation::factory()->create();
    ActivityLog::record($q, null, 'quotation_created', 'Quotation created');
    $p = Project::factory()->create(['tender_id' => null, 'quotation_id' => $q->id]);

    expect($q->activity->first()->description)->toBe('Quotation created')
        ->and($q->activity->first()->tender_id)->toBeNull()
        ->and($q->fresh()->project->id)->toBe($p->id)
        ->and($p->quotation->id)->toBe($q->id)
        ->and(QuotationItem::factory()->create()->quotation)->toBeInstanceOf(Quotation::class);
});
