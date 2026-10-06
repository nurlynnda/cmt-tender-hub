<?php

use App\Collector\Parsers\LlmParser;

const LLM_NOW = '2026-07-16T12:00:00.000Z';
const LLM_URL = 'https://www.llm.gov.my/swasta/tender_detail/12543/';

function llmDetail(array $o = []): string
{
    // Same template as tms-v2 test/llmParseDetail.test.ts detailHtml().
    $o += ['title' => 'T', 'saleStart' => '20.07.2026', 'jenis' => 'Tender', 'kategori' => 'Kerja',
        'dipelawaKepada' => 'Syarikat-syarikat yang berdaftar dengan SSM', 'syaratPendaftaran' => '-',
        'closingDate' => '2026-10-14', 'lawatanTapak' => '-'];

    return <<<HTML
<div class="panel clear-padding" id="tender-table-head">
    <div class="panel-content" id="tender-printarea">
      <header style="font-weight: bold;">{$o['title']}<div style="float:right"><a href="#"><i class="fa fa-print"></i></a></div></header>
      <table class="tender-content"><tbody>
        <tr><td style="font-weight: bold;">Tarikh Mula Jualan Dokumen</td><td>{$o['saleStart']}</td></tr>
        <tr><td style="font-weight: bold;">Tarikh Tamat Jualan Dokumen</td><td>14.09.2026</td></tr>
        <tr><td style="font-weight: bold;">Tender / Sebutharga Adalah Dipelawa kepada</td><td>{$o['dipelawaKepada']}</td></tr>
        <tr><td style="font-weight: bold;">Jenis</td><td>{$o['jenis']}</td></tr>
        <tr><td style="font-weight: bold;">Kategori</td><td>{$o['kategori']}</td></tr>
        <tr><td style="font-weight: bold;">Syarat Pendaftaran</td><td>{$o['syaratPendaftaran']}</td></tr>
        <tr><td style="font-weight: bold;">Lawatan Tapak</td><td>{$o['lawatanTapak']}</td></tr>
        <tr><td style="font-weight: bold;">Tarikh dan Waktu Tutup</td><td>{$o['closingDate']}</td></tr>
      </tbody></table>
    </div>
  </div>
HTML;
}

function llmParse(array $o = [], string $status = 'open')
{
    return LlmParser::detail(llmDetail($o), '12543', LLM_URL, $status, LLM_NOW);
}

it('reads listing links, stripping the #fragment and de-duplicating', function () {
    $row = '<div id="tender-table-head"><table><tbody><tr><td><a href="https://www.llm.gov.my/swasta/tender_detail/12543/#tender-table-head"><header>TITLE ONE</header></a></td></tr></tbody></table></div>';

    expect(LlmParser::listing($row.$row))->toBe([['source_id' => '12543', 'source_url' => 'https://www.llm.gov.my/swasta/tender_detail/12543/']])
        ->and(LlmParser::listing('<div></div>'))->toBe([]);
});

it('parses the saved open listing page: 6 links', function () {
    $links = LlmParser::listing(file_get_contents(base_path('tests/Fixtures/collector/llm-tender_tawaran.html')));

    expect($links)->toHaveCount(6);
    foreach ($links as $l) {
        expect($l['source_url'])->toMatch('#^https://www\.llm\.gov\.my/swasta/tender_detail/\d+/$#');
    }
});

it('reads result rows with the winner', function () {
    $html = '<div id="tender-table-head"><table><tbody><tr><th>Tajuk</th><th>Kontraktor</th><th>Nilai</th></tr><tr>'
        ."<td><a href=\"\n   https://www.llm.gov.my/swasta/tender_detail/12526#tender-table-head\"><header>T</header></a></td>"
        ."<td>D'FA PRINT SDN BHD</td><td>RM 62180.00</td></tr></tbody></table></div>";
    $empty = str_replace(["D'FA PRINT SDN BHD", 'RM 62180.00'], ['', ''], $html);

    expect(LlmParser::results($html))->toBe([[
        'source_id' => '12526', 'source_url' => 'https://www.llm.gov.my/swasta/tender_detail/12526',
        'winner' => ['name' => "D'FA PRINT SDN BHD", 'price_sen' => 6218000],
    ]])->and(LlmParser::results($empty)[0]['winner'])->toBeNull();
});

it('parses the saved results page: 6 rows each with a winner', function () {
    $rows = LlmParser::results(file_get_contents(base_path('tests/Fixtures/collector/llm-tender_keputusan.html')));

    expect($rows)->toHaveCount(6);
    foreach ($rows as $r) {
        expect($r['winner'])->not->toBeNull();
    }
});

it('extracts every field from a detail page', function () {
    $t = llmParse(['title' => 'TAWARAN REQUEST FOR PROPOSAL (RFP) BAGI CADANGAN']);

    expect($t->source)->toBe('llm')
        ->and($t->sourceId)->toBe('12543')
        ->and($t->title)->toBe('TAWARAN REQUEST FOR PROPOSAL (RFP) BAGI CADANGAN')
        ->and($t->status)->toBe('open')
        ->and($t->procurementType)->toBe('tender')
        ->and($t->get('agency'))->toBe('Lembaga Lebuhraya Malaysia (LLM)')
        ->and($t->get('category'))->toBe('Kerja')
        ->and($t->get('advertised_date'))->toBe('2026-07-20')
        ->and($t->get('closing_date'))->toBe('2026-10-14')
        ->and($t->get('raw')['Jenis'])->toBe('Tender')
        ->and($t->dedupKey)->toBe('llm:12543');
});

