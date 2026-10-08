<?php

use App\Enums\Role;
use App\Livewire\ManageUsers;
use App\Models\User;
use Livewire\Livewire;

it('is only reachable by admins', function () {
    $this->actingAs(User::factory()->manager()->create())->get('/settings/users')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/settings/users')->assertOk()->assertSee('Manage Users');
});

it('adds a user and validates the form', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(ManageUsers::class)
        ->call('create')->assertHasErrors(['name', 'email', 'password'])
        ->set('name', 'Muhammad Hafiz')->set('email', 'hafiz@cmt.test')->set('role', 'manager')->set('password', 'temp-pass-123')
        ->call('create')->assertHasNoErrors()->assertSee('Muhammad Hafiz');

    expect(User::where('email', 'hafiz@cmt.test')->first()->role)->toBe(Role::Manager);
});

it('rejects a duplicate email, ignoring letter case', function () {
    User::factory()->create(['email' => 'dup@cmt.test']);

    Livewire::actingAs(User::factory()->admin()->create())->test(ManageUsers::class)
        ->set('name', 'X')->set('email', 'DUP@cmt.test')->set('password', 'temp-pass-123')
        ->call('create')->assertHasErrors('email');
});

it('changes role and toggles active, and explains why self-lockout is refused', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    Livewire::actingAs($admin)->test(ManageUsers::class)
        ->call('changeRole', $other->id, 'manager')
        ->call('toggleActive', $other->id)
        ->call('toggleActive', $admin->id)
        ->assertSee('You cannot deactivate your own account');

    expect($other->fresh()->role)->toBe(Role::Manager)->and($other->fresh()->is_active)->toBeFalse();
});
