<?php

namespace App\Actions\Company;

use App\Models\{CompanyProfile, User};
use Illuminate\Support\Facades\Gate;

final class RemoveCompanyStamp
{
    public function handle(User $actor): CompanyProfile
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $c = CompanyProfile::current();
        $c->update(['stamp_path' => null]); // the file stays for quotations that already use it

        return $c;
    }
}
