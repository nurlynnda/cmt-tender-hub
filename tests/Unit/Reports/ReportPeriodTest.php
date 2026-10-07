<?php

use App\Reports\ReportPeriod as P;
use Carbon\CarbonImmutable;

function period(array $in, string $today = '2026-10-15'): P
{
    return P::fromInput($in, CarbonImmutable::parse($today));
}

it('defaults to all time', function () {
    $p = period([]);

    expect($p->kind)->toBe('all')->and($p->from)->toBeNull()->and($p->to)->toBeNull()
        ->and($p->isAllTime())->toBeTrue()->and($p->invalid)->toBeFalse()->and($p->label())->toBe('All time')
        ->and($p->contains('1999-01-01'))->toBeTrue();
});

it('works out the named periods', function (array $in, string $from, string $to, string $label) {
    $p = period($in);

    expect([$p->from, $p->to, $p->label()])->toBe([$from, $to, $label]);
})->with([
    'this month' => [['period' => 'this_month'], '2026-10-01', '2026-10-31', 'This month (Oct 2026)'],
    'last month' => [['period' => 'last_month'], '2026-09-01', '2026-09-30', 'Last month (Sep 2026)'],
    'this year' => [['period' => 'this_year'], '2026-01-01', '2026-12-31', 'This year (2026)'],
    'a month' => [['period' => 'month', 'month' => '2026-02'], '2026-02-01', '2026-02-28', 'Feb 2026'],
    'custom' => [['period' => 'custom', 'from' => '2026-03-05', 'to' => '2026-04-10'], '2026-03-05', '2026-04-10', '05 Mar 2026 – 10 Apr 2026'],
]);

it('counts the first and last day of the month as inside it', function () {
    $p = period(['period' => 'this_month']);

    expect($p->contains('2026-10-01'))->toBeTrue()->and($p->contains('2026-10-31'))->toBeTrue()
        ->and($p->contains('2026-09-30'))->toBeFalse()->and($p->contains('2026-11-01'))->toBeFalse();
});

it('handles last month across a year end and from the 31st', function () {
    $p = period(['period' => 'last_month'], '2026-01-10');
    $march31 = period(['period' => 'last_month'], '2026-03-31');

    expect([$p->from, $p->to])->toBe(['2025-12-01', '2025-12-31'])
        ->and([$march31->from, $march31->to])->toBe(['2026-02-01', '2026-02-28']);
});

it('falls back to all time for anything it cannot read', function (array $in) {
    $p = period($in);

    expect($p->isAllTime())->toBeTrue()->and($p->invalid)->toBeTrue();
})->with([
    'end before start' => [['period' => 'custom', 'from' => '2026-12-01', 'to' => '2026-01-01']],
    'bad month' => [['period' => 'month', 'month' => 'banana']],
    'bad date' => [['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01']],
    'unknown kind' => [['period' => 'forever']],
]);

it('treats a month or custom range not chosen yet as all time without a warning', function (array $in) {
    $p = period($in);

    expect($p->isAllTime())->toBeTrue()->and($p->invalid)->toBeFalse();
})->with([
    'month empty' => [['period' => 'month', 'month' => '']],
    'custom empty' => [['period' => 'custom', 'from' => '', 'to' => '']],
    'custom half filled' => [['period' => 'custom', 'from' => '2026-01-01', 'to' => '']],
]);
