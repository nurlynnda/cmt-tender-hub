<?php

namespace App\Livewire;

use App\Actions\Collector\StartCollection;
use App\Collector\SourceName;
use App\Models\CollectionRun;
use App\Queries\CollectedTenderQuery;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Find Tenders')]
class FindTenders extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = 'open';
    #[Url] public string $source = '';
    #[Url] public string $type = '';
    #[Url] public string $ministry = '';
    #[Url] public string $codes = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';
    /** '' = the usual order; closing_asc / closing_desc from the Closing heading. */
    #[Url] public string $sort = '';

    public ?string $notice = null;

    public function updated(string $property): void
    {
        if ($property !== 'notice') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'source', 'type', 'ministry', 'codes', 'from', 'to');
        $this->resetPage();
    }

    public function toggleClosingSort(): void
    {
        $this->sort = $this->sort === 'closing_asc' ? 'closing_desc' : 'closing_asc';
        $this->resetPage();
    }

    /** Filters changed from their starting values (status starts as "open"). */
    public function filterCount(): int
    {
        return count(array_filter([$this->status !== 'open', $this->source, $this->type, $this->ministry, $this->codes, $this->from, $this->to]));
    }

    public function collectNow(): void
    {
        $this->authorize('collect-now');
        $run = app(StartCollection::class)->handle(auth()->user(), 'manual', 'open');
        $this->notice = $run ? 'Collecting now — new tenders will appear in a few minutes.' : 'Already collecting — please wait for the current run to finish.';
    }

    public function render()
    {
        app(StartCollection::class)->failStuckRuns();

        return view('livewire.find-tenders', [
            'tenders' => CollectedTenderQuery::build($this->only(['search', 'status', 'source', 'type', 'ministry', 'codes', 'from', 'to', 'sort']))->paginate(25),
            'running' => CollectionRun::where('status', 'running')->latest('started_at')->first(),
            'lastRun' => CollectionRun::whereNotNull('finished_at')->latest('finished_at')->first(),
            'ministries' => CollectedTenderQuery::ministries(),
            'sources' => SourceName::all(),
        ]);
    }
}
