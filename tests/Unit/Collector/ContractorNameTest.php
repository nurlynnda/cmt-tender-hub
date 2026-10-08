<?php

use App\Collector\ContractorName;

it('matches names that differ only in capitals, dots, commas and spacing', function (string $in, string $key) {
    expect(ContractorName::key($in))->toBe($key);
})->with([
    ['10 CREATIVE SOLUTIONS SDN. BHD.', '10 CREATIVE SOLUTIONS SDN BHD'],
    ['10 Creative Solutions Sdn Bhd', '10 CREATIVE SOLUTIONS SDN BHD'],
    ['  10   creative solutions, sdn.bhd. ', '10 CREATIVE SOLUTIONS SDN BHD'],
    ['DARKWHITE CREATIVE SOLUTIONS SDN. BHD.', 'DARKWHITE CREATIVE SOLUTIONS SDN BHD'],
    ['…', ''],
]);
