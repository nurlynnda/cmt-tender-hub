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

it('edits an imported account: real email, name and a temporary password, keeping its tenders', function () {
    $admin = User::factory()->admin()->create();
    $sharul = User::factory()->create(['name' => 'Sharul', 'email' => 'sharul@import.invalid', 'is_active' => false]);
    $tender = \App\Models\Tender::factory()->create(['pic_id' => $sharul->id]);

    Livewire::actingAs($admin)->test(ManageUsers::class)
        ->call('edit', $sharul->id)->assertSet('editName', 'Sharul')->assertSet('editEmail', 'sharul@import.invalid')
        ->assertSee('Edit account')
        ->set('editName', 'Sharul Nizam')->set('editEmail', ' Sharul@CMT.com.my ')->set('editPassword', 'Temp-Pass-2026')
        ->call('saveEdit')->assertHasNoErrors()->assertSet('editingId', null)->assertSee('Saved — Sharul Nizam');

    $fresh = $sharul->fresh();
    expect($fresh->only(['name', 'email']))->toBe(['name' => 'Sharul Nizam', 'email' => 'sharul@cmt.com.my'])
        ->and(\Illuminate\Support\Facades\Hash::check('Temp-Pass-2026', $fresh->password))->toBeTrue()
        ->and($tender->fresh()->pic_id)->toBe($sharul->id);
});

it('keeps the password when the box is empty, and refuses a taken email or a short password', function () {
    $admin = User::factory()->admin()->create();
    $u = User::factory()->create(['email' => 'a@cmt.test']);
    User::factory()->create(['email' => 'taken@cmt.test']);
    $old = $u->password;

    Livewire::actingAs($admin)->test(ManageUsers::class)
        ->call('edit', $u->id)->set('editEmail', 'TAKEN@cmt.test')->call('saveEdit')->assertHasErrors('editEmail')
        ->set('editEmail', 'a@cmt.test')->set('editPassword', 'short')->call('saveEdit')->assertHasErrors('editPassword')
        ->set('editPassword', '')->set('editName', 'Renamed')->call('saveEdit')->assertHasNoErrors()
        ->call('edit', $u->id)->call('cancelEdit')->assertSet('editingId', null);

    expect($u->fresh()->name)->toBe('Renamed')->and($u->fresh()->password)->toBe($old);
});

it('does not let admins set their own password here, or non-admins edit anyone', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(ManageUsers::class)
        ->call('edit', $admin->id)->assertDontSeeHtml('aria-label="New temporary password"')
        ->set('editName', 'Me Myself')->set('editPassword', 'Sneaky-Pass-2026')->call('saveEdit');
    expect($admin->fresh()->name)->toBe('Me Myself')
        ->and(\Illuminate\Support\Facades\Hash::check('Sneaky-Pass-2026', $admin->fresh()->password))->toBeFalse();

    $staff = User::factory()->create();
    expect(fn () => app(\App\Actions\Users\UpdateUserProfile::class)->handle($staff, $admin, 'X', 'x@cmt.test', null))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});
