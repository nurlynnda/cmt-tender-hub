<?php

namespace App\Collector;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** Port of tms-v2 http/politeFetch.ts: serial, paced, retrying, rate-limit aware. */
final class PoliteFetcher implements Fetcher
{
    private const BASE_DELAY_MS = 300;
    private const JITTER_MS = 200;
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_MS = [1000, 4000, 16000];
    private const PENALTY_MS = 60000;

    public function __construct(private array $httpOptions = [], private ?Closure $random = null) {}

    public static function forSpan(): self
    {
        return new self(['verify' => SpanCertificate::bundlePath()]);
    }

    public function httpOptions(): array
    {
        return $this->httpOptions;
    }

    public function getJson(string $url): array
    {
        $data = json_decode($this->request($url, 'application/json'), true);
        if (! is_array($data)) {
            throw new CollectorException("Response was not JSON: {$url}");
        }

        return $data;
    }

    public function getText(string $url): string
    {
        return $this->request($url, 'text/html');
    }

    private function request(string $url, string $accept): string
    {
        $attempt = 0;
        $graceUsed = false;
        while ($attempt < self::MAX_ATTEMPTS) {
            Sleep::for(self::BASE_DELAY_MS + $this->jitter())->milliseconds();
            try {
                $response = Http::withOptions($this->httpOptions)
                    ->withHeaders(['User-Agent' => 'CMTTenderHub/1.0', 'Accept' => $accept])
                    ->timeout(60)
                    ->get($url);
                if ($response->successful()) {
                    return $response->body();
                }
                if (in_array($response->status(), [429, 503], true)) {
                    $retryAfter = (int) $response->header('Retry-After');
                    Sleep::for($retryAfter > 0 ? $retryAfter * 1000 : self::PENALTY_MS)->milliseconds();
                    if (! $graceUsed) {
                        $graceUsed = true; // the first rate-limit wait doesn't use up an attempt

                        continue;
                    }
                }
                $attempt++;
            } catch (ConnectionException) {
                $attempt++;
            }
            if ($attempt < self::MAX_ATTEMPTS) {
                Sleep::for(self::BACKOFF_MS[min($attempt - 1, count(self::BACKOFF_MS) - 1)])->milliseconds();
            }
        }

        throw new CollectorException('Download failed after '.self::MAX_ATTEMPTS." attempts: {$url}");
    }

    private function jitter(): int
    {
        $random = $this->random ?? fn () => mt_rand() / mt_getrandmax();

        return (int) floor($random() * self::JITTER_MS);
    }
}
