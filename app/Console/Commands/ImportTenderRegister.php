<?php

namespace App\Console\Commands;

use App\Enums\TenderStatus;
use App\Import\{RegisterFormatException, RegisterImporter, SampleData, TenderRegister};
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class ImportTenderRegister extends Command
{
    protected $signature = 'tenders:import-register
        {file : Path to the register CSV (kept outside the project)}
        {--commit : Save the import (without it, this only previews)}
        {--replace-samples : Remove the sample tenders, quotations and staff first}
        {--as=admin@cmt.test : Email of the account recorded as doing the import}';

    protected $description = 'Import the tender register spreadsheet (CSV) into the pipeline';

    public function handle(TenderRegister $register, RegisterImporter $importer, SampleData $samples): int
    {
        try {
            $read = $register->read((string) $this->argument('file'));
        } catch (RegisterFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $rows = collect($read['rows']);

        $this->info($rows->count().' tenders ready, '.count($read['skipped']).' rows skipped.');
        foreach (TenderStatus::cases() as $status) {
            $this->line('  '.$status->label().': '.$rows->where('status', $status)->count());
        }
        $this->line('  (of which cancelled: '.$rows->where('was_cancelled', true)->count().')');
        foreach ($read['skipped'] as $s) {
            $this->warn("  Skipped line {$s['line']} ({$s['wo']}): {$s['reason']}");
        }
        foreach ($read['warnings'] as $warning) {
            $this->warn('  '.$warning);
        }
        $accounts = $importer->accountsNeeded($read['rows']);
        $this->line('New switched-off accounts: '.($accounts ? implode(', ', $accounts) : 'none'));
        if ($this->option('replace-samples')) {
            foreach ($samples->preview() as $label => $count) {
                $this->line("Will remove {$label}: {$count}");
            }
        }

        if (! $this->option('commit')) {
            $this->info('Preview only — nothing saved. Run again with --commit to save.');

            return self::SUCCESS;
        }
        if ($rows->isEmpty()) {
            $this->error('There is nothing to import.');

            return self::FAILURE;
        }
        $actor = User::where('email', $this->option('as'))->first();
        if (! $actor) {
            $this->error("No account with the email {$this->option('as')} to record the import against.");

            return self::FAILURE;
        }

        try {
            $result = $importer->import($read['rows'], $actor, (bool) $this->option('replace-samples'));
        } catch (Throwable $e) {
            $this->error('Nothing was saved — '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Saved: {$result['created']} new, {$result['updated']} updated, ".count($result['accounts']).' accounts created.');
        foreach ($result['removed'] as $label => $count) {
            $this->line("Removed {$label}: {$count}");
        }

        return self::SUCCESS;
    }
}
