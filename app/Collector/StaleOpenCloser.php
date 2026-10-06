<?php

namespace App\Collector;

use App\Models\CollectedTender;
use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;

/** Port of tms-v2 reconcileStaleOpen(): a correction from dates, not an observation (provenance untouched). */
final class StaleOpenCloser
{
    public function run(): int
    {
        $now = MalaysiaTime::now();
        $today = $now->toDateString();
        $pastNoon = $now->format('H:i') >= '12:01';

        $closed = CollectedTender::where('status', 'open')
            ->whereNotNull('closing_date')
            ->where(fn ($q) => $q->where('closing_date', '<', $today)
                ->when($pastNoon, fn ($q) => $q->orWhere('closing_date', $today)))
            ->update(['status' => 'closed']);

        $fallbackIds = CollectedTender::where('status', 'open')
            ->whereNull('closing_date')->whereNotNull('advertised_date')
            ->get(['id', 'advertised_date'])
            ->filter(fn ($t) => CarbonImmutable::parse($t->advertised_date->toDateString())->addMonthNoOverflow()->toDateString() <= $today)
            ->pluck('id');

        return $closed + CollectedTender::whereIn('id', $fallbackIds)->update(['status' => 'closed']);
    }
}
