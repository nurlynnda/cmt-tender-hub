<?php

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('shows the settings page', function () {
    $this->actingAs(User::factory()->create())->get('/settings')->assertOk()->assertSee('Change password');
});

it('updates own name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Settings::class)
        ->set('name', 'Siti Aisyah binti Ali')->call('saveProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Siti Aisyah binti Ali');
});

it('changes password only with the current password', function () {
    $user = User::factory()->create(['password' => 'old-pass-123']);

    Livewire::actingAs($user)->test(Settings::class)
        ->set('currentPassword', 'wrong')->set('newPassword', 'new-pass-456')->set('newPassword_confirmation', 'new-pass-456')
        ->call('changePassword')->assertHasErrors('currentPassword')
        ->set('currentPassword', 'old-pass-123')
        ->call('changePassword')->assertHasNoErrors()->assertSee('Password changed');

    expect(Hash::check('new-pass-456', $user->fresh()->password))->toBeTrue();
});
