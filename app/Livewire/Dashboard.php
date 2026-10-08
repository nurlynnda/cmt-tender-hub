<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasReportPeriod;
use App\Reports\{PipelineReport, ReportCards};
use App\Support\MalaysiaTime;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

/** The home page: an at-a-glance view of the pipeline for the chosen period. */
#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use HasReportPeriod;

    public function render()
    {
        $today = MalaysiaTime::today();
        $period = $this->reportPeriod();
        $reports = app(PipelineReport::class);
        $cards = app(ReportCards::class);

        return view('livewire.dashboard', [
            'reportPeriod' => $period,
            'r' => $reports->build($period, $today),
            'deadlines' => $reports->deadlines($today),
            'quotes' => $cards->quotations($period, $today),
            'projects' => $cards->projects(),
            'today' => $today,
        ]);
    }
}
