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

it('puts the prototype icon beside every menu item', function () {
    $html = $this->actingAs(User::factory()->admin()->create())->get('/tenders/in-progress')->getContent();

    foreach (['Dashboard' => 'dashboard', 'Find Tenders' => 'search', 'In Progress' => 'clock', 'Done' => 'check', 'Awarded' => 'award',
        'Lost' => 'x', 'Quotations' => 'quotation', 'Status' => 'staff', 'Settings' => 'settings', 'Manage Users' => 'user',
        'Finance Settings' => 'settings'] as $label => $icon) {
        expect($html)->toContain('data-nav="'.$label.'" data-icon="'.$icon.'"');
    }
});

it('opens the sidebar folded when this computer folded it last time', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertSee('data-sidebar="open"', false);
    $this->actingAs($user)->withUnencryptedCookie('sidebar', 'folded')->get('/dashboard')->assertSee('data-sidebar="folded"', false);
    $this->actingAs($user)->withUnencryptedCookie('sidebar', 'nonsense')->get('/dashboard')->assertSee('data-sidebar="open"', false);
});

it('never folds the phone drawer, so its labels always show', function () {
    $html = $this->actingAs(User::factory()->create())->withUnencryptedCookie('sidebar', 'folded')->get('/dashboard')->getContent();

    $drawer = Illuminate\Support\Str::between($html, 'data-drawer', '<main');
    expect($html)->toContain('data-drawer')->and($drawer)->not->toContain('is-folded')->toContain('Find Tenders');
});
