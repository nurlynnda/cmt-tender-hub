<?php

use App\Models\User;

it('logs out a user who was deactivated while signed in', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/tenders/in-progress')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/tenders/in-progress')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
