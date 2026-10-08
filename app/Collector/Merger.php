<?php

namespace App\Collector;

use App\Models\CollectedTender;
use Illuminate\Support\Facades\DB;

/** Port of tms-v2 storage/repository.ts mergeOne(): field-level, newest-wins, never blank a known value. */
final class Merger
{
    /** Fields where a null observation never overwrites a known value. */
    private const PROTECTED = [
        'ministry', 'agency', 'category', 'advertised_date', 'closing_date', 'indicative_price_sen',
        'winners', 'procurement_type',
    ];

    /** @param list<TenderPatch> $patches */
    public function merge(array $patches): void
    {
        foreach ($patches as $patch) {
            DB::transaction(fn () => $this->mergeOne($patch));
        }
    }

    private function mergeOne(TenderPatch $p): void
    {
        $tender = CollectedTender::where('dedup_key', $p->dedupKey)->lockForUpdate()->first()
            ?? new CollectedTender(['dedup_key' => $p->dedupKey, 'events' => [], 'raw' => [], 'winners' => null]);
        $provenance = $tender->field_updated_at ?? [];
        $fieldCodes = null;

        $observed = [
            'reference_no' => $p->referenceNo, 'title' => $p->title,
            'status' => $p->status, 'procurement_type' => $p->procurementType,
        ] + $p->observed;

        foreach ($observed as $field => $value) {
            $current = $field === 'field_codes' ? null : $tender->getAttribute($field);
            if ($value === null && in_array($field, self::PROTECTED, true) && $current !== null) {
                continue; // "no information" never clobbers a known value
            }
            if (isset($provenance[$field]) && $p->scrapedAt < $provenance[$field]) {
                continue; // stale / out-of-order observation
            }
            if ($field === 'field_codes') {
                $fieldCodes = $value;
            } else {
                $tender->setAttribute($field, $value);
            }
            $provenance[$field] = $p->scrapedAt;
        }

        if (! isset($provenance['scraped_at']) || $p->scrapedAt >= $provenance['scraped_at']) {
            $provenance['scraped_at'] = $p->scrapedAt;
            $tender->scraped_at = $p->scrapedAt;
        }
        $tender->field_updated_at = $provenance;
        $tender->save();

        if ($fieldCodes !== null) {
            $tender->fieldCodes()->delete();
            foreach (array_unique($fieldCodes) as $code) {
                $tender->fieldCodes()->create(['code' => $code]);
            }
        }

        $tender->sources()->updateOrCreate(
            ['source' => $p->source],
            ['source_id' => $p->sourceId, 'source_url' => $p->sourceUrl],
        );
    }
}
