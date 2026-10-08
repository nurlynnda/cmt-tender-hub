<?php

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, TenderDocument, User};
use Carbon\CarbonImmutable;

it('casts enums, dates, money and version', function () {
    $tender = Tender::factory()->create([
        'closing_date' => '2026-10-15',
        'estimated_value_sen' => 16200610,
    ])->fresh();

    expect($tender->status)->toBe(TenderStatus::InProgress)
        ->and($tender->mode)->toBeInstanceOf(TenderMode::class)
        ->and($tender->category)->toBeInstanceOf(TenderCategory::class)
        ->and($tender->closing_date->toDateString())->toBe('2026-10-15')
        ->and($tender->estimated_value_sen)->toBe(16200610)
        ->and($tender->version)->toBe(1);
});

it('belongs to a PIC and an optional opportunity owner', function () {
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'owner_id' => null]);

    expect($tender->pic->is($pic))->toBeTrue()->and($tender->owner)->toBeNull();
});

it('orders documents by position and counts progress', function () {
    $tender = Tender::factory()->create();
    TenderDocument::factory()->for($tender)->create(['name' => 'B', 'position' => 2, 'is_done' => true]);
    TenderDocument::factory()->for($tender)->create(['name' => 'A', 'position' => 1]);
    TenderDocument::factory()->for($tender)->create(['name' => 'C', 'position' => 3]);

    expect($tender->documents->pluck('name')->all())->toBe(['A', 'B', 'C'])
        ->and($tender->documentPercent())->toBe(33)
        ->and(Tender::withDocumentCounts()->find($tender->id)->documentPercent())->toBe(33);
});

it('reports 0% when there are no documents', function () {
    expect(Tender::factory()->create()->documentPercent())->toBe(0);
});

it('flags closing dates by Malaysia day', function (string $closing, ?string $state) {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 7 Oct in Malaysia

    expect(Tender::factory()->make(['closing_date' => $closing])->closingState())->toBe($state);
})->with([
    ['2026-10-06', 'overdue'],
    ['2026-10-07', 'soon'],
    ['2026-10-14', 'soon'],
    ['2026-10-15', null],
]);

it('only flags closing dates while in progress', function () {
    expect(Tender::factory()->make([
        'closing_date' => '2020-01-01', 'status' => TenderStatus::Done,
    ])->closingState())->toBeNull();
});

it('computes company and win variants against the estimated value', function () {
    $tender = Tender::factory()->make([
        'estimated_value_sen' => 40275400,
        'submitted_price_sen' => 52828500,
        'winning_price_sen' => 86617900,
    ]);

    expect($tender->winVariant())->toBe('215.1%')
        ->and($tender->companyVariant())->toBe('131.2%');
});

it('is locked unless in progress', function () {
    expect(Tender::factory()->make()->isLocked())->toBeFalse()
        ->and(Tender::factory()->make(['status' => TenderStatus::Awarded])->isLocked())->toBeTrue();
});

it('lists the five standard documents in order', function () {
    expect(TenderDocument::STANDARD)->toBe([
        'Borang ISI (Tender Form)',
        'Pricing Schedule',
        'Company Profile / SSM Registration',
        'Technical Proposal',
        'Bid Bond / Bank Guarantee',
    ]);
});
