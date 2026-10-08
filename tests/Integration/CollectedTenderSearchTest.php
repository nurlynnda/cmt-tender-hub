<?php

use App\Models\CollectedTender;
use App\Queries\CollectedTenderQuery;

function searchRefs(string $q): array
{
    return CollectedTenderQuery::build(['search' => $q, 'status' => 'all'])->pluck('reference_no')->sort()->values()->all();
}

beforeEach(function () {
    CollectedTender::factory()->create(['reference_no' => 'UTHM/54(KTKEM)/P/02/023/2026(1)', 'title' => 'MAKMAL ELEKTRIK DAN ELEKTRONIK']);
    CollectedTender::factory()->create(['reference_no' => 'QT1', 'title' => 'SEWAAN KOMPUTER RIBA', 'agency' => 'PUSAT DARAH NEGARA']);
});

it('finds words in title, agency and reference, matching word beginnings', function () {
    expect(searchRefs('elektrik'))->toBe(['UTHM/54(KTKEM)/P/02/023/2026(1)'])
        ->and(searchRefs('komputer riba'))->toBe(['QT1'])
        ->and(searchRefs('darah'))->toBe(['QT1'])
        ->and(searchRefs('kompu'))->toBe(['QT1']);
});

it('finds an exact reference number, punctuation and all', function () {
    expect(searchRefs('UTHM/54(KTKEM)/P/02/023/2026(1)'))->toBe(['UTHM/54(KTKEM)/P/02/023/2026(1)']);
});

it('never errors on search-operator characters or very short words', function (string $q) {
    // Called directly: any MySQL syntax error fails the test.
    expect(searchRefs($q))->toBeArray();
})->with(['+', '"', '*', '-', '()', '@@', 'a', 'QT', '"unclosed']);
