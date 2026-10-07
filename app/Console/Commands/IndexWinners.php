<?php

namespace App\Console\Commands;

use App\Collector\WinnerIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IndexWinners extends Command
{
    protected $signature = 'collector:index-winners';

    protected $description = 'Rebuild the searchable winners list from every collected tender (safe to re-run)';

    public function handle(): int
    {
        DB::table('collected_tender_winners')->delete();
        $winners = $tenders = 0;
        DB::table('collected_tenders')->whereNotNull('winners')->select('id', 'winners')->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$winners, &$tenders) {
                $rows = [];
                foreach ($chunk as $t) {
                    // A winners value that isn't a list (bad legacy data) gives no rows and is skipped.
                    $found = WinnerIndex::rows($t->id, json_decode((string) $t->winners, true));
                    if ($found !== []) {
                        $tenders++;
                        array_push($rows, ...$found);
                    }
                }
                foreach (array_chunk($rows, 1000) as $part) {
                    DB::table('collected_tender_winners')->insert($part);
                }
                $winners += count($rows);
            });
        $this->info("Indexed {$winners} winners from {$tenders} tenders.");

        return self::SUCCESS;
    }
}
