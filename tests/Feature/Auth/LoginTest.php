<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('sends guests to the login page', function () {
    $this->get('/tenders/in-progress')->assertRedirect('/login');
});

it('logs in an active user and lands on In Progress', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass-1')
        ->call('login')
        ->assertRedirect(route('tenders.index', 'in-progress'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('rejects a deactivated user even with the right password', function () {
    $user = User::factory()->inactive()->create(['password' => 'secret-pass-1']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass-1')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('locks out after five failed attempts', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);
    $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong');

    foreach (range(1, 5) as $_) {
        $component->call('login');
    }

    $component->set('password', 'secret-pass-1')->call('login')
        ->assertHasErrors('email')
        ->assertSee('Too many attempts');
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
