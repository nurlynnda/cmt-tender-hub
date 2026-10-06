<?php

use App\Collector\Legacy\{LegacyTenderMapper, LegacyTenderSource};
use App\Models\{CollectedTender, CollectedTenderSource};

function legacyDoc(array $o = []): array
{
    return array_merge([
        '_id' => 'QT260000000021376', 'dedupKey' => 'QT260000000021376', 'referenceNo' => 'QT260000000021376',
        'title' => 'BEKALAN UBAT', 'status' => 'closed', 'procurementType' => 'quotation',
        'ministry' => 'KEMENTERIAN KESIHATAN', 'agency' => 'HKL', 'category' => 'Bekalan',
        'fieldCodes' => ['Tiada Maklumat'], 'advertisedDate' => '2026-07-16', 'closingDate' => '2026-07-23',
        'indicativePrice' => 1234.5, 'currency' => 'MYR',
        'events' => [['label' => 'Taklimat', 'date' => '2026-07-18', 'address' => null]],
        'winners' => [['name' => 'KABIMAS MANUFACTURING SDN. BHD.', 'price' => 140520]],
        'raw' => ['Kementerian' => 'KEMENTERIAN KESIHATAN'], 'scrapedAt' => '2026-07-24T04:01:00.000Z',
        'sources' => [['source' => 'myprocurement', 'sourceId' => '980576', 'sourceUrl' => 'https://myprocurement.treasury.gov.my/x']],
        '_provenance' => ['closingDate' => '2026-07-16T04:01:00.000Z', 'winners' => '2026-07-24T04:01:00.000Z'],
    ], $o);
}

function bindLegacy(array $docs): void
{
    app()->instance(LegacyTenderSource::class, new class($docs) implements LegacyTenderSource
    {
        public function __construct(private array $docs) {}

        public function count(): int { return count($this->docs); }

        public function documents(): iterable { yield from $this->docs; }
    });
}

it('maps an old document, converting ringgit to sen and camelCase to columns', function () {
    $m = LegacyTenderMapper::map(legacyDoc());

    expect($m['tender']['dedup_key'])->toBe('QT260000000021376')
        ->and($m['tender']['indicative_price_sen'])->toBe(123450)
        ->and(json_decode($m['tender']['winners'], true))->toBe([['name' => 'KABIMAS MANUFACTURING SDN. BHD.', 'price_sen' => 14052000]])
        ->and(json_decode($m['tender']['field_updated_at'], true))->toBe(['closing_date' => '2026-07-16T04:01:00.000Z', 'winners' => '2026-07-24T04:01:00.000Z'])
        ->and($m['tender']['scraped_at'])->toBe('2026-07-24 04:01:00')
        ->and($m['sources'])->toBe([['source' => 'myprocurement', 'source_id' => '980576', 'source_url' => 'https://myprocurement.treasury.gov.my/x']])
        ->and($m['codes'])->toBe(['Tiada Maklumat']);
});

it('keeps nulls as nulls (no winners yet, no price)', function () {
    $m = LegacyTenderMapper::map(legacyDoc(['winners' => null, 'indicativePrice' => null, 'closingDate' => null]));

    expect($m['tender']['winners'])->toBeNull()
        ->and($m['tender']['indicative_price_sen'])->toBeNull()
        ->and($m['tender']['closing_date'])->toBeNull();
});

it('imports in batches, prints counts per source, and is safe to run twice', function () {
    bindLegacy([
        legacyDoc(),
        legacyDoc(['_id' => 'SPAN/1', 'dedupKey' => 'SPAN/1', 'referenceNo' => 'SPAN/1', 'sources' => [['source' => 'span', 'sourceId' => '1', 'sourceUrl' => 'https://www.span.gov.my/tender/view/1']]]),
        legacyDoc(['_id' => 'K1', 'dedupKey' => 'K1', 'referenceNo' => 'K1', 'fieldCodes' => [], 'sources' => [['source' => 'kwsp', 'sourceId' => 'k1', 'sourceUrl' => 'https://www.kwsp.gov.my/x']]]),
    ]);

    $this->artisan('collector:import-legacy', ['--batch' => 2])
        ->expectsOutputToContain('Imported 3 tenders')
        ->expectsOutputToContain('myprocurement: 1')
        ->expectsOutputToContain('kwsp: 1')
        ->assertSuccessful();
    $this->artisan('collector:import-legacy', ['--batch' => 2])->assertSuccessful();

    expect(CollectedTender::count())->toBe(3)
        ->and(CollectedTenderSource::count())->toBe(3)
        ->and(CollectedTender::firstWhere('dedup_key', 'QT260000000021376')->fieldCodes->pluck('code')->all())->toBe(['Tiada Maklumat']);
});

it('skips a broken document and reports it', function () {
    bindLegacy([legacyDoc(['title' => null]), legacyDoc(['_id' => 'OK', 'dedupKey' => 'OK'])]);

    $this->artisan('collector:import-legacy')->expectsOutputToContain('Skipped 1')->assertSuccessful();

    expect(CollectedTender::pluck('dedup_key')->all())->toBe(['OK']);
});
