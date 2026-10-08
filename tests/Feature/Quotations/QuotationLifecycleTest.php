<?php

use App\Actions\Quotations\{CreateProjectFromQuotation, DuplicateQuotation, MarkQuotationAccepted, MarkQuotationRejected, MarkQuotationSent, MoveQuotationBackToDraft, ReviseQuotation};
use App\Enums\{PdGroup, QuotationStatus};
use App\Exceptions\{InvalidQuotationTransition, QuotationIncomplete};
use App\Models\{CompanyProfile, Quotation, QuotationItem, User};
use Illuminate\Auth\Access\AuthorizationException;

function readyQuotation(array $attrs = []): Quotation
{
    $q = Quotation::factory()->create(array_merge(['customer_name' => 'JPNIN', 'subject' => 'Switches'], $attrs));
    QuotationItem::factory()->for($q)->create(['quantity' => 6, 'unit_price_sen' => 485000, 'title' => 'Switch']);
    QuotationItem::factory()->for($q)->create(['position' => 2, 'quantity' => 1, 'unit_price_sen' => 650000, 'title' => 'Install']);

    return $q->fresh();
}

it('marks a complete draft Sent and logs the total', function () {
    $q = readyQuotation();

    $sent = app(MarkQuotationSent::class)->handle($q->preparer, $q, 1);

    expect($sent->status)->toBe(QuotationStatus::Sent)->and($sent->sent_by)->toBe($q->prepared_by)
        ->and($sent->activity->first()->description)->toBe('Marked Sent — total RM 38,448.00');
});

it('lists what is missing before it can be sent', function () {
    $q = Quotation::factory()->create(['customer_name' => null, 'subject' => '']);

    expect(fn () => app(MarkQuotationSent::class)->handle($q->preparer, $q, 1))
        ->toThrow(QuotationIncomplete::class, 'Add a customer name, a subject and at least one item before marking this quotation Sent.');

    $zero = Quotation::factory()->create();
    QuotationItem::factory()->for($zero)->create(['unit_price_sen' => 0]);
    expect(fn () => app(MarkQuotationSent::class)->handle($zero->preparer, $zero, 1))
        ->toThrow(QuotationIncomplete::class, 'Add a total above RM 0.00 before marking this quotation Sent.');
});

it('accepts or rejects a sent quotation, even after it expired', function () {
    $q = readyQuotation(['status' => QuotationStatus::Sent, 'quote_date' => '2020-01-01']);
    $accepted = app(MarkQuotationAccepted::class)->handle($q->preparer, $q, 1);
    expect($accepted->status)->toBe(QuotationStatus::Accepted)->and($accepted->activity->first()->description)->toBe('Marked Accepted');

    $r = readyQuotation(['status' => QuotationStatus::Sent]);
    $rejected = app(MarkQuotationRejected::class)->handle($r->preparer, $r, 1);
    expect($rejected->status)->toBe(QuotationStatus::Rejected)->and($rejected->rejected_by)->toBe($r->prepared_by);

    $draft = readyQuotation();
    expect(fn () => app(MarkQuotationAccepted::class)->handle($draft->preparer, $draft, 1))
        ->toThrow(InvalidQuotationTransition::class, 'A Draft quotation cannot be marked Accepted.');
});

it('revises a sent quotation into -R1, then -R2, keeping everything', function () {
    $q = readyQuotation(['status' => QuotationStatus::Sent, 'number' => 'QTN-2026-0012', 'preparer_position' => 'Sales Executive',
        'letterhead' => ['name' => 'Old Letterhead Sdn. Bhd.', 'stamp_path' => null]]);

    $q->items[0]->update(['frequency' => 3, 'has_sst' => false, 'unit_price_override_sen' => 485000]);

    $r1 = app(ReviseQuotation::class)->handle($q->preparer, $q->fresh(), 1);
    expect($r1->items[0]->only(['frequency', 'has_sst', 'unit_price_override_sen']))->toBe(['frequency' => 3, 'has_sst' => false, 'unit_price_override_sen' => 485000])
        ->and($r1->number)->toBe('QTN-2026-0012-R1')->and($r1->status)->toBe(QuotationStatus::Draft)
        ->and($r1->revision_of_id)->toBe($q->id)->and($r1->preparer_position)->toBe('Sales Executive')
        ->and($r1->letterhead['name'])->toBe('Old Letterhead Sdn. Bhd.')
        ->and($r1->sent_at)->toBeNull()
        ->and($r1->items->pluck('title')->all())->toBe(['Switch', 'Install'])
        ->and($q->fresh()->status)->toBe(QuotationStatus::Revised)
        ->and($q->fresh()->activity->first()->description)->toBe('Revised as QTN-2026-0012-R1')
        ->and($r1->activity->first()->description)->toBe('Revision of QTN-2026-0012 created');

    $r1->update(['status' => QuotationStatus::Sent]);
    expect(app(ReviseQuotation::class)->handle($q->preparer, $r1->fresh(), 1)->number)->toBe('QTN-2026-0012-R2');
});

