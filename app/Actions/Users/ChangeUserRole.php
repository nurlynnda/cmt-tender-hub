<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;

final class ChangeUserRole
{
    public function handle(User $actor, User $target, Role $role): void
    {
        Gate::forUser($actor)->authorize('manage-users');
        if ($actor->is($target) && $role !== Role::Admin) {
            throw new DomainException('You cannot remove your own admin role — ask another admin.');
        }

        $target->update(['role' => $role]);
    }
}
