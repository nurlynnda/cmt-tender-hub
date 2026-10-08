<?php

namespace App\Collector\Sources;

use App\Collector\{CollectorException, CollectorSource, Fetcher};
use App\Collector\Parsers\MyProcurementParser;
use App\Collector\Support\Text;
use Closure;

final class MyProcurementSource implements CollectorSource
{
    private const BASE_URL = 'https://myprocurement.treasury.gov.my/procurements/fetch';

    private const JOBS = [
        ['kind' => 'open', 'type' => 'advertisements', 'category' => 'quotation', 'procurement' => 'quotation'],
        ['kind' => 'open', 'type' => 'advertisements', 'category' => 'tender', 'procurement' => 'tender'],
        ['kind' => 'open', 'type' => 'advertisements', 'category' => 'requisition', 'procurement' => 'requisition'],
        ['kind' => 'results', 'type' => 'results', 'category' => 'quotation', 'procurement' => 'quotation'],
        ['kind' => 'results', 'type' => 'results', 'category' => 'tender', 'procurement' => 'tender'],
    ];

    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'myprocurement';
    }

    public function collect(string $scope, Closure $onBatch): int
    {
        $total = 0;
        $openTotal = 0;
        foreach (self::JOBS as $job) {
            if ($scope === 'open' && $job['kind'] !== 'open') {
                continue;
            }
            $page = 1;
            do {
                $url = self::BASE_URL."?page={$page}&itemsPerPage=100&type={$job['type']}&category={$job['category']}";
                $body = $this->fetcher->getJson($url);
                if (! is_string($body['html'] ?? null) || ! is_int($body['lastPage'] ?? null) || $body['lastPage'] < 1) {
                    throw new CollectorException("MyProcurement gave an unexpected response for {$url}");
                }
                $now = Text::scrapedAtNow();
                $patches = $job['kind'] === 'open'
                    ? MyProcurementParser::listing($body['html'], 'open', $job['procurement'], $now)
                    : MyProcurementParser::results($body['html'], $job['procurement'], $now);
                $onBatch($patches);
                $total += count($patches);
                if ($job['kind'] === 'open') {
                    $openTotal += count($patches);
                }
                $page++;
            } while ($page <= $body['lastPage']);
        }

        if ($openTotal === 0) {
            throw new CollectorException('MyProcurement returned 0 tenders — page layout may have changed');
        }

        return $total;
    }
}
