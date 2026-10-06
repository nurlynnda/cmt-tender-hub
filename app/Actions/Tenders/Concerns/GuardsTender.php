<?php

namespace App\Actions\Tenders\Concerns;

use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{Tender, User};
use Illuminate\Support\Facades\Gate;

trait GuardsTender
{
    /** Call inside DB::transaction. Locks the row, checks permission, then checks nobody saved in between. */
    private function lockForChange(User $actor, Tender $tender, int $expectedVersion, string $ability = 'update'): Tender
    {
        $fresh = Tender::query()->lockForUpdate()->findOrFail($tender->id);

        Gate::forUser($actor)->authorize($ability, $fresh);

        if ($fresh->version !== $expectedVersion) {
            throw StaleTenderException::for($fresh);
        }

        return $fresh;
    }

    private function requireStatus(Tender $tender, TenderStatus $status, string $action): void
    {
        if ($tender->status !== $status) {
            throw InvalidTenderTransition::make($tender->status, $action);
        }
    }
}
