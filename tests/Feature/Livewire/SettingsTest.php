<?php

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
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

it('signs out other browsers and "keep me signed in" cookies after a password change', function () {
    $user = User::factory()->create(['password' => 'old-pass-123', 'remember_token' => 'old-remember-token']);
    $other = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'mine-elsewhere', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'someone-else', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
    ]);

    Livewire::actingAs($user)->test(Settings::class)
        ->set('currentPassword', 'old-pass-123')->set('newPassword', 'new-pass-456')->set('newPassword_confirmation', 'new-pass-456')
        ->call('changePassword')->assertHasNoErrors();

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['someone-else'])
        ->and($user->fresh()->remember_token)->not->toBe('old-remember-token');
});
