<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\{Gate, Log};

/** Admins correct an account's name and email, and can give it a new temporary password. Tenders and history stay with the account. */
final class UpdateUserProfile
{
    public function handle(User $actor, User $target, string $name, string $email, ?string $password): User
    {
        Gate::forUser($actor)->authorize('manage-users');
        if ($password !== null && $actor->is($target)) {
            throw new DomainException('Change your own password in Settings.');
        }

        $target->forceFill(['name' => trim($name), 'email' => strtolower(trim($email))]);
        if ($password !== null) {
            $target->password = $password; // hashed by the model
        }
        $changed = array_keys($target->getDirty());
        $target->save();
        if ($changed !== []) {
            Log::info("Account #{$target->id} updated by {$actor->email}: ".implode(', ', $changed)); // never the values
        }

        return $target;
    }
}
