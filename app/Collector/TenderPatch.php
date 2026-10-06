<?php

namespace App\Collector;

use InvalidArgumentException;

/** One observation of one tender by one collector job (port of tms-v2 TenderPatch). */
final class TenderPatch
{
    public const OPTIONAL = [
        'ministry', 'agency', 'category', 'field_codes', 'advertised_date', 'closing_date',
        'indicative_price_sen', 'events', 'winners', 'raw',
    ];

    private const REQUIRED = [
        'reference_no', 'title', 'status', 'procurement_type', 'scraped_at', 'source', 'source_id', 'source_url',
    ];

    private function __construct(
        public readonly string $dedupKey,
        public readonly string $referenceNo,
        public readonly string $title,
        public readonly string $status,
        public readonly ?string $procurementType,
        public readonly string $scrapedAt,
        public readonly string $source,
        public readonly string $sourceId,
        public readonly string $sourceUrl,
        public readonly array $observed,
    ) {}

    public static function dedupKey(string $referenceNo, string $fallback): string
    {
        $normalized = preg_replace('/\s+/u', '', mb_strtoupper($referenceNo));

        return $normalized !== '' ? $normalized : $fallback;
    }

    public static function make(array $a): self
    {
        foreach (self::REQUIRED as $key) {
            if (! array_key_exists($key, $a)) {
                throw new InvalidArgumentException("Missing {$key}");
            }
        }
        $unknown = array_diff(array_keys($a), [...self::REQUIRED, ...self::OPTIONAL, 'dedup_key']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown fields: '.implode(', ', $unknown));
        }
        if (trim((string) $a['title']) === '') {
            throw new InvalidArgumentException('Title is empty');
        }
        if (! in_array($a['status'], ['open', 'closed'], true)) {
            throw new InvalidArgumentException("Bad status {$a['status']}");
        }
        if (! in_array($a['procurement_type'], ['quotation', 'tender', 'requisition', null], true)) {
            throw new InvalidArgumentException('Bad procurement type');
        }
        if (trim((string) $a['source_id']) === '' || trim((string) $a['source']) === '') {
            throw new InvalidArgumentException('Missing source id');
        }
        if (filter_var($a['source_url'], FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Bad source url {$a['source_url']}");
        }
        foreach ($a['winners'] ?? [] as $w) {
            if (trim((string) ($w['name'] ?? '')) === '') {
                throw new InvalidArgumentException('Winner without a name');
            }
        }
        foreach ($a['events'] ?? [] as $e) {
            if (trim((string) ($e['label'] ?? '')) === '') {
                throw new InvalidArgumentException('Event without a label');
            }
        }

        $fallback = "{$a['source']}:{$a['source_id']}";

        return new self(
            dedupKey: $a['dedup_key'] ?? self::dedupKey((string) $a['reference_no'], $fallback),
            referenceNo: (string) $a['reference_no'],
            title: (string) $a['title'],
            status: $a['status'],
            procurementType: $a['procurement_type'],
            scrapedAt: (string) $a['scraped_at'],
            source: (string) $a['source'],
            sourceId: (string) $a['source_id'],
            sourceUrl: (string) $a['source_url'],
            observed: array_intersect_key($a, array_flip(self::OPTIONAL)),
        );
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->observed);
    }

    public function get(string $field): mixed
    {
        return $this->observed[$field] ?? null;
    }

    public function with(array $fields): self
    {
        return self::make([
            'dedup_key' => $this->dedupKey, 'reference_no' => $this->referenceNo, 'title' => $this->title,
            'status' => $this->status, 'procurement_type' => $this->procurementType, 'scraped_at' => $this->scrapedAt,
            'source' => $this->source, 'source_id' => $this->sourceId, 'source_url' => $this->sourceUrl,
            ...$this->observed, ...$fields,
        ]);
    }
}
