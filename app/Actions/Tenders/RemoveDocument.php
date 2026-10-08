<?php

namespace App\Actions\Tenders;

use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class RemoveDocument
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion, int $documentId): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion, $documentId) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::InProgress, 'change the checklist of');

            $doc = $t->documents()->findOrFail($documentId);
            $name = $doc->name;
            $doc->delete();

            $t->forceFill(['version' => $t->version + 1])->save();
            ActivityLog::record($t, $actor, 'document_removed', "Removed document: {$name}");

            return $t->fresh();
        });
    }
}
