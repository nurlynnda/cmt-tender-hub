<?php

use App\Rules\Percentage;
use App\Support\Percent;
use Illuminate\Support\Facades\Validator;

it('parses percentages into basis points', function (string $in, int $bp) {
    expect(Percent::parseBp($in))->toBe($bp);
})->with([['20', 2000], ['20.5', 2050], ['18.25', 1825], [' 7 % ', 700], ['0', 0], ['99.99', 9999]]);

it('treats empty as none and rejects junk', function () {
    expect(Percent::parseBp(''))->toBeNull()->and(Percent::parseBp(null))->toBeNull();
    expect(fn () => Percent::parseBp('abc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Percent::parseBp('1.234'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Percent::parseBp('-5'))->toThrow(InvalidArgumentException::class);
});

it('formats basis points', function () {
    expect(Percent::format(2000))->toBe('20.0%')
        ->and(Percent::format(1825, 2))->toBe('18.25%')
        ->and(Percent::format(-350))->toBe('-3.5%');
});

it('turns basis points into input text', function () {
    expect(Percent::toInput(2000))->toBe('20')
        ->and(Percent::toInput(1825))->toBe('18.25')
        ->and(Percent::toInput(1850))->toBe('18.5')
        ->and(Percent::toInput(0))->toBe('0');
});

it('validates margins from 0% to 99.99%', function (string $v, bool $ok) {
    expect(Validator::make(['m' => $v], ['m' => [new Percentage]])->passes())->toBe($ok);
})->with([['20', true], ['0', true], ['99.99', true], ['100', false], ['abc', false]]);
