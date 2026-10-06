<?php

namespace App\Support;

use App\Models\{Tender, User};
use App\Notifications\TenderAssigned;

final class AssignmentNotifier
{
    public function notify(Tender $tender, User $actor, ?int $previousPicId, ?int $previousOwnerId): void
    {
        $recipients = [];
        if ($tender->pic_id !== $previousPicId) {
            $recipients[$tender->pic_id] = 'PIC';
        }
        if ($tender->owner_id !== null && $tender->owner_id !== $previousOwnerId && ! isset($recipients[$tender->owner_id])) {
            $recipients[$tender->owner_id] = 'Opportunity Owner';
        }
        unset($recipients[$actor->id]);

        if ($recipients === []) {
            return;
        }

        User::whereIn('id', array_keys($recipients))->where('is_active', true)->get()
            ->each(fn (User $u) => $u->notify(new TenderAssigned($tender, $recipients[$u->id])));
    }
}
