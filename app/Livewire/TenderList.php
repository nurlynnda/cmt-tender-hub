<?php

namespace App\Livewire;

use App\Enums\{TenderCategory, TenderMode, TenderStatus};
use App\Models\{Tender, User};
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
    #[Url] public string $agency = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';
    /** Registered between (WO date), set by Dashboard / Status links. */
    #[Url] public string $wo_from = '';
    #[Url] public string $wo_to = '';
    /** '' = the list's usual order; or deadline_asc / deadline_desc from the Deadline heading. */
    #[Url] public string $sort = '';

    public function mount(string $list): void
    {
        $this->list = $list;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'mine', 'mode', 'pic', 'category', 'agency', 'from', 'to', 'wo_from', 'wo_to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'mine', 'mode', 'pic', 'category', 'agency', 'from', 'to', 'wo_from', 'wo_to']);
        $this->resetPage();
    }

    public function toggleDeadlineSort(): void
    {
        $this->sort = $this->sort === 'deadline_asc' ? 'deadline_desc' : 'deadline_asc';
        $this->resetPage();
    }

    /** Filters switched on in the Filters panel, plus My tenders. The Dashboard's date range shows separately. */
    public function filterCount(): int
    {
        return count(array_filter([$this->mine, $this->pic, $this->agency, $this->mode, $this->category, $this->from, $this->to]));
    }

    public function render()
    {
        $status = TenderStatus::fromSlug($this->list);
        $agencies = Tender::where('status', $status)->distinct()->orderBy('client')->pluck('client');
        $filters = ['search' => $this->search, 'mine' => $this->mine, 'mode' => $this->mode,
            'pic' => $this->pic, 'category' => $this->category, 'from' => $this->from, 'to' => $this->to,
            'wo_from' => $this->wo_from, 'wo_to' => $this->wo_to,
            'agency' => $agencies->contains($this->agency) ? $this->agency : '',
            'sort' => in_array($this->sort, ['deadline_asc', 'deadline_desc'], true) ? $this->sort : ''];

        return view('livewire.tender-list', [
            'status' => $status,
            'tenders' => TenderListQuery::build($status, auth()->user(), $filters)->paginate(10),
            'people' => User::orderBy('name')->get(['id', 'name']),
            'modes' => TenderMode::cases(),
            'categories' => TenderCategory::cases(),
            'agencies' => $agencies,
        ])->title($status->listTitle());
    }
}
