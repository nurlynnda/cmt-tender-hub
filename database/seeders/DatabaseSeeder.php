<?php

namespace Database\Seeders;

use App\Enums\{Role, TenderCategory, TenderMode, TenderStatus, TenderType};
use App\Actions\Pd\CreateProjectFromCosting;
use App\Models\{ActivityLog, CompanyProfile, ProjectType, Quotation, Tender, TenderDocument, User};
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

        self::seedJpninCosting(Tender::where('wo_number', '200-10092026-001')->firstOrFail());
        self::seedSamplePd();
        self::seedSampleQuotations();
    }

    /** The prototype's three quotations (the letterhead, terms and SST come with the migration). */
    private static function seedSampleQuotations(): void
    {
        $company = CompanyProfile::current();
        $make = function (string $number, string $date, string $email, string $customer, string $subject, string $status, int $sstBp, array $items, array $extra = []) use ($company) {
            $u = User::where('email', $email)->firstOrFail();
            $sent = $status !== 'draft';
            $q = Quotation::create(array_merge([
                'number' => $number, 'status' => $status, 'quote_date' => $date, 'validity_days' => 30,
                'customer_name' => $customer, 'subject' => $subject, 'prepared_by' => $u->id,
                'preparer_position' => 'Sales Executive', 'preparer_email' => $email, 'sst_bp' => $sstBp,
                'terms' => $company->default_terms, 'letterhead' => $company->letterhead(), 'updated_by' => $u->id, 'version' => 1,
                'sent_at' => $sent ? $date.' 10:00:00' : null, 'sent_by' => $sent ? $u->id : null,
            ], $extra));
            foreach ($items as $i => [$title, $details, $qty, $unit, $priceSen]) {
                $q->items()->create(['position' => $i + 1, 'title' => $title, 'details' => $details, 'quantity' => $qty, 'unit' => $unit, 'unit_price_sen' => $priceSen]);
            }
            ActivityLog::record($q, $u, 'quotation_created', "Quotation {$number} created");
        };

        $make('QTN-2026-0010', '2026-08-04', 'ahmad.faizal@cmt.test', 'Pejabat Daerah Kuantan', 'Laptop rental for 18 months', 'sent', 500,
            [['Laptop rental (18 months)', null, 1, 'Lot', 3000000]]);
        $make('QTN-2026-0011', '2026-09-10', 'muhammad.hafiz@cmt.test', 'Majlis Perbandaran Klang', 'Annual maintenance for CCTV system (12 months)', 'accepted', 800,
            [['CCTV preventive maintenance (12 months)', null, 1, 'Lot', 1900000]], ['accepted_at' => '2026-09-20 10:00:00']);
        $make('QTN-2026-0012', '2026-09-18', 'siti.aisyah@cmt.test', 'Jabatan Perpaduan Negara dan Integrasi Nasional',
            'Supply of network switches and installation for JPNIN HQ', 'sent', 800, [
                ['24-port Gigabit PoE+ managed switch', implode("\n", [
                    'Interface: 24× 10/100/1000 Mbps RJ45 PoE+ Ports; 4× Gigabit SFP Slots; 1× RJ45 Console Port; 1× Micro-USB Console Port',
                    'Power Supply: 100–240 V AC, 50/60 Hz, Internal Power Supply',
                    'Dimensions (W x D x H): 17.3 x 13.0 x 1.7 in (440 x 330 x 44 mm)',
                    'Mounting: 19-inch Rack Mountable (1U)',
                    'Switching Capacity: 56 Gbps',
                ]), 6, 'Unit', 485000],
                ['Installation, configuration & testing', null, 1, 'Lot', 650000],
            ], ['attention' => 'Puan Rozita binti Hassan, Ketua Unit ICT', 'customer_address' => "Aras 5, Blok F8, Kompleks F,\nPresint 1, 62000 Putrajaya"]);

        // New quotations continue after the prototype's numbers.
        DB::table('quotation_sequences')->updateOrInsert(['year' => 2026], ['last_seq' => 12]);
    }

    /** Every awarded sample tender gets a project; 200-15122025-006 gets the prototype's PD. */
    private static function seedSamplePd(): void
    {
        $pic = User::where('email', 'ahmad.faizal@cmt.test')->firstOrFail();
        foreach (Tender::where('status', TenderStatus::Awarded)->get() as $t) {
            app(CreateProjectFromCosting::class)->handle($t, $pic);
        }

        $project = Tender::where('wo_number', '200-15122025-006')->firstOrFail()->project;
        $project->lines()->delete(); // replaced by the fuller sample below (none have entries yet)
        $project->update([
            'project_type_id' => ProjectType::where('name', 'Managed Services')->value('id'),
            'approved_margin_bp' => 1500, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30',
        ]);

        $position = 0;
        $add = function (string $group, string $name, int $budget, array $entries = [], ?string $scheduled = null, ?string $ref = null) use ($project, $pic, &$position) {
            $line = $project->lines()->create([
                'position' => ++$position, 'pd_group' => $group, 'name' => $name, 'reference' => $ref,
                'budget_sen' => $budget, 'scheduled_date' => $scheduled, 'updated_by' => $pic->id,
            ]);
            foreach ($entries as [$type, $number, $date, $amount]) {
                $line->entries()->create(['type' => $type, 'number' => $number, 'date' => $date, 'amount_sen' => $amount, 'created_by' => $pic->id]);
            }
        };
        $add('collection', 'Payment 1 (Down Payment)', 37500000,
            [['invoice', 'INV-001', '2026-01-20', 37500000], ['receipt', 'RCV-001', '2026-01-28', 37500000]], '2026-01-20');
        $add('collection', 'Payment 2 (Progress)', 37500000, [['invoice', 'INV-002', '2026-04-15', 37500000]], '2026-04-15');
        $add('collection', 'Payment 3 (Final)', 50000000, [], '2026-07-15');
        $add('principal', 'Workstations and laptops', 45000000, [
            ['pr', 'PR-101', '2026-01-05', 45000000], ['po', 'PO-101', '2026-01-08', 45000000],
            ['invoice', 'SI-5531', '2026-02-10', 45000000], ['payment', 'PV-201', '2026-02-25', 26300000],
        ], null, 'Dell');
        $add('distributor', 'Software licences', 20100000, [['pr', 'PR-102', '2026-01-05', 20100000], ['po', 'PO-102', '2026-01-09', 20100000]]);
        $add('internal', 'Project engineer (6 manmonths)', 9000000);
        $add('tax', 'SST on costs', 4200000);
    }

    /** The prototype's JPNIN costing: 12 one-off lines at 20% (bid RM 166,059.00). */
    public static function seedJpninCosting(Tender $tender): void
    {
        $lines = json_decode(file_get_contents(database_path('seeders/data/jpnin-costing.json')), true);
        foreach ($lines as $i => $line) {
            $tender->costingLines()->create([
                'position' => $i + 1, 'description' => $line['description'], 'unit' => 'Unit', 'quantity' => 1,
                'frequency' => 1, 'unit_cost_sen' => $line['unit_cost_sen'], 'margin_bp' => 2000,
            ]);
        }
    }
}
