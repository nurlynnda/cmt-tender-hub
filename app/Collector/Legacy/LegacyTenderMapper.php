<?php

namespace App\Collector\Legacy;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Maps one tms-v2 Mongo tender document to rows for collected_tenders, its sources and field codes. */
final class LegacyTenderMapper
{
    private const COLUMN = [
        'referenceNo' => 'reference_no', 'procurementType' => 'procurement_type', 'fieldCodes' => 'field_codes',
        'advertisedDate' => 'advertised_date', 'closingDate' => 'closing_date',
        'indicativePrice' => 'indicative_price_sen', 'scrapedAt' => 'scraped_at',
    ];

    /** @return array{tender: array, sources: list<array>, codes: list<string>} */
    public static function map(array $d): array
    {
        $key = (string) ($d['_id'] ?? $d['dedupKey'] ?? '');
        if ($key === '' || trim((string) ($d['title'] ?? '')) === '' || ! in_array($d['status'] ?? null, ['open', 'closed'], true)) {
            throw new InvalidArgumentException("Unusable legacy document {$key}");
        }

        $sen = fn ($ringgit) => $ringgit === null ? null : (int) round(((float) $ringgit) * 100);
        $winners = isset($d['winners']) && is_array($d['winners'])
            ? array_map(fn ($w) => ['name' => (string) $w['name'], 'price_sen' => $sen($w['price'] ?? null)], $d['winners'])
            : null;
        $provenance = [];
        foreach ($d['_provenance'] ?? [] as $field => $at) {
            $provenance[self::COLUMN[$field] ?? $field] = (string) $at;
        }
        $now = now()->format('Y-m-d H:i:s');

        return [
            'tender' => [
                'dedup_key' => $key,
                'reference_no' => (string) ($d['referenceNo'] ?? ''),
                'title' => (string) $d['title'],
                'status' => $d['status'],
                'procurement_type' => $d['procurementType'] ?? null,
                'ministry' => $d['ministry'] ?? null,
                'agency' => $d['agency'] ?? null,
                'category' => $d['category'] ?? null,
                'advertised_date' => $d['advertisedDate'] ?? null,
                'closing_date' => $d['closingDate'] ?? null,
                'indicative_price_sen' => $sen($d['indicativePrice'] ?? null),
                'events' => json_encode($d['events'] ?? []),
                'winners' => $winners === null ? null : json_encode($winners),
                'raw' => json_encode((object) ($d['raw'] ?? [])),
                'field_updated_at' => json_encode((object) $provenance),
                'scraped_at' => isset($d['scrapedAt']) ? CarbonImmutable::parse($d['scrapedAt'])->utc()->format('Y-m-d H:i:s') : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'sources' => array_values(array_map(fn ($s) => [
                'source' => (string) $s['source'], 'source_id' => (string) $s['sourceId'], 'source_url' => (string) $s['sourceUrl'],
            ], array_filter($d['sources'] ?? [], fn ($s) => preg_match('#^https?://#i', (string) ($s['sourceUrl'] ?? ''))))),
            'codes' => array_values(array_unique(array_map('strval', $d['fieldCodes'] ?? []))),
        ];
    }
}
