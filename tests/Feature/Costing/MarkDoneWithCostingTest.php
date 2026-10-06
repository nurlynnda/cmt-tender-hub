<?php

use App\Actions\Tenders\MarkTenderDone;
use App\Enums\TenderStatus;
use App\Exceptions\CostingRequired;
use App\Livewire\TenderDetail;
use App\Models\{CostingLine, Tender, TenderDocument, User};
use Livewire\Livewire;

function readyTender(bool $withCosting = true): array
{
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id]);
    TenderDocument::factory()->for($tender)->create(['is_done' => true]);
    if ($withCosting) {
        CostingLine::factory()->for($tender)->create(['unit_cost_sen' => 10000000, 'margin_bp' => 2000]); // bid RM 125,000
    }

    return [$pic, $tender->fresh()];
}

it('submits the costing bid price', function () {
    [$pic, $tender] = readyTender();

    $done = app(MarkTenderDone::class)->handle($pic, $tender, 1);

    expect($done->status)->toBe(TenderStatus::Done)
        ->and($done->submitted_price_sen)->toBe(12500000)
        ->and($done->activity->first()->description)->toBe('Marked Done — submitted price RM 125,000.00');
});

it('submits the override when one is set', function () {
    [$pic, $tender] = readyTender();
    $tender->update(['bid_price_override_sen' => 11000000]);

    expect(app(MarkTenderDone::class)->handle($pic, $tender, 1)->submitted_price_sen)->toBe(11000000);
});

it('refuses without a costing', function () {
    [$pic, $tender] = readyTender(withCosting: false);

    app(MarkTenderDone::class)->handle($pic, $tender, 1);
})->throws(CostingRequired::class, 'Add a costing before marking this tender Done.');

it('shows the bid price in the confirm dialog, with no price box', function () {
    [$pic, $tender] = readyTender();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->call('openModal', 'done')
        ->assertSet('modal', 'done')
        ->assertSee('RM 125,000.00')
        ->assertDontSeeHtml('wire:model="submittedPrice"')
        ->call('markDone');

    expect($tender->fresh()->submitted_price_sen)->toBe(12500000);
});

it('blocks Mark Done without a costing, or with unsaved costing edits', function () {
    [$pic, $none] = readyTender(withCosting: false);
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $none])
        ->call('openModal', 'done')->assertSet('modal', null)->assertSee('Add a costing before marking this tender Done');

    [$pic2, $tender] = readyTender();
    Livewire::actingAs($pic2)->test(TenderDetail::class, ['tender' => $tender])
        ->set('costingDirty', true)
        ->call('openModal', 'done')->assertSet('modal', null)->assertSee('Save your costing changes first');
});

it('explains when the costing was removed after the dialog opened', function () {
    [$pic, $tender] = readyTender();
    $c = Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])->call('openModal', 'done');
    $tender->costingLines()->delete();

    $c->call('markDone')->assertSet('modal', null)->assertSee('Add a costing before marking this tender Done');
    expect($tender->fresh()->status)->toBe(TenderStatus::InProgress);
});
