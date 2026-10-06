<?php

use App\Models\User;

it('shows a friendly 403 page', function () {
    $this->actingAs(User::factory()->create())->get('/settings/users')
        ->assertForbidden()
        ->assertSee("You don't have permission to do that");
});

it('shows a friendly 404 page', function () {
    $this->actingAs(User::factory()->create())->get('/tenders/999999')
        ->assertNotFound()
        ->assertSee("We couldn't find that page");
});

it('shows a friendly 500 page', function () {
    expect(view('errors.500')->render())->toContain('Something went wrong on our side');
});
