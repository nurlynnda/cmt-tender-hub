<?php

namespace App\Console\Commands;

use App\Collector\Legacy\{LegacyTenderMapper, LegacyTenderSource, MongoLegacyTenderSource};
use App\Models\CollectedTender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportLegacyTenders extends Command
{
    protected $signature = 'collector:import-legacy {--mongo-uri=mongodb://mongo:27017} {--database=tms} {--batch=1000}';

    protected $description = 'One-time copy of tms-v2\'s collected tenders from MongoDB (safe to re-run)';

    public function handle(): int
    {
        $source = app()->bound(LegacyTenderSource::class)
            ? app(LegacyTenderSource::class)
            : new MongoLegacyTenderSource($this->option('mongo-uri'), $this->option('database'));
        $batchSize = max(1, (int) $this->option('batch'));

        $bar = $this->output->createProgressBar($source->count());
        $batch = [];
        $imported = 0;
        $skipped = 0;
        foreach ($source->documents() as $doc) {
            try {
                $batch[] = LegacyTenderMapper::map($doc);
            } catch (InvalidArgumentException $e) {
                $skipped++;
                $this->warn($e->getMessage());
            }
            $bar->advance();
            if (count($batch) >= $batchSize) {
                $imported += $this->write($batch);
                $batch = [];
            }
        }
        $imported += $this->write($batch);
        $bar->finish();
        $this->newLine();

        $this->info("Imported {$imported} tenders. Skipped {$skipped}.");
        foreach (DB::table('collected_tender_sources')->selectRaw('source, count(*) as n')->groupBy('source')->orderBy('source')->get() as $row) {
            $this->line("  {$row->source}: {$row->n}");
        }

        return self::SUCCESS;
    }

    private function write(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }
        DB::transaction(function () use ($batch) {
            $rows = array_column($batch, 'tender');
            DB::table('collected_tenders')->upsert($rows, ['dedup_key'], array_diff(array_keys($rows[0]), ['dedup_key', 'created_at']));
            $ids = CollectedTender::whereIn('dedup_key', array_column($rows, 'dedup_key'))->pluck('id', 'dedup_key');

            DB::table('collected_tender_sources')->whereIn('collected_tender_id', $ids->values())->delete();
            DB::table('collected_tender_field_codes')->whereIn('collected_tender_id', $ids->values())->delete();
            $sources = [];
            $codes = [];
            foreach ($batch as $item) {
                $id = $ids[$item['tender']['dedup_key']];
                foreach ($item['sources'] as $s) {
                    $sources["{$id}|{$s['source']}"] = ['collected_tender_id' => $id] + $s; // one row per tender+source
                }
                foreach ($item['codes'] as $code) {
                    $codes["{$id}|{$code}"] = ['collected_tender_id' => $id, 'code' => $code];
                }
            }
            foreach (array_chunk(array_values($sources), 1000) as $chunk) {
                DB::table('collected_tender_sources')->insert($chunk);
            }
            foreach (array_chunk(array_values($codes), 1000) as $chunk) {
                DB::table('collected_tender_field_codes')->insert($chunk);
            }
        });

        return count($batch);
    }
}
