<?php

use App\Livewire\Auth\{ForgotPassword, ResetPassword};
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotification;
use Illuminate\Support\Facades\{Hash, Notification, Password};
use Livewire\Livewire;

it('emails a reset link and shows the same message whether or not the email exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send')
        ->assertSee('If that email belongs to an account');
    Livewire::test(ForgotPassword::class)->set('email', 'nobody@example.com')->call('send')
        ->assertSee('If that email belongs to an account');

    Notification::assertSentTo($user, ResetNotification::class);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'brand-new-pass-9')
        ->set('password_confirmation', 'brand-new-pass-9')
        ->call('resetPassword')
        ->assertRedirect(route('login'));

    expect(Hash::check('brand-new-pass-9', $user->fresh()->password))->toBeTrue();
});

it('refuses an invalid token', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => 'bogus'])
        ->set('email', $user->email)
        ->set('password', 'brand-new-pass-9')
        ->set('password_confirmation', 'brand-new-pass-9')
        ->call('resetPassword')
        ->assertHasErrors('email');
});
