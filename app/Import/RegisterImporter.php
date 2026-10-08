<?php

namespace App\Import;

use App\Enums\{Role, TenderCategory, TenderStatus};
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

/** Writes register rows into the pipeline, all in one transaction. */
final class RegisterImporter
{
    public const IMPORTED_COST = 'Imported cost (2026 register)';
    public const UNASSIGNED = 'Unassigned';
    public const IMPORT_EVENTS = ['imported', 'import_updated'];
    /** History entries that mean someone moved the tender on in the app. */
    private const STATUS_EVENTS = ['marked_done', 'marked_awarded', 'marked_lost', 'cancelled', 'reopened', 'dropped'];

    public function __construct(private SampleData $samples) {}

    /** Names of people the rows need that have no account yet, as they will be created (first-seen order). */
    public function accountsNeeded(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $name = $row['pic_name'] ?? self::UNASSIGNED;
            $names[mb_strtolower($name)] ??= self::displayName($name);
        }
        $existing = User::query()->get(['name', 'register_name'])
            ->flatMap(fn (User $u) => array_filter([mb_strtolower($u->name), mb_strtolower((string) $u->register_name)]))
            ->flip()->all();

        return array_values(array_diff_key($names, $existing));
    }

    /** WO numbers whose status was changed in the app after the last import and differs from the sheet: the app's status is kept. */
    public function keptInApp(array $rows): array
    {
        $sheet = collect($rows)->keyBy('wo_number');
        $kept = [];
        foreach (Tender::query()->whereIn('wo_number', $sheet->keys())->get(['id', 'wo_number', 'status', 'was_cancelled']) as $t) {
            $row = $sheet[$t->wo_number];
            $differs = $t->status !== $row['status'] || $t->was_cancelled !== $row['was_cancelled'];
            if ($differs && $this->changedInAppSinceImport($t, self::STATUS_EVENTS)) {
                $kept[] = $t->wo_number;
            }
        }

        return $kept;
    }

    public function import(array $rows, User $actor, bool $replaceSamples = false): array
    {
        return DB::transaction(function () use ($rows, $actor, $replaceSamples) {
            $removed = $replaceSamples ? $this->samples->remove() : [];
            $accounts = $this->accountsNeeded($rows);
            $kept = array_flip($this->keptInApp($rows));
            $people = [];
            $created = $updated = 0;

            foreach ($rows as $row) {
                $pic = $this->person($row['pic_name'] ?? self::UNASSIGNED, $people);
                $tender = Tender::query()->where('wo_number', $row['wo_number'])->lockForUpdate()->first();
                $isNew = $tender === null;
                // Category isn't in the register: new tenders start as General; an existing tender keeps what people chose.
                $tender ??= (new Tender)->forceFill(['wo_number' => $row['wo_number'], 'category' => TenderCategory::General, 'version' => 0]);

                $tender->forceFill([
                    'wo_date' => $row['wo_date'], 'mode' => $row['mode'], 'type' => $row['type'],
                    'tender_code' => $row['tender_code'], 'title' => $row['title'],
                    'ministry' => $row['ministry'], 'client' => $row['client'],
                    'publish_date' => $row['publish_date'], 'closing_date' => $row['closing_date'],
                    'has_briefing' => $row['briefing_date'] !== null, 'briefing_date' => $row['briefing_date'],
                    'estimated_value_sen' => $row['estimated_value_sen'],
                    'version' => $tender->version + 1,
                ]);
                // People: the sheet's PIC, unless someone reassigned the tender in the app since the last import.
                if ($isNew || ! $this->changedInAppSinceImport($tender, ['pic_changed'])) {
                    $tender->pic_id = $pic->id;
                }
                if ($isNew) {
                    $tender->owner_id = $pic->id;
                }
                // Status and prices: the sheet's, unless the tender was moved on in the app since the last import.
                if (! isset($kept[$row['wo_number']])) {
                    $this->applyStatus($tender, $row);
                }
                $tender->save();

                $this->costing($tender, $row);
                if ($isNew && $row['status'] === TenderStatus::InProgress) {
                    foreach (TenderDocument::STANDARD as $i => $name) {
                        $tender->documents()->create(['name' => $name, 'position' => $i + 1]);
                    }
                }
                ActivityLog::record($tender, $actor, $isNew ? 'imported' : 'import_updated',
                    $isNew ? 'Imported from the 2026 register' : 'Updated from the register');
                $isNew ? $created++ : $updated++;
            }

            $this->advanceWoCounters(array_column($rows, 'wo_number'));
            if ($replaceSamples) {
                $removed += $this->samples->removeStaff();
            }

            return ['created' => $created, 'updated' => $updated, 'accounts' => $accounts, 'kept' => array_keys($kept), 'removed' => $removed];
        });
    }

    private function applyStatus(Tender $tender, array $row): void
    {
        $status = $row['status'];
        $closing = $row['closing_date'].' 00:00:00';
        $tender->forceFill([
            'status' => $status,
            'was_cancelled' => $row['was_cancelled'],
            'submitted_price_sen' => $row['submitted_price_sen'],
            'winning_price_sen' => $row['winning_price_sen'],
            'done_at' => in_array($status, [TenderStatus::Done, TenderStatus::Awarded], true) ? $closing : null,
            'awarded_at' => $status === TenderStatus::Awarded ? $closing : null,
            'lost_at' => $status === TenderStatus::Lost ? $closing : null,
            'dropped_at' => $status === TenderStatus::Dropped ? $closing : null,
        ]);
        // Reasons belong to the status they explain.
        if ($status !== TenderStatus::Lost) {
            $tender->lost_reason = null;
        }
        if ($status !== TenderStatus::Dropped) {
            $tender->drop_reason = null;
        }
    }

    /** True when one of $events was recorded for this tender after its latest import entry. */
    private function changedInAppSinceImport(Tender $tender, array $events): bool
    {
        $lastImport = (int) ActivityLog::where('tender_id', $tender->id)->whereIn('event', self::IMPORT_EVENTS)->max('id');
        $lastChange = (int) ActivityLog::where('tender_id', $tender->id)->whereIn('event', $events)->max('id');

        return $lastChange > $lastImport;
    }

    /** Move each day's WO counter past the register's numbers, so "Register tender" never repeats one. */
    private function advanceWoCounters(array $woNumbers): void
    {
        $max = [];
        foreach ($woNumbers as $wo) {
            if (preg_match('/^200-(\d{2})(\d{2})(\d{4})-(\d{3})$/', $wo, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                $date = "{$m[3]}-{$m[2]}-{$m[1]}";
                $max[$date] = max($max[$date] ?? 0, (int) $m[4]);
            }
        }
        foreach ($max as $date => $seq) {
            DB::table('wo_sequences')->insertOrIgnore(['date' => $date, 'last_seq' => 0]);
            DB::table('wo_sequences')->where('date', $date)->where('last_seq', '<', $seq)->update(['last_seq' => $seq]);
        }
    }

    /** One costing line holding the register's submitted cost, priced at the submitted price, so Gross matches the sheet. */
    private function costing(Tender $tender, array $row): void
    {
        $hadImported = $tender->costingLines()->where('description', self::IMPORTED_COST)->delete() > 0;
        $cost = $row['submitted_cost_sen'];
        $price = $row['submitted_price_sen'];
        if ($cost === null || $price === null) {
            // The sheet no longer has both: drop the bid price we set, unless people have their own costing lines.
            if ($hadImported && ! $tender->costingLines()->exists()) {
                $tender->forceFill(['bid_price_override_sen' => null])->save();
            }

            return;
        }
        $tender->costingLines()->create([
            'position' => (int) $tender->costingLines()->max('position') + 1,
            'description' => self::IMPORTED_COST, 'unit' => 'lot', 'quantity' => 1,
            'frequency' => 1,
            'unit_cost_sen' => $cost, 'margin_bp' => 0,
        ]);
        $tender->forceFill(['bid_price_override_sen' => $price])->save();
    }

    /** The account for a register PIC name: found by the name it was imported under, then by its current name; else created switched off. */
    private function person(string $name, array &$people): User
    {
        $key = mb_strtolower($name);
        if (isset($people[$key])) {
            return $people[$key];
        }
        $user = User::query()->whereRaw('LOWER(register_name) = ?', [$key])->first()
            ?? User::query()->whereRaw('LOWER(name) = ?', [$key])->first();
        if ($user) {
            if ($user->register_name === null) {
                $user->forceFill(['register_name' => self::displayName($name)])->save();
            }

            return $people[$key] = $user;
        }

        $base = Str::slug($name) ?: 'person';
        $email = "{$base}@import.invalid";
        for ($n = 2; User::where('email', $email)->exists(); $n++) {
            $email = "{$base}-{$n}@import.invalid";   // two names with the same email form, e.g. "Siti A." and "Siti A"
        }

        return $people[$key] = User::query()->forceCreate([
            'name' => self::displayName($name),
            'register_name' => self::displayName($name),
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role' => Role::Staff,
            'is_active' => false,
        ]);
    }

    /** "aminah" → "Aminah"; names already written with capitals are kept as written. */
    private static function displayName(string $name): string
    {
        return $name === mb_strtolower($name) ? mb_convert_case($name, MB_CASE_TITLE) : $name;
    }
}
