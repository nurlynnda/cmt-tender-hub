<?php

use App\Enums\{TenderMode, TenderStatus, TenderType};
use App\Import\{RegisterFormatException, TenderRegister};

function sampleRegister(): array
{
    return (new TenderRegister)->read(dirname(__DIR__, 2).'/fixtures/register/register-sample.csv');
}

it('reads the rows it can use and explains the ones it skips', function () {
    $r = sampleRegister();

    expect(collect($r['rows'])->pluck('wo_number')->all())->toBe([
        '200-01012026-001', '200-01012026-002', '200-01012026-003', '200-01012026-004',
        '200-01012026-005', '200-01012026-006', '200-01012026-007',
    ]);
    expect($r['skipped'])->toBe([
        ['line' => 9, 'wo' => '200-01012026-008', 'reason' => "unknown status 'Pending'"],
        ['line' => 10, 'wo' => '200-01012026-001', 'reason' => 'duplicate WO number in file'],
    ]); // the blank trailing row is ignored silently
});

it('maps statuses, modes and types like the register means them', function () {
    $rows = collect(sampleRegister()['rows'])->keyBy('wo_number');

    expect($rows['200-01012026-001']['status'])->toBe(TenderStatus::InProgress)
        ->and($rows['200-01012026-002']['status'])->toBe(TenderStatus::InProgress)
        ->and($rows['200-01012026-003']['status'])->toBe(TenderStatus::Done)
        ->and($rows['200-01012026-004']['status'])->toBe(TenderStatus::Awarded)
        ->and($rows['200-01012026-005']['status'])->toBe(TenderStatus::Lost)
        ->and($rows['200-01012026-005']['was_cancelled'])->toBeFalse()
        ->and($rows['200-01012026-006']['status'])->toBe(TenderStatus::Lost)
        ->and($rows['200-01012026-006']['was_cancelled'])->toBeTrue()
        ->and($rows['200-01012026-007']['status'])->toBe(TenderStatus::Dropped)
        ->and($rows['200-01012026-002']['mode'])->toBe(TenderMode::NonEp)
        ->and($rows['200-01012026-002']['type'])->toBe(TenderType::Quotation)
        ->and($rows['200-01012026-001']['type'])->toBe(TenderType::Tender);
});

it('cleans money, dates, names and blanks', function () {
    $rows = collect(sampleRegister()['rows'])->keyBy('wo_number');

    expect($rows['200-01012026-001']['estimated_value_sen'])->toBe(100000000)
        ->and($rows['200-01012026-001']['wo_date'])->toBe('2026-01-02')
        ->and($rows['200-01012026-001']['briefing_date'])->toBe('2026-01-10')
        ->and($rows['200-01012026-001']['title'])->toBe('SAMPLE TENDER ONE, WITH COMMA')
        ->and($rows['200-01012026-002']['estimated_value_sen'])->toBeNull()   // "-"
        ->and($rows['200-01012026-002']['publish_date'])->toBeNull()
        ->and($rows['200-01012026-002']['pic_name'])->toBe('aminah')
        ->and($rows['200-01012026-003']['client'])->toBe('KEMENTERIAN CONTOH') // blank PTJ → ministry
        ->and($rows['200-01012026-003']['submitted_cost_sen'])->toBe(32000000)
        ->and($rows['200-01012026-004']['ministry'])->toBe('KEMENTERIAN CONTOH') // tab collapsed
        ->and($rows['200-01012026-005']['winning_price_sen'])->toBeNull()    // "`"
        ->and($rows['200-01012026-005']['pic_name'])->toBeNull();
});

it('warns about amounts it cannot read, but not about the sheet\'s usual blanks', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    $csv = file_get_contents(dirname(__DIR__, 2).'/fixtures/register/register-sample.csv');
    file_put_contents($path, str_replace('"1,000,000.00"', 'about 1 million', $csv));

    expect((new TenderRegister)->read($path)['warnings'])->toBe(["Line 2: Indicative Price 'about 1 million' is not an amount — left empty"]);
});

it('refuses a file without the register columns', function () {
    $path = tempnam(sys_get_temp_dir(), 'reg');
    file_put_contents($path, "Name,Amount\nX,1\n");

    expect(fn () => (new TenderRegister)->read($path))->toThrow(RegisterFormatException::class, 'missing columns: WO Number');
});

it('refuses a file it cannot open', function () {
    expect(fn () => (new TenderRegister)->read('/nope/missing.csv'))->toThrow(RegisterFormatException::class, 'Cannot open');
});
