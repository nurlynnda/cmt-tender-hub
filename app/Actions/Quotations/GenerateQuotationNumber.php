<?php

namespace App\Actions\Quotations;

use Illuminate\Support\Facades\DB;

final class GenerateQuotationNumber
{
    public function next(int $year): string
    {
        return DB::transaction(function () use ($year) {
            DB::table('quotation_sequences')->insertOrIgnore(['year' => $year, 'last_seq' => 0]);
            // Row lock: two people creating at the same moment queue here instead of sharing a number.
            $next = DB::table('quotation_sequences')->where('year', $year)->lockForUpdate()->value('last_seq') + 1;
            DB::table('quotation_sequences')->where('year', $year)->update(['last_seq' => $next]);

            return sprintf('QTN-%d-%04d', $year, $next);
        });
    }
}
