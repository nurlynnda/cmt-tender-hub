<?php

use App\Enums\{PdEntryType, PdGroup, TenderStatus};
use App\Livewire\TenderList;
use App\Models\{PdEntry, PdLine, Project, Tender, User};
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

it('shows actual GP % and closed projects on the Awarded list', function () {
    $this->actingAs(User::factory()->create());
    $project = Project::factory()->create(['approved_margin_bp' => 1500, 'closed_at' => now()]);
    $rev = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Collection]);
    $cost = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($rev, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 100000]);
    PdEntry::factory()->for($cost, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 85000]);
    Tender::factory()->status(TenderStatus::Awarded)->create(); // no project → dash

    Livewire::test(TenderList::class, ['list' => 'awarded'])
        ->assertSee('Actual GP')->assertSee('6.0%')   // (1,000 − 850 − 90) ÷ 1,000
        ->assertSee('Closed');
});

it('shows a dash rather than 0% before anything is invoiced to the customer', function () {
    $this->actingAs(User::factory()->create());
    $project = Project::factory()->create();
    $cost = PdLine::factory()->for($project)->create(['pd_group' => PdGroup::Principal]);
    PdEntry::factory()->for($cost, 'line')->create(['type' => PdEntryType::Invoice, 'amount_sen' => 85000]);

    Livewire::test(TenderList::class, ['list' => 'awarded'])->assertDontSee('0.0%');
});

it('seeds a sample PD on an awarded tender', function () {
    $this->seed(DatabaseSeeder::class);

    $project = Tender::where('wo_number', '200-15122025-006')->first()->project;
    $s = $project->summary('2026-03-01');

    expect($project->projectType->name)->toBe('Managed Services')
        ->and($s['pnl']['budget']['revenue'])->toBe(125000000)
        ->and(collect($s['lines'])->where('group', 'collection')->count())->toBe(3)
        ->and($s['pnl']['actual']['revenue'])->toBe(75000000)
        ->and(Tender::where('status', TenderStatus::Awarded)->whereDoesntHave('project')->count())->toBe(0);
});
