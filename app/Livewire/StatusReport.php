<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasReportPeriod;
use App\Reports\PipelineReport;
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;

/** Per-PIC performance for the chosen period, sortable by any column. */
#[Layout('layouts.app')]
#[Title('Status')]
class StatusReport extends Component
{
    use HasReportPeriod;

    public const COLUMNS = ['name', 'total', 'in_progress', 'done', 'awarded', 'lost', 'dropped', 'win_rate_bp', 'bid_value_sen', 'won_value_sen'];

    #[Url] public string $sort = 'bid_value_sen';
    #[Url] public string $dir = 'desc';

    /** A new column sorts names A→Z and numbers highest first; clicking the same column again flips it. */
    public function sortBy(string $column): void
    {
        if (! in_array($column, self::COLUMNS, true)) {
            $this->reset('sort', 'dir');

            return;
        }
        $this->dir = $this->sort === $column
            ? ($this->dir === 'asc' ? 'desc' : 'asc')
            : ($column === 'name' ? 'asc' : 'desc');
        $this->sort = $column;
    }

    public function render()
    {
        $period = $this->reportPeriod();
        $report = app(PipelineReport::class)->build($period, MalaysiaTime::today());
        $sort = in_array($this->sort, self::COLUMNS, true) ? $this->sort : 'bid_value_sen';
        $dir = $this->dir === 'asc' ? 'asc' : 'desc';
        $rows = collect($report['pics'])->sortBy([[$sort, $dir], ['name', 'asc']])->values();

        return view('livewire.status-report', ['reportPeriod' => $period, 'rows' => $rows, 'totals' => $report['totals']]);
    }
}
