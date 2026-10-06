<?php

namespace App\Actions\Tenders;

use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use App\Support\{AssignmentNotifier, MalaysiaTime};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Gate};

final class RegisterTender
{
    public const FIELDS = [
        'mode', 'type', 'category', 'tender_code', 'title', 'client', 'scope',
        'pic_id', 'owner_id', 'publish_date', 'closing_date',
        'has_briefing', 'briefing_date', 'estimated_value_sen',
        'collected_tender_id',
    ];

    public function __construct(private GenerateWoNumber $woNumbers, private AssignmentNotifier $notifier) {}

    public function handle(User $actor, array $data): Tender
    {
        Gate::forUser($actor)->authorize('create', Tender::class);

        $tender = DB::transaction(function () use ($actor, $data) {
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

            ActivityLog::record($tender, $actor, 'registered', 'Tender registered');

            return $tender->fresh();
        });

        $this->notifier->notify($tender, $actor, null, null);

        return $tender;
    }
}
