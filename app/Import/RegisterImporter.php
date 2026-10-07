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

    public function __construct(private SampleData $samples) {}

    /** Names of people the rows need that have no account yet, as they will be created (first-seen order). */
    public function accountsNeeded(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $name = $row['pic_name'] ?? self::UNASSIGNED;
            $names[mb_strtolower($name)] ??= self::displayName($name);
        }
        $existing = User::query()->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip()->all();

        return array_values(array_diff_key($names, $existing));
    }

    public function import(array $rows, User $actor, bool $replaceSamples = false): array
    {
        return DB::transaction(function () use ($rows, $actor, $replaceSamples) {
            $removed = $replaceSamples ? $this->samples->remove() : [];
            $accounts = $this->accountsNeeded($rows);
            $people = [];
            $created = $updated = 0;

            foreach ($rows as $row) {
                $pic = $this->person($row['pic_name'] ?? self::UNASSIGNED, $people);
                $tender = Tender::query()->where('wo_number', $row['wo_number'])->lockForUpdate()->first();
                $isNew = $tender === null;
                // Category isn't in the register: new tenders start as General; an existing tender keeps what people chose.
                $tender ??= (new Tender)->forceFill(['wo_number' => $row['wo_number'], 'category' => TenderCategory::General, 'version' => 0]);

                $status = $row['status'];
                $closing = $row['closing_date'].' 00:00:00';
                $tender->forceFill([
                    'wo_date' => $row['wo_date'], 'mode' => $row['mode'], 'type' => $row['type'],
                    'tender_code' => $row['tender_code'], 'title' => $row['title'],
                    'ministry' => $row['ministry'], 'client' => $row['client'],
                    'pic_id' => $pic->id, 'owner_id' => $pic->id,
                    'publish_date' => $row['publish_date'], 'closing_date' => $row['closing_date'],
                    'has_briefing' => $row['briefing_date'] !== null, 'briefing_date' => $row['briefing_date'],
                    'estimated_value_sen' => $row['estimated_value_sen'],
                    'submitted_price_sen' => $row['submitted_price_sen'],
                    'winning_price_sen' => $row['winning_price_sen'],
                    'status' => $status,
                    'was_cancelled' => $row['was_cancelled'],
                    'done_at' => in_array($status, [TenderStatus::Done, TenderStatus::Awarded], true) ? $closing : null,
                    'awarded_at' => $status === TenderStatus::Awarded ? $closing : null,
                    'lost_at' => $status === TenderStatus::Lost ? $closing : null,
                    'dropped_at' => $status === TenderStatus::Dropped ? $closing : null,
                    'version' => $tender->version + 1,
                ])->save();

                $this->costing($tender, $row);
                if ($isNew && $status === TenderStatus::InProgress) {
                    foreach (TenderDocument::STANDARD as $i => $name) {
                        $tender->documents()->create(['name' => $name, 'position' => $i + 1]);
                    }
                }
                ActivityLog::record($tender, $actor, $isNew ? 'imported' : 'import_updated',
                    $isNew ? 'Imported from the 2026 register' : 'Updated from the register');
                $isNew ? $created++ : $updated++;
            }

            if ($replaceSamples) {
                $removed += $this->samples->removeStaff();
            }

            return ['created' => $created, 'updated' => $updated, 'accounts' => $accounts, 'removed' => $removed];
        });
    }

    /** One costing line holding the register's submitted cost, priced at the submitted price, so Gross matches the sheet. */
    private function costing(Tender $tender, array $row): void
    {
        $tender->costingLines()->where('description', self::IMPORTED_COST)->delete();
        $cost = $row['submitted_cost_sen'];
        $price = $row['submitted_price_sen'];
        if ($cost === null || $price === null) {
            return;
        }
        $tender->costingLines()->create([
            'position' => (int) $tender->costingLines()->max('position') + 1,
            'description' => self::IMPORTED_COST, 'unit' => 'lot', 'quantity' => 1,
            'frequency' => 'one_off', 'months' => 1, 'project_year' => 1,
            'unit_cost_sen' => $cost, 'margin_bp' => 0,
        ]);
        $tender->forceFill(['bid_price_override_sen' => $price])->save();
    }

    private function person(string $name, array &$people): User
    {
        $key = mb_strtolower($name);

        return $people[$key] ??= User::query()->whereRaw('LOWER(name) = ?', [$key])->first()
            ?? User::query()->forceCreate([
                'name' => self::displayName($name),
                'email' => Str::slug($name).'@import.invalid',
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
