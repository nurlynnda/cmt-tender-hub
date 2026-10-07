<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasMarketYear;
use App\Market\MarketReport;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

/** Market Insights → See all: every ministry's awarded value for the chosen year. */
#[Layout('layouts.app')]
#[Title('Spend by ministry')]
class MarketMinistries extends Component
{
    use HasMarketYear;

    public function render()
    {
        $r = app(MarketReport::class);
        $years = $r->years();
        $y = $this->marketYear($years);

        return view('livewire.market-ministries', [
            'years' => $years,
            'yearLabel' => $y ? (string) $y : 'All years',
            'ministries' => $r->byMinistry($y),
        ]);
    }
}
