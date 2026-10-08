<?php

use App\Collector\Parsers\MyProcurementParser;

const MP_NOW = '2026-07-07T12:00:00.000Z';

function mpCard(): string
{
    // Verbatim from tms-v2 test/parseListing.test.ts CARD_HTML.
    return <<<'HTML'
<div>
  <div x-data="{ selected: false, open: true }" class="flex flex-col">
    <div class="flex">
      <button x-on:click="selected = !selected; $dispatch('select-procurement', { id: 789195 })"></button>
    </div>
    <div class="flex-grow text-sm md:text-base break-words">
      <div>
        <div class="mx-4 px-4 py-2 inline-block rounded-md bg-primary/20">
          Tarikh Pelawaan: 07/07/2026
        </div>
        <div class="px-4 py-2 rounded-md">
          <span class="font-bold">No. Sebut Harga</span>: UTHM/54(KTKEM)/P/02/023/2026(1)
        </div>
        <div class="px-4 py-2 rounded-md text-justify font-bold text-primary uppercase">
          <a href="https://myprocurement.treasury.gov.my/advertisements/quotation/71ebb6ee">MAKMAL ELEKTRIK &amp; ELEKTRONIK 2</a>
        </div>
        <div x-show="open" class="flex flex-col w-full px-4">
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Kementerian:</div>
            <div class="w-full sm:w-2/3 uppercase">KEMENTERIAN PENDIDIKAN TINGGI</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Agensi:</div>
            <div class="w-full sm:w-2/3 uppercase">UNIVERSITI TUN HUSSEIN ONN MALAYSIA (UTHM)</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Kategori Perolehan:</div>
            <div class="w-full sm:w-2/3 uppercase">Perkhidmatan Bukan Perunding</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Kod Bidang:</div>
            <div class="w-full sm:w-2/3 uppercase">E05, E32</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Tarikh Tutup Pelawaan:</div>
            <div class="w-full sm:w-2/3 uppercase">17/07/2026</div>
          </div>
          <div class="flex flex-col sm:flex-row mt-2">
            <div class="w-full sm:w-1/3 font-bold align-top">Harga Indikatif Jabatan:</div>
            <div class="w-full sm:w-2/3 uppercase">RM 28,800.00</div>
          </div>
        </div>
        <div x-show="open" class="mt-2 w-full">
          <table class="w-full hidden md:block">
            <tr class="bg-primary/20"><th>Bil.</th><th>Perkara</th><th>Tarikh</th><th>Alamat</th></tr>
            <tr class="uppercase">
              <td>1.</td>
              <td>Lawatan Tapak</td>
              <td>10/07/2026</td>
              <td class="w-full">MAKMAL OR, BLOK A, STRIDE, KAJANG, SELANGOR</td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
HTML;
}

function mpResultsCard(string $rows = '<tr><td>1.</td><td>EVERLASTING LUCK SDN. BHD.</td><td>72,000.00</td></tr>'): string
{
    // Verbatim shell from tms-v2 test/parseResults.test.ts SINGLE_WINNER_CARD.
    return <<<HTML
<div x-data="{ selected: false, open: true }">
  <button x-on:click="\$dispatch('select-procurement', { id: 980576 })"></button>
  <div class="mx-4 px-4 py-2 inline-block rounded-md bg-primary/20">Tarikh Paparan Keputusan: 01/07/2026</div>
  <div class="px-4 py-2 rounded-md"><span class="font-bold">No. Sebut Harga</span>: 52000003</div>
  <div class="font-bold text-primary uppercase">
    <a href="https://myprocurement.treasury.gov.my/archive/results-quotation/f256becbbb44e73ed436120b9b0ab381">PERKHIDMATAN SEWAAN 45 UNIT RUMAH KELUARGA</a>
  </div>
  <div class="w-full flex flex-col px-4">
    <div class="font-bold align-top">Kementerian:</div><div>KEMENTERIAN PERTAHANAN</div>
    <div class="font-bold align-top">Agensi:</div><div>TENTERA LAUT DIRAJA MALAYSIA (TLDM)</div>
    <div class="font-bold align-top">Kategori Perolehan:</div><div>Perkhidmatan Bukan Perunding</div>
  </div>
  <table>
    <tr><th>Bil.</th><th>Nama Petender Berjaya</th><th>Harga Setuju Terima (RM)</th></tr>
    {$rows}
  </table>
</div>
HTML;
}

function mpFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/collector/{$name}.json")), true);
}

it('extracts every field from a listing card', function () {
    [$t] = MyProcurementParser::listing(mpCard(), 'open', 'quotation', MP_NOW);

    expect($t->source)->toBe('myprocurement')
        ->and($t->sourceId)->toBe('789195')
        ->and($t->sourceUrl)->toBe('https://myprocurement.treasury.gov.my/advertisements/quotation/71ebb6ee')
        ->and($t->referenceNo)->toBe('UTHM/54(KTKEM)/P/02/023/2026(1)')
        ->and($t->dedupKey)->toBe('UTHM/54(KTKEM)/P/02/023/2026(1)')
        ->and($t->title)->toBe('MAKMAL ELEKTRIK & ELEKTRONIK 2')
        ->and($t->status)->toBe('open')
        ->and($t->procurementType)->toBe('quotation')
        ->and($t->get('ministry'))->toBe('KEMENTERIAN PENDIDIKAN TINGGI')
        ->and($t->get('agency'))->toBe('UNIVERSITI TUN HUSSEIN ONN MALAYSIA (UTHM)')
        ->and($t->get('category'))->toBe('Perkhidmatan Bukan Perunding')
        ->and($t->get('field_codes'))->toBe(['E05', 'E32'])
        ->and($t->get('advertised_date'))->toBe('2026-07-07')
        ->and($t->get('closing_date'))->toBe('2026-07-17')
        ->and($t->get('indicative_price_sen'))->toBe(2880000)
        ->and($t->get('events'))->toBe([['label' => 'Lawatan Tapak', 'date' => '2026-07-10', 'address' => 'MAKMAL OR, BLOK A, STRIDE, KAJANG, SELANGOR']])
        ->and($t->get('raw')['No. Sebut Harga'])->toBe('UTHM/54(KTKEM)/P/02/023/2026(1)')
        ->and($t->get('raw')['Harga Indikatif Jabatan'])->toBe('RM 28,800.00')
        ->and($t->scrapedAt)->toBe(MP_NOW);
});

