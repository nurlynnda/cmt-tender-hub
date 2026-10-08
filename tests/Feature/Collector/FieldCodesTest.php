<?php

use App\Collector\FieldCodes;
use App\Models\CollectedTender;
use Illuminate\Support\Facades\DB;

it('has the 534 MOF codes from the old system, in three levels with Malay names', function () {
    $mof = FieldCodes::mof();

    expect($mof)->toHaveCount(534)
        ->and($mof[0])->toBe(['code' => '01', 'name' => 'Penerbitan Dan Penyiaran', 'level' => 1])
        ->and($mof[1])->toBe(['code' => '0101', 'name' => 'Penerbitan', 'level' => 2])
        ->and(collect($mof)->countBy('level')->all())->toBe([1 => 16, 2 => 90, 3 => 428])
        ->and(FieldCodes::label('010101'))->toBe('010101 — Bahan Bacaan Terbitan Luar Negara')
        ->and(FieldCodes::label('B04'))->toBe('B04')
        ->and(FieldCodes::label(''))->toBe('');
});

it('lists the CIDB codes found in collected tenders, sorted, without MOF codes or odd values', function () {
    $t = CollectedTender::factory()->create();
    foreach (['B04', 'CE21', 'B01', '050201', '2221302', 'B04x'] as $code) {
        DB::table('collected_tender_field_codes')->insert(['collected_tender_id' => $t->id, 'code' => $code]);
    }

    expect(FieldCodes::cidb())->toBe(['B01', 'B04', 'CE21']);
});
