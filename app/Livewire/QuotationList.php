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

    public function updated(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $q = app(CreateQuotation::class)->handle(auth()->user());
        $this->redirectRoute('quotations.show', $q);
    }

    public function render()
    {
        return view('livewire.quotation-list', [
            'quotations' => QuotationListQuery::build($this->status, $this->search, $this->mine, auth()->user(), MalaysiaTime::today())->paginate(25),
        ]);
    }
}
