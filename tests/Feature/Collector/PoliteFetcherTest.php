<?php

use App\Collector\CollectorException;
use App\Collector\PoliteFetcher;
use App\Collector\SpanCertificate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(fn () => Sleep::fake());

function fetcher(): PoliteFetcher
{
    return new PoliteFetcher(random: fn () => 0.5); // jitter = 100 ms → 400 ms pause
}

it('pauses politely, identifies itself and returns the body', function () {
    Http::fake(['*' => Http::response(['html' => '<p/>', 'lastPage' => 1])]);

    expect(fetcher()->getJson('https://example.test/a'))->toBe(['html' => '<p/>', 'lastPage' => 1]);

    Sleep::assertSequence([Sleep::for(400)->milliseconds()]);
    Http::assertSent(fn ($r) => $r->hasHeader('User-Agent', 'CMTTenderHub/1.0') && $r->hasHeader('Accept', 'application/json'));
});

it('asks for HTML in text mode', function () {
    Http::fake(['*' => Http::response('<html>ok</html>')]);

    expect(fetcher()->getText('https://example.test/b'))->toBe('<html>ok</html>');
    Http::assertSent(fn ($r) => $r->hasHeader('Accept', 'text/html'));
});

it('retries failures with growing back-off, then gives up', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect(fn () => fetcher()->getText('https://example.test/c'))->toThrow(CollectorException::class, 'after 3 attempts');

    Sleep::assertSequence([
        Sleep::for(400)->milliseconds(), Sleep::for(1000)->milliseconds(),
        Sleep::for(400)->milliseconds(), Sleep::for(4000)->milliseconds(),
        Sleep::for(400)->milliseconds(),
    ]);
    Http::assertSentCount(3);
});

it('waits as long as Retry-After says, and the first such wait is free', function () {
    Http::fake(['*' => Http::sequence()
        ->push('', 429, ['Retry-After' => '7'])
        ->push('', 503)
        ->push('<p>ok</p>', 200)]);

    expect(fetcher()->getText('https://example.test/d'))->toBe('<p>ok</p>');

    Sleep::assertSequence([
        Sleep::for(400)->milliseconds(), Sleep::for(7000)->milliseconds(),                                  // 429: free wait
        Sleep::for(400)->milliseconds(), Sleep::for(60000)->milliseconds(), Sleep::for(1000)->milliseconds(), // 503: penalty + back-off
        Sleep::for(400)->milliseconds(),
    ]);
});

it('treats a connection failure like any other failed attempt', function () {
    Http::fake(['*' => Http::sequence()->pushFailedConnection()->push('<p>ok</p>')]);

    expect(fetcher()->getText('https://example.test/e'))->toBe('<p>ok</p>');
});

it('rejects a body that is not JSON in JSON mode', function () {
    Http::fake(['*' => Http::response('<html>maintenance</html>')]);

    fetcher()->getJson('https://example.test/f');
})->throws(CollectorException::class, 'not JSON');

it('trusts SPAN\'s extra certificate only on the SPAN downloader', function () {
    $bundle = file_get_contents(SpanCertificate::bundlePath());

    expect(PoliteFetcher::forSpan()->httpOptions()['verify'])->toBe(SpanCertificate::bundlePath())
        ->and($bundle)->toContain(trim(file_get_contents(resource_path('certs/span-digicert-intermediate.pem'))))
        ->and(substr_count($bundle, 'BEGIN CERTIFICATE'))->toBeGreaterThan(50)
        ->and((new PoliteFetcher)->httpOptions())->toBe([]);
});

it('trusts SPAN\'s current GeoTrust intermediate too (SPAN switched certificates in 2026)', function () {
    expect(file_get_contents(SpanCertificate::bundlePath()))
        ->toContain(trim(file_get_contents(resource_path('certs/span-geotrust-tls-rsa-ca-g1.pem'))));
});

it('rebuilds the SPAN trust file when it is out of date', function () {
    file_put_contents(storage_path('app/span-ca-bundle.pem'), "stale bundle from an older version\n");

    expect(file_get_contents(SpanCertificate::bundlePath()))
        ->not->toContain('stale bundle')
        ->toContain(trim(file_get_contents(resource_path('certs/span-geotrust-tls-rsa-ca-g1.pem'))));
});

it('keeps the underlying reason when a download fails', function () {
    Http::fake(['*' => Http::failedConnection('cURL error 60: SSL certificate problem')]);

    expect(fn () => fetcher()->getText('https://example.test/g'))
        ->toThrow(CollectorException::class, 'SSL certificate problem');
});
