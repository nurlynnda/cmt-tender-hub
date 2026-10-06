<?php

use App\Livewire\NotificationBell;
use App\Models\{Tender, User};
use App\Notifications\TenderAssigned;
use Livewire\Livewire;

it('shows the unread count and opens a notification', function () {
    $user = User::factory()->create();
    $tender = Tender::factory()->create();
    $user->notify(new TenderAssigned($tender, 'PIC'));
    $user->notify(new TenderAssigned($tender, 'PIC'));

    $id = $user->notifications()->first()->id;

    Livewire::actingAs($user)->test(NotificationBell::class)
        ->assertSeeHtml('data-unread="2"')
        ->assertSee($tender->wo_number)
        ->call('open', $id)
        ->assertRedirect(route('tenders.show', $tender));

    expect($user->unreadNotifications()->count())->toBe(1);
});

it('marks everything read', function () {
    $user = User::factory()->create();
    $user->notify(new TenderAssigned(Tender::factory()->create(), 'PIC'));

    Livewire::actingAs($user)->test(NotificationBell::class)->call('markAllRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('cannot open someone else\'s notification', function () {
    $owner = User::factory()->create();
    $owner->notify(new TenderAssigned(Tender::factory()->create(), 'PIC'));

    Livewire::actingAs(User::factory()->create())->test(NotificationBell::class)
        ->call('open', $owner->notifications()->first()->id)
        ->assertNotFound();
});

it('appears in the top bar', function () {
    $this->actingAs(User::factory()->create())->get('/tenders/in-progress')
        ->assertSeeHtml('aria-label="Notifications"');
});
