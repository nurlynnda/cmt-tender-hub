<?php

namespace Database\Seeders;

use App\Enums\{Role, TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Development sample data taken from the Claude Design prototype. Never for production. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample data must never be loaded in production.');
        }

        $password = config('tenderhub.seed_password');
        $people = collect([
            ['Ahmad Faizal', 'ahmad.faizal@cmt.test', Role::Staff],
            ['Nurul Ain', 'nurul.ain@cmt.test', Role::Staff],
            ['Siti Aisyah', 'siti.aisyah@cmt.test', Role::Staff],
            ['Muhammad Hafiz', 'muhammad.hafiz@cmt.test', Role::Staff],
            ['Sales Manager', 'manager@cmt.test', Role::Manager],
            ['System Admin', 'admin@cmt.test', Role::Admin],
        ])->mapWithKeys(fn ($p) => [$p[0] => User::create([
            'name' => $p[0], 'email' => $p[1], 'role' => $p[2], 'password' => $password, 'is_active' => true,
        ])]);

        $rows = json_decode(file_get_contents(database_path('seeders/data/prototype-tenders.json')), true);
        $maxSeq = [];

        foreach ($rows as $row) {
            $status = TenderStatus::from($row['status']);
            $pic = $people[$row['pic']];
            $owner = $people[$row['owner']] ?? null;

            $tender = Tender::create([
                'wo_number' => $row['wo_number'],
                'wo_date' => $row['wo_date'],
                'mode' => TenderMode::from($row['mode']),
                'type' => TenderType::from($row['type']),
                'category' => TenderCategory::tryFrom($row['category']) ?? TenderCategory::Default,
                'tender_code' => $row['tender_code'],
                'title' => $row['title'],
                'client' => $row['client'],
                'scope' => $row['scope'],
                'pic_id' => $pic->id,
                'owner_id' => $owner?->id,
                'publish_date' => $row['publish_date'],
                'closing_date' => $row['closing_date'],
                'has_briefing' => $row['has_briefing'],
                'briefing_date' => $row['briefing_date'],
                'estimated_value_sen' => $row['estimated_value_sen'],
                'status' => $status,
                'submitted_price_sen' => $row['submitted_price_sen'],
                'winning_price_sen' => $row['winning_price_sen'],
                'done_at' => $status !== TenderStatus::InProgress ? now() : null,
                'awarded_at' => $status === TenderStatus::Awarded ? now() : null,
                'lost_at' => $status === TenderStatus::Lost ? now() : null,
                'version' => 1,
            ]);

            // The prototype stores a percentage; round it to a whole number of the 5 standard documents.
            $ticked = $status === TenderStatus::InProgress ? (int) round($row['doc_percent'] / 20) : 5;
            foreach (TenderDocument::STANDARD as $i => $name) {
                $tender->documents()->create([
                    'name' => $name,
                    'position' => $i + 1,
                    'is_done' => $i < $ticked,
                    'done_by' => $i < $ticked ? $pic->id : null,
                    'done_at' => $i < $ticked ? now() : null,
                ]);
            }

            ActivityLog::record($tender, $owner ?? $pic, 'registered', 'Tender registered');
            if ($status !== TenderStatus::InProgress) {
                ActivityLog::record($tender, $pic, 'marked_done', 'Marked Done — submitted price '.Money::format($row['submitted_price_sen']));
            }
            if ($status === TenderStatus::Awarded) {
                ActivityLog::record($tender, $pic, 'marked_awarded', 'Marked Awarded');
            }
            if ($status === TenderStatus::Lost) {
                ActivityLog::record($tender, $pic, 'marked_lost', 'Marked Lost — winning price '.Money::format($row['winning_price_sen']));
            }

            // 200-DDMMYYYY-NNN → remember the highest NNN per day
            [, $dmy, $seq] = explode('-', $row['wo_number']);
            $date = substr($dmy, 4, 4).'-'.substr($dmy, 2, 2).'-'.substr($dmy, 0, 2);
            $maxSeq[$date] = max($maxSeq[$date] ?? 0, (int) $seq);
        }

        foreach ($maxSeq as $date => $seq) {
            DB::table('wo_sequences')->updateOrInsert(['date' => $date], ['last_seq' => $seq]);
        }
    }
}
