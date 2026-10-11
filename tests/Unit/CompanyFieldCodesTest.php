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

it('lists the codes the company does not hold, upper-cased, keeping the tender\'s order', function () {
    expect(CompanyFieldCodes::missing(['B04', '210102', 'ce21']))->toBe(['B04', 'CE21'])
        ->and(CompanyFieldCodes::missing(['210101', '220301']))->toBe([])
        ->and(CompanyFieldCodes::missing([]))->toBe([]);
});

it('needs the company to hold EVERY code the tender asks for', function () {
    CompanyFieldCodes::assertHeld(['210101', '220301']); // all held
    expect(true)->toBeTrue();

    expect(fn () => CompanyFieldCodes::assertHeld(['B04', 'CE21']))->toThrow(FieldCodeNotHeld::class, 'B04, CE21')
        ->and(fn () => CompanyFieldCodes::assertHeld(['210101', 'B04']))->toThrow(FieldCodeNotHeld::class, 'field code(s) B04.')  // one missing is enough to refuse
        ->and(fn () => CompanyFieldCodes::assertHeld([]))->toThrow(FieldCodeNotHeld::class, 'no field codes');
});

it('tells which codes were asked for and which are missing', function () {
    try {
        CompanyFieldCodes::assertHeld(['210101', 'B04']);
    } catch (FieldCodeNotHeld $e) {
        expect($e->tenderCodes)->toBe(['210101', 'B04'])->and($e->missing)->toBe(['B04']);

        return;
    }
    $this->fail('Expected FieldCodeNotHeld');
});
