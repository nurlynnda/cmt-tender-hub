<?php

use App\Actions\Tenders\UpdateTender;
use App\Enums\TenderStatus;
use App\Livewire\TenderDetail;
use App\Models\{Tender, TenderDocument, User};
use Livewire\Livewire;

function detailFixture(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'title' => 'SISTEM RONDAAN'], $attrs));
    foreach (TenderDocument::STANDARD as $i => $name) {
        TenderDocument::factory()->for($tender)->create(['name' => $name, 'position' => $i + 1]);
    }

    return [$pic, $tender];
}

it('shows the tender with its tabs', function () {
    [$pic, $tender] = detailFixture();

    $this->actingAs($pic)->get(route('tenders.show', $tender))
        ->assertOk()
        ->assertSee($tender->wo_number)
        ->assertSee('SISTEM RONDAAN')
        ->assertSee('Overview')->assertSee('Documents')->assertSee('Activity');
});

it('lets the PIC edit and save details', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('startEdit')
        ->set('form.title', 'NEW TITLE')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertSet('version', 2);

    expect($tender->fresh()->title)->toBe('NEW TITLE');
});

it('hides edit controls from staff who are not the PIC and forbids crafted calls', function () {
    [, $tender] = detailFixture();
    $other = User::factory()->create();

    Livewire::actingAs($other)->test(TenderDetail::class, ['tender' => $tender])
        ->assertDontSee('Edit details')
        ->assertDontSee('Mark Done')
        ->call('startEdit')->assertForbidden();

    Livewire::actingAs($other)->test(TenderDetail::class, ['tender' => $tender])
        ->call('toggleDocument', $tender->documents->first()->id)->assertForbidden();

    expect($tender->documents()->where('is_done', true)->count())->toBe(0);
});

it('shows a conflict message instead of overwriting a newer save', function () {
    [$pic, $tender] = detailFixture();
    $component = Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])->call('startEdit');

    app(UpdateTender::class)->handle(User::factory()->manager()->create(['name' => 'Ahmad Faizal']), $tender, 1, ['title' => 'THEIRS']);

    $component->set('form.title', 'MINE')->call('save')
        ->assertSee('This tender was changed by Ahmad Faizal');
    expect($tender->fresh()->title)->toBe('THEIRS');
});

it('blocks Mark Done with the list of unticked documents', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'done')
        ->assertSet('modal', null)
        ->assertSee('Bid Bond / Bank Guarantee')
        ->assertSee('still not ticked');
});

it('marks Done after all documents are ticked', function () {
    [$pic, $tender] = detailFixture();
    $tender->documents()->update(['is_done' => true]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'done')
        ->assertSet('modal', 'done')
        ->set('submittedPrice', '0')
        ->call('markDone')->assertHasErrors('submittedPrice')
        ->set('submittedPrice', '166,059.00')
        ->call('markDone')
        ->assertHasNoErrors();

    expect($tender->fresh()->status)->toBe(TenderStatus::Done)
        ->and($tender->fresh()->submitted_price_sen)->toBe(16605900);
});

it('ticks, adds and removes documents', function () {
    [$pic, $tender] = detailFixture();
    $first = $tender->documents->first();

    $c = Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->set('tab', 'documents')
        ->call('toggleDocument', $first->id)
        ->assertSee('1 / 5 done')
        ->set('newDocument', 'Surat Akuan')
        ->call('addDocument')
        ->assertSee('Surat Akuan')
        ->assertSee('1 / 6 done');

    $c->call('removeDocument', $first->id)->assertSee('0 / 5 done');
});

it('cancels with a required reason', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'cancel')
        ->call('cancelTender')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Not in our scope')
        ->call('cancelTender');

    expect($tender->fresh()->status)->toBe(TenderStatus::Lost)->and($tender->fresh()->was_cancelled)->toBeTrue();
});

it('marks a Done tender Awarded or Lost', function () {
    [$pic, $won] = detailFixture(['status' => TenderStatus::Done]);
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $won])->call('markAwarded');
    expect($won->fresh()->status)->toBe(TenderStatus::Awarded);

    [$pic2, $lost] = detailFixture(['status' => TenderStatus::Done]);
    Livewire::actingAs($pic2)->test(TenderDetail::class, ['tender' => $lost])
        ->call('openModal', 'lost')
        ->set('winningPrice', 'abc')->call('markLost')->assertHasErrors('winningPrice')
        ->set('winningPrice', '866,179')->set('lostReason', 'Lower bidder')->call('markLost');
    expect($lost->fresh()->status)->toBe(TenderStatus::Lost)->and($lost->fresh()->winning_price_sen)->toBe(86617900);
});

it('lets a manager edit a tender whose PIC and owner have since been deactivated', function () {
    [$pic, $tender] = detailFixture();
    $owner = User::factory()->create(['name' => 'Left Company']);
    $tender->update(['owner_id' => $owner->id]);
    $pic->update(['is_active' => false]);
    $owner->update(['is_active' => false]);

    Livewire::actingAs(User::factory()->manager()->create())->test(TenderDetail::class, ['tender' => $tender->fresh()])
        ->call('startEdit')
        ->assertSee('Siti Aisyah (deactivated)')
        ->assertSee('Left Company (deactivated)')
        ->set('form.closingDate', '2026-12-31')
        ->call('save')
        ->assertHasNoErrors();

    expect($tender->fresh()->closing_date->toDateString())->toBe('2026-12-31')
        ->and($tender->fresh()->pic_id)->toBe($pic->id)
        ->and($tender->fresh()->owner_id)->toBe($owner->id);
});

it('still refuses switching a tender to a different deactivated person', function () {
    [$pic, $tender] = detailFixture();
    $leaver = User::factory()->inactive()->create();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('startEdit')
        ->set('form.picId', (string) $leaver->id)
        ->call('save')
        ->assertHasErrors('form.picId');
});

it('reloads the page after a status change so the sidebar counts update', function () {
    [$pic, $tender] = detailFixture(['status' => TenderStatus::Done]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('markAwarded')
        ->assertRedirect(route('tenders.show', $tender));
});

it('does not reload the page for edits that keep the status', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('toggleDocument', $tender->documents->first()->id)
        ->assertNoRedirect();
});

it('shows Reopen only to managers and admins', function () {
    [$pic, $tender] = detailFixture(['status' => TenderStatus::Awarded]);

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])->assertDontSee('Reopen')
        ->call('reopen')->assertForbidden();

    Livewire::actingAs(User::factory()->manager()->create())->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('Reopen')->call('reopen');
    expect($tender->fresh()->status)->toBe(TenderStatus::InProgress);
});

it('shows the activity log newest first', function () {
    [$pic, $tender] = detailFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('toggleDocument', $tender->documents->first()->id)
        ->set('tab', 'activity')
        ->assertSee('Ticked: Borang ISI (Tender Form)')
        ->assertSee('Siti Aisyah');
});
