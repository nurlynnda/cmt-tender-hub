<?php

namespace App\Reports;

use App\Enums\QuotationStatus;
use App\Models\{Project, Quotation};
use Carbon\CarbonImmutable;

/** The two small Dashboard cards: quotations and projects. */
final class ReportCards
{
    /** Open = Sent and not expired (whatever the period); Accepted = quotation dated in the period. */
    public function quotations(ReportPeriod $period, CarbonImmutable $today): array
    {
        $open = Quotation::with('items')->where('status', QuotationStatus::Sent)
            ->whereRaw('DATE_ADD(quote_date, INTERVAL validity_days DAY) >= ?', [$today->format('Y-m-d')])->get();
        $accepted = Quotation::with('items')->where('status', QuotationStatus::Accepted)
            ->when(! $period->isAllTime(), fn ($q) => $q->whereBetween('quote_date', [$period->from, $period->to]))->get();

        return [
            'open' => $open->count(),
            'open_total_sen' => $open->sum(fn (Quotation $q) => $q->totals()['total_sen']),
            'accepted' => $accepted->count(),
            'accepted_total_sen' => $accepted->sum(fn (Quotation $q) => $q->totals()['total_sen']),
        ];
    }

    /** Current state, whatever the period: projects run across months. */
    public function projects(): array
    {
        $running = Project::with(['lines', 'tender', 'quotation'])->whereNull('closed_at')->get()
            ->filter(fn (Project $p) => $p->isActive());

        return [
            'running' => $running->count(),
            'below_margin' => $running->filter(fn (Project $p) => $p->summary()['below_margin'])->count(),
        ];
    }
}
