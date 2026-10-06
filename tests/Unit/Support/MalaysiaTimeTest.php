<?php

use App\Support\MalaysiaTime;
use Carbon\CarbonImmutable;

it('gives the Malaysia calendar day even when UTC is still on the previous day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC')); // 00:30 MYT on the 7th

    expect(MalaysiaTime::today()->toDateString())->toBe('2026-10-07')
        ->and(MalaysiaTime::now()->format('H:i'))->toBe('00:30')
        ->and(MalaysiaTime::now()->getTimezone()->getName())->toBe('Asia/Kuala_Lumpur');
});
