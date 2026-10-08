<?php

use App\Collector\StaleOpenCloser;
use App\Models\CollectedTender;
use Carbon\CarbonImmutable;

it('closes tenders whose closing day 12:01pm MYT has passed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:05', 'Asia/Kuala_Lumpur'));
    $yesterday = CollectedTender::factory()->create(['closing_date' => '2026-10-06']);
    $today = CollectedTender::factory()->create(['closing_date' => '2026-10-07']);
    $tomorrow = CollectedTender::factory()->create(['closing_date' => '2026-10-08']);

    expect(app(StaleOpenCloser::class)->run())->toBe(2)
        ->and($yesterday->fresh()->status)->toBe('closed')
        ->and($today->fresh()->status)->toBe('closed')
        ->and($tomorrow->fresh()->status)->toBe('open');
});

it('keeps today\'s tenders open before 12:01pm', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 11:59', 'Asia/Kuala_Lumpur'));
    $today = CollectedTender::factory()->create(['closing_date' => '2026-10-07']);

    app(StaleOpenCloser::class)->run();

    expect($today->fresh()->status)->toBe('open');
});

it('falls back to one month after advertising, clamped to month end', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-28 09:00', 'Asia/Kuala_Lumpur'));
    $due = CollectedTender::factory()->create(['closing_date' => null, 'advertised_date' => '2026-01-31']);
    $notYet = CollectedTender::factory()->create(['closing_date' => null, 'advertised_date' => '2026-02-01']);
    $unknown = CollectedTender::factory()->create(['closing_date' => null, 'advertised_date' => null]);

    app(StaleOpenCloser::class)->run();

    expect($due->fresh()->status)->toBe('closed')
        ->and($notYet->fresh()->status)->toBe('open')
        ->and($unknown->fresh()->status)->toBe('open');
});
