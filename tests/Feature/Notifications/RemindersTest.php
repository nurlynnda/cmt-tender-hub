<?php

use App\Enums\TenderStatus;
use App\Models\{Tender, User};
use App\Notifications\{BriefingTomorrow, TenderClosingSoon};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 7 Oct, 00:30 in Malaysia
});

it('reminds PIC and owner once when closing is within 3 days', function () {
    $pic = User::factory()->create();
    $owner = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'owner_id' => $owner->id, 'closing_date' => '2026-10-10']);
    Tender::factory()->create(['closing_date' => '2026-10-11']); // 4 days — too early

    $this->artisan('tenders:send-reminders')->assertSuccessful();
    $this->artisan('tenders:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($pic, TenderClosingSoon::class, 1);
    Notification::assertSentToTimes($owner, TenderClosingSoon::class, 1);
    Notification::assertCount(2);
    expect($tender->fresh()->closing_soon_notified_at)->not->toBeNull()
        ->and($tender->fresh()->version)->toBe(1);
});

it('reminds about a briefing tomorrow in Malaysia time', function () {
    $pic = User::factory()->create();
    Tender::factory()->create(['pic_id' => $pic->id, 'has_briefing' => true, 'briefing_date' => '2026-10-08']);
    Tender::factory()->create(['has_briefing' => true, 'briefing_date' => '2026-10-07']); // today — not tomorrow

    $this->artisan('tenders:send-reminders');

    Notification::assertSentToTimes($pic, BriefingTomorrow::class, 1);
    Notification::assertCount(1);
});

it('skips closed tenders, past closing dates and deactivated people', function () {
    Tender::factory()->status(TenderStatus::Done)->create(['closing_date' => '2026-10-08']);
    Tender::factory()->create(['closing_date' => '2026-10-01']);
    Tender::factory()->create(['pic_id' => User::factory()->inactive(), 'closing_date' => '2026-10-08']);

    $this->artisan('tenders:send-reminders');

    Notification::assertNothingSent();
});

it('says how many days are left', function () {
    $pic = User::factory()->create();
    $tender = Tender::factory()->create(['pic_id' => $pic->id, 'closing_date' => '2026-10-09', 'wo_number' => '200-01102026-001']);

    expect((new TenderClosingSoon($tender))->toArray($pic)['message'])
        ->toBe('200-01102026-001 closes in 2 days (09 Oct 2026)');
});

it('is scheduled hourly', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

    expect($events->first(fn ($e) => str_contains($e->command, 'tenders:send-reminders'))?->expression)->toBe('0 * * * *');
});
