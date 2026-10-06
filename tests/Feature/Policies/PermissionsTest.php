<?php

use App\Models\{Tender, User};
use Illuminate\Support\Facades\Gate;

it('lets everyone view and register tenders', function () {
    $staff = User::factory()->create();
    $tender = Tender::factory()->create();

    expect(Gate::forUser($staff)->allows('view', $tender))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('create', Tender::class))->toBeTrue();
});

it('lets staff edit only tenders where they are PIC', function () {
    $staff = User::factory()->create();
    $mine = Tender::factory()->create(['pic_id' => $staff->id]);
    $theirs = Tender::factory()->create(['owner_id' => $staff->id]); // owner is not enough

    expect(Gate::forUser($staff)->allows('update', $mine))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('update', $theirs))->toBeFalse();
});

it('lets managers and admins edit and reopen any tender', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $tender = Tender::factory()->create();

    expect(Gate::forUser($user)->allows('update', $tender))->toBeTrue()
        ->and(Gate::forUser($user)->allows('reopen', $tender))->toBeTrue();
})->with(['manager', 'admin']);

it('does not let staff reopen, even their own tender', function () {
    $staff = User::factory()->create();

    expect(Gate::forUser($staff)->allows('reopen', Tender::factory()->create(['pic_id' => $staff->id])))->toBeFalse();
});

it('lets only admins manage users', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('manage-users'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->manager()->create())->allows('manage-users'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('manage-users'))->toBeFalse();
});
