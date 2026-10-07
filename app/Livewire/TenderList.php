<?php

namespace App\Livewire;

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\User;
use App\Queries\TenderListQuery;
use Livewire\Attributes\{Layout, Url};
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TenderList extends Component
{
    use WithPagination;

    public string $list = 'in-progress';

    #[Url] public string $search = '';
    #[Url] public bool $mine = false;
    #[Url] public string $mode = '';
    #[Url] public string $pic = '';
    #[Url] public string $category = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';
    /** Registered between (WO date), set by Dashboard / Status links. */
    #[Url] public string $wo_from = '';
    #[Url] public string $wo_to = '';

    public function mount(string $list): void
    {
        $this->list = $list;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'mine', 'mode', 'pic', 'category', 'from', 'to', 'wo_from', 'wo_to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'mine', 'mode', 'pic', 'category', 'from', 'to', 'wo_from', 'wo_to']);
        $this->resetPage();
    }

    public function render()
    {
        $status = TenderStatus::fromSlug($this->list);
        $filters = ['search' => $this->search, 'mine' => $this->mine, 'mode' => $this->mode,
            'pic' => $this->pic, 'category' => $this->category, 'from' => $this->from, 'to' => $this->to,
            'wo_from' => $this->wo_from, 'wo_to' => $this->wo_to];

        return view('livewire.tender-list', [
            'status' => $status,
            'tenders' => TenderListQuery::build($status, auth()->user(), $filters)->paginate(10),
            'people' => User::orderBy('name')->get(['id', 'name']),
            'modes' => TenderMode::cases(),
            'categories' => TenderCategory::cases(),
        ])->title($status->listTitle());
    }
}
