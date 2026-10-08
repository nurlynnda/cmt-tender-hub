<?php

namespace App\Collector;

/**
 * span.gov.my sends only its own certificate plus a mismatched intermediate, so a normal HTTPS
 * client can't verify it. We trust the normal public roots plus SPAN's intermediates
 * (resources/certs/span-*.pem) — for SPAN requests only. Verification stays fully on.
 *
 * - span-digicert-intermediate.pem: the one tms-v2 needed (DigiCert Global G2 TLS RSA SHA256 2020 CA1)
 * - span-geotrust-tls-rsa-ca-g1.pem: SPAN's current issuer since its 2026 certificate change,
 *   from http://cacerts.geotrust.com/GeoTrustTLSRSACAG1.crt (signed by DigiCert Global Root G2)
 */
final class SpanCertificate
{
    public static function bundlePath(): string
    {
        $path = storage_path('app/span-ca-bundle.pem');
        $wanted = self::bundleContents();
        if (! is_file($path) || file_get_contents($path) !== $wanted) {
            file_put_contents($path, $wanted); // rebuilt whenever a certificate is added or changed
        }

        return $path;
    }

    private static function bundleContents(): string
    {
        $candidates = [openssl_get_cert_locations()['default_cert_file'] ?? '', '/etc/ssl/certs/ca-certificates.crt'];
        $system = collect($candidates)->first(fn ($f) => $f !== '' && is_file($f));
        $extra = collect(glob(resource_path('certs/span-*.pem')))->sort()->map(fn ($f) => trim(file_get_contents($f)));

        return rtrim(file_get_contents($system))."\n".$extra->implode("\n")."\n";
    }
}
