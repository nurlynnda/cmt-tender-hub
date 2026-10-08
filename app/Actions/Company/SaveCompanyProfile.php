<?php

namespace App\Actions\Company;

use App\Models\{CompanyProfile, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

final class SaveCompanyProfile
{
    public const FIELDS = ['name', 'registration_no', 'sst_no', 'address', 'phone', 'email', 'website', 'default_terms', 'default_sst_bp'];

    public function handle(User $actor, array $data): CompanyProfile
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $c = CompanyProfile::current();
        $c->update(Arr::only($data, self::FIELDS));

        return $c;
    }
}
