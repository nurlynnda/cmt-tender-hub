<?php

use App\Livewire\MarketInsights;
use App\Models\{CollectedTender, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function win(string $closing, string $name, int $price, string $ministry = 'KEMENTERIAN A'): void
{
    CollectedTender::factory()->create(['status' => 'closed', 'closing_date' => $closing, 'ministry' => $ministry, 'winners' => [['name' => $name, 'price_sen' => $price]]]);
}

it('shows the year figures, the trend and our rank, defaulting to the current year', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 04:00:00', 'UTC'));
    win('2026-02-01', '10 CREATIVE SOLUTIONS SDN. BHD.', 420000000);
    win('2025-02-01', 'ACME', 100);
    foreach (range(1, 11) as $i) {
        win('2026-03-01', "BIG CONTRACTOR {$i}", 900000000 + $i);
    }

    $this->get(route('market.index'))->assertOk()->assertSee('Market Insights');

    Livewire::test(MarketInsights::class)
        ->assertSet('year', '2026')
        ->assertSee('Awarded value by year')->assertSee('Tenders awarded by year')->assertSee('2025')
        ->assertSee('Spend by ministry')->assertSee('KEMENTERIAN A')->assertSee('Top contractors')->assertSee('BIG CONTRACTOR 11')
        ->assertSee('10 Creative Solutions Sdn Bhd')
        ->assertSeeHtml('data-own-rank')->assertSee('#12 of 12')
        ->assertSeeHtml('data-own-extra')                                   // we're outside the top 10
        ->assertSee(route('find-tenders.index', ['status' => 'awarded', 'ours' => 1, 'from' => '2026-01-01', 'to' => '2026-12-31']))
        ->assertSee(route('find-tenders.index', ['status' => 'awarded', 'contractor' => 'BIG CONTRACTOR 11', 'from' => '2026-01-01', 'to' => '2026-12-31']));
});

it('falls back to the current year for a year with no awards, and says so', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 04:00:00', 'UTC'));
    win('2026-02-01', 'ACME', 100);

    Livewire::withQueryParams(['year' => '2030'])->test(MarketInsights::class)
        ->assertSet('year', '2026')->assertSee('That year has no awards — showing 2026')
        ->assertSee('No awards in 2026')->assertDontSeeHtml('data-own-rank');     // our company won nothing

    Livewire::withQueryParams(['year' => 'abc'])->test(MarketInsights::class)->assertSet('year', '2026')->assertSee('showing 2026');
});

it('shows all years when asked, and links the right-now boxes to Find Tenders', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 01:00:00', 'UTC'));
    win('2025-02-01', 'ACME', 100);
    CollectedTender::factory()->create(['status' => 'open', 'closing_date' => '2026-10-07']);

    Livewire::withQueryParams(['year' => 'all'])->test(MarketInsights::class)
        ->assertSee('All years')->assertSee('RM 1.00')->assertSee('No awards in any year')
        ->assertSee('Closing today')->assertSee(route('find-tenders.index', ['from' => '2026-10-07', 'to' => '2026-10-07']))
        ->set('year', '2025')->assertSee('ACME');
});

it('links to the See-all pages, keeping the year', function () {
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 04:00:00', 'UTC'));
    win('2026-02-01', 'ACME', 100);

    Livewire::test(MarketInsights::class)
        ->assertSee(route('market.ministries', ['year' => '2026']))->assertSee(route('market.contractors', ['year' => '2026']));
});

it('lists every ministry for the year', function () {
    win('2026-02-01', 'ACME', 100);
    win('2025-02-01', 'BETA', 100, 'KEMENTERIAN LAMA');

    $this->get(route('market.ministries', ['year' => '2026']))->assertOk()->assertSee('Spend by ministry');
    Livewire::withQueryParams(['year' => '2026'])->test(\App\Livewire\MarketMinistries::class)
        ->assertSee('KEMENTERIAN A')->assertDontSee('KEMENTERIAN LAMA')->assertSee(route('market.index', ['year' => '2026']))
        ->set('year', 'all')->assertSee('KEMENTERIAN LAMA');
});

it('lists every contractor with a search and 50 per page, our rows marked', function () {
    foreach (range(1, 55) as $i) {
        win('2026-02-01', "CONTRACTOR {$i}", 1000 + $i);
    }
    win('2026-02-01', '10 CREATIVE SOLUTIONS SDN. BHD.', 1);

    $this->get(route('market.contractors', ['year' => '2026']))->assertOk()->assertSee('Top contractors');
    Livewire::withQueryParams(['year' => '2026'])->test(\App\Livewire\MarketContractors::class)
        ->assertSee('Showing 1–50 of 56 contractors')->assertSee('CONTRACTOR 55')->assertDontSee('10 CREATIVE SOLUTIONS')
        ->call('gotoPage', 2)->assertSee('Showing 51–56 of 56')->assertSee('56. 10 CREATIVE SOLUTIONS SDN. BHD.')
        ->set('search', 'creative')->assertSee('Showing 1–1 of 1')->assertSee('10 CREATIVE SOLUTIONS SDN. BHD.')->assertSeeHtml('data-ours')
        ->assertDontSee('CONTRACTOR 7')
        ->assertSee(route('find-tenders.index', ['status' => 'awarded', 'contractor' => '10 CREATIVE SOLUTIONS SDN. BHD.', 'from' => '2026-01-01', 'to' => '2026-12-31']));
});
