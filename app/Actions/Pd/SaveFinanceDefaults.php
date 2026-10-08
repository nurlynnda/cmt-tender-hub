<?php

namespace App\Actions\Pd;

use App\Models\{FinanceSetting, User};
use Illuminate\Support\Facades\Gate;

final class SaveFinanceDefaults
{
    public function handle(User $actor, int $chargeBp, int $shareBp): FinanceSetting
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $s = FinanceSetting::current();
        $s->update(['project_charge_bp' => $chargeBp, 'commission_share_bp' => $shareBp]);

        return $s;
    }
}
