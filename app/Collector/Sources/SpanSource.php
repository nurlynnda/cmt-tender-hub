<?php

namespace App\Collector\Sources;

use App\Collector\{CollectorException, CollectorSource, Fetcher};
use App\Collector\Parsers\SpanParser;
use App\Collector\Support\Text;
use App\Support\MalaysiaTime;
use Closure;
use Illuminate\Support\Facades\Log;

final class SpanSource implements CollectorSource
{
    public function __construct(private Fetcher $fetcher) {}

    public function name(): string
    {
        return 'span';
    }

    public function collect(string $scope, Closure $onBatch): int
    {
        $patches = SpanParser::listing(
            $this->fetcher->getText('https://www.span.gov.my/tender/'.MalaysiaTime::now()->year),
            Text::scrapedAtNow(),
        );
        $onBatch($patches);

        if ($scope === 'daily') {
            foreach ($patches as $patch) {
                if ($patch->status !== 'closed') {
                    continue;
                }
                try {
                    $winners = SpanParser::winners($this->fetcher->getText($patch->sourceUrl));
                } catch (CollectorException $e) {
                    Log::warning("[span] skipping detail page {$patch->sourceUrl}: ".$e->getMessage());

                    continue;
                }
                $onBatch([$patch->with(['winners' => $winners !== [] ? $winners : null])]);
            }
        }

        return count($patches);
    }
}
