<?php

namespace App\Collector\Sources;

use App\Collector\{CollectorException, CollectorSource, Fetcher};
use App\Collector\Parsers\LlmParser;
use App\Collector\Support\Text;
use Closure;
use Illuminate\Support\Facades\Log;

final class LlmSource implements CollectorSource
{
    private const OPEN_URL = 'https://www.llm.gov.my/swasta/tender_tawaran';
    private const RESULTS_URL = 'https://www.llm.gov.my/swasta/tender_keputusan';
    private const PAGE_SIZE = 6;
    private const MAX_PAGES = 50; // safety stop if the site ever repeats pages forever

    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'llm';
    }

    public function collect(string $scope, Closure $onBatch): int
    {
        $count = $this->detailPages($this->openLinks(), 'open', $onBatch);

        if ($scope === 'daily') {
            // Their results pagination past page 1 is broken (404s), so only the first page is read.
            $rows = LlmParser::results($this->fetcher->getText(self::RESULTS_URL.'/'));
            $count += $this->detailPages($rows, 'closed', $onBatch);
        }

        return $count;
    }

    private function openLinks(): array
    {
        $links = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $offset = $page * self::PAGE_SIZE;
            $html = $this->fetcher->getText(self::OPEN_URL.'/'.($offset === 0 ? '' : $offset));
            $pageLinks = LlmParser::listing($html);
            if ($pageLinks === []) {
                break;
            }
            array_push($links, ...$pageLinks);
        }

        return $links;
    }

    private function detailPages(array $links, string $status, Closure $onBatch): int
    {
        $count = 0;
        foreach ($links as $link) {
            try {
                $patch = LlmParser::detail(
                    $this->fetcher->getText($link['source_url']),
                    $link['source_id'], $link['source_url'], $status, Text::scrapedAtNow(),
                );
            } catch (CollectorException $e) {
                Log::warning("[llm] skipping detail page {$link['source_url']}: ".$e->getMessage());

                continue;
            }
            if ($patch === null) {
                continue;
            }
            if ($status === 'closed') {
                $patch = $patch->with(['winners' => ($link['winner'] ?? null) ? [$link['winner']] : null]);
            }
            $onBatch([$patch]);
            $count++;
        }

        return $count;
    }
}
