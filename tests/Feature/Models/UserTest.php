<?php

use App\Enums\Role;
use App\Models\User;

it('stores role and active flag with sensible defaults', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe(Role::Staff)
        ->and($user->fresh()->is_active)->toBeTrue();
});

it('has manager, admin and inactive factory states', function () {
    expect(User::factory()->manager()->create()->role)->toBe(Role::Manager)
        ->and(User::factory()->admin()->create()->role)->toBe(Role::Admin)
        ->and(User::factory()->inactive()->create()->is_active)->toBeFalse();
});

it('derives initials from the first two words of the name', function () {
    expect(User::factory()->make(['name' => 'Siti Aisyah'])->initials())->toBe('SA')
        ->and(User::factory()->make(['name' => 'Madonna'])->initials())->toBe('M')
        ->and(User::factory()->make(['name' => 'muhammad hafiz bin ali'])->initials())->toBe('MH');
});
