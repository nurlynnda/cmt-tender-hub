<?php

use App\Models\CollectedTender;
use App\Queries\CollectedTenderQuery;

// Final-review fixes I4 (common English words) and I5 (short-word fallback).

function searchFor(string $q): array
{
    return CollectedTenderQuery::build(['search' => $q, 'status' => 'all'])->pluck('reference_no')->sort()->values()->all();
}

beforeEach(function () {
    CollectedTender::factory()->create(['reference_no' => 'A1', 'title' => 'SUPPLY FOR SCHOOL COMPUTERS']);
    CollectedTender::factory()->create(['reference_no' => 'B2', 'title' => 'SERVICES THE HOSPITAL KL NEEDS']);
    CollectedTender::factory()->create(['reference_no' => 'C3', 'title' => 'CLEANING WORKS']);
});

it('I4: ignores common English words the search index never stores', function () {
    expect(searchFor('supply for'))->toBe(['A1'])
        ->and(searchFor('services the'))->toBe(['B2'])
        ->and(searchFor('the'))->toBe([]);
});

it('I5: falls back to a text match for short words', function () {
    expect(searchFor('KL'))->toBe(['B2'])
        ->and(searchFor('cleaning 99'))->toBe(['C3']);
});
