<?php

namespace App\Actions\Tenders;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateWoNumber
{
    /** @param CarbonImmutable $malaysiaDay a Malaysia calendar day (see MalaysiaTime::today()) */
    public function next(CarbonImmutable $malaysiaDay): string
    {
        return DB::transaction(function () use ($malaysiaDay) {
            $date = $malaysiaDay->toDateString();

            DB::table('wo_sequences')->insertOrIgnore(['date' => $date, 'last_seq' => 0]);
            // Row lock: two people registering at the same moment queue here instead of sharing a number.
            $current = DB::table('wo_sequences')->where('date', $date)->lockForUpdate()->value('last_seq');
            $next = $current + 1;
            DB::table('wo_sequences')->where('date', $date)->update(['last_seq' => $next]);

            return sprintf('200-%s-%03d', $malaysiaDay->format('dmY'), $next);
        });
    }
}
