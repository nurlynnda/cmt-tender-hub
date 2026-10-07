<?php

use App\Enums\{TenderMode, TenderStatus};
use App\Models\{Tender, User};
use App\Reports\{PipelineReport, ReportPeriod};
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

function pipeline(array $period = [], string $today = '2026-10-15'): array
{
    $t = CarbonImmutable::parse($today);

    return app(PipelineReport::class)->build(ReportPeriod::fromInput($period, $t), $t);
}

function tenderFor(User $pic, TenderStatus $status, array $attrs = []): Tender
{
    return Tender::factory()->status($status)->create(array_merge(['pic_id' => $pic->id, 'wo_date' => '2026-10-05',
        'estimated_value_sen' => null, 'submitted_price_sen' => null, 'mode' => TenderMode::Ep], $attrs));
}

it('counts statuses, win rate and values, leaving cancelled out', function () {
    $siti = User::factory()->create(['name' => 'Siti Aisyah']);
    tenderFor($siti, TenderStatus::InProgress, ['estimated_value_sen' => 100000]);
    tenderFor($siti, TenderStatus::Done, ['submitted_price_sen' => 200000]);
    tenderFor($siti, TenderStatus::Awarded, ['submitted_price_sen' => 300000]);
    tenderFor($siti, TenderStatus::Lost, ['submitted_price_sen' => 400000]);
    tenderFor($siti, TenderStatus::Lost, ['submitted_price_sen' => 999999, 'was_cancelled' => true]);

    $r = pipeline();

    expect($r['counts'])->toBe(['in_progress' => 1, 'done' => 1, 'awarded' => 1, 'lost' => 2, 'cancelled' => 1, 'total' => 5])
        ->and([$r['won'], $r['decided'], $r['win_rate_bp']])->toBe([1, 2, 5000])
        ->and($r['bid_value_sen'])->toBe(1000000)      // 1,000 + 2,000 + 3,000 + 4,000; cancelled excluded
        ->and($r['won_value_sen'])->toBe(300000)
        ->and($r['without_value'])->toBe(0);
});

it('shows no win rate before anything is decided, and reports tenders without a value', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::InProgress);
    tenderFor($u, TenderStatus::Done);
    tenderFor($u, TenderStatus::Lost, ['was_cancelled' => true]);

    $r = pipeline();

    expect($r['win_rate_bp'])->toBeNull()->and($r['decided'])->toBe(0)
        ->and($r['without_value'])->toBe(2)->and($r['bid_value_sen'])->toBe(0);
});

it('only counts tenders registered in the period', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::Done, ['wo_date' => '2026-10-01']);
    tenderFor($u, TenderStatus::Done, ['wo_date' => '2026-10-31']);
    tenderFor($u, TenderStatus::Done, ['wo_date' => '2026-09-30']);

    expect(pipeline(['period' => 'this_month'])['counts']['total'])->toBe(2)
        ->and(pipeline(['period' => 'last_month'])['counts']['total'])->toBe(1)
        ->and(pipeline()['counts']['total'])->toBe(3);
});

it('splits EP and non-EP', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::Done, ['submitted_price_sen' => 500, 'mode' => TenderMode::Ep]);
    tenderFor($u, TenderStatus::InProgress, ['estimated_value_sen' => 700, 'mode' => TenderMode::NonEp]);

    $r = pipeline();

    expect($r['modes']['EP'])->toMatchArray(['done' => 1, 'total' => 1, 'bid_value_sen' => 500])
        ->and($r['modes']['NON_EP'])->toMatchArray(['in_progress' => 1, 'total' => 1, 'bid_value_sen' => 700]);
});

it('counts tenders due within the week', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-15']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-22']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-23']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-14']);
    tenderFor($u, TenderStatus::Done, ['closing_date' => '2026-10-16']);

    expect(pipeline()['due_this_week'])->toBe(2);
});

it('lists every active person, zero rows included, and switched-off people only when they have tenders', function () {
    $ahmad = User::factory()->create(['name' => 'Ahmad Faizal']);
    User::factory()->create(['name' => 'Nurul Ain']);
    $gone = User::factory()->create(['name' => 'Gone Person', 'is_active' => false]);
    User::factory()->create(['name' => 'Left Early', 'is_active' => false]);
    tenderFor($ahmad, TenderStatus::Awarded, ['submitted_price_sen' => 300]);
    tenderFor($ahmad, TenderStatus::Lost, ['submitted_price_sen' => 100]);
    tenderFor($gone, TenderStatus::Done, ['submitted_price_sen' => 600]);

    $r = pipeline();
    $rows = collect($r['pics'])->keyBy('name');

    expect($rows->keys()->all())->toContain('Ahmad Faizal', 'Nurul Ain', 'Gone Person')->not->toContain('Left Early')
        ->and($rows['Ahmad Faizal'])->toMatchArray(['total' => 2, 'awarded' => 1, 'lost' => 1, 'win_rate_bp' => 5000,
            'bid_value_sen' => 400, 'won_value_sen' => 300, 'share_bp' => 4000, 'initials' => 'AF'])
        ->and($rows['Nurul Ain'])->toMatchArray(['total' => 0, 'win_rate_bp' => null, 'share_bp' => 0])
        ->and($r['pics'][0]['name'])->toBe('Gone Person')   // biggest bid value first
        ->and($r['totals'])->toMatchArray(['name' => 'Total', 'total' => 3, 'bid_value_sen' => 1000, 'share_bp' => 10000]);
});

it('lists the soonest deadlines, ignoring the period and past dates', function () {
    $u = User::factory()->create();
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-20', 'title' => 'Later', 'wo_date' => '2020-01-01']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-16', 'title' => 'Sooner']);
    tenderFor($u, TenderStatus::InProgress, ['closing_date' => '2026-10-01', 'title' => 'Past']);
    tenderFor($u, TenderStatus::Done, ['closing_date' => '2026-10-17', 'title' => 'Submitted']);

    expect(app(PipelineReport::class)->deadlines(CarbonImmutable::parse('2026-10-15'))->pluck('title')->all())->toBe(['Sooner', 'Later']);
});

it('matches the prototype sample data', function () {
    $this->seed(DatabaseSeeder::class);

    $r = pipeline();

    expect($r['counts'])->toMatchArray(['total' => 41, 'in_progress' => 13, 'done' => 20, 'awarded' => 3, 'lost' => 5])
        ->and([$r['modes']['EP']['total'], $r['modes']['NON_EP']['total']])->toBe([40, 1])
        ->and(collect($r['pics'])->whereIn('name', ['Ahmad Faizal', 'Nurul Ain', 'Siti Aisyah', 'Muhammad Hafiz'])->pluck('total', 'name')->all())
        ->toEqualCanonicalizing(['Ahmad Faizal' => 11, 'Nurul Ain' => 10, 'Siti Aisyah' => 10, 'Muhammad Hafiz' => 10]);
});
