<?php

namespace App\Actions\Tenders;

use App\Actions\Pd\CreateProjectFromCosting;
use App\Actions\Tenders\Concerns\GuardsTender;
use App\Enums\TenderStatus;
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Support\Facades\DB;

final class MarkTenderAwarded
{
    use GuardsTender;

    public function handle(User $actor, Tender $tender, int $expectedVersion): Tender
    {
        return DB::transaction(function () use ($actor, $tender, $expectedVersion) {
            $t = $this->lockForChange($actor, $tender, $expectedVersion);
            $this->requireStatus($t, TenderStatus::Done, 'mark as Awarded');

            $t->forceFill([
                'status' => TenderStatus::Awarded,
                'awarded_at' => now(),
                'version' => $t->version + 1,
            ])->save();

            ActivityLog::record($t, $actor, 'marked_awarded', 'Marked Awarded');
            app(CreateProjectFromCosting::class)->handle($t, $actor);

            return $t->fresh();
        });
    }
}
