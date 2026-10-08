<?php

use App\Support\Initials;

it('takes the first letter of the first two words, upper-cased', function (string $name, string $expected) {
    expect(Initials::of($name))->toBe($expected);
})->with([
    ['Ahmad Faizal', 'AF'],
    ['siti aisyah binti ahmad', 'SA'],
    ['  Nurul   Ain ', 'NA'],
    ['Zara', 'Z'],
    ['', ''],
]);
