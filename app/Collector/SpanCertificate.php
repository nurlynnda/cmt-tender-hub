<?php

namespace App\Collector;

/**
 * span.gov.my sends only its own certificate and omits DigiCert's intermediate, so a normal
 * HTTPS client can't verify it. We trust the normal public roots plus that one intermediate —
 * for SPAN requests only (see tms-v2 scrapers/span/digicertIntermediateCert.ts).
 */
final class SpanCertificate
{
    public static function bundlePath(): string
    {
        $path = storage_path('app/span-ca-bundle.pem');
        if (! is_file($path)) {
            $candidates = [openssl_get_cert_locations()['default_cert_file'] ?? '', '/etc/ssl/certs/ca-certificates.crt'];
            $system = collect($candidates)->first(fn ($f) => $f !== '' && is_file($f));
            file_put_contents($path, rtrim(file_get_contents($system))."\n".file_get_contents(resource_path('certs/span-digicert-intermediate.pem')));
        }

        return $path;
    }
}
