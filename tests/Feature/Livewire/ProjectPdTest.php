<?php

use App\Enums\{PdGroup, TenderStatus};
use App\Livewire\{TenderDetail, ProjectPd};
use App\Models\{PdEntry, PdLine, Project, ProjectType, Tender, User};
use Livewire\Livewire;

function pdTab(): array
{
    $pic = User::factory()->create(['name' => 'Siti Aisyah']);
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create(['pic_id' => $pic->id]);
    $project = Project::factory()->for($tender)->create(['approved_margin_bp' => 1500]);
    PdLine::factory()->for($project)->create(['position' => 1, 'pd_group' => PdGroup::Collection, 'name' => 'Contract value', 'budget_sen' => 125000000]);
    PdLine::factory()->for($project)->create(['position' => 2, 'pd_group' => PdGroup::Principal, 'name' => 'Dell laptops', 'budget_sen' => 65100000]);

    return [$pic, $tender->fresh(), $project->fresh()];
}

it('shows the PD tab only for awarded tenders with a project', function () {
    [$pic, $tender] = pdTab();
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $tender])
        ->assertSeeHtml("\$set('tab', 'pd')")->set('tab', 'pd')->assertSeeLivewire(ProjectPd::class);

    $other = Tender::factory()->create(['pic_id' => $pic->id]);
    Livewire::actingAs($pic)->test(TenderDetail::class, ['tender' => $other])
        ->assertDontSeeHtml("\$set('tab', 'pd')")->set('tab', 'pd')->assertDontSeeLivewire(ProjectPd::class);
});

it('shows the P&L figures and lines', function () {
    [$pic, $tender, $project] = pdTab();
    $collectionId = $project->lines[0]->id;

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->assertSee('Profit & Loss')
        ->assertSee('RM 1,250,000.00')   // revenue
        ->assertSee('RM 112,500.00')     // 9% project charges
        ->assertSee('RM 486,500.00')     // GP = 1,250,000 − 651,000 − 112,500
        ->assertSee('Approved margin')
        ->assertSet("rows.l{$collectionId}.name", 'Contract value');
});

it('edits a line in place and saves it straight away', function () {
    [$pic, $tender, $project] = pdTab();
    $id = $project->lines[1]->id;

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->call('selectGroup', 'principal')
        ->set("rows.l{$id}.budget", '700,000')
        ->assertHasNoErrors()
        ->assertSet("versions.l{$id}", 2);

    expect(PdLine::find($id)->budget_sen)->toBe(70000000);
});

it('shows field errors and saves nothing for bad input', function () {
    [$pic, $tender, $project] = pdTab();
    $id = $project->lines[1]->id;

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->set("rows.l{$id}.budget", 'abc')->assertHasErrors("rows.l{$id}.budget")
        ->set("rows.l{$id}.name", '')->assertHasErrors("rows.l{$id}.name");

    expect(PdLine::find($id)->version)->toBe(1);
});

it('adds lines to the selected group and removes empty ones', function () {
    [$pic, $tender] = pdTab();

    $c = Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->call('selectGroup', 'tax')->call('addLine');
    $line = PdLine::where('pd_group', 'tax')->first();
    expect($line)->not->toBeNull();

    $c->call('removeLine', $line->id);
    expect(PdLine::find($line->id))->toBeNull();
});

it('records, edits and removes documents on a line', function () {
    [$pic, $tender, $project] = pdTab();
    $id = $project->lines[1]->id;

    $c = Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->call('selectGroup', 'principal')->call('openDocuments', $id)
        ->set('entry.type', 'invoice')->set('entry.number', 'INV-9')->set('entry.date', '2026-02-01')->set('entry.amount', '50,000')
        ->call('saveEntry')->assertHasNoErrors()
        ->assertSee('INV-9');
    $entry = PdEntry::first();
    expect($entry->amount_sen)->toBe(5000000);

    $c->call('editEntry', $entry->id)->assertSet('entry.amount', '50000.00')
        ->set('entry.amount', '40000')->call('saveEntry');
    expect($entry->fresh()->amount_sen)->toBe(4000000);

    $c->call('editEntry', $entry->id)->call('cancelEntry')->assertSet('editingEntry', null)
        ->call('removeEntry', $entry->id);
    expect(PdEntry::count())->toBe(0);
});

it('rejects a document type the line does not take', function () {
    [$pic, $tender, $project] = pdTab();
    $collectionId = $project->lines[0]->id;

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->call('openDocuments', $collectionId)
        ->set('entry.type', 'po')->set('entry.date', '2026-02-01')->set('entry.amount', '10')
        ->call('saveEntry')
        ->assertHasErrors('entry.type');
    expect(PdEntry::count())->toBe(0);
});

it('explains why a line with documents cannot be removed', function () {
    [$pic, $tender, $project] = pdTab();
    $line = $project->lines[1];
    PdEntry::factory()->for($line, 'line')->create();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->call('removeLine', $line->id)->assertSee("Remove this line's documents first.");
});

