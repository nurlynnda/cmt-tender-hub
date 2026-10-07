<?php

use App\Market\MarketReport;
use App\Models\CollectedTender;

function award(?string $closing, ?string $ministry, array $winners): void
{
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => $closing, 'ministry' => $ministry, 'winners' => $winners]);
}

beforeEach(function () {
    award('2026-02-01', 'KEMENTERIAN A', [['name' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'price_sen' => 300]]);
    award('2026-03-01', 'KEMENTERIAN A', [['name' => 'ACME SDN BHD', 'price_sen' => 1000], ['name' => 'Acme Sdn. Bhd.', 'price_sen' => 50]]); // same company twice on one tender
    award('2026-04-01', null, [['name' => 'BETA', 'price_sen' => null]]);
    award('2025-05-01', 'KEMENTERIAN B', [['name' => '10 CREATIVE SOLUTIONS SDN BHD', 'price_sen' => 700]]);
    award(null, 'KEMENTERIAN B', [['name' => 'GAMMA', 'price_sen' => 9]]);                         // undated → left out of "2023 – now"
    award('2022-06-01', 'KEMENTERIAN LAMA', [['name' => 'OLDCO', 'price_sen' => 99999]]);          // before 2023 → left out
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => '2026-01-01', 'winners' => null]); // closed, no winner
    CollectedTender::factory()->create(['status' => 'open', 'closing_date' => '2026-05-01', 'winners' => [['name' => 'OPEN CO', 'price_sen' => 5]]]); // not closed → not an award
});

it('summarises a year and 2023 onwards (older and undated awards left out)', function () {
    $r = app(MarketReport::class);

    expect($r->summary(2026))->toBe(['tenders' => 3, 'value_sen' => 1350, 'contractors' => 3, 'unpriced' => 1])
        ->and($r->summary(null))->toBe(['tenders' => 4, 'value_sen' => 2050, 'contractors' => 3, 'unpriced' => 1])
        ->and(array_column($r->byMinistry(null), 'ministry'))->not->toContain('KEMENTERIAN LAMA')
        ->and(MarketReport::fromYear())->toBe(2023)
        ->and($r->byYear())->toBe([['year' => 2026, 'tenders' => 3, 'value_sen' => 1350], ['year' => 2025, 'tenders' => 1, 'value_sen' => 700]])
        ->and($r->years())->toBe([2026, 2025]);
});

it('ranks ministries and contractors, merging spellings and counting a tender once per contractor', function () {
    $r = app(MarketReport::class);

    expect($r->byMinistry(2026))->toBe([
        ['ministry' => 'KEMENTERIAN A', 'tenders' => 2, 'value_sen' => 1350], ['ministry' => 'Not stated', 'tenders' => 1, 'value_sen' => 0],
    ])->and($r->byMinistry(2026, 1))->toHaveCount(1);
    $top = array_map(fn ($c) => [$c['name_key'], $c['wins'], $c['value_sen']], $r->contractors(2026));
    expect($top)->toBe([['ACME SDN BHD', 1, 1050], ['10 CREATIVE SOLUTIONS SDN BHD', 1, 300], ['BETA', 1, 0]])
        ->and($r->contractors(2026, 'acme'))->toHaveCount(1)->and($r->contractors(2026, 'a'))->toHaveCount(3)
        ->and($r->topContractors(2026, 2))->toHaveCount(2);
});

it('finds our rank, or none when we won nothing that year', function () {
    $r = app(MarketReport::class);

    expect($r->ownRank(2026))->toBe(['rank' => 2, 'of' => 3, 'wins' => 1, 'value_sen' => 300])
        ->and($r->ownRank(null))->toBe(['rank' => 2, 'of' => 3, 'wins' => 2, 'value_sen' => 1000])   // ACME 1050 first; OLDCO and GAMMA left out
        ->and($r->ownRank(2024))->toBeNull();

    \App\Models\FinanceSetting::current()->update(['own_company_names' => null]);
    expect($r->ownRank(2026))->toBeNull();
});

it('counts open tenders, those closing today and those closing this week, in Malaysia time', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 01:00:00', 'UTC')); // 9am in Malaysia
    foreach (['2026-10-07', '2026-10-10', '2026-10-30', null] as $d) {
        CollectedTender::factory()->create(['status' => 'open', 'closing_date' => $d]);
    }

    expect(app(MarketReport::class)->now())->toBe(['open' => 4, 'closing_today' => 1, 'closing_week' => 2]);
});

it('treats a blank ministry like a missing one', function () {
    award('2026-05-01', '', [['name' => 'DELTA', 'price_sen' => 5]]);

    expect(app(MarketReport::class)->byMinistry(2026))->toBe([
        ['ministry' => 'KEMENTERIAN A', 'tenders' => 2, 'value_sen' => 1350], ['ministry' => 'Not stated', 'tenders' => 2, 'value_sen' => 5],
    ]);
});

it('remembers its figures until the winners list changes', function () {
    $r = app(MarketReport::class);
    $before = $r->byMinistry(2026);
    $top = $r->topContractors(2026, 2);

    \Illuminate\Support\Facades\DB::table('collected_tenders')->where('ministry', 'KEMENTERIAN A')->update(['ministry' => 'RENAMED']);
    expect($r->byMinistry(2026))->toBe($before)                       // served from memory: no award changed
        ->and($top)->toBe([['name_key' => 'ACME SDN BHD', 'name' => 'Acme Sdn. Bhd.', 'wins' => 1, 'value_sen' => 1050],
            ['name_key' => '10 CREATIVE SOLUTIONS SDN BHD', 'name' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'wins' => 1, 'value_sen' => 300]]);

    award('2026-06-01', 'KEMENTERIAN C', [['name' => 'EPSILON', 'price_sen' => 1]]);
    expect(array_column($r->byMinistry(2026), 'ministry'))->toBe(['RENAMED', 'KEMENTERIAN C', 'Not stated']);
});

it('keeps one stored copy of each figure, replaced when the winners change (no pile-up of old copies)', function () {
    $r = app(MarketReport::class);
    $r->byMinistry(2026);
    award('2026-06-01', 'KEMENTERIAN C', [['name' => 'EPSILON', 'price_sen' => 1]]);
    $r->byMinistry(2026);

    expect(\Illuminate\Support\Facades\Cache::get('market:by-ministry:2026')['value'])->toHaveCount(3);
});
