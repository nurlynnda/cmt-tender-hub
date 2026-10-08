<?php

use App\Enums\{PdEntryType, PdGroup, TenderStatus};
use App\Models\{CostingLine, FinanceSetting, PdEntry, PdLine, Project, ProjectType, Tender};

it('ships the 22 project types and the company defaults', function () {
    expect(ProjectType::count())->toBe(22)
        ->and(ProjectType::where('name', 'Managed Services')->value('approved_margin_bp'))->toBe(1500)
        ->and(ProjectType::where('name', 'Leasing (ICT Peripherals)')->value('approved_margin_bp'))->toBe(400)
        ->and(FinanceSetting::current()->project_charge_bp)->toBe(900)
        ->and(FinanceSetting::current()->commission_share_bp)->toBe(5000);
});

it('links a project to its tender with ordered lines and entries', function () {
    $tender = Tender::factory()->status(TenderStatus::Awarded)->create();
    $project = Project::factory()->for($tender)->create();
    PdLine::factory()->for($project)->create(['position' => 2, 'name' => 'B']);
    $a = PdLine::factory()->for($project)->create(['position' => 1, 'name' => 'A', 'pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($a, 'line')->create(['type' => PdEntryType::Po, 'date' => '2026-02-01', 'amount_sen' => 500]);
    PdEntry::factory()->for($a, 'line')->create(['type' => PdEntryType::Pr, 'date' => '2026-01-01', 'amount_sen' => 400]);

    $fresh = $tender->fresh()->project;
    expect($fresh->id)->toBe($project->id)
        ->and($fresh->isOpen())->toBeTrue()
        ->and($fresh->lines->pluck('name')->all())->toBe(['A', 'B'])
        ->and($fresh->lines->first()->pd_group)->toBe(PdGroup::Principal)
        ->and($fresh->lines->first()->entries->pluck('type')->all())->toBe([PdEntryType::Pr, PdEntryType::Po]);
});

it('knows which entry types each group takes', function () {
    expect(PdGroup::Collection->entryTypes())->toBe([PdEntryType::Invoice, PdEntryType::Receipt])
        ->and(PdGroup::Principal->entryTypes())->toBe([PdEntryType::Pr, PdEntryType::Po, PdEntryType::Invoice, PdEntryType::Payment])
        ->and(PdGroup::costGroups())->not->toContain(PdGroup::Collection)
        ->and(PdGroup::Partner->isCostOfSales())->toBeTrue()
        ->and(PdGroup::Tax->isCostOfSales())->toBeFalse()
        ->and(PdGroup::Tax->label())->toBe('Tax (SST)')
        ->and(PdEntryType::Po->label())->toBe('PO');
});

it('describes every group', function () {
    foreach (PdGroup::cases() as $g) {
        expect($g->label())->not->toBe('')->and($g->description())->not->toBe('');
    }
});

it('summarises a saved project', function () {
    $project = Project::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $line = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection, 'budget_sen' => 100000, 'scheduled_date' => '2026-02-01']);
    PdEntry::factory()->for($line, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 40000, 'date' => '2026-02-03']);

    $s = $project->fresh()->summary('2026-07-02');

    expect($s['pnl']['budget']['revenue'])->toBe(100000)
        ->and($s['pnl']['actual']['revenue'])->toBe(40000)
        ->and($s['lines'][0]['id'])->toBe($line->id)
        ->and($s['duration_pct'])->toBe(50);   // 182 of 364 days
});

it('refuses to delete a line that still has entries', function () {
    $line = PdLine::factory()->create();
    PdEntry::factory()->for($line, 'line')->create();

    $line->delete();
})->throws(Illuminate\Database\QueryException::class);
