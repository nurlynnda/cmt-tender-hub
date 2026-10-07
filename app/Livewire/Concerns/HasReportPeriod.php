<?php

namespace App\Livewire\Concerns;

use App\Reports\ReportPeriod;
use App\Support\MalaysiaTime;
use Livewire\Attributes\Url;

/** The period filter shared by the Dashboard and Status pages (kept in the page address). */
trait HasReportPeriod
{
    #[Url] public string $period = 'all';
    #[Url] public string $month = '';
    #[Url] public string $from = '';
    #[Url] public string $to = '';

    protected function reportPeriod(): ReportPeriod
    {
        return ReportPeriod::fromInput(
            ['period' => $this->period, 'month' => $this->month, 'from' => $this->from, 'to' => $this->to],
            MalaysiaTime::today(),
        );
    }
}
