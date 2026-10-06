<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;

final class SetUserActive
{
    public function handle(User $actor, User $target, bool $active): void
    {
        Gate::forUser($actor)->authorize('manage-users');
        if ($actor->is($target) && ! $active) {
            throw new DomainException('You cannot deactivate your own account — ask another admin.');
        }

        $target->update(['is_active' => $active]);
    }
}
