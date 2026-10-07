<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasMarketYear;
use App\Market\{MarketReport, OwnCompany};
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

/** Market Insights → See all: every contractor by awarded value for the chosen year, searchable, 50 per page. */
#[Layout('layouts.app')]
#[Title('Top contractors')]
class MarketContractors extends Component
{
    use HasMarketYear, WithPagination;

    #[Url] public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $r = app(MarketReport::class);
        $years = $r->years();
        $y = $this->marketYear($years);

        return view('livewire.market-contractors', [
            'years' => $years,
            'yearLabel' => $y ? (string) $y : 'All years',
            'contractors' => $r->contractors($y, $this->search)->paginate(50),
            'ownKeys' => OwnCompany::keys(),
            'range' => $this->yearRange($y),
        ]);
    }
}
