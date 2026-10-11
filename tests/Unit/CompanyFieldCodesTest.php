<?php

use App\Exceptions\FieldCodeNotHeld;
use App\Support\CompanyFieldCodes;

it('holds the 30 field codes the company is registered under', function () {
    $codes = array_column(CompanyFieldCodes::all(), 'code');

    expect($codes)->toHaveCount(30)->toContain('010302', '210101', '210110', '221207')->not->toContain('050201');
});

it('reads codes separated by commas, spaces or new lines, upper-cased and without repeats', function () {
    expect(CompanyFieldCodes::parse("210101, b04;210101\n 210105"))->toBe(['210101', 'B04', '210105'])
        ->and(CompanyFieldCodes::parse('  '))->toBe([])
        ->and(CompanyFieldCodes::parse(null))->toBe([]);
});

it('finds which of a tender\'s codes the company holds', function () {
    expect(CompanyFieldCodes::matching(['B04', '210102', '999999']))->toBe(['210102'])
        ->and(CompanyFieldCodes::matching(['B04']))->toBe([]);
});

it('refuses when none match, saying which codes the tender asked for', function () {
    expect(fn () => CompanyFieldCodes::assertHeld(['B04', 'CE21']))->toThrow(FieldCodeNotHeld::class, 'B04, CE21')
        ->and(fn () => CompanyFieldCodes::assertHeld([]))->toThrow(FieldCodeNotHeld::class, 'no field codes');

    CompanyFieldCodes::assertHeld(['B04', '220301']); // one match is enough
    expect(true)->toBeTrue();
});
