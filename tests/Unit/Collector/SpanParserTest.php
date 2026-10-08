<?php

use App\Collector\Parsers\SpanParser;

const SPAN_NOW = '2026-07-09T12:00:00.000Z';

function spanCard(): string
{
    // Verbatim from tms-v2 test/spanParseListing.test.ts CARD_HTML.
    return <<<'HTML'
<div class="table-listing">
    <a href="https://www.span.gov.my/tender/view/188">
        <h3>SPAN/BKP/PROC/STM/26(8)</h3>
        CADANGAN UNTUK MELANTIK PERUNDING BAGI PELAKSANAAN KAJIAN HALA TUJU PELAN STRATEGIK ICT (GAP ANALYSIS) SURUHANJAYA PERKHIDMATAN AIR NEGARA (SPAN) 2026-2030 SECARA SEBUT HARGA TERBUKA<br>
        Tarikh Iklan 2026-06-22<br>
        Tarikh Tutup 2026-07-06 12:00PM<br>
        Maklumat Sebutharga:
            <span class="badge badge-warning">Diiklankan</span>
                </a>
</div>
HTML;
}

it('extracts every field from a listing card', function () {
    [$t] = SpanParser::listing(spanCard(), SPAN_NOW);

    expect($t->source)->toBe('span')
        ->and($t->sourceId)->toBe('188')
        ->and($t->sourceUrl)->toBe('https://www.span.gov.my/tender/view/188')
        ->and($t->referenceNo)->toBe('SPAN/BKP/PROC/STM/26(8)')
        ->and($t->title)->toBe('CADANGAN UNTUK MELANTIK PERUNDING BAGI PELAKSANAAN KAJIAN HALA TUJU PELAN STRATEGIK ICT (GAP ANALYSIS) SURUHANJAYA PERKHIDMATAN AIR NEGARA (SPAN) 2026-2030 SECARA SEBUT HARGA TERBUKA')
        ->and($t->status)->toBe('open')
        ->and($t->procurementType)->toBe('quotation')
        ->and($t->get('agency'))->toBe('Suruhanjaya Perkhidmatan Air Negara (SPAN)')
        ->and($t->get('advertised_date'))->toBe('2026-06-22')
        ->and($t->get('closing_date'))->toBe('2026-07-06')
        ->and($t->get('raw')['Status'])->toBe('Diiklankan');
    foreach (['ministry', 'category', 'field_codes', 'indicative_price_sen', 'events', 'winners'] as $f) {
        expect($t->has($f))->toBeFalse();
    }
});

it('maps Selesai and Dibatalkan badges to closed', function () {
    expect(SpanParser::listing(str_replace('badge-warning">Diiklankan', 'badge-info">Selesai', spanCard()), SPAN_NOW)[0]->status)->toBe('closed')
        ->and(SpanParser::listing(str_replace('Diiklankan', 'Dibatalkan', spanCard()), SPAN_NOW)[0]->status)->toBe('closed');
});

it('infers the type from the title', function () {
    expect(SpanParser::listing(str_replace('SECARA SEBUT HARGA TERBUKA', 'SECARA TENDER TERBUKA', spanCard()), SPAN_NOW)[0]->procurementType)->toBe('tender')
        ->and(SpanParser::listing(str_replace('SECARA SEBUT HARGA TERBUKA', 'UNTUK KEGUNAAN SURUHANJAYA', spanCard()), SPAN_NOW)[0]->procurementType)->toBeNull();
});

it('skips cards with no link or empty reference', function () {
    expect(SpanParser::listing(str_replace('href="https://www.span.gov.my/tender/view/188"', '', spanCard()), SPAN_NOW))->toBe([])
        ->and(SpanParser::listing(str_replace('<h3>SPAN/BKP/PROC/STM/26(8)</h3>', '<h3></h3>', spanCard()), SPAN_NOW))->toBe([]);
});

it('parses the saved 2026 page: 5 tenders, 3 open, 2 closed', function () {
    $patches = SpanParser::listing(file_get_contents(base_path('tests/Fixtures/collector/span-2026.html')), SPAN_NOW);

    expect($patches)->toHaveCount(5)
        ->and(collect($patches)->where('status', 'open'))->toHaveCount(3)
        ->and(collect($patches)->where('status', 'closed'))->toHaveCount(2);
});

it('reads winners from adjacent or colon-separated cells, ignoring bidder tables', function () {
    $adjacent = '<table><tr><td>Kod Penyebut Harga</td><td>Kos</td></tr><tr><td>Petender 1/4</td><td>RM150,377.47</td></tr></table>'
        .'<table><tr><td>Nama Pembekal</td><td><p>UMPSA SERVICES SDN BHD</p></td><td>Harga Tawaran</td><td><b><span>RM132,192.00</span></b><br></td></tr>'
        .'<tr><td>Tarikh Mula Kontrak</td><td>-</td><td>Tarikh Tamat Kontrak</td><td>-</td></tr></table>';
    $colon = '<table><tr><td>Nama Pembekal</td><td><div align="center">:</div></td><td><p>RANHILL CONSULTING SDN BHD</p></td>'
        .'<td>Harga Tawaran</td><td><div align="center">:</div></td><td>RM1,285,996.03</td></tr></table>';
    $multi = '<table><tr><td>Nama Pembekal</td><td>ALPHA ENGINEERING SDN BHD</td><td>Harga Tawaran</td><td>RM50,000.00</td></tr></table>'
        .'<table><tr><td>Nama Pembekal</td><td>BETA CONSTRUCTION SDN BHD</td><td>Harga Tawaran</td><td>RM75,500.50</td></tr></table>';

    expect(SpanParser::winners($adjacent))->toBe([['name' => 'UMPSA SERVICES SDN BHD', 'price_sen' => 13219200]])
        ->and(SpanParser::winners($colon))->toBe([['name' => 'RANHILL CONSULTING SDN BHD', 'price_sen' => 128599603]])
        ->and(SpanParser::winners($multi))->toBe([
            ['name' => 'ALPHA ENGINEERING SDN BHD', 'price_sen' => 5000000],
            ['name' => 'BETA CONSTRUCTION SDN BHD', 'price_sen' => 7550050],
        ]);
});

it('returns no winners for postponed, cancelled or unrelated pages', function () {
    $postponed = '<table><tr><td>Nama Pembekal</td><td>:</td><td colspan="4"><p>SEBUTHARGA DITANGGUHKAN</p></td></tr></table>';
    expect(SpanParser::winners($postponed))->toBe([])
        ->and(SpanParser::winners('<p>Dibatalkan <br>*Sebutharga terbatal</p>'))->toBe([])
        ->and(SpanParser::winners(''))->toBe([]);
});
