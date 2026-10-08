<?php

use App\Collector\Support\Text;
use Carbon\CarbonImmutable;

it('collapses whitespace including non-breaking spaces', function () {
    expect(Text::clean("  A \n\t B\u{00A0}C  "))->toBe('A B C')
        ->and(Text::clean(null))->toBe('');
});

it('parses the four date shapes the sites use, rejecting impossible dates', function () {
    expect(Text::ddmmyyyy('07/07/2026'))->toBe('2026-07-07')
        ->and(Text::ddmmyyyy('31/02/2026'))->toBeNull()
        ->and(Text::ddmmyyyy('7/7/2026'))->toBeNull()
        ->and(Text::isoPrefix('2026-07-06 12:00PM'))->toBe('2026-07-06')
        ->and(Text::isoPrefix('2026-02-30'))->toBeNull()
        ->and(Text::dotted('20.07.2026'))->toBe('2026-07-20')
        ->and(Text::dashed('20-07-2026'))->toBe('2026-07-20')
        ->and(Text::dashed('20-07-2026 extra'))->toBeNull()
        ->and(Text::ddmmyyyy(null))->toBeNull();
});

it('parses RM prices into sen', function () {
    expect(Text::rmPriceSen('RM 28,800.00'))->toBe(2880000)
        ->and(Text::rmPriceSen('RM429,782.20'))->toBe(42978220)
        ->and(Text::rmPriceSen('rm 5'))->toBe(500)
        ->and(Text::rmPriceSen('28,800.00'))->toBeNull()
        ->and(Text::rmPriceSen(null))->toBeNull();
});

it('splits comma-separated field codes', function () {
    expect(Text::fieldCodes('E05, E32,'))->toBe(['E05', 'E32'])
        ->and(Text::fieldCodes(''))->toBe([]);
});

it('stamps observations in sortable UTC ISO form', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 04:05:06.789', 'UTC'));
    expect(Text::scrapedAtNow())->toBe('2026-10-06T04:05:06.789Z');
});