it('tags status and type from the job, not the page', function () {
    [$t] = MyProcurementParser::listing(mpCard(), 'closed', 'tender', MP_NOW);
    expect($t->status)->toBe('closed')->and($t->procurementType)->toBe('tender');
});

it('skips cards without a title link, and ignores non-card x-data wrappers', function () {
    $noLink = preg_replace('/<a href="[^"]*">.*?<\/a>/s', '', mpCard());
    expect(MyProcurementParser::listing($noLink, 'open', 'quotation', MP_NOW))->toBe([]);

    $withPager = '<div x-data="{ page: 1 }">pager</div>'.mpCard();
    expect(MyProcurementParser::listing($withPager, 'open', 'quotation', MP_NOW))->toHaveCount(1);
});

it('defaults a missing event address to null and falls back the dedup key', function () {
    $html = str_replace('<td class="w-full">MAKMAL OR, BLOK A, STRIDE, KAJANG, SELANGOR</td>', '<td class="w-full"></td>', mpCard());
    $html = str_replace(': UTHM/54(KTKEM)/P/02/023/2026(1)', ':', $html);
    [$t] = MyProcurementParser::listing($html, 'open', 'quotation', MP_NOW);

    expect($t->get('events')[0]['address'])->toBeNull()
        ->and($t->referenceNo)->toBe('')
        ->and($t->dedupKey)->toBe('myprocurement:789195');
});

it('parses every card in each saved listing page', function (string $file, string $status, string $type) {
    $page = mpFixture($file);
    $patches = MyProcurementParser::listing($page['html'], $status, $type, MP_NOW);
    preg_match_all("/select-procurement'?,?\s*\{\s*id:\s*(\d+)/", $page['html'], $m);

    expect($patches)->not->toBeEmpty()
        ->and(collect($patches)->pluck('sourceId')->sort()->values()->all())->toBe(collect($m[1])->unique()->sort()->values()->all());
    foreach ($patches as $t) {
        expect($t->status)->toBe($status)->and($t->procurementType)->toBe($type);
    }
})->with([
    ['open-quotation-p1', 'open', 'quotation'],
    ['open-tender-p1', 'open', 'tender'],
    ['open-requisition-p1', 'open', 'requisition'],
    ['archive-quotation-p1', 'closed', 'quotation'],
]);

it('extracts a results card with a single winner and only the fields results pages carry', function () {
    [$t] = MyProcurementParser::results(mpResultsCard(), 'quotation', MP_NOW);

    expect($t->sourceId)->toBe('980576')
        ->and($t->referenceNo)->toBe('52000003')
        ->and($t->status)->toBe('closed')
        ->and($t->procurementType)->toBe('quotation')
        ->and($t->get('ministry'))->toBe('KEMENTERIAN PERTAHANAN')
        ->and($t->get('agency'))->toBe('TENTERA LAUT DIRAJA MALAYSIA (TLDM)')
        ->and($t->get('winners'))->toBe([['name' => 'EVERLASTING LUCK SDN. BHD.', 'price_sen' => 7200000]])
        ->and($t->has('field_codes'))->toBeFalse()
        ->and($t->has('closing_date'))->toBeFalse()
        ->and($t->has('indicative_price_sen'))->toBeFalse();
});

it('parses multi-lot winners', function () {
    $rows = '<tr><td>1.</td><td>DOUBLE R ENTERPRISE</td><td>15,000.00</td></tr><tr><td>2.</td><td>IMPIAN BENTARA</td><td>429,782.20</td></tr>';
    [$t] = MyProcurementParser::results(mpResultsCard($rows), 'tender', MP_NOW);

    expect($t->procurementType)->toBe('tender')
        ->and($t->get('winners'))->toBe([
            ['name' => 'DOUBLE R ENTERPRISE', 'price_sen' => 1500000],
            ['name' => 'IMPIAN BENTARA', 'price_sen' => 42978220],
        ]);
});

it('parses every card in the saved results page, each with winners', function () {
    $page = mpFixture('results-quotation-p1');
    $patches = MyProcurementParser::results($page['html'], 'quotation', MP_NOW);
    // Each card's id appears twice (select + select-all), so compare unique ids like tms-v2 does.
    preg_match_all("/select-procurement'?,?\s*\{\s*id:\s*(\d+)/", $page['html'], $m);

    expect(collect($patches)->pluck('sourceId')->all())->toBe(array_values(array_unique($m[1])));
    foreach ($patches as $t) {
        expect($t->status)->toBe('closed')->and($t->get('winners'))->not->toBeEmpty();
    }
});

it('returns nothing (never throws) for unrelated html', function () {
    expect(MyProcurementParser::listing('<p>maintenance</p>', 'open', 'tender', MP_NOW))->toBe([])
        ->and(MyProcurementParser::results('', 'tender', MP_NOW))->toBe([]);
});
