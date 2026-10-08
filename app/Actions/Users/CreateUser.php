<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateUser
{
    public function handle(User $actor, string $name, string $email, Role $role, string $password): User
    {
        Gate::forUser($actor)->authorize('manage-users');

        return User::create([
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'role' => $role,
            'password' => $password,
            'is_active' => true,
        ]);
    }
}
