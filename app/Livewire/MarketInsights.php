<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasMarketYear;
use App\Market\{MarketReport, OwnCompany};
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

/** The government market at a glance: awards by year, spend by ministry, top contractors and our rank. */
#[Layout('layouts.app')]
#[Title('Market Insights')]
class MarketInsights extends Component
{
    use HasMarketYear;

    public function render()
    {
        $r = app(MarketReport::class);
        $byYear = $r->byYear();
        $years = array_column($byYear, 'year');
        $y = $this->marketYear($years);
        $today = MalaysiaTime::today();

        return view('livewire.market-insights', [
            'years' => $years,
            'yearLabel' => $y ? (string) $y : 'All years',
            'now' => $r->now(),
            'today' => $today->toDateString(),
            'weekEnd' => $today->addDays(7)->toDateString(),
            'summary' => $r->summary($y),
            'byYear' => $byYear,
            'ministries' => $r->byMinistry($y, 10),
            'top' => $r->topContractors($y, 10),
            'own' => $r->ownRank($y),
            'ownKeys' => OwnCompany::keys(),
            'ownLabel' => OwnCompany::label(),
            'range' => $this->yearRange($y),
        ]);
    }
}
