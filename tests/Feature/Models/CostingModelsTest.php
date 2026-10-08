<?php

use App\Models\{CostingLine, CostingSubItem, Tender};

it('stores lines with sub-items in order, and tender costing settings', function () {
    $tender = Tender::factory()->create();
    CostingLine::factory()->for($tender)->create(['position' => 2, 'description' => 'B']);
    $a = CostingLine::factory()->for($tender)->create(['position' => 1, 'description' => 'A']);
    $a->subItems()->create(['position' => 1, 'description' => 'PC', 'unit' => 'unit', 'quantity' => 1, 'unit_cost_sen' => 300000]);

    $fresh = $tender->fresh();
    expect($fresh->costingLines->pluck('description')->all())->toBe(['A', 'B'])
        ->and($fresh->costingLines->first()->subItems->pluck('description')->all())->toBe(['PC'])
        ->and($fresh->default_margin_bp)->toBe(2000)
        ->and($fresh->bid_price_override_sen)->toBeNull();
});

it('deletes lines and sub-items with the tender', function () {
    $tender = Tender::factory()->create();
    CostingLine::factory()->for($tender)->create()->subItems()->create(['position' => 1, 'description' => 'X', 'unit' => 'u', 'quantity' => 1, 'unit_cost_sen' => 1]);

    $tender->delete();

    expect(CostingLine::count())->toBe(0)->and(CostingSubItem::count())->toBe(0);
});
