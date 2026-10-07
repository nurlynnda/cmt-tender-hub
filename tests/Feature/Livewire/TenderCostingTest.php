<?php

use App\Actions\Tenders\UpdateTender;
use App\Livewire\{TenderCosting, TenderDetail};
use App\Models\{CostingLine, Tender, User};
use Livewire\Livewire;

function costingFixture(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'estimated_value_sen' => 15000000], $attrs));

    return [$pic, $tender];
}

function costingComponent(User $u, Tender $t)
{
    return Livewire::actingAs($u)->test(TenderCosting::class, ['tender' => $t, 'version' => $t->version, 'canEdit' => true]);
}

it('adds lines, shows live totals and marks itself unsaved', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->assertSet('unsaved', false)
        ->call('addLine')
        ->assertDispatched('costing-dirty', dirty: true)
        ->set('lines.0.description', 'Server')
        ->set('lines.0.unit_cost', '100,000')
        ->assertSet('unsaved', true)
        ->assertSee('RM 125,000.00')
        ->assertSee('20.0%')
        ->assertSee('Unsaved changes');
});

it('keeps live totals working while a field holds junk', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.unit_cost', 'abc')->set('lines.0.margin', 'x')
        ->assertOk()
        ->assertSee('RM 0.00');
});

it('saves, tells the page its new version, and logs it', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '100000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('unsaved', false)
        ->assertDispatched('costing-dirty', dirty: false)
        ->assertDispatched('costing-saved', version: 2);

    expect($tender->fresh()->costingSummary()['bid_price_sen'])->toBe(12500000);
});

it('shows validation errors beside the fields and saves nothing', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.quantity', '0')->set('lines.0.margin', '120')
        ->call('save')
        ->assertHasErrors(['lines.0.description', 'lines.0.quantity', 'lines.0.margin'])
        ->assertSee('Enter a margin from 0 to 99.99%.');

    expect($tender->fresh()->costingLines)->toHaveCount(0);
});

it('handles sub-items, reordering and removal', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Set')
        ->call('addLine')->set('lines.1.description', 'Second')
        ->call('addSubItem', 0)->set('lines.0.sub_items.0.description', 'PC')->set('lines.0.sub_items.0.unit_cost', '3000')
        ->assertSee('RM 3,000.00')
        ->call('moveLine', 1, -1)
        ->assertSet('lines.0.description', 'Second')
        ->call('moveLine', 0, -1) // already at the top: nothing happens
        ->assertSet('lines.0.description', 'Second')
        ->call('removeSubItem', 1, 0)
        ->assertCount('lines.1.sub_items', 0)
        ->call('removeLine', 0)
        ->assertCount('lines', 1)
        ->assertSet('lines.0.description', 'Set');
});

it('applies the default margin to every line', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->call('addLine')->set('lines.1.margin', '30')
        ->set('defaultMargin', '15')
        ->call('applyDefaultToAll')
        ->assertSet('lines.0.margin', '15')->assertSet('lines.1.margin', '15')
        ->set('defaultMargin', '150')
        ->call('applyDefaultToAll')
        ->assertHasErrors('defaultMargin')
        ->assertSet('lines.0.margin', '15');
});

it('overrides the bid price and resets back to the suggested one', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '100000')
        ->set('override', '110,000')
        ->assertSee('Your price')->assertSee('Below the 18% target')
        ->call('resetOverride')
        ->assertSet('override', '')->assertDontSee('Below the 18% target');
});

it('imports pasted rows and reports bad ones', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->set('importText', "Router\t2\tunit\t1,000\nBad\tx\tunit\t1")
        ->call('import')
        ->assertCount('lines', 1)
        ->assertSet('lines.0.description', 'Router')
        ->assertSet('lines.0.margin', '20')
        ->assertSet('unsaved', true)
        ->assertSee('Row 2: quantity must be a whole number of at least 1.');
});

it('loads the saved costing and is read-only when you cannot edit', function () {
    [$pic, $tender] = costingFixture();
    CostingLine::factory()->for($tender)->create(['description' => 'Saved line']);

    Livewire::actingAs($pic)->test(TenderCosting::class, ['tender' => $tender->fresh(), 'version' => 1, 'canEdit' => false])
        ->assertSet('lines.0.description', 'Saved line')   // inputs are filled in by the browser from this state
        ->assertSeeHtml('wire:model.live.blur="lines.0.description" disabled')
        ->assertDontSee('+ Add line')->assertDontSee('Save costing');
});

it('refuses a save from someone without permission even if the page offered it', function () {
    [, $tender] = costingFixture();

    Livewire::actingAs(User::factory()->create())->test(TenderCosting::class, ['tender' => $tender, 'version' => 1, 'canEdit' => true])
        ->call('save')->assertForbidden();
});

it('keeps your edits and explains when someone else saved first', function () {
    [$pic, $tender] = costingFixture();
    $c = costingComponent($pic, $tender)->call('addLine')->set('lines.0.description', 'Mine')->set('lines.0.unit_cost', '10');
    app(UpdateTender::class)->handle(User::factory()->manager()->create(['name' => 'Ahmad Faizal']), $tender, 1, ['title' => 'X']);

    $c->call('save')->assertSee('This tender was changed by Ahmad Faizal')->assertSet('lines.0.description', 'Mine');
    expect($tender->fresh()->costingLines)->toHaveCount(0);
});

it('shows the Costing tab on the tender page and tracks unsaved edits', function () {
    [$pic, $tender] = costingFixture();

    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSee('Costing')
        ->assertSeeLivewire(TenderCosting::class)
        ->dispatch('costing-dirty', dirty: true)->assertSet('costingDirty', true)
        ->dispatch('costing-saved', version: 5)->assertSet('version', 5)->assertSet('costingDirty', false);
});

it('summarises the costing in four boxes and turns the margin red below target', function () {
    [$pic, $tender] = costingFixture();

    $html = costingComponent($pic, $tender)
        ->call('addLine')->set('lines.0.description', 'Server')->set('lines.0.unit_cost', '100,000')->set('lines.0.margin', '10')
        ->html();   // 10% is below the 18% company target

    foreach (['cost', 'sell', 'margin', 'margin-pct'] as $box) {
        expect($html)->toContain('data-costing-box="'.$box.'"');
    }
    expect($html)->toMatch('/data-costing-box="margin-pct"[^>]*text-bad-ink/');
});

it('opens Bulk Import as a dialog and closes it again', function () {
    [$pic, $tender] = costingFixture();

    costingComponent($pic, $tender)
        ->call('openImport')->assertSeeHtml('role="dialog"')->assertSee('Bulk Import Items')
        ->call('closeImport')->assertDontSeeHtml('role="dialog"');
});
