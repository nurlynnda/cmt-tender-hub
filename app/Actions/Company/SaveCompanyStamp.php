<?php

namespace App\Actions\Company;

use App\Models\{CompanyProfile, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/** Stores a new stamp file. Old files are kept: quotations created earlier still point at them. */
final class SaveCompanyStamp
{
    public function handle(User $actor, UploadedFile $file): CompanyProfile
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $c = CompanyProfile::current();
        $c->update(['stamp_path' => $file->store('company-stamps', 'local')]);

        return $c;
    }
}
