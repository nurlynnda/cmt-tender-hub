<?php

namespace App\Actions\Tenders;

use App\Enums\TenderStatus;
use App\Exceptions\FieldCodeNotHeld;
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use App\Support\{AssignmentNotifier, CompanyFieldCodes, MalaysiaTime};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Gate};

final class RegisterTender
{
    public const FIELDS = [
        'mode', 'type', 'category', 'tender_code', 'title', 'client', 'ministry', 'field_codes', 'scope',
        'pic_id', 'owner_id', 'publish_date', 'closing_date',
        'has_briefing', 'briefing_date', 'estimated_value_sen',
        'collected_tender_id',
    ];

    public function __construct(private GenerateWoNumber $woNumbers, private AssignmentNotifier $notifier) {}

    /** @param  bool  $overrideFieldCodes  register even though none of the tender's field codes is one the company holds */
    public function handle(User $actor, array $data, bool $overrideFieldCodes = false): Tender
    {
        Gate::forUser($actor)->authorize('create', Tender::class);

        $codes = $data['field_codes'] ?? [];
        $outsideCodes = CompanyFieldCodes::matching($codes) === [];
        if ($outsideCodes && ! $overrideFieldCodes) {
            throw new FieldCodeNotHeld(array_values($codes));
        }

        $tender = DB::transaction(function () use ($actor, $data, $outsideCodes, $codes) {
            $today = MalaysiaTime::today();

            $tender = new Tender(Arr::only($data, self::FIELDS));
            $tender->forceFill([
                'wo_number' => $this->woNumbers->next($today),
                'wo_date' => $today->toDateString(),
                'status' => TenderStatus::InProgress,
                'version' => 1,
            ])->save();

            foreach (TenderDocument::STANDARD as $i => $name) {
                $tender->documents()->create(['name' => $name, 'position' => $i + 1]);
            }

            ActivityLog::record($tender, $actor, 'registered', $outsideCodes
                ? 'Tender registered anyway — none of its field codes is one the company holds'.($codes ? ' ('.implode(', ', $codes).')' : ' (no field codes given)')
                : 'Tender registered');

            return $tender->fresh();
        });

        $this->notifier->notify($tender, $actor, null, null);

        return $tender;
    }
}