it('keeps the typed value and explains when someone else changed the line', function () {
    [$pic, $tender, $project] = pdTab();
    $line = $project->lines[1];
    $c = Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project]);
    $line->update(['version' => 5, 'updated_by' => User::factory()->manager()->create(['name' => 'Ahmad Faizal'])->id]);

    $c->set("rows.l{$line->id}.name", 'Mine')
        ->assertSee('This line was changed by Ahmad Faizal')
        ->assertSet("rows.l{$line->id}.name", 'Mine');
});

it('picks a project type and dates, and lets managers change rates and close', function () {
    [$pic, $tender, $project] = pdTab();
    $type = ProjectType::where('name', 'Networking')->first();
    $manager = User::factory()->manager()->create();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->set('header.project_type_id', (string) $type->id)
        ->assertSet('rates.approved', '20')
        ->set('header.start_date', '2026-01-01')->set('header.end_date', '2025-12-01')
        ->assertHasErrors('header.end_date')
        ->assertDontSee('Close project');

    Livewire::actingAs($manager)->test(ProjectPd::class, ['project' => $tender->project])
        ->set('rates.charge', '10')->assertHasNoErrors()
        ->call('closeProject')->assertSee('Reopen project')->assertSee('This project is closed')
        ->call('reopenProject')->assertSee('Close project');

    expect($project->fresh()->project_charge_bp)->toBe(1000);
});

it('refuses rate changes from staff even if the page is crafted', function () {
    [$pic, $tender] = pdTab();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->set('rates.charge', '1')->assertForbidden();
});

it('is read-only for other staff and when closed', function () {
    [$pic, $tender, $project] = pdTab();

    Livewire::actingAs(User::factory()->create())->test(ProjectPd::class, ['project' => $tender->project])
        ->assertDontSee('+ Add line');

    $project->update(['closed_at' => now()]);
    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->fresh()->project])
        ->assertDontSee('+ Add line')->assertSee('This project is closed');
});

it('accepts an end date before any start date is set', function () {
    [$pic, $tender, $project] = pdTab();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->set('header.end_date', '2026-06-30')->assertHasNoErrors();

    expect($project->fresh()->end_date->format('Y-m-d'))->toBe('2026-06-30');
});

it('handles a line a colleague added after the tab was opened', function () {
    [$pic, $tender, $project] = pdTab();
    $c = Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project]);
    $new = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Principal, 'name' => 'Added by Ahmad']);

    $c->call('selectGroup', 'principal')
        ->assertSet("rows.l{$new->id}.name", 'Added by Ahmad')
        ->call('openDocuments', $new->id)
        ->set('entry.type', 'invoice')->set('entry.date', '2026-02-01')->set('entry.amount', '100')
        ->call('saveEntry')->assertHasNoErrors();

    expect($new->fresh()->entries)->toHaveCount(1);
});

it('explains when a colleague removed the line you are working on', function () {
    [$pic, $tender, $project] = pdTab();
    $line = $project->lines[1];
    $c = Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])->call('openDocuments', $line->id);
    $line->delete();

    $c->set('entry.type', 'invoice')->set('entry.date', '2026-02-01')->set('entry.amount', '100')
        ->call('saveEntry')->assertOk()->assertSee('This line was removed by someone else — reload to see the latest.');
    $c->set("rows.l{$line->id}.name", 'Mine')->assertOk()->assertSee('This line was removed by someone else');
    $c->call('removeLine', $line->id)->assertOk()->assertSee('This line was removed by someone else');
});

it('ignores lines and documents from another project', function () {
    [$pic, $tender] = pdTab();
    $otherLine = PdLine::factory()->create(['name' => 'Not yours']);
    $otherEntry = PdEntry::factory()->for($otherLine, 'line')->create();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->call('removeLine', $otherLine->id)->assertOk()->assertSee('This line was removed by someone else')
        ->call('editEntry', $otherEntry->id)->assertOk()->assertSet('editingEntry', null)
        ->call('removeEntry', $otherEntry->id)->assertOk();

    expect(PdLine::find($otherLine->id))->not->toBeNull()->and(PdEntry::find($otherEntry->id))->not->toBeNull();
});

it('shows the cash flow', function () {
    [$pic, $tender, $project] = pdTab();
    $project->lines[0]->update(['scheduled_date' => '2026-03-01']);

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->assertSee('Cash flow')->assertSee('Mar 2026');
});

it('lays the PD out in titled cards', function () {
    [$pic, $tender] = pdTab();

    Livewire::actingAs($pic)->test(ProjectPd::class, ['project' => $tender->project])
        ->assertSeeHtml('data-pd-card="pnl"')->assertSeeHtml('data-pd-card="lines"')->assertSeeHtml('data-pd-card="cashflow"');
});
