<?php

use App\Costing\CostingImport;

it('reads tab-separated rows copied from Excel', function () {
    $r = CostingImport::parse("Server rack\t2\tunit\t12,500.00\r\nLicence, annual\t1\tlot\t3000");

    expect($r['errors'])->toBe([])
        ->and($r['rows'])->toBe([
            ['description' => 'Server rack', 'quantity' => '2', 'unit' => 'unit', 'unit_cost' => '12500.00'],
            ['description' => 'Licence, annual', 'quantity' => '1', 'unit' => 'lot', 'unit_cost' => '3000.00'],
        ]);
});

it('reads comma-separated rows, keeping commas inside the description', function () {
    $r = CostingImport::parse('Supply, deliver and install switch, 4, unit, 1500');

    expect($r['rows'][0])->toBe(['description' => 'Supply, deliver and install switch', 'quantity' => '4', 'unit' => 'unit', 'unit_cost' => '1500.00']);
});

it('fills a blank unit and a blank cost with defaults', function () {
    expect(CostingImport::parse("Cable\t3\t\t")['rows'][0])
        ->toBe(['description' => 'Cable', 'quantity' => '3', 'unit' => 'unit', 'unit_cost' => '0.00']);
});

it('skips blank lines and reports bad rows by number without stopping', function () {
    $r = CostingImport::parse("Good\t1\tunit\t10\n\nNo qty\tx\tunit\t10\nNo cost\t1\tunit\tabc\nToo short\t1\n\t1\tunit\t5");

    expect($r['rows'])->toHaveCount(1)
        ->and($r['errors'])->toBe([
            'Row 3: quantity must be a whole number of at least 1.',
            'Row 4: unit cost is not a valid amount.',
            'Row 5: needs description, quantity, unit and unit cost.',
            'Row 6: needs description, quantity, unit and unit cost.',
        ]);
});

it('returns nothing for empty text', function () {
    expect(CostingImport::parse("  \n "))->toBe(['rows' => [], 'errors' => []]);
});
