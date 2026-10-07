<?php

use App\Actions\Quotations\{AddQuotationItem, CreateQuotation, MoveQuotationItem, RemoveQuotationItem, UpdateQuotation, UpdateQuotationItem};
use App\Enums\QuotationStatus;
use App\Exceptions\{QuotationLocked, StaleQuotation};
use App\Models\{Quotation, QuotationItem, User};
use App\Support\MalaysiaTime;
use Illuminate\Auth\Access\AuthorizationException;

it('creates a draft filled with today, the defaults and a copy of the letterhead', function () {
    $siti = User::factory()->create(['name' => 'Siti Aisyah', 'email' => 'siti@cmt.test']);

    $q = app(CreateQuotation::class)->handle($siti);

    expect($q->number)->toBe('QTN-'.MalaysiaTime::today()->year.'-0001')
        ->and($q->status)->toBe(QuotationStatus::Draft)
        ->and($q->quote_date->format('Y-m-d'))->toBe(MalaysiaTime::today()->format('Y-m-d'))
        ->and($q->validity_days)->toBe(30)
        ->and($q->prepared_by)->toBe($siti->id)
        ->and($q->preparer_email)->toBe('siti@cmt.test')
        ->and($q->sst_bp)->toBe(800)
        ->and(substr_count($q->terms, "\n"))->toBe(5)
        ->and($q->letterhead['name'])->toBe('CMT Sdn. Bhd.')
        ->and($q->activity->first()->description)->toBe("Quotation {$q->number} created");
});

it('saves details while draft and bumps the version', function () {
    $q = Quotation::factory()->create();

    $q = app(UpdateQuotation::class)->handle($q->preparer, $q, 1, ['customer_name' => 'JPNIN', 'sst_bp' => 600, 'show_stamp' => false, 'number' => 'HACK']);

    expect($q->customer_name)->toBe('JPNIN')->and($q->sst_bp)->toBe(600)->and($q->show_stamp)->toBeFalse()
        ->and($q->number)->not->toBe('HACK')->and($q->version)->toBe(2);
});

it('refuses a preparer who is switched off or unknown', function () {
    $q = Quotation::factory()->create();
    $off = User::factory()->create(['is_active' => false]);

    app(UpdateQuotation::class)->handle($q->preparer, $q, 1, ['prepared_by' => $off->id]);
})->throws(InvalidArgumentException::class, 'Choose an active person as the preparer.');

it('adds, edits, moves and removes items', function () {
    $q = Quotation::factory()->create();
    $u = $q->preparer;

    $q = app(AddQuotationItem::class)->handle($u, $q, 1);
    $q = app(AddQuotationItem::class)->handle($u, $q, 2);
    [$a, $b] = $q->items->all();
    expect($a->title)->toBe('New item')->and($b->position)->toBe(2);

    $q = app(UpdateQuotationItem::class)->handle($u, $b, 3, ['title' => 'Switch', 'details' => 'Ports: 24', 'quantity' => 6, 'unit' => 'Unit', 'unit_price_sen' => 485000]);
    $q = app(MoveQuotationItem::class)->handle($u, $b->fresh(), 4, -1);
    expect($q->items->pluck('title')->all())->toBe(['Switch', 'New item'])
        ->and($q->items->first()->details)->toBe('Ports: 24')
        ->and($q->totals()['subtotal_sen'])->toBe(2910000);

    $q = app(MoveQuotationItem::class)->handle($u, $b->fresh(), $q->version, -1); // already first: nothing changes
    expect($q->version)->toBe(5);
    $q = app(RemoveQuotationItem::class)->handle($u, $a->fresh(), $q->version);
    expect($q->items->pluck('title')->all())->toBe(['Switch']);
});

it('rejects bad item data', function () {
    $item = QuotationItem::factory()->create();

    app(UpdateQuotationItem::class)->handle($item->quotation->preparer, $item, 1, ['title' => ' ', 'details' => null, 'quantity' => 0, 'unit' => 'Unit', 'unit_price_sen' => 1]);
})->throws(InvalidArgumentException::class, 'An item needs a title, a quantity of at least 1 and a price of RM 0.00 or more.');

it('refuses any change once sent, even through a crafted request', function () {
    $q = Quotation::factory()->create(['status' => QuotationStatus::Sent]);
    $item = QuotationItem::factory()->for($q)->create();
    $u = $q->preparer;

    expect(fn () => app(UpdateQuotation::class)->handle($u, $q, 1, ['subject' => 'x']))->toThrow(QuotationLocked::class, 'This quotation has been sent. Revise it to make changes.')
        ->and(fn () => app(AddQuotationItem::class)->handle($u, $q, 1))->toThrow(QuotationLocked::class)
        ->and(fn () => app(UpdateQuotationItem::class)->handle($u, $item, 1, ['title' => 'x', 'details' => null, 'quantity' => 1, 'unit' => 'U', 'unit_price_sen' => 1]))->toThrow(QuotationLocked::class)
        ->and(fn () => app(MoveQuotationItem::class)->handle($u, $item, 1, 1))->toThrow(QuotationLocked::class)
        ->and(fn () => app(RemoveQuotationItem::class)->handle($u, $item, 1))->toThrow(QuotationLocked::class);
});

it('refuses other staff and out-of-date pages', function () {
    $q = Quotation::factory()->create();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);

    expect(fn () => app(UpdateQuotation::class)->handle(User::factory()->create(), $q, 1, ['subject' => 'x']))->toThrow(AuthorizationException::class);

    app(UpdateQuotation::class)->handle($manager, $q, 1, ['subject' => 'Manager edit']);
    expect(fn () => app(UpdateQuotation::class)->handle($q->preparer, $q, 1, ['subject' => 'Mine']))
        ->toThrow(StaleQuotation::class, 'This quotation was changed by Ahmad Faizal — reload to see their changes.');
});
