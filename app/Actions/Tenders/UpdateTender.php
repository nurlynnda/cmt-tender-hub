<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use App\Support\AssignmentNotifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateTender
{
    use GuardsTender;

    private const LABELS = [
        'mode' => 'mode', 'type' => 'type', 'category' => 'category',
        'tender_code' => 'tender code', 'title' => 'title', 'client' => 'client', 'ministry' => 'ministry', 'scope' => 'scope',
        'publish_date' => 'publish date', 'closing_date' => 'closing date',
        'has_briefing' => 'briefing', 'briefing_date' => 'briefing date',
        'estimated_value_sen' => 'estimated value',
    ];

    public function __construct(private AssignmentNotifier $notifier) {}

    public function handle(User $actor, Tender $tender, int $expectedVersion, array $data): Tender
    {
        [$fresh, $oldPicId, $oldOwnerId, $changed] = DB::transaction(function () use ($actor, $tender, $expectedVersion, $data) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'edit');

            $oldPicId = $t->pic_id;
            $oldOwnerId = $t->owner_id;
            $oldPicName = $t->pic->name;
            $oldOwnerName = $t->owner?->name ?? 'nobody';

            $t->fill(Arr::only($data, RegisterTender::FIELDS));
            if (! $t->isDirty()) {
                return [$t, null, null, []];
            }

            $changed = array_keys($t->getDirty());
            if ($t->isDirty('closing_date')) {
                $t->closing_soon_notified_at = null;
            }
            if ($t->isDirty(['briefing_date', 'has_briefing'])) {
                $t->briefing_notified_at = null;
            }
            $t->version = $t->version + 1;
            $t->save();
            $t->load('pic', 'owner');

            if (in_array('pic_id', $changed, true)) {
                ActivityLog::record($t, $actor, 'pic_changed', "PIC changed from {$oldPicName} to {$t->pic->name}");
            }
            if (in_array('owner_id', $changed, true)) {
                $newOwner = $t->owner?->name ?? 'nobody';
                ActivityLog::record($t, $actor, 'owner_changed', "Opportunity Owner changed from {$oldOwnerName} to {$newOwner}");
            }

            $other = array_values(array_intersect(array_keys(self::LABELS), $changed));
            if ($other !== []) {
                $labels = array_map(fn (string $f) => self::LABELS[$f], $other);
                ActivityLog::record($t, $actor, 'updated', 'Details updated: '.implode(', ', $labels));
            }

            return [$t->fresh(), $oldPicId, $oldOwnerId, $changed];
        });

        if (array_intersect(['pic_id', 'owner_id'], $changed) !== []) {
            $this->notifier->notify($fresh, $actor, $oldPicId, $oldOwnerId);
        }

        return $fresh;
    }
}