it('extracts reference numbers in all three title shapes', function (string $title, string $ref) {
    $t = llmParse(['title' => $title, 'jenis' => 'Sebut Harga']);
    expect($t->referenceNo)->toBe($ref)->and($t->dedupKey)->toBe($ref)->and($t->procurementType)->toBe('quotation');
})->with([
    ['(NO. SEBUT HARGA: LLM/KEW/SH:9/6/2026) - SEBUT HARGA BAGI PERKHIDMATAN', 'LLM/KEW/SH:9/6/2026'],
    ['NO. SEBUT HARGA: LLM/KEW/SH:4/4/2026 - PERKHIDMATAN MEREKA BENTUK, MENTERJEMAH, MENCETAK DAN MEMBEKAL BUKU', 'LLM/KEW/SH:4/4/2026'],
    ['NO. SEBUT HARGA: LLM/KEW/SH:3/2/2026 SEBUT HARGA PERKHIDMATAN PEMULIHAN BENCANA', 'LLM/KEW/SH:3/2/2026'],
]);

it('maps an unknown Jenis to null and honours the given status', function () {
    expect(llmParse(['jenis' => 'Lain-lain'])->procurementType)->toBeNull()
        ->and(llmParse([], 'closed')->status)->toBe('closed');
});

it('extracts field codes from the registration text', function () {
    expect(llmParse(['dipelawaKepada' => 'Pelawaan adalah terbuka kepada Syarikat Bumiputera dan Bukan Bumiputera yang berkelayakan dan berdaftar dengan Kementerian Kewangan Malaysia di bawah Kod Bidang: 2221302 - (Rakaman) 221304 - (Audio Visual) atau 221303 - (Fotografi) yang mana pendaftarannya masih berkuatkuasa.'])->get('field_codes'))
        ->toBe(['2221302', '221304', '221303'])
        ->and(llmParse(['dipelawaKepada' => 'Berdaftar dengan Kementerian Kewangan Malaysia (MOF) Kod Bidang 210103 Dan Sijil Pematuhan Cukai'])->get('field_codes'))->toBe(['210103'])
        ->and(llmParse(['dipelawaKepada' => 'Syarikat berdaftar dengan SSM', 'syaratPendaftaran' => 'Wajib berdaftar di bawah Kod Bidang: 040101 - (Elektrik) sahaja.'])->get('field_codes'))->toBe(['040101'])
        ->and(llmParse()->get('field_codes'))->toBe([])
        ->and(LlmParser::fieldCodes('Kod Bidang: 040101 - (Elektrik). Sila rujuk juga Kod Bidang: 040101 - (Elektrik) di atas.'))->toBe(['040101'])
        ->and(LlmParser::fieldCodes('KOD BIDANG: 040101 - (Elektrik) DAN 040102 - (Mekanikal)'))->toBe(['040101', '040102']);
});

it('turns Lawatan Tapak into an event, relabelled when a briefing is mentioned', function () {
    $address = 'Auditorium, Blok B, Ibu Pejabat, LLM, Kajang, Selangor.';

    expect(llmParse(['lawatanTapak' => "Tarikh: 20-07-2026  Tempat: {$address}  Masa: 10:00 AM"])->get('events'))
        ->toBe([['label' => 'Lawatan Tapak', 'date' => '2026-07-20', 'address' => $address]])
        ->and(llmParse([
            'syaratPendaftaran' => 'Taklimat Tender: Tarikh: 6 Julai 2026 Masa: 11.00 Pagi (KEHADIRAN TAKLIMAT TENDER ADALAH DIWAJIBKAN)',
            'lawatanTapak' => "Tarikh: 06-07-2026  Tempat: {$address}  Masa: 11:00 AM",
        ])->get('events'))->toBe([['label' => 'Taklimat & Lawatan Tapak', 'date' => '2026-07-06', 'address' => $address]])
        ->and(llmParse(['lawatanTapak' => '-'])->get('events'))->toBe([])
        ->and(llmParse(['lawatanTapak' => 'Tarikh: Tempat: - Masa: 8:00 AM'])->get('events'))->toBe([]);
});

it('returns null for a non-tender page or an empty title', function () {
    expect(LlmParser::detail('<div>not a tender page</div>', '1', LLM_URL, 'open', LLM_NOW))->toBeNull()
        ->and(llmParse(['title' => '']))->toBeNull();
});

it('parses the saved detail page 12540', function () {
    $t = LlmParser::detail(file_get_contents(base_path('tests/Fixtures/collector/llm-tender_detail_12540.html')),
        '12540', 'https://www.llm.gov.my/swasta/tender_detail/12540/', 'open', LLM_NOW);

    expect($t->title)->toBe('PERKHIDMATAN LESEN PENGOPERASIAN PUSAT DATA DI LEMBAGA LEBUHRAYA MALAYSIA BAGI TEMPOH TIGA (3) TAHUN (2026-2029)')
        ->and($t->procurementType)->toBe('tender')
        ->and($t->get('category'))->toBe('Bekalan Perkhidmatan')
        ->and($t->get('events'))->toBe([['label' => 'Taklimat & Lawatan Tapak', 'date' => '2026-07-06', 'address' => 'Auditorium, Blok B, Ibu Pejabat, LLM, Kajang, Selangor.']])
        ->and($t->get('field_codes'))->toBe(['210103'])
        ->and($t->get('advertised_date'))->toBe('2026-07-06')
        ->and($t->get('closing_date'))->toBe('2026-07-24');
});
