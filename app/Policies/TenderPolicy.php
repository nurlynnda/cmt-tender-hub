<?php

namespace App\Policies;

use App\Models\{Tender, User};

class TenderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tender $tender): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tender $tender): bool
    {
        return $user->role->canManageAllTenders() || $tender->pic_id === $user->id;
    }

    public function reopen(User $user, Tender $tender): bool
    {
        return $user->role->canManageAllTenders();
    }
}
