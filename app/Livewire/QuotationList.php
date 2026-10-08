<?php

namespace App\Livewire;

use App\Actions\Quotations\CreateQuotation;
use App\Quotations\QuotationListQuery;
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Quotations')]
class QuotationList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = 'all';
    #[Url] public bool $mine = false;
    /** date_desc (newest first) or date_asc, toggled from the Date heading. */
    #[Url] public string $sort = 'date_desc';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function toggleDateSort(): void
    {
        $this->sort = $this->sort === 'date_asc' ? 'date_desc' : 'date_asc';
        $this->resetPage();
    }

    public function create(): void
    {
        $q = app(CreateQuotation::class)->handle(auth()->user());
        $this->redirectRoute('quotations.show', $q);
    }

    public function render()
    {
        $today = MalaysiaTime::today();
        $sort = $this->sort === 'date_asc' ? 'date_asc' : 'date_desc';

        return view('livewire.quotation-list', [
            'quotations' => QuotationListQuery::build($this->status, $this->search, $this->mine, auth()->user(), $today, $sort)->paginate(25),
            'counts' => QuotationListQuery::counts($this->search, $this->mine, auth()->user(), $today),
            'sortDir' => $sort,
        ]);
    }
}
