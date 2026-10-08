<?php

use App\Actions\Tenders\{MarkTenderAwarded, ReopenTender};
use App\Enums\{PdGroup, TenderStatus};
use App\Models\{CostingLine, PdEntry, Tender, User};

function doneTender(array $attrs = []): array
{
    $pic = User::factory()->create();

    return [$pic, Tender::factory()->status(TenderStatus::Done)->create(array_merge(['pic_id' => $pic->id], $attrs))];
}

it('creates the project from the costing when a tender is awarded', function () {
    [$pic, $tender] = doneTender();
    CostingLine::factory()->for($tender)->create(['position' => 1, 'description' => 'Laptops', 'vendor' => 'Dell',
        'unit_cost_sen' => 100000, 'quantity' => 2]);
    CostingLine::factory()->for($tender)->create(['position' => 2, 'description' => 'Support', 'frequency' => 12, 'unit_cost_sen' => 50000]);

    $awarded = app(MarkTenderAwarded::class)->handle($pic, $tender, 1);
    $project = $awarded->project;
    $bid = $tender->costingSummary()['bid_price_sen'];

    expect($project)->not->toBeNull()
        ->and($project->project_charge_bp)->toBe(900)
        ->and($project->commission_share_bp)->toBe(5000)
        ->and($project->approved_margin_bp)->toBe(0)
        ->and($project->lines->map(fn ($l) => [$l->pd_group, $l->name, $l->reference, $l->budget_sen])->all())->toBe([
            [PdGroup::Collection, 'Contract value', null, $bid],
            [PdGroup::Principal, 'Laptops', 'Dell', 200000],
            [PdGroup::Principal, 'Support', null, 600000],   // frequency 12: the whole cost
        ])
        ->and($awarded->activity->first()->description)->toBe('Project created from the costing');
});

it('uses the submitted price when there is no costing', function () {
    [$pic, $tender] = doneTender(['submitted_price_sen' => 7184620]);

    $awarded = app(MarkTenderAwarded::class)->handle($pic, $tender, 1);

    expect($awarded->project->lines)->toHaveCount(1)
        ->and($awarded->project->lines->first()->budget_sen)->toBe(7184620)
        ->and($awarded->activity->first()->description)->toBe('Project created');
});

it('keeps the same project when a tender is reopened and awarded again', function () {
    [$pic, $tender] = doneTender(['submitted_price_sen' => 100]);
    $manager = User::factory()->manager()->create();
    $project = app(MarkTenderAwarded::class)->handle($pic, $tender, 1)->project;
    PdEntry::factory()->for($project->lines->first(), 'line')->create(['type' => 'receipt']);

    $t = app(ReopenTender::class)->handle($manager, $tender, 2);
    $t->update(['status' => TenderStatus::Done, 'version' => $t->version + 1]);
    $again = app(MarkTenderAwarded::class)->handle($pic, $t->fresh(), $t->fresh()->version)->project;

    expect($again->id)->toBe($project->id)
        ->and($again->lines)->toHaveCount(1)
        ->and(PdEntry::count())->toBe(1);
});
