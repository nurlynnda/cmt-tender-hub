<?php

namespace App\Policies;

use App\Models\{Quotation, User};

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return true;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->role->canManageAllTenders() || $quotation->prepared_by === $user->id;
    }

    public function backToDraft(User $user, Quotation $quotation): bool
    {
        return $user->role->canManageAllTenders();
    }
}
