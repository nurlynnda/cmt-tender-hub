<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasMarketYear;
use App\Market\{MarketReport, OwnCompany};
use Illuminate\Pagination\LengthAwarePaginator;
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

    /** 50 contractors per page from the remembered list (each row as an object, like a database row). */
    private function pageOf(array $list): LengthAwarePaginator
    {
        $page = max(1, min($this->getPage(), (int) ceil(count($list) / 50) ?: 1));

        return new LengthAwarePaginator(array_map(fn ($c) => (object) $c, array_slice($list, ($page - 1) * 50, 50)), count($list), 50, $page);
    }

    public function render()
    {
        $r = app(MarketReport::class);
        $years = $r->years();
        $y = $this->marketYear($years);

        return view('livewire.market-contractors', [
            'years' => $years,
            'yearLabel' => $y ? (string) $y : MarketReport::periodLabel(),
            'emptyNote' => $y ? "No awards in {$y}" : 'No awards since '.MarketReport::fromYear(),
            'contractors' => $this->pageOf($r->contractors($y, $this->search)),
            'ownKeys' => OwnCompany::keys(),
            'range' => $this->yearRange($y),
        ]);
    }
}
