<?php

use App\Actions\Tenders\UpdateTender;
use App\Enums\TenderStatus;
use App\Exceptions\{InvalidTenderTransition, StaleTenderException};
use App\Models\{ActivityLog, Tender, User};
use Illuminate\Auth\Access\AuthorizationException;

function editable(array $attrs = []): array
{
    $pic = User::factory()->create(['name' => 'Nurul Ain']);
    $tender = Tender::factory()->create(array_merge(['pic_id' => $pic->id, 'closing_date' => '2026-10-15'], $attrs));

    return [$pic, $tender];
}

it('saves changes, bumps the version and logs which fields changed', function () {
    [$pic, $tender] = editable();

    $updated = app(UpdateTender::class)->handle($pic, $tender, 1, [
        'title' => 'NEW TITLE', 'closing_date' => '2026-10-20',
    ]);

    expect($updated->title)->toBe('NEW TITLE')
        ->and($updated->version)->toBe(2)
        ->and($updated->activity->first()->description)->toBe('Details updated: title, closing date');
});

it('logs a PIC change with both names', function () {
    [$pic, $tender] = editable();
    $new = User::factory()->create(['name' => 'Siti Aisyah']);

    $updated = app(UpdateTender::class)->handle($pic, $tender, 1, ['pic_id' => $new->id]);

    expect($updated->activity->first()->event)->toBe('pic_changed')
        ->and($updated->activity->first()->description)->toBe('PIC changed from Nurul Ain to Siti Aisyah');
});

it('does nothing when nothing changed', function () {
    [$pic, $tender] = editable();

    $same = app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => $tender->title]);

    expect($same->version)->toBe(1)->and(ActivityLog::count())->toBe(0);
});

it('refuses a save based on an out-of-date copy (two tabs)', function () {
    [$pic, $tender] = editable();
    $manager = User::factory()->manager()->create(['name' => 'Ahmad Faizal']);

    app(UpdateTender::class)->handle($manager, $tender, 1, ['title' => 'FIRST']);

    expect(fn () => app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => 'SECOND']))
        ->toThrow(StaleTenderException::class, 'This tender was changed by Ahmad Faizal');
    expect($tender->fresh()->title)->toBe('FIRST');
});

it('re-arms reminders when the closing or briefing date changes', function () {
    [$pic, $tender] = editable(['closing_soon_notified_at' => now(), 'briefing_notified_at' => now()]);

    $updated = app(UpdateTender::class)->handle($pic, $tender, 1, [
        'closing_date' => '2026-11-01', 'has_briefing' => true, 'briefing_date' => '2026-10-25',
    ]);

    expect($updated->closing_soon_notified_at)->toBeNull()
        ->and($updated->briefing_notified_at)->toBeNull();
});

it('refuses staff who are not the PIC', function () {
    [, $tender] = editable();

    app(UpdateTender::class)->handle(User::factory()->create(), $tender, 1, ['title' => 'X']);
})->throws(AuthorizationException::class);

it('refuses edits once the tender is no longer in progress', function () {
    [$pic, $tender] = editable(['status' => TenderStatus::Done]);

    app(UpdateTender::class)->handle($pic, $tender, 1, ['title' => 'X']);
})->throws(InvalidTenderTransition::class);
