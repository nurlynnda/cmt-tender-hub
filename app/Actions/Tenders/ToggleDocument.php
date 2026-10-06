<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class ToggleDocument
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $documentId) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $doc = $t->documents()->findOrFail($documentId);
            $nowDone = ! $doc->is_done;
            $doc->forceFill([
                'is_done' => $nowDone,
                'done_by' => $nowDone ? $actor->id : null,
                'done_at' => $nowDone ? now() : null,
            ])->save();

            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record(
                $t, $actor,
                $nowDone ? 'document_ticked' : 'document_unticked',
                ($nowDone ? 'Ticked: ' : 'Unticked: ').$doc->name,
            );

            return $t->fresh();
        });
    }
}
