<?php

use App\Enums\TenderStatus;
use App\Livewire\TenderList;
use App\Models\{CostingLine, Tender, User};
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

it('shows the costing margin as Gross on the Done list', function () {
    $this->actingAs(User::factory()->create());
    $t = Tender::factory()->status(TenderStatus::Done)->create();
    CostingLine::factory()->for($t)->create(['unit_cost_sen' => 10000000, 'margin_bp' => 1850]);
    Tender::factory()->status(TenderStatus::Done)->create(); // no costing → dash

    Livewire::test(TenderList::class, ['list' => 'done'])->assertSee('Gross')->assertSee('18.5%');
});

it('does not show Gross on other lists', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TenderList::class, ['list' => 'in-progress'])->assertDontSee('Gross');
});

it('seeds the JPNIN costing', function () {
    $this->seed(DatabaseSeeder::class);

    $s = Tender::where('wo_number', '200-10092026-001')->first()->costingSummary();

    expect($s['total_cost_sen'])->toBe(13284500)->and($s['bid_price_sen'])->toBe(16605900)
        ->and(count($s['lines']))->toBe(12);
});
