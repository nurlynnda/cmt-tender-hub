<?php

use App\Actions\Users\{ChangeUserRole, CreateUser, SetUserActive};
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

it('lets an admin create a user with a temporary password', function () {
    $user = app(CreateUser::class)->handle(User::factory()->admin()->create(), 'Nurul Ain', 'Nurul@CMT.test ', Role::Staff, 'temp-pass-123');

    expect($user->role)->toBe(Role::Staff)
        ->and($user->email)->toBe('nurul@cmt.test')
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('temp-pass-123', $user->password))->toBeTrue();
});

it('refuses non-admins', function () {
    app(CreateUser::class)->handle(User::factory()->manager()->create(), 'X', 'x@cmt.test', Role::Staff, 'temp-pass-123');
})->throws(AuthorizationException::class);

it('changes roles and active state of other users', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    app(ChangeUserRole::class)->handle($admin, $target, Role::Manager);
    app(SetUserActive::class)->handle($admin, $target, false);

    expect($target->fresh()->role)->toBe(Role::Manager)->and($target->fresh()->is_active)->toBeFalse();
});

it('stops an admin from demoting or deactivating themselves', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => app(ChangeUserRole::class)->handle($admin, $admin, Role::Staff))->toThrow(DomainException::class)
        ->and(fn () => app(SetUserActive::class)->handle($admin, $admin, false))->toThrow(DomainException::class);
    expect($admin->fresh()->role)->toBe(Role::Admin)->and($admin->fresh()->is_active)->toBeTrue();
});

it('refuses role and active changes by non-admins', function () {
    $manager = User::factory()->manager()->create();
    $target = User::factory()->create();

    expect(fn () => app(ChangeUserRole::class)->handle($manager, $target, Role::Admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetUserActive::class)->handle($manager, $target, false))->toThrow(AuthorizationException::class);
});