it('duplicates any quotation as a new draft for the person duplicating, with the current letterhead', function () {
    $q = readyQuotation(['status' => QuotationStatus::Rejected, 'preparer_position' => 'Sales Executive',
        'letterhead' => ['name' => 'Old Letterhead Sdn. Bhd.', 'stamp_path' => null]]);
    $hafiz = User::factory()->create(['email' => 'hafiz@cmt.test']);

    $sub = [['description' => 'Rack', 'unit' => 'unit', 'quantity' => 1, 'unit_cost_sen' => 5, 'vendor' => null, 'quote_url' => null]];
    $q->items[0]->update(['frequency' => 3, 'has_sst' => false, 'unit_cost_sen' => 7, 'margin_bp' => 1500, 'vendor' => 'V', 'sub_items' => $sub]);
    $q->update(['default_margin_bp' => 1700]);

    $copy = app(DuplicateQuotation::class)->handle($hafiz, $q->fresh());
    expect($copy->default_margin_bp)->toBe(1700)
        ->and($copy->items[0]->only(['frequency', 'has_sst', 'unit_cost_sen', 'margin_bp', 'vendor', 'sub_items', 'unit_price_sen']))
        ->toEqual(['frequency' => 3, 'has_sst' => false, 'unit_cost_sen' => 7, 'margin_bp' => 1500, 'vendor' => 'V', 'sub_items' => $sub, 'unit_price_sen' => 485000]); // MySQL JSON reorders keys

    expect($copy->number)->not->toBe($q->number)->and($copy->status)->toBe(QuotationStatus::Draft)
        ->and($copy->prepared_by)->toBe($hafiz->id)->and($copy->preparer_position)->toBeNull()
        ->and($copy->preparer_email)->toBe('hafiz@cmt.test')
        ->and($copy->letterhead['name'])->toBe(CompanyProfile::current()->name)
        ->and($copy->customer_name)->toBe('JPNIN')->and($copy->items)->toHaveCount(2)
        ->and($copy->revision_of_id)->toBeNull()
        ->and($copy->activity->first()->description)->toBe("Duplicated from {$q->number}");

    $own = app(DuplicateQuotation::class)->handle($q->preparer, $q);
    expect($own->preparer_position)->toBe('Sales Executive');
});

it('lets managers move a quotation back to draft, but not once it has a project', function () {
    $q = readyQuotation(['status' => QuotationStatus::Accepted]);
    $manager = User::factory()->manager()->create();

    expect(fn () => app(MoveQuotationBackToDraft::class)->handle($q->preparer, $q, 1))->toThrow(AuthorizationException::class);

    app(CreateProjectFromQuotation::class)->handle($q->preparer, $q, 1);
    expect(fn () => app(MoveQuotationBackToDraft::class)->handle($manager, $q, 1))
        ->toThrow(InvalidQuotationTransition::class, 'This quotation has a project, so it cannot go back to Draft.');

    $s = readyQuotation(['status' => QuotationStatus::Sent, 'sent_at' => now()]);
    $back = app(MoveQuotationBackToDraft::class)->handle($manager, $s, 1);
    expect($back->status)->toBe(QuotationStatus::Draft)->and($back->sent_at)->toBeNull()
        ->and($back->activity->first()->description)->toBe('Moved back to Draft (was Sent)');
});

it('creates one project from an accepted quotation with the subtotal as contract value', function () {
    $q = readyQuotation(['status' => QuotationStatus::Accepted]);
    $q->items[0]->update(['unit_cost_sen' => 300000, 'vendor' => 'Cisco', 'frequency' => 2]);   // Switch × 6; Install has no cost

    $project = app(CreateProjectFromQuotation::class)->handle($q->preparer, $q->fresh(), 1);

    expect($project->quotation_id)->toBe($q->id)->and($project->tender_id)->toBeNull()
        ->and($project->project_charge_bp)->toBe(900)
        ->and($project->lines->map(fn ($l) => [$l->pd_group, $l->name, $l->reference, $l->budget_sen])->all())->toBe([
            [PdGroup::Collection, 'Contract value', null, $q->fresh()->totals()['subtotal_sen']],
            [PdGroup::Principal, 'Switch', 'Cisco', 300000 * 6 * 2],   // items with no cost are skipped
        ])
        ->and($q->activity()->first()->description)->toBe('Project created from the quotation')
        ->and(fn () => app(CreateProjectFromQuotation::class)->handle($q->preparer, $q, 1))->toThrow(DomainException::class, 'This quotation already has a project.');

    $sent = readyQuotation(['status' => QuotationStatus::Sent]);
    expect(fn () => app(CreateProjectFromQuotation::class)->handle($sent->preparer, $sent, 1))->toThrow(InvalidQuotationTransition::class);
});
