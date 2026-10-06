<?php

use App\Exceptions\StaleTenderException;
use App\Models\{ActivityLog, Tender, User};

it('records an activity entry and lists newest first', function () {
    $tender = Tender::factory()->create();
    $user = User::factory()->create(['name' => 'Nurul Ain']);

    ActivityLog::record($tender, $user, 'registered', 'Tender registered');
    ActivityLog::record($tender, $user, 'updated', 'Details updated: title');

    expect($tender->activity->pluck('event')->all())->toBe(['updated', 'registered'])
        ->and($tender->activity->first()->user->name)->toBe('Nurul Ain');
});

it('cannot be edited once written', function () {
    $log = ActivityLog::record(Tender::factory()->create(), null, 'registered', 'Tender registered');

    $log->description = 'tampered';
    $log->save();

    expect($log->fresh()->description)->toBe('Tender registered');
});

it('names the last person to change a tender in the stale message', function () {
    $tender = Tender::factory()->create();
    ActivityLog::record($tender, User::factory()->create(['name' => 'Ahmad Faizal']), 'updated', 'x');

    expect(StaleTenderException::for($tender)->getMessage())
        ->toBe('This tender was changed by Ahmad Faizal — reload to see their changes.');
});
