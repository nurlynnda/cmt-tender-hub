<?php

use App\Collector\TenderPatch;

function patchInput(array $o = []): array
{
    return array_merge([
        'reference_no' => 'QT 123', 'title' => 'T', 'status' => 'open', 'procurement_type' => 'quotation',
        'scraped_at' => '2026-07-07T12:00:00.000Z', 'source' => 'myprocurement', 'source_id' => '789',
        'source_url' => 'https://myprocurement.treasury.gov.my/x',
    ], $o);
}

it('computes the dedup key like tms-v2', function () {
    expect(TenderPatch::dedupKey(' qt 12 3 ', 'x:1'))->toBe('QT123')
        ->and(TenderPatch::dedupKey('  ', 'myprocurement:789'))->toBe('myprocurement:789')
        ->and(TenderPatch::make(patchInput())->dedupKey)->toBe('QT123')
        ->and(TenderPatch::make(patchInput(['reference_no' => '']))->dedupKey)->toBe('myprocurement:789');
});

it('keeps only the optional fields that were observed', function () {
    $p = TenderPatch::make(patchInput(['ministry' => null, 'winners' => [['name' => 'A', 'price_sen' => 100]]]));

    expect($p->has('ministry'))->toBeTrue()
        ->and($p->get('ministry'))->toBeNull()
        ->and($p->has('closing_date'))->toBeFalse()
        ->and($p->get('winners'))->toBe([['name' => 'A', 'price_sen' => 100]]);
});

it('returns a copy with extra fields', function () {
    $p = TenderPatch::make(patchInput());
    $q = $p->with(['winners' => null]);

    expect($p->has('winners'))->toBeFalse()->and($q->has('winners'))->toBeTrue();
});

it('rejects invalid observations', function (array $bad) {
    TenderPatch::make(patchInput($bad));
})->with([
    'empty title' => [['title' => '  ']],
    'bad status' => [['status' => 'pending']],
    'bad type' => [['procurement_type' => 'auction']],
    'no source id' => [['source_id' => '']],
    'bad url' => [['source_url' => 'not a url']],
    'unknown field' => [['colour' => 'red']],
    'winner without name' => [['winners' => [['name' => '', 'price_sen' => 1]]]],
    'event without label' => [['events' => [['label' => '', 'date' => null, 'address' => null]]]],
])->throws(InvalidArgumentException::class);
