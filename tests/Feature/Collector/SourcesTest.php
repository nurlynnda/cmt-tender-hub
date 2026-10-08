<?php

use App\Collector\CollectorException;
use App\Collector\Parsers\{LlmParser, SpanParser};
use App\Collector\Sources\{LlmSource, MyProcurementSource, SpanSource};
use Carbon\CarbonImmutable;
use Tests\Support\FakeFetcher;

function fx(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/collector/{$name}"));
}

function mpUrl(string $type, string $category, int $page = 1): string
{
    return "https://myprocurement.treasury.gov.my/procurements/fetch?page={$page}&itemsPerPage=100&type={$type}&category={$category}";
}

function collectAll($source, string $scope): array
{
    $batches = [];
    $count = $source->collect($scope, function (array $batch) use (&$batches) { $batches[] = $batch; });

    return [$count, array_merge([], ...$batches)];
}

it('MyProcurement daily: pages through 3 open jobs and 2 results jobs', function () {
    $open = json_decode(fx('open-quotation-p1.json'), true);
    $results = json_decode(fx('results-quotation-p1.json'), true);
    $one = fn ($p) => ['html' => $p['html'], 'lastPage' => 1];
    $two = fn ($p) => ['html' => $p['html'], 'lastPage' => 2];
    $fetcher = new FakeFetcher([
        mpUrl('advertisements', 'quotation') => $two($open),
        mpUrl('advertisements', 'quotation', 2) => $two($open),
        mpUrl('advertisements', 'tender') => $one(json_decode(fx('open-tender-p1.json'), true)),
        mpUrl('advertisements', 'requisition') => $one(json_decode(fx('open-requisition-p1.json'), true)),
        mpUrl('results', 'quotation') => $one($results),
        mpUrl('results', 'tender') => $one($results),
    ]);

    [$count, $patches] = collectAll(new MyProcurementSource($fetcher), 'daily');

    expect($fetcher->urls)->toHaveCount(6)
        ->and($count)->toBe(count($patches))
        ->and(collect($patches)->where('status', 'closed')->pluck('procurementType')->unique()->sort()->values()->all())->toBe(['quotation', 'tender']);
});

it('MyProcurement open scope: open jobs only', function () {
    $page = fn ($f) => ['html' => json_decode(fx($f), true)['html'], 'lastPage' => 1];
    $fetcher = new FakeFetcher([
        mpUrl('advertisements', 'quotation') => $page('open-quotation-p1.json'),
        mpUrl('advertisements', 'tender') => $page('open-tender-p1.json'),
        mpUrl('advertisements', 'requisition') => $page('open-requisition-p1.json'),
    ]);

    collectAll(new MyProcurementSource($fetcher), 'open');

    expect($fetcher->urls)->toHaveCount(3);
});

it('MyProcurement flags a layout change when no open tenders are found at all', function () {
    $empty = ['html' => '<div>nothing</div>', 'lastPage' => 1];
    $fetcher = new FakeFetcher([
        mpUrl('advertisements', 'quotation') => $empty,
        mpUrl('advertisements', 'tender') => $empty,
        mpUrl('advertisements', 'requisition') => $empty,
    ]);

    collectAll(new MyProcurementSource($fetcher), 'open');
})->throws(CollectorException::class, 'returned 0 tenders');

it('MyProcurement rejects a response without html/lastPage', function () {
    $fetcher = new FakeFetcher([mpUrl('advertisements', 'quotation') => ['error' => 'x']]);

    collectAll(new MyProcurementSource($fetcher), 'open');
})->throws(CollectorException::class, 'unexpected response');

it('SPAN: current Malaysia year; daily also reads winners of closed tenders, skipping failed detail pages', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-31 16:30', 'UTC')); // already 2027 in Malaysia
    $listing = fx('span-2026.html');
    $closed = collect(SpanParser::listing($listing, 'x'))->where('status', 'closed')->values();
    $winnerHtml = '<table><tr><td>Nama Pembekal</td><td>ACME SDN BHD</td><td>Harga Tawaran</td><td>RM1,000.00</td></tr></table>';
    $fetcher = new FakeFetcher([
        'https://www.span.gov.my/tender/2027' => $listing,
        $closed[0]->sourceUrl => $winnerHtml,
        $closed[1]->sourceUrl => new CollectorException('boom'),
    ]);

    [$count, $patches] = collectAll(new SpanSource($fetcher), 'daily');

    expect($count)->toBe(5)
        ->and($fetcher->urls[0])->toBe('https://www.span.gov.my/tender/2027')
        ->and(collect($patches)->filter(fn ($p) => $p->has('winners'))->first()->get('winners'))
        ->toBe([['name' => 'ACME SDN BHD', 'price_sen' => 100000]]);

    $open = new FakeFetcher(['https://www.span.gov.my/tender/2027' => $listing]);
    collectAll(new SpanSource($open), 'open');
    expect($open->urls)->toHaveCount(1);
});

it('LLM daily: pages the open listing until empty, reads each detail, attaches winners from results', function () {
    $detail = fx('llm-tender_detail_12540.html');
    $listing = fx('llm-tender_tawaran.html');
    $links = LlmParser::listing($listing);
    $results = LlmParser::results(fx('llm-tender_keputusan.html'));
    $responses = [
        'https://www.llm.gov.my/swasta/tender_tawaran/' => $listing,
        'https://www.llm.gov.my/swasta/tender_tawaran/6' => '<div>no more</div>',
        'https://www.llm.gov.my/swasta/tender_keputusan/' => fx('llm-tender_keputusan.html'),
    ];
    foreach ([...$links, ...$results] as $l) {
        $responses[$l['source_url']] = $detail;
    }
    $responses[$links[0]['source_url']] = new CollectorException('one bad page');
    $fetcher = new FakeFetcher($responses);

    [$count, $patches] = collectAll(new LlmSource($fetcher), 'daily');

    $closed = collect($patches)->where('status', 'closed');
    expect($count)->toBe(11)                       // 6 open - 1 failed + 6 results
        ->and($closed)->toHaveCount(6)
        ->and($closed->first()->get('winners'))->toBe([$results[0]['winner']])
        ->and($fetcher->urls)->not->toContain('https://www.llm.gov.my/swasta/tender_keputusan/6');
});

it('LLM open scope skips the results listing', function () {
    $fetcher = new FakeFetcher([
        'https://www.llm.gov.my/swasta/tender_tawaran/' => '<div>none</div>',
    ]);

    [$count] = collectAll(new LlmSource($fetcher), 'open');

    expect($count)->toBe(0)->and($fetcher->urls)->toBe(['https://www.llm.gov.my/swasta/tender_tawaran/']);
});
