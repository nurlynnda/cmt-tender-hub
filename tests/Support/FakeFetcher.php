<?php

namespace Tests\Support;

use App\Collector\CollectorException;
use App\Collector\Fetcher;
use Throwable;

/** Test stand-in for the polite downloader: URL → canned response (array = JSON, string = HTML, Throwable = failure). */
final class FakeFetcher implements Fetcher
{
    public array $urls = [];

    public function __construct(private array $responses) {}

    public function getJson(string $url): array
    {
        return $this->respond($url);
    }

    public function getText(string $url): string
    {
        return $this->respond($url);
    }

    private function respond(string $url): mixed
    {
        $this->urls[] = $url;
        if (! array_key_exists($url, $this->responses)) {
            throw new CollectorException("No fake response for {$url}");
        }
        $r = $this->responses[$url];
        if ($r instanceof Throwable) {
            throw $r;
        }

        return $r;
    }
}
