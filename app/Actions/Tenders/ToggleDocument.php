<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\{GuardsTender, SyncsSubtasks};
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, TenderDocument, User};
use Illuminate\Support\Facades\DB;

final class ToggleDocument
{
    use GuardsTender, SyncsSubtasks;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $documentId) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $doc = $t->documents()->findOrFail($documentId);
            $nowDone = ! $doc->is_done;
            $stamp = [
                'is_done' => $nowDone,
                'done_by' => $nowDone ? $actor->id : null,
                'done_at' => $nowDone ? now() : null,
            ];
            $doc->forceFill($stamp)->save();

            if ($doc->parent_id === null) {
                // Ticking (or unticking) a task covers all of its subtasks.
                TenderDocument::where('parent_id', $doc->id)->update($stamp);
            } else {
                $this->syncParent($doc->parent, $actor);
            }

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
