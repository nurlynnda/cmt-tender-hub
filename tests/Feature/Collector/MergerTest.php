<?php

use App\Collector\{Merger, TenderPatch};
use App\Models\CollectedTender;

function patch(array $o = []): TenderPatch
{
    return TenderPatch::make(array_merge([
        'reference_no' => 'QT1', 'title' => 'TITLE', 'status' => 'open', 'procurement_type' => 'quotation',
        'scraped_at' => '2026-07-07T12:00:00.000Z', 'source' => 'myprocurement', 'source_id' => '1',
        'source_url' => 'https://myprocurement.treasury.gov.my/1',
    ], $o));
}

it('creates a tender with its source, field codes and provenance', function () {
    app(Merger::class)->merge([patch(['closing_date' => '2026-07-17', 'field_codes' => ['E05', 'E32'], 'indicative_price_sen' => 100])]);

    $t = CollectedTender::sole();
    expect($t->dedup_key)->toBe('QT1')
        ->and($t->closing_date->toDateString())->toBe('2026-07-17')
        ->and($t->fieldCodes->pluck('code')->all())->toBe(['E05', 'E32'])
        ->and($t->sources->pluck('source_url')->all())->toBe(['https://myprocurement.treasury.gov.my/1'])
        ->and($t->field_updated_at['closing_date'])->toBe('2026-07-07T12:00:00.000Z')
        ->and($t->events)->toBe([])
        ->and($t->scraped_at->toIso8601ZuluString())->toBe('2026-07-07T12:00:00Z');
});

it('lets a newer observation update a field', function () {
    app(Merger::class)->merge([
        patch(['closing_date' => '2026-07-17']),
        patch(['closing_date' => '2026-07-24', 'scraped_at' => '2026-07-08T12:00:00.000Z']),
    ]);

    expect(CollectedTender::sole()->closing_date->toDateString())->toBe('2026-07-24');
});

it('never lets an older observation overwrite a newer one', function () {
    app(Merger::class)->merge([
        patch(['title' => 'NEW', 'scraped_at' => '2026-07-08T12:00:00.000Z']),
        patch(['title' => 'OLD', 'scraped_at' => '2026-07-07T12:00:00.000Z']),
    ]);

    expect(CollectedTender::sole()->title)->toBe('NEW');
});

it('never lets "no information" wipe out a known value', function () {
    app(Merger::class)->merge([
        patch(['status' => 'closed', 'winners' => [['name' => 'ACME', 'price_sen' => 5]], 'ministry' => 'KKM']),
        patch(['status' => 'closed', 'winners' => null, 'ministry' => null, 'scraped_at' => '2026-07-09T00:00:00.000Z']),
    ]);

    $t = CollectedTender::sole();
    expect($t->winners)->toBe([['name' => 'ACME', 'price_sen' => 5]])->and($t->ministry)->toBe('KKM');
});

it('leaves fields a job did not observe untouched', function () {
    app(Merger::class)->merge([
        patch(['closing_date' => '2026-07-17', 'field_codes' => ['E05']]),
        patch(['status' => 'closed', 'winners' => [['name' => 'A', 'price_sen' => 1]], 'scraped_at' => '2026-07-20T00:00:00.000Z']),
    ]);

    $t = CollectedTender::sole();
    expect($t->closing_date->toDateString())->toBe('2026-07-17')
        ->and($t->fieldCodes->pluck('code')->all())->toBe(['E05'])
        ->and($t->status)->toBe('closed');
});

it('records each source once and merges two sources listing the same reference', function () {
    app(Merger::class)->merge([
        patch(),
        patch(['source_url' => 'https://myprocurement.treasury.gov.my/1-moved', 'scraped_at' => '2026-07-08T00:00:00.000Z']),
        patch(['source' => 'span', 'source_id' => '9', 'source_url' => 'https://www.span.gov.my/tender/view/9']),
    ]);

    expect(CollectedTender::count())->toBe(1)
        ->and(CollectedTender::sole()->sources->pluck('source_url', 'source')->all())->toBe([
            'myprocurement' => 'https://myprocurement.treasury.gov.my/1-moved',
            'span' => 'https://www.span.gov.my/tender/view/9',
        ]);
});

it('replaces field codes when a newer observation lists different ones', function () {
    app(Merger::class)->merge([
        patch(['field_codes' => ['E05', 'E32']]),
        patch(['field_codes' => ['E05'], 'scraped_at' => '2026-07-08T00:00:00.000Z']),
    ]);

    expect(CollectedTender::sole()->fieldCodes->pluck('code')->all())->toBe(['E05']);
});
