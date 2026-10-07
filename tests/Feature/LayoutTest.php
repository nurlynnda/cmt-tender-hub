<?php

use App\Enums\TenderStatus;
use App\Models\{Tender, User};

it('shows the sidebar with live counts and the signed-in user', function () {
    $user = User::factory()->create(['name' => 'Siti Aisyah']);
    Tender::factory()->count(2)->create();
    Tender::factory()->status(TenderStatus::Lost)->create();

    $this->actingAs($user)->get('/tenders/in-progress')
        ->assertSeeInOrder(['In Progress', '2'])
        ->assertSeeInOrder(['Lost', '1'])
        ->assertSee('Siti Aisyah')
        ->assertSee('Staff')
        ->assertSee(route('quotations.index'))   // Quotations arrived in Stage 5
        ->assertSee(route('dashboard'));   // the Dashboard arrived in Stage 6
});

it('shows Manage Users only to admins', function () {
    $this->actingAs(User::factory()->create())->get('/tenders/in-progress')->assertDontSee('Manage Users');
    $this->actingAs(User::factory()->admin()->create())->get('/tenders/in-progress')->assertSee('Manage Users');
});
