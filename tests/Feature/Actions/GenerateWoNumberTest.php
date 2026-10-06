<?php

use App\Actions\Tenders\GenerateWoNumber;
use App\Models\Tender;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

it('numbers WOs per day and restarts at 001 the next day', function () {
    $wo = app(GenerateWoNumber::class);
    $day1 = CarbonImmutable::parse('2026-10-06', 'Asia/Kuala_Lumpur');
    $day2 = $day1->addDay();

    expect($wo->next($day1))->toBe('200-06102026-001')
        ->and($wo->next($day1))->toBe('200-06102026-002')
        ->and($wo->next($day2))->toBe('200-07102026-001')
        ->and($wo->next($day1))->toBe('200-06102026-003');
});

it('has a database backstop against duplicate WO numbers', function () {
    Tender::factory()->create(['wo_number' => '200-06102026-001']);
    Tender::factory()->create(['wo_number' => '200-06102026-001']);
})->throws(QueryException::class);
