<?php

use App\Actions\Tenders\{RegisterTender, UpdateTender};
use App\Models\User;
use App\Notifications\TenderAssigned;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('notifies the PIC and a different opportunity owner on registration, but not the registrant', function () {
    $actor = User::factory()->create();
    $pic = User::factory()->create();
    $owner = User::factory()->create();

    app(RegisterTender::class)->handle($actor, tenderData(['pic_id' => $pic->id, 'owner_id' => $owner->id]));

    Notification::assertSentTo($pic, TenderAssigned::class, fn ($n) => str_contains($n->toArray($pic)['message'], 'as PIC'));
    Notification::assertSentTo($owner, TenderAssigned::class, fn ($n) => str_contains($n->toArray($owner)['message'], 'as Opportunity Owner'));
    Notification::assertNotSentTo($actor, TenderAssigned::class);
});

it('sends one notification when PIC and owner are the same person', function () {
    $pic = User::factory()->create();

    app(RegisterTender::class)->handle(User::factory()->create(), tenderData(['pic_id' => $pic->id, 'owner_id' => $pic->id]));

    Notification::assertSentToTimes($pic, TenderAssigned::class, 1);
});

it('does not notify someone who registers a tender for themselves', function () {
    $me = User::factory()->create();

    app(RegisterTender::class)->handle($me, tenderData(['pic_id' => $me->id]));

    Notification::assertNothingSent();
});

it('notifies only the newly assigned PIC on edit', function () {
    $manager = User::factory()->manager()->create();
    $oldPic = User::factory()->create();
    $newPic = User::factory()->create();
    $tender = app(RegisterTender::class)->handle($manager, tenderData(['pic_id' => $oldPic->id]));
    Notification::fake();

    app(UpdateTender::class)->handle($manager, $tender, 1, ['pic_id' => $newPic->id, 'title' => 'CHANGED']);

    Notification::assertSentTo($newPic, TenderAssigned::class);
    Notification::assertNotSentTo($oldPic, TenderAssigned::class);
});

it('does not notify on edits that leave PIC and owner alone', function () {
    $pic = User::factory()->create();
    $tender = app(RegisterTender::class)->handle(User::factory()->manager()->create(), tenderData(['pic_id' => $pic->id]));
    Notification::fake();

    app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => 'CHANGED']);

    Notification::assertNothingSent();
});
